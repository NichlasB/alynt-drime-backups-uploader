<?php
/**
 * Server runner backup creation and archive behavior.
 *
 * @package Alynt_Drime_Backups_Uploader
 */
trait Alynt_Server_Backup_Runner_Backup_Archive {
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
		$archive_started_at = gmdate( 'c' );
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
				'file_archive_started_at'     => $archive_started_at,
				'consistency_status'          => 'pending',
			)
		);
		if ( ! $this->write_json( $manifest_path, $manifest ) ) {
			$this->error( 'Could not write package manifest.' );
			return 1;
		}

		$archive_name = $package_id . '.' . $this->archive_format();
		$temp_archive = $this->work_path() . DIRECTORY_SEPARATOR . $archive_name . '.tmp';
		$final_archive = $this->outbox_path() . DIRECTORY_SEPARATOR . $archive_name;

		$archive_result = $this->create_archive( $temp_archive, $work_dir, $db_path, $manifest_path );
		if ( false === $archive_result ) {
			return 1;
		}

		$manifest = $this->manifest(
			$package_id,
			$db_path,
			array(
				'package_started_at'                       => $package_started_at,
				'database_dump_started_at'                 => $database_dump_started_at,
				'database_dump_finished_at'                => $database_dump_finished_at,
				'file_archive_started_at'                  => $archive_started_at,
				'file_archive_finished_at'                 => gmdate( 'c' ),
				'file_archive_exit_code'                   => (string) $archive_result['exit_code'],
				'file_archive_warning_count'               => (string) $archive_result['warning_count'],
				'file_archive_live_change_warning_count'   => (string) $archive_result['live_change_warning_count'],
				'consistency_status'                       => $this->consistency_status( $archive_result ),
			)
		);
		$manifest['file_archive_warning_samples'] = $archive_result['warning_samples'];

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

		if (
			! $this->write_json_atomic(
				$final_archive . '.remote-index.json',
				$this->remote_package_index( $final_archive )
			)
		) {
			$this->error( 'Could not write completed package remote index.' );
			return 1;
		}

		if (
			! $this->write_json_atomic(
				$final_archive . '.remote-catalog.json',
				$this->remote_catalog_snapshot()
			)
		) {
			$this->error( 'Could not write completed package remote catalog.' );
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
		return $this->export_database_from_path( $db_path, $this->wordpress_path() );
	}

	/**
	 * Exports the WordPress database from a specific WordPress path with WP-CLI.
	 *
	 * @param string $db_path Database dump path.
	 * @param string $wordpress_path WordPress path.
	 * @return bool
	 */
	private function export_database_from_path( $db_path, $wordpress_path ) {
		$command = escapeshellarg( $this->wp_cli_path() )
			. ' --path=' . escapeshellarg( $wordpress_path )
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
	 * @return array{exit_code:int,warning_count:int,live_change_warning_count:int,warning_samples:array<int,string>}|false
	 */
	private function create_archive( $temp_archive, $work_dir, $db_path, $manifest_path ) {
		$exclude_file = $work_dir . DIRECTORY_SEPARATOR . 'tar-excludes.txt';
		if ( ! $this->write_file( $exclude_file, implode( "\n", $this->tar_exclude_patterns() ) . "\n" ) ) {
			$this->error( 'Could not write archive exclude file.' );
			return false;
		}

		$wp_parent = dirname( $this->wordpress_path() );
		$wp_base   = basename( $this->wordpress_path() );
		$command   = 'tar ' . $this->tar_ignore_failed_read_option() . '--exclude-from=' . escapeshellarg( $exclude_file )
			. ' -czf ' . escapeshellarg( $temp_archive )
			. ' -C ' . escapeshellarg( $wp_parent ) . ' ' . escapeshellarg( $wp_base )
			. ' -C ' . escapeshellarg( $work_dir );

		if ( '' !== $db_path ) {
			$command .= ' ' . escapeshellarg( basename( $db_path ) );
		}

		$command .= ' ' . escapeshellarg( basename( $manifest_path ) );

		$result = $this->run_shell_command( $command );
		if ( 0 !== $result['exit_code'] && ! $this->is_recoverable_tar_archive_warning( $result, $temp_archive ) ) {
			$this->error( 'Archive creation failed.' );
			$this->error( implode( "\n", $result['output'] ) );
			return false;
		}

		if ( ! is_file( $temp_archive ) || filesize( $temp_archive ) <= 0 ) {
			return false;
		}

		$warning_lines = $this->archive_warning_lines( $result );
		$live_warnings = $this->archive_live_file_change_warning_lines( $warning_lines );

		return array(
			'exit_code'                 => (int) $result['exit_code'],
			'warning_count'             => count( $warning_lines ),
			'live_change_warning_count' => count( $live_warnings ),
			'warning_samples'           => array_slice( $warning_lines, 0, 5 ),
		);
	}


	/**
	 * Determines whether tar completed with only live-file churn warnings.
	 *
	 * GNU tar exits with status 1 when files change while being read. On live
	 * WordPress sites, cache and upload files can change during a package run.
	 * Treat that specific warning as recoverable only when a non-empty archive
	 * was still produced.
	 *
	 * @param array{exit_code:int,output:array<int,string>} $result Command result.
	 * @param string                                        $temp_archive Temporary archive path.
	 * @return bool
	 */
	private function is_recoverable_tar_archive_warning( array $result, $temp_archive ) {
		if ( 1 !== (int) $result['exit_code'] || ! is_file( $temp_archive ) || filesize( $temp_archive ) <= 0 ) {
			return false;
		}

		$output = isset( $result['output'] ) && is_array( $result['output'] ) ? $result['output'] : array();
		if ( empty( $output ) ) {
			return false;
		}

		foreach ( $output as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}

			if ( false === strpos( $line, 'file changed as we read it' ) ) {
				return false;
			}
		}

		$this->line( 'Archive completed with live file-change warnings.' );

		return true;
	}

	/**
	 * Returns non-empty archive warning/output lines.
	 *
	 * @param array{exit_code:int,output:array<int,string>} $result Command result.
	 * @return array<int,string>
	 */
	private function archive_warning_lines( array $result ) {
		$output = isset( $result['output'] ) && is_array( $result['output'] ) ? $result['output'] : array();
		$lines  = array();
		foreach ( $output as $line ) {
			$line = trim( (string) $line );
			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}

		return $lines;
	}

	/**
	 * Returns live file-change warning lines.
	 *
	 * @param array<int,string> $warning_lines Warning lines.
	 * @return array<int,string>
	 */
	private function archive_live_file_change_warning_lines( array $warning_lines ) {
		return array_values(
			array_filter(
				$warning_lines,
				function ( $line ) {
					return false !== strpos( (string) $line, 'file changed as we read it' );
				}
			)
		);
	}

	/**
	 * Returns the optional GNU tar live-read flag when supported.
	 *
	 * @return string
	 */
	private function tar_ignore_failed_read_option() {
		$result = $this->run_shell_command( 'tar --ignore-failed-read --help' );

		return 0 === $result['exit_code'] ? '--ignore-failed-read ' : '';
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
			'site_uuid'                 => $this->config_string( 'site_uuid' ),
			'site_url'                  => $this->config_string( 'site_url' ),
			'created_at'                => $this->timing_value( $timing, 'package_started_at' ),
			'backup_type'               => 'logical_wordpress_backup',
			'archive_format'            => $this->archive_format(),
			'consistency_mode'          => $this->consistency_mode(),
			'consistency_status'        => $this->timing_value( $timing, 'consistency_status' ),
			'wordpress_path'            => $this->wordpress_path(),
			'database_dump'             => '' !== $db_path ? basename( $db_path ) : '',
			'database_dump_started_at'  => $this->timing_value( $timing, 'database_dump_started_at' ),
			'database_dump_finished_at' => $this->timing_value( $timing, 'database_dump_finished_at' ),
			'file_archive_started_at'   => $this->timing_value( $timing, 'file_archive_started_at' ),
			'file_archive_finished_at'  => $this->timing_value( $timing, 'file_archive_finished_at' ),
			'file_archive_exit_code'    => $this->timing_int_value( $timing, 'file_archive_exit_code' ),
			'file_archive_warning_count' => $this->timing_int_value( $timing, 'file_archive_warning_count' ),
			'file_archive_live_change_warning_count' => $this->timing_int_value( $timing, 'file_archive_live_change_warning_count' ),
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
	 * Returns a timing integer value.
	 *
	 * @param array<string,string> $timing Timing values.
	 * @param string               $key Key.
	 * @return int
	 */
	private function timing_int_value( array $timing, $key ) {
		return isset( $timing[ $key ] ) ? max( 0, (int) $timing[ $key ] ) : 0;
	}


	/**
	 * Returns the configured consistency mode.
	 *
	 * @return string
	 */
	private function consistency_mode() {
		$mode = strtolower( $this->config_string( 'consistency_mode' ) );

		return 'light' === $mode ? 'light' : 'standard';
	}

	/**
	 * Returns the final consistency status for a completed package.
	 *
	 * @param array{exit_code:int,warning_count:int,live_change_warning_count:int,warning_samples:array<int,string>} $archive_result Archive result.
	 * @return string
	 */
	private function consistency_status( array $archive_result ) {
		if ( 'light' !== $this->consistency_mode() ) {
			return 'not_checked';
		}

		if ( $archive_result['live_change_warning_count'] > 0 ) {
			return 'file_changes_detected';
		}

		if ( $archive_result['warning_count'] > 0 ) {
			return 'warnings_detected';
		}

		return 'clean';
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


}
