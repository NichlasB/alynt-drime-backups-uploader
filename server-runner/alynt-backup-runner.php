#!/usr/bin/env php
<?php
/**
 * Alynt server backup runner.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

/**
 * Creates backup packages for the Alynt Drime Backups Uploader plugin.
 */
class Alynt_Server_Backup_Runner {
	const VERSION = '0.1.0';

	/**
	 * Config.
	 *
	 * @var array<string,mixed>
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $config Config.
	 */
	public function __construct( array $config ) {
		$this->config = $config;
	}

	/**
	 * Runs a command.
	 *
	 * @param string              $command Command.
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	public function dispatch( $command, array $options ) {
		switch ( $command ) {
			case 'health':
				return $this->health();
			case 'run':
				return $this->run();
			case 'list':
				return $this->list_packages();
			case 'verify':
				return $this->verify_command( $options );
			case 'inspect':
				return $this->inspect_command( $options );
			case 'fetch':
				return $this->fetch_command( $options );
			case 'stage-restore':
				return $this->stage_restore_command( $options );
			default:
				$this->error( 'Unknown command: ' . $command );
				$this->usage();
				return 1;
		}
	}

	/**
	 * Checks runner prerequisites.
	 *
	 * @return int Exit code.
	 */
	private function health() {
		$work_path    = $this->work_path();
		$outbox_path  = $this->outbox_path();
		$restore_path = $this->restore_path();

		$checks = array(
			'php_cli'                  => PHP_SAPI === 'cli',
			'archive_format'           => 'tar.gz' === $this->archive_format(),
			'wordpress_path'           => is_dir( $this->wordpress_path() ) && is_readable( $this->wordpress_path() ),
			'outbox_path'              => $this->ensure_directory( $outbox_path ) && is_writable( $outbox_path ),
			'work_path'                => $this->ensure_directory( $work_path ) && is_writable( $work_path ),
			'restore_path'             => $this->ensure_directory( $restore_path ) && is_writable( $restore_path ),
			'work_free_space'          => $this->has_minimum_free_space( $work_path ),
			'outbox_free_space'        => $this->has_minimum_free_space( $outbox_path ),
			'restore_free_space'       => $this->has_minimum_free_space( $restore_path ),
			'work_outbox_same_device'  => $this->same_filesystem_device( $work_path, $outbox_path ),
			'tar_available'            => $this->command_available( 'tar' ),
			'wp_cli'                   => ! $this->database_enabled() || $this->command_available( $this->wp_cli_path() ),
		);

		$ok = true;
		foreach ( $checks as $name => $passed ) {
			$this->line( sprintf( '%s: %s', $name, $passed ? 'ok' : 'failed' ) );
			$ok = $ok && $passed;
		}

		return $ok ? 0 : 1;
	}

	/**
	 * Creates one backup package.
	 *
	 * @return int Exit code.
	 */
	private function run() {
		if ( 0 !== $this->health_quiet() ) {
			$this->error( 'Runner health checks failed.' );
			return 1;
		}

		$lock = $this->acquire_run_lock();
		if ( false === $lock ) {
			$this->error( 'Another backup runner process is already running.' );
			return 1;
		}

		try {
		$package_started_at = gmdate( 'c' );
		$package_id         = $this->package_id();
		$work_dir           = $this->work_path() . DIRECTORY_SEPARATOR . $package_id;
		if ( ! $this->ensure_directory( $work_dir ) ) {
			$this->error( 'Could not create package work directory.' );
			return 1;
		}

		$db_path                   = '';
		$database_dump_started_at  = '';
		$database_dump_finished_at = '';
		if ( $this->database_enabled() ) {
			$db_path                  = $work_dir . DIRECTORY_SEPARATOR . 'database.sql';
			$database_dump_started_at = gmdate( 'c' );
			if ( ! $this->export_database( $db_path ) ) {
				return 1;
			}

			$database_dump_finished_at = gmdate( 'c' );
		}

		$manifest_path = $work_dir . DIRECTORY_SEPARATOR . 'manifest.json';
		$manifest      = $this->manifest(
			$package_id,
			$db_path,
			array(
				'package_started_at'          => $package_started_at,
				'database_dump_started_at'    => $database_dump_started_at,
				'database_dump_finished_at'   => $database_dump_finished_at,
				'file_archive_started_at'     => gmdate( 'c' ),
			)
		);
		if ( ! $this->write_json( $manifest_path, $manifest ) ) {
			$this->error( 'Could not write package manifest.' );
			return 1;
		}

		$archive_name = $package_id . '.' . $this->archive_format();
		$temp_archive = $this->work_path() . DIRECTORY_SEPARATOR . $archive_name . '.tmp';
		$final_archive = $this->outbox_path() . DIRECTORY_SEPARATOR . $archive_name;

		if ( ! $this->create_archive( $temp_archive, $work_dir, $db_path, $manifest_path ) ) {
			return 1;
		}

		$checksum = hash_file( 'sha256', $temp_archive );
		if ( false === $checksum ) {
			$this->error( 'Could not calculate package checksum.' );
			return 1;
		}

		if ( ! rename( $temp_archive, $final_archive ) ) {
			$this->error( 'Could not move completed archive into outbox.' );
			return 1;
		}

		if (
			! $this->write_json_atomic( $final_archive . '.manifest.json', $manifest )
			|| ! $this->write_file_atomic( $final_archive . '.sha256', $checksum . '  ' . basename( $final_archive ) . "\n" )
		) {
			$this->error( 'Could not write completed package sidecars.' );
			return 1;
		}

		if ( ! $this->remove_directory( $work_dir ) ) {
			$this->error( 'Warning: package was created, but the work directory could not be removed.' );
		}

		$this->line( 'Created package: ' . $final_archive );
		$this->line( 'Checksum: ' . $checksum );

		return 0;
		} finally {
			$this->release_run_lock( $lock );
		}
	}

	/**
	 * Lists completed packages.
	 *
	 * @return int Exit code.
	 */
	private function list_packages() {
		$packages = glob( $this->outbox_path() . DIRECTORY_SEPARATOR . '*.tar.gz' );
		if ( ! is_array( $packages ) ) {
			return 0;
		}

		sort( $packages );
		foreach ( $packages as $package ) {
			$this->line( $package );
		}

		return 0;
	}

	/**
	 * Verifies one package.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function verify_command( array $options ) {
		$package = isset( $options['package'] ) ? (string) $options['package'] : '';
		if ( '' === $package ) {
			$this->error( 'Missing --package=/path/to/archive.tar.gz.' );
			return 1;
		}

		return $this->verify_package( $package ) ? 0 : 1;
	}

	/**
	 * Inspects a verified package without extracting it.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function inspect_command( array $options ) {
		$package = isset( $options['package'] ) ? (string) $options['package'] : '';
		if ( '' === $package ) {
			$this->error( 'Missing --package=/path/to/archive.tar.gz.' );
			return 1;
		}

		if ( ! $this->verify_package( $package ) ) {
			return 1;
		}

		$manifest = $this->read_package_manifest( $package );
		if ( empty( $manifest ) ) {
			return 1;
		}

		$this->line( 'Package ID: ' . $this->manifest_value( $manifest, 'package_id' ) );
		$this->line( 'Producer: ' . $this->manifest_value( $manifest, 'producer' ) );
		$this->line( 'Created At: ' . $this->manifest_value( $manifest, 'created_at' ) );
		$this->line( 'Site URL: ' . $this->manifest_value( $manifest, 'site_url' ) );
		$this->line( 'Archive Format: ' . $this->manifest_value( $manifest, 'archive_format' ) );
		$this->line( 'File Root: ' . $this->manifest_value( $manifest, 'file_root' ) );
		$this->line( 'Database Dump: ' . $this->manifest_value( $manifest, 'database_dump' ) );
		$this->line( 'Backup Type: ' . $this->manifest_value( $manifest, 'backup_type' ) );
		$this->line( 'Database Dump Started: ' . $this->manifest_value( $manifest, 'database_dump_started_at' ) );
		$this->line( 'Database Dump Finished: ' . $this->manifest_value( $manifest, 'database_dump_finished_at' ) );
		$this->line( 'File Archive Started: ' . $this->manifest_value( $manifest, 'file_archive_started_at' ) );
		$this->line( 'Archive Preview:' );

		foreach ( $this->archive_preview( $package ) as $entry ) {
			$this->line( '  ' . $entry );
		}

		return 0;
	}

	/**
	 * Fetches a package and sidecars from Drime into a local directory.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function fetch_command( array $options ) {
		$package_id    = isset( $options['package-id'] ) ? trim( (string) $options['package-id'] ) : '';
		$download_path = isset( $options['download-path'] ) ? $this->normalize_path( (string) $options['download-path'] ) : '';
		$folder_hash   = isset( $options['folder-hash'] ) ? trim( (string) $options['folder-hash'] ) : '';
		$workspace_id  = isset( $options['workspace-id'] ) ? max( 0, (int) $options['workspace-id'] ) : 0;
		$token_env     = isset( $options['token-env'] ) ? trim( (string) $options['token-env'] ) : 'ALYNT_DRIME_TOKEN';
		$overwrite     = ! empty( $options['overwrite'] ) && '0' !== (string) $options['overwrite'];

		if ( '' === $package_id ) {
			$this->error( 'Missing --package-id=example-com-YYYYmmdd-HHMMSS.' );
			return 1;
		}

		if ( '' === $folder_hash ) {
			$this->error( 'Missing --folder-hash=DRIME_FOLDER_HASH.' );
			return 1;
		}

		if ( '' === $download_path ) {
			$this->error( 'Missing --download-path=/private/restore/downloads.' );
			return 1;
		}

		if ( ! $this->ensure_directory( $download_path ) || ! is_writable( $download_path ) ) {
			$this->error( 'Download path is not writable.' );
			return 1;
		}

		if ( ! function_exists( 'curl_init' ) ) {
			$this->error( 'The PHP cURL extension is required for Drime fetch.' );
			return 1;
		}

		$token = getenv( $token_env );
		if ( ! is_string( $token ) || '' === trim( $token ) ) {
			$this->error( 'Missing Drime bearer token environment variable: ' . $token_env );
			return 1;
		}

		$names = $this->fetch_package_names( $package_id );
		if ( empty( $names ) ) {
			return 1;
		}

		$entries = $this->list_drime_entries( $workspace_id, $folder_hash, $package_id, trim( $token ) );
		if ( empty( $entries ) ) {
			return 1;
		}

		foreach ( $names as $name ) {
			$entry = $this->find_drime_entry_by_name( $entries, $name );
			if ( empty( $entry['hash'] ) ) {
				$this->error( 'Required remote package file was not found: ' . $name );
				return 1;
			}

			$destination = $download_path . DIRECTORY_SEPARATOR . $name;
			if ( file_exists( $destination ) && ! $overwrite ) {
				$this->error( 'Local file already exists; refusing to overwrite: ' . $destination );
				return 1;
			}

			if ( ! $this->download_drime_entry( (string) $entry['hash'], $destination, trim( $token ) ) ) {
				return 1;
			}

			$this->line( 'Fetched: ' . $name );
		}

		$package = $download_path . DIRECTORY_SEPARATOR . $names[0];
		if ( ! $this->verify_package( $package ) ) {
			return 1;
		}

		$this->line( 'Fetched package verified: ' . $package );

		return 0;
	}

	/**
	 * Extracts a verified package into a non-destructive restore staging directory.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function stage_restore_command( array $options ) {
		$package = isset( $options['package'] ) ? (string) $options['package'] : '';
		if ( '' === $package ) {
			$this->error( 'Missing --package=/path/to/archive.tar.gz.' );
			return 1;
		}

		if ( ! $this->verify_package( $package ) ) {
			return 1;
		}

		$manifest = $this->read_package_manifest( $package );
		if ( empty( $manifest['package_id'] ) ) {
			$this->error( 'Manifest package ID is missing.' );
			return 1;
		}

		$restore_base = isset( $options['restore-path'] ) ? $this->normalize_path( (string) $options['restore-path'] ) : $this->restore_path();
		if ( ! $this->ensure_directory( $restore_base ) || ! is_writable( $restore_base ) ) {
			$this->error( 'Restore path is not writable.' );
			return 1;
		}

		$restore_dir = $restore_base . DIRECTORY_SEPARATOR . $this->safe_slug( (string) $manifest['package_id'] );
		if ( file_exists( $restore_dir ) ) {
			$this->error( 'Restore directory already exists; refusing to overwrite: ' . $restore_dir );
			return 1;
		}

		if ( ! mkdir( $restore_dir, 0750, true ) ) {
			$this->error( 'Could not create restore directory.' );
			return 1;
		}

		if ( ! $this->validate_archive_members( $package ) ) {
			$this->error( 'Package failed restore safety validation.' );
			rmdir( $restore_dir );
			return 1;
		}

		$command = 'tar -xzf ' . escapeshellarg( $package ) . ' -C ' . escapeshellarg( $restore_dir );
		$result  = $this->run_shell_command( $command );
		if ( 0 !== $result['exit_code'] ) {
			$this->error( 'Package extraction failed.' );
			$this->error( implode( "\n", $result['output'] ) );
			return 1;
		}

		$notes = array(
			'Package staged for inspection only.',
			'No database import was performed.',
			'No live WordPress files were overwritten.',
			'Package ID: ' . (string) $manifest['package_id'],
			'Created At: ' . $this->manifest_value( $manifest, 'created_at' ),
			'Site URL: ' . $this->manifest_value( $manifest, 'site_url' ),
		);

		$this->write_file( $restore_dir . DIRECTORY_SEPARATOR . 'RESTORE_NOTES.txt', implode( "\n", $notes ) . "\n" );
		$this->line( 'Package staged at: ' . $restore_dir );
		$this->line( 'No database import or production overwrite was performed.' );

		return 0;
	}

	/**
	 * Verifies one package checksum and manifest sidecar.
	 *
	 * @param string $package Package path.
	 * @return bool
	 */
	private function verify_package( $package ) {
		if ( ! is_file( $package ) || ! is_readable( $package ) ) {
			$this->error( 'Package is not readable.' );
			return false;
		}

		$manifest_path = $package . '.manifest.json';
		$checksum_path = $package . '.sha256';
		if ( ! is_readable( $manifest_path ) || ! is_readable( $checksum_path ) ) {
			$this->error( 'Package sidecars are missing or unreadable.' );
			return false;
		}

		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
		if ( ! is_array( $manifest ) || empty( $manifest['package_id'] ) ) {
			$this->error( 'Manifest is invalid.' );
			return false;
		}

		$checksum_line = trim( (string) file_get_contents( $checksum_path ) );
		if ( ! preg_match( '/^([a-fA-F0-9]{64})\s+/', $checksum_line, $matches ) ) {
			$this->error( 'Checksum sidecar is invalid.' );
			return false;
		}

		$actual = hash_file( 'sha256', $package );
		if ( strtolower( $matches[1] ) !== $actual ) {
			$this->error( 'Checksum mismatch.' );
			return false;
		}

		$this->line( 'Package verified: ' . $package );
		return true;
	}

	/**
	 * Reads a package manifest sidecar.
	 *
	 * @param string $package Package path.
	 * @return array<string,mixed>
	 */
	private function read_package_manifest( $package ) {
		$manifest_path = $package . '.manifest.json';
		if ( ! is_readable( $manifest_path ) ) {
			$this->error( 'Package manifest sidecar is missing or unreadable.' );
			return array();
		}

		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
		if ( ! is_array( $manifest ) ) {
			$this->error( 'Package manifest is invalid JSON.' );
			return array();
		}

		return $manifest;
	}

	/**
	 * Returns a printable manifest value.
	 *
	 * @param array<string,mixed> $manifest Manifest.
	 * @param string              $key Key.
	 * @return string
	 */
	private function manifest_value( array $manifest, $key ) {
		return isset( $manifest[ $key ] ) && is_scalar( $manifest[ $key ] ) ? (string) $manifest[ $key ] : '';
	}

	/**
	 * Returns a small archive listing preview.
	 *
	 * @param string $package Package path.
	 * @return array<int,string>
	 */
	private function archive_preview( $package ) {
		$handle = popen( 'tar -tzf ' . escapeshellarg( $package ) . ' 2>&1', 'r' );
		if ( false === $handle ) {
			return array();
		}

		$entries = array();
		while ( ! feof( $handle ) && count( $entries ) < 40 ) {
			$line = fgets( $handle );
			if ( false === $line ) {
				break;
			}

			$line = trim( $line );
			if ( '' !== $line ) {
				$entries[] = $line;
			}
		}

		pclose( $handle );

		return $entries;
	}

	/**
	 * Builds required package filenames for fetch.
	 *
	 * @param string $package_id Package ID.
	 * @return array<int,string>
	 */
	private function fetch_package_names( $package_id ) {
		$slug = $this->safe_slug( $package_id );
		if ( $slug !== $package_id ) {
			$this->error( 'Package ID contains unsupported characters.' );
			return array();
		}

		$archive = $package_id . '.' . $this->archive_format();

		return array(
			$archive,
			$archive . '.manifest.json',
			$archive . '.sha256',
		);
	}

	/**
	 * Lists candidate Drime entries for a package.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $folder_hash Folder hash.
	 * @param string $query Query.
	 * @param string $token Bearer token.
	 * @return array<int,array<string,mixed>>
	 */
	private function list_drime_entries( $workspace_id, $folder_hash, $query, $token ) {
		$args = array(
			'workspaceId' => max( 0, (int) $workspace_id ),
			'folderId'    => $folder_hash,
			'query'       => $query,
			'perPage'     => 100,
			'page'        => 1,
		);

		$url      = $this->drime_api_url( '/drive/file-entries?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 ) );
		$response = $this->drime_json_request( $url, $token );
		if ( ! is_array( $response ) ) {
			return array();
		}

		if ( empty( $response['data'] ) || ! is_array( $response['data'] ) ) {
			$this->error( 'No matching remote package files were found.' );
			return array();
		}

		return $response['data'];
	}

	/**
	 * Finds a Drime entry by exact filename.
	 *
	 * @param array<int,array<string,mixed>> $entries Entries.
	 * @param string                         $name Name.
	 * @return array<string,mixed>
	 */
	private function find_drime_entry_by_name( array $entries, $name ) {
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['name'] ) || (string) $entry['name'] !== $name ) {
				continue;
			}

			if ( empty( $entry['hash'] ) || ! is_scalar( $entry['hash'] ) ) {
				continue;
			}

			return $entry;
		}

		return array();
	}

	/**
	 * Downloads one Drime file entry to a local path.
	 *
	 * @param string $hash Entry hash.
	 * @param string $destination Destination.
	 * @param string $token Bearer token.
	 * @return bool
	 */
	private function download_drime_entry( $hash, $destination, $token ) {
		$temp_path = $destination . '.tmp';
		if ( file_exists( $temp_path ) && ! unlink( $temp_path ) ) {
			$this->error( 'Could not remove stale temporary download file.' );
			return false;
		}

		$handle = fopen( $temp_path, 'wb' );
		if ( false === $handle ) {
			$this->error( 'Could not create temporary download file.' );
			return false;
		}

		$curl = curl_init( $this->drime_api_url( '/file-entries/download/' . rawurlencode( $hash ) ) );
		if ( false === $curl ) {
			fclose( $handle );
			unlink( $temp_path );
			$this->error( 'Could not initialize Drime download request.' );
			return false;
		}

		curl_setopt( $curl, CURLOPT_HTTPHEADER, array( 'Authorization: Bearer ' . $token ) );
		curl_setopt( $curl, CURLOPT_FILE, $handle );
		curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 15 );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 0 );

		$ok   = curl_exec( $curl );
		$code = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
		$error = curl_error( $curl );

		$this->close_curl( $curl );
		fclose( $handle );

		if ( true !== $ok || $code < 200 || $code >= 300 ) {
			$this->error( 'Drime download failed with HTTP status ' . $code . '.' );
			if ( '' !== $error ) {
				$this->error( $error );
			}

			unlink( $temp_path );
			return false;
		}

		if ( ! is_file( $temp_path ) || filesize( $temp_path ) <= 0 ) {
			$this->error( 'Downloaded file is empty.' );
			unlink( $temp_path );
			return false;
		}

		if ( file_exists( $destination ) && ! unlink( $destination ) ) {
			$this->error( 'Could not replace existing destination file.' );
			unlink( $temp_path );
			return false;
		}

		if ( ! rename( $temp_path, $destination ) ) {
			$this->error( 'Could not promote downloaded file into place.' );
			unlink( $temp_path );
			return false;
		}

		return true;
	}

	/**
	 * Performs a Drime JSON request.
	 *
	 * @param string $url URL.
	 * @param string $token Bearer token.
	 * @return array<string,mixed>
	 */
	private function drime_json_request( $url, $token ) {
		$curl = curl_init( $url );
		if ( false === $curl ) {
			$this->error( 'Could not initialize Drime API request.' );
			return array();
		}

		curl_setopt( $curl, CURLOPT_HTTPHEADER, array( 'Authorization: Bearer ' . $token ) );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 15 );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 60 );

		$body  = curl_exec( $curl );
		$code  = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
		$error = curl_error( $curl );

		$this->close_curl( $curl );

		if ( ! is_string( $body ) || $code < 200 || $code >= 300 ) {
			$this->error( 'Drime API request failed with HTTP status ' . $code . '.' );
			if ( '' !== $error ) {
				$this->error( $error );
			}

			return array();
		}

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			$this->error( 'Drime API returned invalid JSON.' );
			return array();
		}

		return $decoded;
	}

	/**
	 * Builds a Drime API URL.
	 *
	 * @param string $path API path.
	 * @return string
	 */
	private function drime_api_url( $path ) {
		$base = $this->config_string( 'drime_api_base_url' );
		if ( '' === $base ) {
			$base = 'https://app.drime.cloud/api/v1';
		}

		return rtrim( $base, '/' ) . '/' . ltrim( $path, '/' );
	}

	/**
	 * Closes cURL handles on PHP versions where explicit close is still needed.
	 *
	 * @param resource|CurlHandle $curl cURL handle.
	 * @return void
	 */
	private function close_curl( $curl ) {
		if ( PHP_VERSION_ID < 80500 ) {
			curl_close( $curl );
		}
	}

	/**
	 * Validates archive members before restore extraction.
	 *
	 * @param string $package Package path.
	 * @return bool
	 */
	private function validate_archive_members( $package ) {
		$list_result = $this->run_shell_command( 'tar -tzf ' . escapeshellarg( $package ) );
		if ( 0 !== $list_result['exit_code'] ) {
			$this->error( 'Could not list package archive members.' );
			$this->error( implode( "\n", $list_result['output'] ) );
			return false;
		}

		foreach ( $list_result['output'] as $entry ) {
			if ( ! $this->is_safe_archive_member_name( $entry ) ) {
				$this->error( 'Unsafe archive member path: ' . $entry );
				return false;
			}
		}

		$verbose_result = $this->run_shell_command( 'tar -tvzf ' . escapeshellarg( $package ) );
		if ( 0 !== $verbose_result['exit_code'] ) {
			$this->error( 'Could not inspect package archive member types.' );
			$this->error( implode( "\n", $verbose_result['output'] ) );
			return false;
		}

		foreach ( $verbose_result['output'] as $entry ) {
			if ( preg_match( '/^[lh]/', ltrim( $entry ) ) ) {
				$this->error( 'Archive links are not allowed in restore staging: ' . $entry );
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks whether an archive member name is safe to extract into a staging directory.
	 *
	 * @param string $entry Archive member name.
	 * @return bool
	 */
	private function is_safe_archive_member_name( $entry ) {
		$entry = trim( str_replace( '\\', '/', $entry ) );
		if ( '' === $entry || false !== strpos( $entry, "\0" ) ) {
			return false;
		}

		if ( '/' === $entry[0] || preg_match( '/^[A-Za-z]:\//', $entry ) ) {
			return false;
		}

		$parts = explode( '/', rtrim( $entry, '/' ) );
		foreach ( $parts as $part ) {
			if ( '' === $part || '.' === $part || '..' === $part ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Runs health checks quietly.
	 *
	 * @return int Exit code.
	 */
	private function health_quiet() {
		$work_path    = $this->work_path();
		$outbox_path  = $this->outbox_path();
		$restore_path = $this->restore_path();

		return (
			is_dir( $this->wordpress_path() )
			&& 'tar.gz' === $this->archive_format()
			&& is_readable( $this->wordpress_path() )
			&& $this->ensure_directory( $outbox_path )
			&& is_writable( $outbox_path )
			&& $this->ensure_directory( $work_path )
			&& is_writable( $work_path )
			&& $this->ensure_directory( $restore_path )
			&& is_writable( $restore_path )
			&& $this->has_minimum_free_space( $work_path )
			&& $this->has_minimum_free_space( $outbox_path )
			&& $this->has_minimum_free_space( $restore_path )
			&& $this->same_filesystem_device( $work_path, $outbox_path )
			&& $this->command_available( 'tar' )
			&& ( ! $this->database_enabled() || $this->command_available( $this->wp_cli_path() ) )
		) ? 0 : 1;
	}

	/**
	 * Exports the WordPress database with WP-CLI.
	 *
	 * @param string $db_path Database dump path.
	 * @return bool
	 */
	private function export_database( $db_path ) {
		$command = escapeshellarg( $this->wp_cli_path() )
			. ' --path=' . escapeshellarg( $this->wordpress_path() )
			. ' db export ' . escapeshellarg( $db_path )
			. ' --quiet';

		$result = $this->run_shell_command( $command );
		if ( 0 !== $result['exit_code'] ) {
			$this->error( 'Database export failed.' );
			$this->error( implode( "\n", $result['output'] ) );
			return false;
		}

		return is_file( $db_path ) && filesize( $db_path ) > 0;
	}

	/**
	 * Creates a tar.gz archive.
	 *
	 * @param string $temp_archive Temporary archive path.
	 * @param string $work_dir Work directory.
	 * @param string $db_path Database path.
	 * @param string $manifest_path Manifest path.
	 * @return bool
	 */
	private function create_archive( $temp_archive, $work_dir, $db_path, $manifest_path ) {
		$exclude_file = $work_dir . DIRECTORY_SEPARATOR . 'tar-excludes.txt';
		if ( ! $this->write_file( $exclude_file, implode( "\n", $this->tar_exclude_patterns() ) . "\n" ) ) {
			$this->error( 'Could not write archive exclude file.' );
			return false;
		}

		$wp_parent = dirname( $this->wordpress_path() );
		$wp_base   = basename( $this->wordpress_path() );
		$command   = 'tar --exclude-from=' . escapeshellarg( $exclude_file )
			. ' -czf ' . escapeshellarg( $temp_archive )
			. ' -C ' . escapeshellarg( $wp_parent ) . ' ' . escapeshellarg( $wp_base )
			. ' -C ' . escapeshellarg( $work_dir );

		if ( '' !== $db_path ) {
			$command .= ' ' . escapeshellarg( basename( $db_path ) );
		}

		$command .= ' ' . escapeshellarg( basename( $manifest_path ) );

		$result = $this->run_shell_command( $command );
		if ( 0 !== $result['exit_code'] ) {
			$this->error( 'Archive creation failed.' );
			$this->error( implode( "\n", $result['output'] ) );
			return false;
		}

		return is_file( $temp_archive ) && filesize( $temp_archive ) > 0;
	}

	/**
	 * Builds package manifest.
	 *
	 * @param string $package_id Package ID.
	 * @param string              $db_path Database path.
	 * @param array<string,string> $timing Timing values.
	 * @return array<string,mixed>
	 */
	private function manifest( $package_id, $db_path, array $timing ) {
		return array(
			'manifest_version'          => 1,
			'package_id'                => $package_id,
			'backup_set_id'             => $package_id,
			'producer'                  => 'alynt_server_runner',
			'producer_version'          => self::VERSION,
			'site_id'                   => $this->config_string( 'site_id' ),
			'site_url'                  => $this->config_string( 'site_url' ),
			'created_at'                => $this->timing_value( $timing, 'package_started_at' ),
			'backup_type'               => 'logical_wordpress_backup',
			'archive_format'            => $this->archive_format(),
			'wordpress_path'            => $this->wordpress_path(),
			'database_dump'             => '' !== $db_path ? basename( $db_path ) : '',
			'database_dump_started_at'  => $this->timing_value( $timing, 'database_dump_started_at' ),
			'database_dump_finished_at' => $this->timing_value( $timing, 'database_dump_finished_at' ),
			'file_archive_started_at'   => $this->timing_value( $timing, 'file_archive_started_at' ),
			'file_root'                 => basename( $this->wordpress_path() ),
			'exclude_paths'             => $this->exclude_paths(),
		);
	}

	/**
	 * Returns a timing value.
	 *
	 * @param array<string,string> $timing Timing values.
	 * @param string               $key Key.
	 * @return string
	 */
	private function timing_value( array $timing, $key ) {
		return isset( $timing[ $key ] ) ? (string) $timing[ $key ] : '';
	}

	/**
	 * Returns the WordPress path.
	 *
	 * @return string
	 */
	private function wordpress_path() {
		return $this->normalize_path( $this->config_string( 'wordpress_path' ) );
	}

	/**
	 * Returns outbox path.
	 *
	 * @return string
	 */
	private function outbox_path() {
		return $this->normalize_path( $this->config_string( 'outbox_path' ) );
	}

	/**
	 * Returns work path.
	 *
	 * @return string
	 */
	private function work_path() {
		return $this->normalize_path( $this->config_string( 'work_path' ) );
	}

	/**
	 * Returns restore staging path.
	 *
	 * @return string
	 */
	private function restore_path() {
		$configured = $this->config_string( 'restore_path' );

		return '' !== $configured ? $this->normalize_path( $configured ) : dirname( $this->wordpress_path() ) . DIRECTORY_SEPARATOR . 'restores';
	}

	/**
	 * Returns WP-CLI executable.
	 *
	 * @return string
	 */
	private function wp_cli_path() {
		return '' !== $this->config_string( 'wp_cli_path' ) ? $this->config_string( 'wp_cli_path' ) : 'wp';
	}

	/**
	 * Returns the package archive format.
	 *
	 * @return string
	 */
	private function archive_format() {
		$format = strtolower( $this->config_string( 'archive_format' ) );

		return '' !== $format ? $format : 'tar.gz';
	}

	/**
	 * Returns configured excludes.
	 *
	 * @return array<int,string>
	 */
	private function exclude_paths() {
		$paths = isset( $this->config['exclude_paths'] ) && is_array( $this->config['exclude_paths'] ) ? $this->config['exclude_paths'] : array();

		return array_values( array_filter( array_map( 'strval', $paths ) ) );
	}

	/**
	 * Returns tar exclude patterns for the archive root layout.
	 *
	 * @return array<int,string>
	 */
	private function tar_exclude_patterns() {
		$patterns = array();
		$wp_base  = basename( $this->wordpress_path() );

		foreach ( $this->exclude_paths() as $path ) {
			$normalized = trim( $this->normalize_path( $path ), '/' );
			if ( '' === $normalized ) {
				continue;
			}

			$patterns[] = $normalized;
			$patterns[] = $wp_base . '/' . $normalized;
			$patterns[] = '*/' . $normalized;
		}

		foreach ( $this->symlink_exclude_patterns( $wp_base ) as $pattern ) {
			$patterns[] = $pattern;
		}

		return array_values( array_unique( $patterns ) );
	}

	/**
	 * Returns tar exclude patterns for symlinks inside the WordPress path.
	 *
	 * @param string $wp_base WordPress base directory name in the archive.
	 * @return array<int,string>
	 */
	private function symlink_exclude_patterns( $wp_base ) {
		$wp_path = $this->wordpress_path();
		if ( '' === $wp_path || ! is_dir( $wp_path ) ) {
			return array();
		}

		$patterns = array();
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $wp_path, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( ! $item->isLink() ) {
				continue;
			}

			$relative = ltrim( str_replace( '\\', '/', substr( $item->getPathname(), strlen( $wp_path ) ) ), '/' );
			if ( '' === $relative ) {
				continue;
			}

			$patterns[] = $relative;
			$patterns[] = $wp_base . '/' . $relative;
			$patterns[] = '*/' . $relative;
		}

		return $patterns;
	}

	/**
	 * Returns whether DB dump is enabled.
	 *
	 * @return bool
	 */
	private function database_enabled() {
		$database = isset( $this->config['database'] ) && is_array( $this->config['database'] ) ? $this->config['database'] : array();

		return ! isset( $database['enabled'] ) || (bool) $database['enabled'];
	}

	/**
	 * Returns a config string.
	 *
	 * @param string $key Config key.
	 * @return string
	 */
	private function config_string( $key ) {
		return isset( $this->config[ $key ] ) ? trim( (string) $this->config[ $key ] ) : '';
	}

	/**
	 * Returns the configured minimum free space required for package operations.
	 *
	 * @return int
	 */
	private function minimum_free_space_bytes() {
		if ( ! isset( $this->config['minimum_free_space_bytes'] ) ) {
			return 1073741824;
		}

		$value = (int) $this->config['minimum_free_space_bytes'];

		return max( 0, $value );
	}

	/**
	 * Checks whether a path has the configured minimum free disk space.
	 *
	 * @param string $path Directory path.
	 * @return bool
	 */
	private function has_minimum_free_space( $path ) {
		if ( '' === $path || ! is_dir( $path ) ) {
			return false;
		}

		$minimum = $this->minimum_free_space_bytes();
		if ( 0 === $minimum ) {
			return true;
		}

		$free = disk_free_space( $path );

		return false !== $free && $free >= $minimum;
	}

	/**
	 * Checks whether two directories are on the same filesystem device.
	 *
	 * @param string $left First directory path.
	 * @param string $right Second directory path.
	 * @return bool
	 */
	private function same_filesystem_device( $left, $right ) {
		if ( '' === $left || '' === $right || ! is_dir( $left ) || ! is_dir( $right ) ) {
			return false;
		}

		$left_stat  = stat( $left );
		$right_stat = stat( $right );

		if ( false === $left_stat || false === $right_stat ) {
			return false;
		}

		return isset( $left_stat['dev'], $right_stat['dev'] ) && $left_stat['dev'] === $right_stat['dev'];
	}

	/**
	 * Builds a package ID.
	 *
	 * @return string
	 */
	private function package_id() {
		$prefix = $this->config_string( 'package_prefix' );
		if ( '' === $prefix ) {
			$prefix = preg_replace( '/[^a-z0-9]+/', '-', strtolower( $this->config_string( 'site_id' ) ) );
			$prefix = trim( (string) $prefix, '-' );
		}

		return ( '' !== $prefix ? $prefix : 'wordpress-site' ) . '-' . gmdate( 'Ymd-His' );
	}

	/**
	 * Builds a filesystem-safe slug.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function safe_slug( $value ) {
		$slug = preg_replace( '/[^a-zA-Z0-9._-]+/', '-', $value );
		$slug = trim( (string) $slug, '.-' );

		return '' !== $slug ? $slug : 'restore-package';
	}

	/**
	 * Ensures a directory exists.
	 *
	 * @param string $path Directory path.
	 * @return bool
	 */
	private function ensure_directory( $path ) {
		if ( '' === $path ) {
			return false;
		}

		return is_dir( $path ) || mkdir( $path, 0750, true );
	}

	/**
	 * Acquires the runner-level package creation lock.
	 *
	 * @return resource|false
	 */
	private function acquire_run_lock() {
		$path   = $this->work_path() . DIRECTORY_SEPARATOR . '.alynt-backup-runner.lock';
		$handle = fopen( $path, 'c' );
		if ( false === $handle ) {
			return false;
		}

		if ( ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
			fclose( $handle );
			return false;
		}

		ftruncate( $handle, 0 );
		fwrite( $handle, (string) getmypid() . "\n" );

		return $handle;
	}

	/**
	 * Releases the runner-level package creation lock.
	 *
	 * @param resource $handle Lock handle.
	 * @return void
	 */
	private function release_run_lock( $handle ) {
		flock( $handle, LOCK_UN );
		fclose( $handle );
	}

	/**
	 * Checks whether a command is available.
	 *
	 * @param string $command Command.
	 * @return bool
	 */
	private function command_available( $command ) {
		if ( '' === $command ) {
			return false;
		}

		if ( false !== strpos( $command, '/' ) ) {
			return is_file( $command ) && is_executable( $command );
		}

		$result = $this->run_shell_command( 'command -v ' . escapeshellarg( $command ) );

		return 0 === $result['exit_code'];
	}

	/**
	 * Runs a shell command.
	 *
	 * @param string $command Command.
	 * @return array{exit_code:int,output:array<int,string>}
	 */
	private function run_shell_command( $command ) {
		$output = array();
		$exit_code = 0;
		exec( $command . ' 2>&1', $output, $exit_code );

		return array(
			'exit_code' => (int) $exit_code,
			'output'    => $output,
		);
	}

	/**
	 * Writes JSON to disk.
	 *
	 * @param string              $path Path.
	 * @param array<string,mixed> $data Data.
	 * @return bool
	 */
	private function write_json( $path, array $data ) {
		return $this->write_file( $path, json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
	}

	/**
	 * Writes JSON atomically.
	 *
	 * @param string              $path Path.
	 * @param array<string,mixed> $data Data.
	 * @return bool
	 */
	private function write_json_atomic( $path, array $data ) {
		return $this->write_file_atomic( $path, json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
	}

	/**
	 * Writes a file.
	 *
	 * @param string $path Path.
	 * @param string $contents Contents.
	 * @return bool
	 */
	private function write_file( $path, $contents ) {
		return false !== file_put_contents( $path, $contents );
	}

	/**
	 * Writes a file atomically.
	 *
	 * @param string $path Path.
	 * @param string $contents Contents.
	 * @return bool
	 */
	private function write_file_atomic( $path, $contents ) {
		$temp_path = $path . '.tmp';

		return $this->write_file( $temp_path, $contents ) && rename( $temp_path, $path );
	}

	/**
	 * Removes a generated work directory.
	 *
	 * @param string $path Directory path.
	 * @return bool
	 */
	private function remove_directory( $path ) {
		$work_path = $this->work_path();
		if ( '' === $path || '' === $work_path || 0 !== strpos( $path, $work_path . DIRECTORY_SEPARATOR ) || ! is_dir( $path ) ) {
			return false;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				if ( ! rmdir( $item->getPathname() ) ) {
					return false;
				}
			} elseif ( ! unlink( $item->getPathname() ) ) {
				return false;
			}
		}

		return rmdir( $path );
	}

	/**
	 * Normalizes a path.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function normalize_path( $path ) {
		return rtrim( str_replace( '\\', '/', $path ), '/' );
	}

	/**
	 * Writes a normal output line.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private function line( $message ) {
		fwrite( STDOUT, $message . "\n" );
	}

	/**
	 * Writes an error output line.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private function error( $message ) {
		fwrite( STDERR, $message . "\n" );
	}

	/**
	 * Prints usage.
	 *
	 * @return void
	 */
	private function usage() {
		$this->line( alynt_runner_usage() );
	}
}

/**
 * Parses CLI options.
 *
 * @param array<int,string> $argv Arguments.
 * @return array{command:string,options:array<string,string>}
 */
function alynt_runner_parse_args( array $argv ) {
	$command = isset( $argv[1] ) ? $argv[1] : 'help';
	$options = array();

	foreach ( array_slice( $argv, 2 ) as $arg ) {
		if ( 0 !== strpos( $arg, '--' ) ) {
			continue;
		}

		$parts = explode( '=', substr( $arg, 2 ), 2 );
		$options[ $parts[0] ] = isset( $parts[1] ) ? $parts[1] : '1';
	}

	return array(
		'command' => $command,
		'options' => $options,
	);
}

/**
 * Loads runner config.
 *
 * @param string $path Config path.
 * @return array<string,mixed>
 */
function alynt_runner_load_config( $path ) {
	if ( '' === $path || ! is_readable( $path ) ) {
		throw new RuntimeException( 'Config file is missing or unreadable.' );
	}

	$config = json_decode( (string) file_get_contents( $path ), true );
	if ( ! is_array( $config ) ) {
		throw new RuntimeException( 'Config file is not valid JSON.' );
	}

	return $config;
}

/**
 * Returns runner CLI usage.
 *
 * @return string
 */
function alynt_runner_usage() {
	return 'Usage: php alynt-backup-runner.php <health|run|list|verify|inspect|fetch|stage-restore> '
		. '--config=/path/to/config.json [--package=/path/to/archive.tar.gz] '
		. '[--package-id=package-id --folder-hash=hash --download-path=/path] '
		. '[--restore-path=/path/to/restores]';
}

$parsed = alynt_runner_parse_args( $argv );

if ( 'help' === $parsed['command'] || '--help' === $parsed['command'] ) {
	fwrite( STDOUT, alynt_runner_usage() . "\n" );
	exit( 0 );
}

try {
	$config = alynt_runner_load_config( isset( $parsed['options']['config'] ) ? $parsed['options']['config'] : '' );
	$runner = new Alynt_Server_Backup_Runner( $config );
	exit( $runner->dispatch( $parsed['command'], $parsed['options'] ) );
} catch ( Exception $exception ) {
	fwrite( STDERR, $exception->getMessage() . "\n" );
	exit( 1 );
}
