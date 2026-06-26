<?php
/**
 * Scanner tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class ScannerTest extends TestCase {
	use Alynt_Drime_Backups_Uploader_Test_Producer_Adapter_Assertions;

	/**
	 * Temporary backup directory.
	 *
	 * @var string
	 */
	private $backup_dir = '';

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->backup_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'alynt-drime-scanner-' . uniqid( '', true );
		mkdir( $this->backup_dir );
	}

	protected function tearDown(): void {
		$this->remove_directory( $this->backup_dir );
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_incomplete_wpvivid_listed_set_is_not_returned() {
		$present = $this->write_stable_file( 'wpvivid-abc_2026-06-19-10-00_backup_db.zip' );
		$scanner = $this->scanner_with_options(
			array(
				'wpvivid_backup_list' => array(
					'abc' => array(
						'backup' => array(
							'files' => array(
								array(
									'file_name' => basename( $present ),
									'type'      => 'backup_db',
								),
								array(
									'file_name' => 'wpvivid-abc_2026-06-19-10-00_backup_uploads.zip',
									'type'      => 'backup_uploads',
								),
							),
						),
					),
				),
			)
		);

		$result = $scanner->scan();

		$this->assertSame( array(), $result['candidates'] );
	}

	public function test_complete_wpvivid_listed_set_is_returned() {
		$db      = $this->write_stable_file( 'wpvivid-def_2026-06-19-10-00_backup_db.zip' );
		$uploads = $this->write_stable_file( 'wpvivid-def_2026-06-19-10-00_backup_uploads.zip' );
		$scanner = $this->scanner_with_options(
			array(
				'wpvivid_backup_list' => array(
					'def' => array(
						'backup' => array(
							'files' => array(
								array(
									'file_name' => basename( $db ),
									'type'      => 'backup_db',
								),
								array(
									'file_name' => basename( $uploads ),
									'type'      => 'backup_uploads',
								),
							),
						),
					),
				),
			)
		);

		$result = $scanner->scan();

		$this->assertCount( 2, $result['candidates'] );
		$this->assertArrayHasKey( 'wpvivid', $result['producers'] );
		$this->assert_normalized_producer_candidate( $result['candidates'][0], 'wpvivid', 'WPvivid' );
		$this->assertSame( 'wpvivid', $result['candidates'][0]['producer_key'] );
		$this->assertSame( 'WPvivid', $result['candidates'][0]['producer_label'] );
		$this->assertSame( basename( $db ), $result['candidates'][0]['filename'] );
		$this->assertSame( $result['candidates'][0]['signature'], $result['candidates'][0]['package_id'] );
		$this->assertTrue( $result['candidates'][0]['wpvivid']['from_list'] );
		$this->assertTrue( $result['candidates'][0]['metadata']['wpvivid']['from_list'] );
	}

	public function test_complete_wpvivid_listed_split_set_is_returned() {
		$part_one = $this->write_stable_file( 'wpvivid-jkl_2026-06-19-10-00_backup_db.part001.zip' );
		$part_two = $this->write_stable_file( 'wpvivid-jkl_2026-06-19-10-00_backup_db.part002.zip' );
		$scanner  = $this->scanner_with_options(
			array(
				'wpvivid_backup_list' => array(
					'jkl' => array(
						'backup' => array(
							'files' => array(
								array(
									'file_name' => basename( $part_one ),
									'type'      => 'backup_db',
								),
								array(
									'file_name' => basename( $part_two ),
									'type'      => 'backup_db',
								),
							),
						),
					),
				),
			)
		);

		$result = $scanner->scan();

		$this->assertCount( 2, $result['candidates'] );
		$this->assertSame( basename( $part_one ), $result['candidates'][0]['name'] );
		$this->assertSame( 2, $result['candidates'][0]['wpvivid']['set_file_count'] );
	}

	public function test_orphaned_split_part_is_not_returned() {
		$this->write_stable_file( 'wpvivid-ghi_2026-06-19-10-00_backup_db.part001.zip' );
		$scanner = $this->scanner_with_options();

		$result = $scanner->scan();

		$this->assertSame( array(), $result['candidates'] );
	}

	public function test_custom_producer_candidate_is_normalized_by_scanner() {
		$scanner = new Alynt_Drime_Backups_Uploader_Scanner(
			new Alynt_Drime_Backups_Uploader_Settings(),
			new Alynt_Drime_Backups_Uploader_WPvivid_Detector(),
			null,
			array(
				new Alynt_Drime_Backups_Uploader_Test_Producer(
					'custom_source',
					'Custom Source',
					array(
						'directory'  => 'C:/backups',
						'candidates' => array(
							array(
								'signature'      => 'custom-one',
								'path'           => 'C:/backups/custom-one.zip',
								'name'           => 'custom-one.zip',
								'size'           => '123',
								'mtime'          => '456',
								'producer_key'   => 'wrong',
								'producer_label' => 'Wrong',
								'metadata'       => 'not-an-array',
							),
						),
						'errors'     => array(),
					)
				),
			)
		);

		$result    = $scanner->scan();
		$candidate = $result['candidates'][0];

		$this->assertCount( 1, $result['candidates'] );
		$this->assert_normalized_producer_candidate( $candidate, 'custom_source', 'Custom Source' );
		$this->assertSame( 'custom-one', $candidate['package_id'] );
		$this->assertSame( 'custom-one.zip', $candidate['filename'] );
		$this->assertSame( 456, $candidate['modified_time'] );
		$this->assertSame( 'custom-one', $candidate['backup_set_id'] );
		$this->assertSame( 1, $candidate['backup_set_total'] );
		$this->assertSame( array(), $candidate['metadata'] );
	}

	public function test_invalid_custom_producer_candidates_are_skipped() {
		$scanner = new Alynt_Drime_Backups_Uploader_Scanner(
			new Alynt_Drime_Backups_Uploader_Settings(),
			new Alynt_Drime_Backups_Uploader_WPvivid_Detector(),
			null,
			array(
				new Alynt_Drime_Backups_Uploader_Test_Producer(
					'custom_source',
					'Custom Source',
					array(
						'directory'  => 'C:/backups',
						'candidates' => array(
							array(
								'path' => 'C:/backups/missing-signature.zip',
								'name' => 'missing-signature.zip',
							),
							'not-a-record',
							array(
								'signature' => 'custom-two',
								'path'      => 'C:/backups/custom-two.zip',
								'name'      => 'custom-two.zip',
							),
						),
						'errors'     => array(),
					)
				),
			)
		);

		$result = $scanner->scan();

		$this->assertCount( 1, $result['candidates'] );
		$this->assertSame( 'custom-two', $result['candidates'][0]['signature'] );
		$this->assertCount( 2, $result['errors'] );
		$this->assertSame( 'The custom_source backup producer returned an invalid package record.', $result['errors'][0] );
	}

	/**
	 * Creates a scanner with mocked WordPress options.
	 *
	 * @param array<string,mixed> $overrides Option overrides.
	 * @return Alynt_Drime_Backups_Uploader_Scanner
	 */
	private function scanner_with_options( array $overrides = array() ) {
		$options = array_merge(
			array(
				Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME => array(
					'backup_path_override' => $this->backup_dir,
					'min_file_age_seconds' => 60,
				),
				Alynt_Drime_Backups_Uploader_Scanner::SNAPSHOT_OPTION => $this->stable_snapshots(),
				'wpvivid_backup_list' => array(),
			),
			$overrides
		);

		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) use ( &$options ) {
				return array_key_exists( $name, $options ) ? $options[ $name ] : $default;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) use ( &$options ) {
				$options[ $name ] = $value;
				return true;
			}
		);

		return new Alynt_Drime_Backups_Uploader_Scanner(
			new Alynt_Drime_Backups_Uploader_Settings(),
			new Alynt_Drime_Backups_Uploader_WPvivid_Detector()
		);
	}

	/**
	 * Writes an old ZIP fixture.
	 *
	 * @param string $name File basename.
	 * @return string
	 */
	private function write_stable_file( $name ) {
		$path = $this->backup_dir . DIRECTORY_SEPARATOR . $name;
		file_put_contents( $path, 'test backup' );
		touch( $path, time() - 120 );

		return $path;
	}

	/**
	 * Builds previous scan snapshots for files in the temp directory.
	 *
	 * @return array<string,array<string,int>>
	 */
	private function stable_snapshots() {
		$snapshots = array();
		$scanner   = new Alynt_Drime_Backups_Uploader_Scanner(
			new Alynt_Drime_Backups_Uploader_Settings(),
			new Alynt_Drime_Backups_Uploader_WPvivid_Detector()
		);

		foreach ( glob( $this->backup_dir . DIRECTORY_SEPARATOR . '*.zip' ) as $file ) {
			$size  = filesize( $file );
			$mtime = filemtime( $file );

			if ( false === $size || false === $mtime ) {
				continue;
			}

			$snapshots[ $scanner->signature( $file ) ] = array(
				'size'  => $size,
				'mtime' => $mtime,
			);
		}

		return $snapshots;
	}

	/**
	 * Removes a directory recursively.
	 *
	 * @param string $directory Directory.
	 * @return void
	 */
	private function remove_directory( $directory ) {
		if ( '' === $directory || ! is_dir( $directory ) ) {
			return;
		}

		foreach ( glob( $directory . DIRECTORY_SEPARATOR . '*' ) as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}

		rmdir( $directory );
	}
}

class Alynt_Drime_Backups_Uploader_Test_Producer implements Alynt_Drime_Backups_Uploader_Producer_Interface {
	private $key;
	private $label;
	private $result;

	public function __construct( $key, $label, array $result ) {
		$this->key    = $key;
		$this->label  = $label;
		$this->result = $result;
	}

	public function key() {
		return $this->key;
	}

	public function label() {
		return $this->label;
	}

	public function scan() {
		return $this->result;
	}
}
