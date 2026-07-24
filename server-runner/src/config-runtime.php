<?php
/**
 * Shared configuration, process, filesystem, and output runtime helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 */
trait Alynt_Server_Backup_Runner_Config_Runtime {
	/**
	 * Imports a database dump through WP-CLI.
	 *
	 * @param string $db_path Database dump path.
	 * @param string $target_path Target WordPress path.
	 * @return array{exit_code:int,output:array<int,string>}
	 */
	private function import_database( $db_path, $target_path ) {
		$command = escapeshellarg( $this->wp_cli_path() )
			. ' --path=' . escapeshellarg( $target_path )
			. ' db import ' . escapeshellarg( $db_path );

		return $this->run_shell_command( $command );
	}

	/**
	 * Creates a pre-restore tar.gz backup of the current target files.
	 *
	 * @param string $final_archive Final archive path.
	 * @param string $target_path Target WordPress path.
	 * @return bool
	 */
	private function create_pre_restore_file_backup( $final_archive, $target_path ) {
		$target_path = $this->normalize_path( $target_path );
		if ( '' === $final_archive || '' === $target_path || $this->dangerous_restore_target_path( $target_path ) || ! is_dir( $target_path ) || ! is_readable( $target_path ) ) {
			return false;
		}

		$temp_archive = $final_archive . '.tmp';
		if ( file_exists( $temp_archive ) && ! unlink( $temp_archive ) ) {
			return false;
		}

		$exclude_file = dirname( $final_archive ) . DIRECTORY_SEPARATOR . '.pre-restore-tar-excludes-' . uniqid( '', true ) . '.txt';
		if ( ! $this->write_file( $exclude_file, implode( "\n", $this->pre_restore_tar_exclude_patterns( basename( $target_path ) ) ) . "\n" ) ) {
			return false;
		}

		$command = 'tar ' . $this->tar_ignore_failed_read_option()
			. '--exclude-from=' . escapeshellarg( $exclude_file )
			. ' -czf ' . escapeshellarg( $temp_archive )
			. ' -C ' . escapeshellarg( dirname( $target_path ) )
			. ' ' . escapeshellarg( basename( $target_path ) );

		$result = $this->run_shell_command( $command );
		if ( is_file( $exclude_file ) ) {
			unlink( $exclude_file );
		}

		if ( 0 !== $result['exit_code'] && ! $this->is_recoverable_tar_archive_warning( $result, $temp_archive ) ) {
			$this->error( 'Pre-restore file backup creation failed.' );
			$this->error( implode( "\n", $result['output'] ) );
			return false;
		}

		if ( ! is_file( $temp_archive ) || filesize( $temp_archive ) <= 0 ) {
			return false;
		}

		return rename( $temp_archive, $final_archive );
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
	 * Returns restore dry-run reports path.
	 *
	 * @return string
	 */
	private function restore_reports_path() {
		return $this->normalize_path( $this->config_string( 'restore_reports_path' ) );
	}

	/**
	 * Returns the private production-simulation pre-restore evidence path.
	 *
	 * @return string
	 */
	private function production_pre_backup_path() {
		return $this->normalize_path( $this->config_string( 'production_pre_backup_path' ) );
	}

	/**
	 * Returns pre-restore backup evidence path.
	 *
	 * @return string
	 */
	private function restore_pre_backup_evidence_path() {
		return $this->normalize_path( $this->config_string( 'restore_pre_backup_evidence_path' ) );
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
	 * Returns tar exclude patterns for pre-restore snapshots.
	 *
	 * Symlink entries are intentionally not excluded here because restore-apply
	 * reports known drop-ins from the pre-restore file backup.
	 *
	 * @param string $wp_base WordPress base directory name in the archive.
	 * @return array<int,string>
	 */
	private function pre_restore_tar_exclude_patterns( $wp_base ) {
		$patterns = array();
		foreach ( $this->exclude_paths() as $path ) {
			$normalized = trim( $this->normalize_path( $path ), '/' );
			if ( '' === $normalized ) {
				continue;
			}

			$patterns[] = $normalized;
			$patterns[] = $wp_base . '/' . $normalized;
			$patterns[] = '*/' . $normalized;
		}

		return array_values( array_unique( $patterns ) );
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
	 * Returns a normalized string-list config value.
	 *
	 * @param string $key Config key.
	 * @return array<int,string>
	 */
	private function config_array( $key ) {
		if ( ! isset( $this->config[ $key ] ) || ! is_array( $this->config[ $key ] ) ) {
			return array();
		}

		$values = array();
		foreach ( $this->config[ $key ] as $value ) {
			$value = trim( (string) $value );
			if ( '' !== $value ) {
				$values[] = $value;
			}
		}

		return array_values( array_unique( $values ) );
	}

	/**
	 * Returns a normalized string-map config value.
	 *
	 * @param string $key Config key.
	 * @return array<string,string>
	 */
	private function config_string_map( $key ) {
		if ( ! array_key_exists( $key, $this->config ) || ! is_array( $this->config[ $key ] ) ) {
			return array();
		}

		$values = array();
		foreach ( $this->config[ $key ] as $map_key => $value ) {
			$map_key = trim( (string) $map_key );
			$value   = trim( (string) $value );
			if ( '' !== $map_key && '' !== $value ) {
				$values[ $map_key ] = $value;
			}
		}
		ksort( $values );

		return $values;
	}

	/**
	 * Returns whether an enrolled string list exactly matches runtime values.
	 *
	 * @param string            $key Config key.
	 * @param array<int,string> $actual Actual values.
	 * @return bool
	 */
	private function config_list_matches_actual( $key, array $actual ) {
		if ( ! array_key_exists( $key, $this->config ) || ! is_array( $this->config[ $key ] ) ) {
			return false;
		}

		$expected = $this->config_array( $key );
		sort( $expected );
		sort( $actual );

		return $expected === $actual;
	}

	/**
	 * Returns whether an enrolled string map exactly matches runtime values.
	 *
	 * @param string               $key Config key.
	 * @param array<string,string> $actual Actual values.
	 * @return bool
	 */
	private function config_string_map_matches_actual( $key, array $actual ) {
		if ( ! array_key_exists( $key, $this->config ) || ! is_array( $this->config[ $key ] ) ) {
			return false;
		}

		$normalized_actual = array();
		foreach ( $actual as $map_key => $value ) {
			$map_key = trim( (string) $map_key );
			$value   = trim( (string) $value );
			if ( '' !== $map_key && '' !== $value ) {
				$normalized_actual[ $map_key ] = $value;
			}
		}
		ksort( $normalized_actual );

		return $this->config_string_map( $key ) === $normalized_actual;
	}

	/**
	 * Hashes string-map values while retaining their identifying keys.
	 *
	 * @param array<string,string> $values Values to hash.
	 * @return array<string,string>
	 */
	private function hash_string_map( array $values ) {
		$hashed = array();
		foreach ( $values as $key => $value ) {
			$hashed[ (string) $key ] = hash( 'sha256', (string) $value );
		}
		ksort( $hashed );

		return $hashed;
	}

	/**
	 * Returns a config value as a strict opt-in boolean.
	 *
	 * @param string $key Config key.
	 * @return bool
	 */
	private function config_bool( $key ) {
		if ( ! isset( $this->config[ $key ] ) ) {
			return false;
		}

		return $this->truthy_value( $this->config[ $key ] );
	}

	/**
	 * Returns the enrolled production site root.
	 *
	 * @param string $target_path Target WordPress path.
	 * @return string
	 */
	private function production_target_site_root( $target_path ) {
		$configured = $this->normalize_path( $this->config_string( 'production_target_site_root' ) );

		return '' !== $configured ? $configured : $this->normalize_path( dirname( $target_path ) );
	}

	/**
	 * Returns the external wp-config.php path used for target identity checks.
	 *
	 * @param string $site_root Site root.
	 * @return string
	 */
	private function production_target_wp_config_path( $site_root ) {
		$configured = $this->normalize_path( $this->config_string( 'production_target_wp_config_path' ) );

		return '' !== $configured ? $configured : $site_root . DIRECTORY_SEPARATOR . 'wp-config.php';
	}

	/**
	 * Normalizes an HTTP(S) site URL for strict comparisons.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function normalize_site_url( $url ) {
		$url   = trim( $url );
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || ! in_array( strtolower( (string) $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return '';
		}

		$normalized = strtolower( (string) $parts['scheme'] ) . '://' . strtolower( (string) $parts['host'] );
		if ( isset( $parts['port'] ) ) {
			$normalized .= ':' . (int) $parts['port'];
		}
		if ( ! empty( $parts['path'] ) && '/' !== $parts['path'] ) {
			$normalized .= '/' . trim( (string) $parts['path'], '/' );
		}

		return $normalized;
	}

	/**
	 * Returns a normalized hostname from a URL or hostname.
	 *
	 * @param string $value URL or hostname.
	 * @return string
	 */
	private function site_host( $value ) {
		$value = strtolower( trim( $value ) );
		if ( false === strpos( $value, '://' ) ) {
			return trim( $value, './ ' );
		}

		$host = parse_url( $value, PHP_URL_HOST );

		return is_string( $host ) ? strtolower( $host ) : '';
	}

	/**
	 * Returns whether a value is a UUID.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	private function valid_uuid( $value ) {
		return 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', strtolower( trim( $value ) ) );
	}

	/**
	 * Returns whether a value is a parseable timestamp.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	private function valid_iso_timestamp( $value ) {
		return '' !== trim( $value ) && false !== strtotime( $value );
	}

	/**
	 * Returns whether package identity matches the enrolled hostname.
	 *
	 * @param array<string,mixed> $package Package report.
	 * @param string              $target_host Target hostname.
	 * @return bool
	 */
	private function package_site_identity_matches( array $package, $target_host ) {
		$site_id = isset( $package['site_id'] ) ? strtolower( trim( (string) $package['site_id'] ) ) : '';
		if ( '' !== $site_id ) {
			return $site_id === $target_host;
		}

		return $this->site_host( isset( $package['site_url'] ) ? (string) $package['site_url'] : '' ) === $target_host;
	}

	/**
	 * Returns whether host-native backup evidence is mandatory.
	 *
	 * @return bool
	 */
	private function production_native_backup_required() {
		return ! array_key_exists( 'production_native_backup_required', $this->config ) || $this->config_bool( 'production_native_backup_required' );
	}

	/**
	 * Returns native backup evidence maximum age.
	 *
	 * @return int
	 */
	private function production_native_backup_max_age_seconds() {
		$value = isset( $this->config['production_native_backup_max_age_seconds'] ) ? (int) $this->config['production_native_backup_max_age_seconds'] : self::DAY_IN_SECONDS;

		return max( 300, $value );
	}

	/**
	 * Returns production pre-restore evidence maximum age.
	 *
	 * @return int
	 */
	private function production_pre_backup_max_age_seconds() {
		$value = isset( $this->config['production_pre_backup_max_age_seconds'] ) ? (int) $this->config['production_pre_backup_max_age_seconds'] : 3600;

		return max( 300, $value );
	}

	/**
	 * Returns the production restore disk safety margin.
	 *
	 * @return int
	 */
	private function production_disk_safety_margin_bytes() {
		$value = isset( $this->config['production_disk_safety_margin_bytes'] ) ? (int) $this->config['production_disk_safety_margin_bytes'] : 3221225472;

		return max( 0, $value );
	}

	/**
	 * Returns whether a scalar value is truthy.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private function truthy_value( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'true', 'yes', 'on' ), true );
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

		$probe  = 'Windows' === PHP_OS_FAMILY ? 'where ' : 'command -v ';
		$result = $this->run_shell_command( $probe . escapeshellarg( $command ) );

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

}
