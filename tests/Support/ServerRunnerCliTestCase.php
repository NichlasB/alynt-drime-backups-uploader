<?php
/**
 * Shared server runner CLI test helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use PHPUnit\Framework\TestCase;

/**
 * Provides isolated filesystem fixtures for server runner CLI tests.
 */
abstract class Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case extends TestCase {
	/**
	 * Temporary fixture root.
	 *
	 * @var string
	 */
	protected $root = '';

	protected function setUp(): void {
		parent::setUp();

		$this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'alynt-runner-' . uniqid( '', true );
		mkdir( $this->root );
	}

	protected function tearDown(): void {
		$this->remove_directory( $this->root );

		parent::tearDown();
	}

	/**
	 * Runs the standalone server runner.
	 *
	 * @param string            $command Runner command.
	 * @param string            $config Config path.
	 * @param array<int,string> $extra_args Extra CLI args.
	 * @return array{exit_code:int,output:array<int,string>,error:array<int,string>}
	 */
	protected function run_runner( $command, $config, array $extra_args = array() ) {
		$runner = ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . DIRECTORY_SEPARATOR . 'server-runner' . DIRECTORY_SEPARATOR . 'alynt-backup-runner.php';
		$cmd    = array_merge(
			array(
				escapeshellarg( PHP_BINARY ),
				escapeshellarg( $runner ),
				escapeshellarg( $command ),
				'--config=' . escapeshellarg( $config ),
			),
			$extra_args
		);

		$descriptors = array(
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$process = proc_open( implode( ' ', $cmd ), $descriptors, $pipes );
		if ( ! is_resource( $process ) ) {
			$this->fail( 'Could not start server runner process.' );
		}

		$output    = stream_get_contents( $pipes[1] );
		$error     = stream_get_contents( $pipes[2] );
		$exit_code = proc_close( $process );

		return array(
			'exit_code' => $exit_code,
			'output'    => '' === trim( (string) $output ) ? array() : explode( "\n", trim( (string) $output ) ),
			'error'     => '' === trim( (string) $error ) ? array() : explode( "\n", trim( (string) $error ) ),
		);
	}

	/**
	 * Returns whether tar is available to the test runtime.
	 *
	 * @return bool
	 */
	protected function tar_available() {
		$output = array();
		$code   = 1;
		exec( 'tar --version', $output, $code );

		return 0 === $code;
	}

	/**
	 * Creates a tar.gz archive fixture.
	 *
	 * @param string $archive Archive path.
	 * @param string $source Source directory.
	 * @return void
	 */
	protected function create_tar_archive( $archive, $source ) {
		$command = 'tar -czf ' . escapeshellarg( $archive ) . ' -C ' . escapeshellarg( $source ) . ' htdocs database.sql manifest.json';
		$output  = array();
		$code    = 1;
		exec( $command, $output, $code );

		$this->assertSame( 0, $code, implode( "\n", $output ) );
	}

	/**
	 * Builds the staged-input integrity object expected in RESTORE_REPORT.json.
	 *
	 * @param string $file_root Staged file root.
	 * @param string $database_dump Staged database dump.
	 * @return array<string,mixed>
	 */
	protected function staged_integrity_fixture( $file_root, $database_dump ) {
		return array(
			'schema_version' => 1,
			'algorithm'      => 'sha256',
			'file_tree'     => $this->staged_tree_integrity_fixture( $file_root ),
			'database_dump' => array(
				'valid'       => true,
				'sha256'      => hash_file( 'sha256', $database_dump ),
				'total_bytes' => (int) filesize( $database_dump ),
			),
		);
	}

	/**
	 * Reproduces the runner's deterministic directory digest for fixtures.
	 *
	 * @param string $root Directory root.
	 * @return array<string,mixed>
	 */
	private function staged_tree_integrity_fixture( $root ) {
		$records         = array();
		$file_count      = 0;
		$directory_count = 0;
		$symlink_count   = 0;
		$total_bytes     = 0;
		$iterator        = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$path     = $item->getPathname();
			$relative = ltrim( substr( str_replace( '\\', '/', $path ), strlen( str_replace( '\\', '/', $root ) ) ), '/' );
			if ( $item->isLink() ) {
				$records[] = "link\0" . $relative . "\0" . readlink( $path );
				++$symlink_count;
				continue;
			}
			if ( $item->isDir() ) {
				$records[] = "directory\0" . $relative;
				++$directory_count;
				continue;
			}

			$size      = (int) $item->getSize();
			$records[] = "file\0" . $relative . "\0" . $size . "\0" . hash_file( 'sha256', $path );
			++$file_count;
			$total_bytes += $size;
		}

		sort( $records, SORT_STRING );
		$context = hash_init( 'sha256' );
		foreach ( $records as $record ) {
			hash_update( $context, strlen( $record ) . ':' . $record );
		}

		return array(
			'valid'           => true,
			'sha256'          => hash_final( $context ),
			'file_count'      => $file_count,
			'directory_count' => $directory_count,
			'symlink_count'   => $symlink_count,
			'total_bytes'     => $total_bytes,
		);
	}

	/**
	 * Writes runner config.
	 *
	 * @param string $outbox Outbox path.
	 * @return string
	 */
	protected function write_config( $outbox, array $overrides = array() ) {
		$config = $this->root . DIRECTORY_SEPARATOR . 'config.json';
		$data   = array_merge(
			array(
				'wordpress_path' => $this->make_directory( 'htdocs' ),
				'outbox_path'    => $outbox,
				'work_path'      => $this->make_directory( 'work' ),
				'restore_path'   => $this->make_directory( 'restores' ),
				'site_id'        => 'example-com',
				'site_url'       => 'https://example.com',
				'database'       => array(
					'enabled' => false,
				),
			),
			$overrides
		);
		file_put_contents( $config, json_encode( $data ) );

		return $config;
	}

	/**
	 * Creates a fixture directory under the root.
	 *
	 * @param string $name Directory name.
	 * @return string
	 */
	protected function make_directory( $name ) {
		$path = $this->root . DIRECTORY_SEPARATOR . $name;
		if ( ! is_dir( $path ) ) {
			mkdir( $path );
		}

		return $path;
	}

	/**
	 * Removes a directory recursively.
	 *
	 * @param string $directory Directory path.
	 * @return void
	 */
	private function remove_directory( $directory ) {
		if ( '' === $directory || ! is_dir( $directory ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
				continue;
			}

			unlink( $item->getPathname() );
		}

		rmdir( $directory );
	}
}
