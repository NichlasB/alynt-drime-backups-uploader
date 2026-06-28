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
	const DAY_IN_SECONDS = 86400;

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
				return $this->list_packages( $options );
			case 'cleanup-preview':
				return $this->cleanup_preview_command( $options );
			case 'cleanup':
				return $this->cleanup_command( $options );
			case 'verify':
				return $this->verify_command( $options );
			case 'inspect':
				return $this->inspect_command( $options );
			case 'fetch':
				return $this->fetch_command( $options );
			case 'stage-restore':
				return $this->stage_restore_command( $options );
			case 'restore-dry-run':
				return $this->restore_dry_run_command( $options );
			case 'restore-apply':
				return $this->restore_apply_command( $options );
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
	 * Lists completed packages.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function list_packages( array $options ) {
		$packages = glob( $this->outbox_path() . DIRECTORY_SEPARATOR . '*.' . $this->archive_format() );
		if ( ! is_array( $packages ) ) {
			return 0;
		}

		sort( $packages );
		if ( isset( $options['format'] ) && 'json' === (string) $options['format'] ) {
			$this->line(
				json_encode(
					array(
						'schema_version' => 1,
						'generated_at'   => gmdate( 'c' ),
						'archive_format' => $this->archive_format(),
						'package_count'  => count( $packages ),
						'packages'       => array_map( array( $this, 'package_inventory_record' ), $packages ),
					),
					JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
				)
			);

			return 0;
		}

		foreach ( $packages as $package ) {
			$this->line( $package );
		}

		return 0;
	}

	/**
	 * Builds a package inventory record for restore discovery.
	 *
	 * @param string $package Package path.
	 * @param bool   $include_remote_index Whether to read the remote index sidecar.
	 * @return array<string,mixed>
	 */
	private function package_inventory_record( $package, $include_remote_index = true ) {
		$manifest_path        = $package . '.manifest.json';
		$checksum_path        = $package . '.sha256';
		$remote_index_path    = $package . '.remote-index.json';
		$manifest             = $this->read_package_manifest_quiet( $package );
		$checksum             = $this->read_checksum_sidecar( $package );
		$remote_index         = $include_remote_index ? $this->read_remote_index_quiet( $package ) : array();
		$basename             = basename( $package );
		$manifest_present     = is_readable( $manifest_path );
		$checksum_present     = is_readable( $checksum_path );
		$remote_index_present = is_readable( $remote_index_path );
		$manifest_valid       = ! empty( $manifest['package_id'] );
		$checksum_valid       = ! empty( $checksum['value'] );
		$remote_index_valid   = $this->remote_index_is_valid( $remote_index );
		$package_id           = $manifest_valid ? $this->manifest_value( $manifest, 'package_id' ) : $this->package_id_from_archive_name( $basename );

		return array(
			'package_id'           => $package_id,
			'archive_name'         => $basename,
			'archive_size'         => is_file( $package ) ? (int) filesize( $package ) : 0,
			'archive_modified_at'  => is_file( $package ) ? gmdate( 'c', (int) filemtime( $package ) ) : '',
			'manifest_name'        => basename( $manifest_path ),
			'manifest_present'     => $manifest_present,
			'manifest_valid'       => $manifest_valid,
			'checksum_name'        => basename( $checksum_path ),
			'checksum_present'     => $checksum_present,
			'checksum_valid'       => $checksum_valid,
			'remote_index_name'    => basename( $remote_index_path ),
			'remote_index_present' => $remote_index_present,
			'remote_index_valid'   => $remote_index_valid,
			'checksum_algorithm'   => isset( $checksum['algorithm'] ) ? (string) $checksum['algorithm'] : '',
			'checksum_value'       => isset( $checksum['value'] ) ? (string) $checksum['value'] : '',
			'site_url'             => $this->manifest_value( $manifest, 'site_url' ),
			'created_at'           => $this->manifest_value( $manifest, 'created_at' ),
			'producer'             => $this->manifest_value( $manifest, 'producer' ),
			'backup_type'          => $this->manifest_value( $manifest, 'backup_type' ),
			'archive_format'       => $this->manifest_value( $manifest, 'archive_format' ),
			'file_root'            => $this->manifest_value( $manifest, 'file_root' ),
			'database_dump'        => $this->manifest_value( $manifest, 'database_dump' ),
			'consistency_mode'     => $this->manifest_value( $manifest, 'consistency_mode' ),
			'consistency_status'   => $this->manifest_value( $manifest, 'consistency_status' ),
			'verification_ready'   => $manifest_valid && $checksum_valid,
		);
	}

	/**
	 * Builds a remote-safe package index for Drime restore discovery.
	 *
	 * @param string $package Package path.
	 * @return array<string,mixed>
	 */
	private function remote_package_index( $package ) {
		$package_record                         = $this->package_inventory_record( $package, false );
		$package_record['remote_index_present'] = true;
		$package_record['remote_index_valid']   = true;

		return array(
			'schema_version' => 1,
			'index_type'     => 'single_package_restore_index',
			'generated_at'   => gmdate( 'c' ),
			'archive_format' => $this->archive_format(),
			'package_count'  => 1,
			'packages'       => array( $package_record ),
			'restore_policy' => array(
				'requires_archive_manifest_checksum' => true,
				'destructive_restore_automated'     => false,
				'manual_restore_required'           => true,
			),
		);
	}

	/**
	 * Builds a remote-safe package catalog snapshot for Drime restore discovery.
	 *
	 * @return array<string,mixed>
	 */
	private function remote_catalog_snapshot() {
		$packages = glob( $this->outbox_path() . DIRECTORY_SEPARATOR . '*.' . $this->archive_format() );
		if ( ! is_array( $packages ) ) {
			$packages = array();
		}

		sort( $packages );

		return array(
			'schema_version' => 1,
			'catalog_type'   => 'folder_package_catalog_snapshot',
			'generated_at'   => gmdate( 'c' ),
			'archive_format' => $this->archive_format(),
			'package_count'  => count( $packages ),
			'packages'       => array_map( array( $this, 'package_inventory_record' ), $packages ),
			'restore_policy' => array(
				'requires_archive_manifest_checksum' => true,
				'destructive_restore_automated'     => false,
				'manual_restore_required'           => true,
			),
		);
	}

	/**
	 * Prints read-only cleanup candidates for local package artifacts.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function cleanup_preview_command( array $options ) {
		$older_than_days = isset( $options['older-than-days'] ) ? max( 0, (int) $options['older-than-days'] ) : 14;
		$preview         = $this->cleanup_preview_data( $older_than_days );

		if ( isset( $options['format'] ) && 'json' === (string) $options['format'] ) {
			$this->line( json_encode( $preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

			return 0;
		}

		$this->line( 'Cleanup preview only. No files were deleted.' );
		$this->line( 'Older than days: ' . (string) $preview['older_than_days'] );
		$this->line( 'Outbox candidates: ' . (string) $preview['outbox']['candidate_count'] );
		foreach ( $preview['outbox']['candidates'] as $candidate ) {
			$this->line( '- outbox package: ' . $candidate['archive_name'] . ' (age_days=' . (string) $candidate['age_days'] . ')' );
		}

		$this->line( 'Restore staging candidates: ' . (string) $preview['restore_staging']['candidate_count'] );
		foreach ( $preview['restore_staging']['candidates'] as $candidate ) {
			$this->line( '- restore directory: ' . $candidate['directory_name'] . ' (age_days=' . (string) $candidate['age_days'] . ')' );
		}

		return 0;
	}

	/**
	 * Builds read-only cleanup preview data.
	 *
	 * @param int $older_than_days Minimum age.
	 * @return array<string,mixed>
	 */
	private function cleanup_preview_data( $older_than_days ) {
		$cutoff_timestamp = time() - ( $older_than_days * self::DAY_IN_SECONDS );
		$outbox           = $this->cleanup_preview_outbox_candidates( $cutoff_timestamp );
		$restore_staging  = $this->cleanup_preview_restore_candidates( $cutoff_timestamp );

		return array(
			'schema_version'                => 1,
			'generated_at'                  => gmdate( 'c' ),
			'older_than_days'               => $older_than_days,
			'cutoff_timestamp'              => $cutoff_timestamp,
			'cutoff_at'                     => gmdate( 'c', $cutoff_timestamp ),
			'outbox'                        => array(
				'candidate_count' => count( $outbox ),
				'candidates'      => $outbox,
			),
			'restore_staging'               => array(
				'candidate_count' => count( $restore_staging ),
				'candidates'      => $restore_staging,
			),
			'total_candidate_count'         => count( $outbox ) + count( $restore_staging ),
			'destructive_actions_performed' => false,
		);
	}

	/**
	 * Finds local outbox packages that are old enough for operator review.
	 *
	 * @param int $cutoff_timestamp Cutoff Unix timestamp.
	 * @return array<int,array<string,mixed>>
	 */
	private function cleanup_preview_outbox_candidates( $cutoff_timestamp ) {
		$packages = glob( $this->outbox_path() . DIRECTORY_SEPARATOR . '*.' . $this->archive_format() );
		if ( ! is_array( $packages ) ) {
			return array();
		}

		sort( $packages );

		$candidates = array();
		foreach ( $packages as $package ) {
			$modified_at = is_file( $package ) ? (int) filemtime( $package ) : 0;
			if ( 0 === $modified_at || $modified_at > $cutoff_timestamp ) {
				continue;
			}

			$record                     = $this->package_inventory_record( $package );
			$record['age_days']         = $this->age_days( $modified_at );
			$record['suggested_action'] = 'operator_review';
			$candidates[]               = $record;
		}

		return $candidates;
	}

	/**
	 * Finds restore staging directories that are old enough for operator review.
	 *
	 * @param int $cutoff_timestamp Cutoff Unix timestamp.
	 * @return array<int,array<string,mixed>>
	 */
	private function cleanup_preview_restore_candidates( $cutoff_timestamp ) {
		$directories = glob( $this->restore_path() . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR );
		if ( ! is_array( $directories ) ) {
			return array();
		}

		sort( $directories );

		$candidates = array();
		foreach ( $directories as $directory ) {
			$modified_at = is_dir( $directory ) ? (int) filemtime( $directory ) : 0;
			if ( 0 === $modified_at || $modified_at > $cutoff_timestamp ) {
				continue;
			}

			$candidates[] = array(
				'directory_name'         => basename( $directory ),
				'modified_at'            => gmdate( 'c', $modified_at ),
				'age_days'               => $this->age_days( $modified_at ),
				'restore_notes_present'  => is_readable( $directory . DIRECTORY_SEPARATOR . 'RESTORE_NOTES.txt' ),
				'restore_report_present' => is_readable( $directory . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json' ),
				'suggested_action'       => 'operator_review',
			);
		}

		return $candidates;
	}

	/**
	 * Deletes old local artifacts after explicit operator confirmation.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function cleanup_command( array $options ) {
		$confirm = isset( $options['confirm'] ) ? trim( (string) $options['confirm'] ) : '';
		if ( 'delete-local-artifacts' !== $confirm ) {
			$this->error( 'Refusing cleanup without --confirm=delete-local-artifacts.' );
			return 1;
		}

		$older_than_days = isset( $options['older-than-days'] ) ? max( 0, (int) $options['older-than-days'] ) : 14;
		$preview         = $this->cleanup_preview_data( $older_than_days );
		$outbox          = $this->delete_outbox_cleanup_candidates( $preview['outbox']['candidates'] );
		$restore_staging = $this->delete_restore_cleanup_candidates( $preview['restore_staging']['candidates'] );
		$failure_count   = count( $outbox['failures'] ) + count( $restore_staging['failures'] );

		$result = array(
			'schema_version'                => 1,
			'generated_at'                  => gmdate( 'c' ),
			'older_than_days'               => $older_than_days,
			'confirmation'                  => $confirm,
			'outbox'                        => $outbox,
			'restore_staging'               => $restore_staging,
			'total_candidate_count'         => $preview['total_candidate_count'],
			'failure_count'                 => $failure_count,
			'destructive_actions_performed' => true,
		);

		if ( isset( $options['format'] ) && 'json' === (string) $options['format'] ) {
			$this->line( json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

			return 0 === $failure_count ? 0 : 1;
		}

		$this->line( 'Local cleanup completed.' );
		$this->line( 'Older than days: ' . (string) $older_than_days );
		$this->line( 'Outbox packages deleted: ' . (string) $outbox['deleted_package_count'] );
		$this->line( 'Outbox files deleted: ' . (string) $outbox['deleted_file_count'] );
		$this->line( 'Restore staging directories deleted: ' . (string) $restore_staging['deleted_directory_count'] );
		$this->line( 'Failures: ' . (string) $failure_count );

		return 0 === $failure_count ? 0 : 1;
	}

	/**
	 * Deletes confirmed outbox cleanup candidates.
	 *
	 * @param array<int,array<string,mixed>> $candidates Candidates.
	 * @return array<string,mixed>
	 */
	private function delete_outbox_cleanup_candidates( array $candidates ) {
		$result = array(
			'candidate_count'        => count( $candidates ),
			'deleted_package_count'  => 0,
			'deleted_file_count'     => 0,
			'deleted'                => array(),
			'failures'               => array(),
		);

		foreach ( $candidates as $candidate ) {
			$archive_name = isset( $candidate['archive_name'] ) ? (string) $candidate['archive_name'] : '';
			$deleted      = $this->delete_outbox_package_set( $archive_name );
			if ( ! empty( $deleted['failure_reason'] ) ) {
				$result['failures'][] = $deleted;
				continue;
			}

			++$result['deleted_package_count'];
			$result['deleted_file_count'] += $deleted['deleted_file_count'];
			$result['deleted'][]           = $deleted;
		}

		return $result;
	}

	/**
	 * Deletes one archive and its known server-runner sidecars.
	 *
	 * @param string $archive_name Archive basename.
	 * @return array<string,mixed>
	 */
	private function delete_outbox_package_set( $archive_name ) {
		if ( ! $this->safe_cleanup_child_name( $archive_name ) ) {
			return $this->cleanup_failure( $archive_name, 'unsafe_archive_name' );
		}

		$archive = $this->outbox_path() . DIRECTORY_SEPARATOR . $archive_name;
		if ( ! $this->path_is_expected_child( $this->outbox_path(), $archive, $archive_name ) || ! is_file( $archive ) ) {
			return $this->cleanup_failure( $archive_name, 'archive_missing_or_outside_outbox' );
		}

		$deleted_files = array();
		foreach ( $this->outbox_cleanup_paths( $archive ) as $path ) {
			if ( ! file_exists( $path ) ) {
				continue;
			}

			if ( ! is_file( $path ) || ! $this->path_is_expected_child( $this->outbox_path(), $path, basename( $path ) ) ) {
				return $this->cleanup_failure( $archive_name, 'unsafe_outbox_file' );
			}

			if ( ! unlink( $path ) ) {
				return $this->cleanup_failure( $archive_name, 'delete_failed' );
			}

			$deleted_files[] = basename( $path );
		}

		return array(
			'archive_name'        => $archive_name,
			'deleted_file_count'  => count( $deleted_files ),
			'deleted_file_names'  => $deleted_files,
			'failure_reason'      => '',
		);
	}

	/**
	 * Returns the archive and known sidecar paths for local cleanup.
	 *
	 * @param string $archive Archive path.
	 * @return array<int,string>
	 */
	private function outbox_cleanup_paths( $archive ) {
		$paths = array();
		foreach ( array( '.manifest.json', '.sha256', '.sha256sum', '.remote-index.json', '.remote-catalog.json' ) as $suffix ) {
			$paths[] = $archive . $suffix;
		}
		$paths[] = $archive;

		return $paths;
	}

	/**
	 * Deletes confirmed restore staging cleanup candidates.
	 *
	 * @param array<int,array<string,mixed>> $candidates Candidates.
	 * @return array<string,mixed>
	 */
	private function delete_restore_cleanup_candidates( array $candidates ) {
		$result = array(
			'candidate_count'           => count( $candidates ),
			'deleted_directory_count'   => 0,
			'deleted'                   => array(),
			'failures'                  => array(),
		);

		foreach ( $candidates as $candidate ) {
			$directory_name = isset( $candidate['directory_name'] ) ? (string) $candidate['directory_name'] : '';
			$deleted        = $this->delete_restore_staging_directory( $directory_name );
			if ( ! empty( $deleted['failure_reason'] ) ) {
				$result['failures'][] = $deleted;
				continue;
			}

			++$result['deleted_directory_count'];
			$result['deleted'][] = $deleted;
		}

		return $result;
	}

	/**
	 * Deletes one restore staging directory under the configured restore path.
	 *
	 * @param string $directory_name Directory basename.
	 * @return array<string,mixed>
	 */
	private function delete_restore_staging_directory( $directory_name ) {
		if ( ! $this->safe_cleanup_child_name( $directory_name ) ) {
			return $this->cleanup_failure( $directory_name, 'unsafe_directory_name' );
		}

		$directory = $this->restore_path() . DIRECTORY_SEPARATOR . $directory_name;
		if ( ! $this->path_is_expected_child( $this->restore_path(), $directory, $directory_name ) || ! is_dir( $directory ) ) {
			return $this->cleanup_failure( $directory_name, 'directory_missing_or_outside_restore_path' );
		}

		if ( ! $this->remove_restore_staging_directory( $directory ) ) {
			return $this->cleanup_failure( $directory_name, 'delete_failed' );
		}

		return array(
			'directory_name' => $directory_name,
			'failure_reason' => '',
		);
	}

	/**
	 * Removes a restore staging directory after restore-path containment checks.
	 *
	 * @param string $directory Directory path.
	 * @return bool
	 */
	private function remove_restore_staging_directory( $directory ) {
		$restore_path = $this->restore_path();
		$normalized   = $this->normalize_path( $directory );
		$base         = $this->normalize_path( $restore_path );
		if ( '' === $normalized || '' === $base || 0 !== strpos( $normalized, $base . '/' ) || ! is_dir( $directory ) ) {
			return false;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				if ( ! rmdir( $item->getPathname() ) ) {
					return false;
				}
				continue;
			}

			if ( ! unlink( $item->getPathname() ) ) {
				return false;
			}
		}

		return rmdir( $directory );
	}

	/**
	 * Builds a cleanup failure record.
	 *
	 * @param string $name Name.
	 * @param string $reason Reason.
	 * @return array<string,mixed>
	 */
	private function cleanup_failure( $name, $reason ) {
		return array(
			'name'           => $name,
			'failure_reason' => $reason,
		);
	}

	/**
	 * Checks whether a cleanup candidate basename is safe to reconstruct.
	 *
	 * @param string $name Basename.
	 * @return bool
	 */
	private function safe_cleanup_child_name( $name ) {
		return '' !== $name
			&& '.' !== $name
			&& '..' !== $name
			&& false === strpos( $name, '/' )
			&& false === strpos( $name, '\\' )
			&& basename( $name ) === $name;
	}

	/**
	 * Checks whether a path is the expected direct child of a base path.
	 *
	 * @param string $base Base path.
	 * @param string $path Child path.
	 * @param string $name Expected basename.
	 * @return bool
	 */
	private function path_is_expected_child( $base, $path, $name ) {
		$base = $this->normalize_path( $base );
		$path = $this->normalize_path( $path );

		return '' !== $base && $path === $base . '/' . $name;
	}

	/**
	 * Calculates whole days elapsed since a timestamp.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return int
	 */
	private function age_days( $timestamp ) {
		return max( 0, (int) floor( ( time() - $timestamp ) / self::DAY_IN_SECONDS ) );
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

		if ( ! $this->verify_package( $package ) ) {
			return 1;
		}

		$this->print_verify_next_steps( $package );

		return 0;
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

		$this->print_inspect_next_steps( $package, $manifest );

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
		$this->print_fetch_next_steps( $package );

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
			'Archive Format: ' . $this->manifest_value( $manifest, 'archive_format' ),
			'File Root: ' . $this->manifest_value( $manifest, 'file_root' ),
			'Database Dump: ' . $this->manifest_value( $manifest, 'database_dump' ),
			'',
			'Recommended inspection:',
			'- Review htdocs/ before any file replacement.',
			'- Review database.sql before any database import.',
			'- Review manifest.json and package sidecars before approving recovery work.',
			'- Keep production restore steps manual until separately approved.',
		);

		$this->write_file( $restore_dir . DIRECTORY_SEPARATOR . 'RESTORE_NOTES.txt', implode( "\n", $notes ) . "\n" );
		if ( ! $this->write_json( $restore_dir . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json', $this->restore_report( $package, $restore_dir, $manifest ) ) ) {
			$this->error( 'Warning: package was staged, but RESTORE_REPORT.json could not be written.' );
		}

		$this->print_stage_restore_next_steps( $package, $restore_dir, $manifest );

		return 0;
	}

	/**
	 * Builds a non-destructive restore staging report.
	 *
	 * @param string              $package Package path.
	 * @param string              $restore_dir Restore staging directory.
	 * @param array<string,mixed> $manifest Manifest.
	 * @return array<string,mixed>
	 */
	private function restore_report( $package, $restore_dir, array $manifest ) {
		$checksum = $this->read_checksum_sidecar( $package );

		return array(
			'schema_version'             => 1,
			'generated_at'               => gmdate( 'c' ),
			'status'                     => 'staged_for_inspection',
			'package_id'                 => $this->manifest_value( $manifest, 'package_id' ),
			'archive_name'               => basename( $package ),
			'archive_size'               => is_file( $package ) ? (int) filesize( $package ) : 0,
			'manifest_name'              => basename( $package . '.manifest.json' ),
			'checksum_name'              => basename( $package . '.sha256' ),
			'checksum_algorithm'         => isset( $checksum['algorithm'] ) ? (string) $checksum['algorithm'] : '',
			'checksum_value'             => isset( $checksum['value'] ) ? (string) $checksum['value'] : '',
			'site_url'                   => $this->manifest_value( $manifest, 'site_url' ),
			'created_at'                 => $this->manifest_value( $manifest, 'created_at' ),
			'producer'                   => $this->manifest_value( $manifest, 'producer' ),
			'backup_type'                => $this->manifest_value( $manifest, 'backup_type' ),
			'archive_format'             => $this->manifest_value( $manifest, 'archive_format' ),
			'file_root'                  => $this->manifest_value( $manifest, 'file_root' ),
			'database_dump'              => $this->manifest_value( $manifest, 'database_dump' ),
			'consistency_mode'           => $this->manifest_value( $manifest, 'consistency_mode' ),
			'consistency_status'         => $this->manifest_value( $manifest, 'consistency_status' ),
			'restore_directory_name'     => basename( $restore_dir ),
			'package_verified'           => true,
			'archive_members_safe'       => true,
			'extracted_for_inspection'   => true,
			'database_imported'          => false,
			'live_files_overwritten'     => false,
			'manual_restore_required'    => true,
			'recommended_next_steps'     => array(
				'Review RESTORE_NOTES.txt.',
				'Review htdocs before any file replacement.',
				'Review database.sql before any database import.',
				'Keep production restore steps manual until separately approved.',
			),
		);
	}

	/**
	 * Performs a read-only restore apply preflight.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function restore_dry_run_command( array $options ) {
		$staged_path               = isset( $options['staged-path'] ) ? $this->normalize_path( (string) $options['staged-path'] ) : '';
		$scope                     = isset( $options['scope'] ) ? strtolower( trim( (string) $options['scope'] ) ) : 'files-and-database';
		$pre_backup_evidence_path  = isset( $options['pre-restore-evidence'] ) ? $this->normalize_path( (string) $options['pre-restore-evidence'] ) : $this->restore_pre_backup_evidence_path();
		$write_report              = isset( $options['write-report'] ) && $this->truthy_value( $options['write-report'] );

		if ( '' === $staged_path ) {
			$this->error( 'Missing --staged-path=/path/to/staged/package.' );
			return 1;
		}

		if ( ! in_array( $scope, array( 'files', 'database', 'files-and-database' ), true ) ) {
			$this->error( 'Invalid --scope. Use files, database, or files-and-database.' );
			return 1;
		}

		$result = $this->restore_dry_run_result( $staged_path, $scope, $pre_backup_evidence_path );
		if ( $write_report ) {
			$result = $this->write_restore_dry_run_report( $result );
		}

		if ( isset( $options['format'] ) && 'json' === strtolower( (string) $options['format'] ) ) {
			$this->line( json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		} else {
			$this->print_restore_dry_run_result( $result );
		}

		return 0 === (int) $result['failure_count'] ? 0 : 1;
	}

	/**
	 * Applies a staged restore after destructive gates pass.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function restore_apply_command( array $options ) {
		$staged_path              = isset( $options['staged-path'] ) ? $this->normalize_path( (string) $options['staged-path'] ) : '';
		$scope                    = isset( $options['scope'] ) ? strtolower( trim( (string) $options['scope'] ) ) : 'files-and-database';
		$confirm                  = isset( $options['confirm'] ) ? (string) $options['confirm'] : '';
		$pre_backup_evidence_path = isset( $options['pre-restore-evidence'] ) ? $this->normalize_path( (string) $options['pre-restore-evidence'] ) : $this->restore_pre_backup_evidence_path();

		if ( '' === $staged_path ) {
			$this->error( 'Missing --staged-path=/path/to/staged/package.' );
			return 1;
		}

		if ( 'restore-staging-site' !== $confirm ) {
			$this->error( 'Restore apply requires --confirm=restore-staging-site.' );
			return 1;
		}

		if ( ! in_array( $scope, array( 'database', 'files' ), true ) ) {
			$this->error( 'Only --scope=database or --scope=files is implemented for restore-apply in this release.' );
			return 1;
		}

		$result = 'database' === $scope
			? $this->restore_apply_database_result( $staged_path, $pre_backup_evidence_path )
			: $this->restore_apply_files_result( $staged_path, $pre_backup_evidence_path );

		if ( isset( $options['format'] ) && 'json' === strtolower( (string) $options['format'] ) ) {
			$this->line( json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		} else {
			$this->print_restore_apply_result( $result );
		}

		return 'succeeded' === $result['status'] ? 0 : 1;
	}

	/**
	 * Imports a staged database after dry-run gates pass.
	 *
	 * @param string $staged_path Staged restore path.
	 * @param string $pre_backup_evidence_path Pre-restore evidence path.
	 * @return array<string,mixed>
	 */
	private function restore_apply_database_result( $staged_path, $pre_backup_evidence_path ) {
		$scope       = 'database';
		$dry_run     = $this->restore_dry_run_result( $staged_path, $scope, $pre_backup_evidence_path );
		$result      = array(
			'schema_version'                    => 1,
			'generated_at'                      => gmdate( 'c' ),
			'command'                           => 'restore-apply',
			'status'                            => 'failed',
			'scope'                             => $scope,
			'package_id'                        => $dry_run['package_id'],
			'staged_path'                       => $staged_path,
			'target_wordpress_path'             => $dry_run['target_wordpress_path'],
			'restore_environment'               => $dry_run['restore_environment'],
			'confirmation_phrase_accepted'      => true,
			'dry_run_checks_passed'             => 0 === (int) $dry_run['failure_count'],
			'dry_run_failure_count'             => (int) $dry_run['failure_count'],
			'dry_run_checks'                    => $dry_run['checks'],
			'pre_restore_backup_path'           => $dry_run['pre_restore_backup_path'],
			'pre_restore_evidence_path'         => $dry_run['pre_restore_evidence_path'],
			'database_dump_path'                => '',
			'database_import_attempted'         => false,
			'database_import_succeeded'         => false,
			'database_import_exit_code'         => null,
			'database_import_output'            => array(),
			'database_imported'                 => false,
			'file_restore_attempted'            => false,
			'file_restore_succeeded'            => false,
			'live_files_overwritten'            => false,
			'destructive_actions_performed'     => false,
			'pre_restore_backup_created'        => false,
			'restore_apply_report_written'      => false,
			'restore_apply_report_path'         => '',
			'restore_apply_report_error'        => '',
			'failure_step'                      => '',
			'manual_recovery_notes'             => array(),
		);

		if ( ! $result['dry_run_checks_passed'] ) {
			$result['failure_step']          = 'restore-dry-run';
			$result['manual_recovery_notes'] = array( 'No database import was attempted because restore dry-run checks failed.' );
			return $this->write_restore_apply_report( $result );
		}

		$report_path = $this->restore_apply_report_path( (string) $result['package_id'] );
		if ( '' === $report_path ) {
			$result['failure_step']               = 'restore-apply-report-path';
			$result['restore_apply_report_error'] = 'Restore apply reports path is missing, unsafe, or not writable.';
			$result['manual_recovery_notes']      = array( 'No database import was attempted because the restore apply report path was not ready.' );
			return $result;
		}
		$result['restore_apply_report_path'] = $report_path;

		$database_dump_path = $this->restore_apply_database_dump_path( $staged_path );
		$result['database_dump_path'] = $database_dump_path;
		if ( '' === $database_dump_path || ! is_readable( $database_dump_path ) ) {
			$result['failure_step']          = 'staged-database-dump';
			$result['manual_recovery_notes'] = array( 'No database import was attempted because the staged database dump was not readable.' );
			return $this->write_restore_apply_report( $result );
		}

		$result['database_import_attempted']     = true;
		$result['destructive_actions_performed'] = true;
		$import_result                           = $this->import_database( $database_dump_path, (string) $result['target_wordpress_path'] );
		$result['database_import_exit_code']     = (int) $import_result['exit_code'];
		$result['database_import_output']        = array_slice( $import_result['output'], 0, 20 );
		$result['database_import_succeeded']     = 0 === (int) $import_result['exit_code'];
		$result['database_imported']             = $result['database_import_succeeded'];
		$result['status']                        = $result['database_import_succeeded'] ? 'succeeded' : 'failed';

		if ( ! $result['database_import_succeeded'] ) {
			$result['failure_step']          = 'database-import';
			$result['manual_recovery_notes'] = array( 'Review pre-restore backup evidence before attempting manual database recovery.' );
		}

		return $this->write_restore_apply_report( $result );
	}

	/**
	 * Replaces target files from staged htdocs after dry-run gates pass.
	 *
	 * @param string $staged_path Staged restore path.
	 * @param string $pre_backup_evidence_path Pre-restore evidence path.
	 * @return array<string,mixed>
	 */
	private function restore_apply_files_result( $staged_path, $pre_backup_evidence_path ) {
		$scope   = 'files';
		$dry_run = $this->restore_dry_run_result( $staged_path, $scope, $pre_backup_evidence_path );
		$result  = array(
			'schema_version'                    => 1,
			'generated_at'                      => gmdate( 'c' ),
			'command'                           => 'restore-apply',
			'status'                            => 'failed',
			'scope'                             => $scope,
			'package_id'                        => $dry_run['package_id'],
			'staged_path'                       => $staged_path,
			'target_wordpress_path'             => $dry_run['target_wordpress_path'],
			'restore_environment'               => $dry_run['restore_environment'],
			'confirmation_phrase_accepted'      => true,
			'dry_run_checks_passed'             => 0 === (int) $dry_run['failure_count'],
			'dry_run_failure_count'             => (int) $dry_run['failure_count'],
			'dry_run_checks'                    => $dry_run['checks'],
			'pre_restore_backup_path'           => $dry_run['pre_restore_backup_path'],
			'pre_restore_evidence_path'         => $dry_run['pre_restore_evidence_path'],
			'database_dump_path'                => '',
			'database_import_attempted'         => false,
			'database_import_succeeded'         => false,
			'database_import_exit_code'         => null,
			'database_import_output'            => array(),
			'database_imported'                 => false,
			'file_root_path'                    => '',
			'file_restore_attempted'            => false,
			'file_restore_succeeded'            => false,
			'live_files_overwritten'            => false,
			'destructive_actions_performed'     => false,
			'pre_restore_backup_created'        => false,
			'restore_apply_report_written'      => false,
			'restore_apply_report_path'         => '',
			'restore_apply_report_error'        => '',
			'failure_step'                      => '',
			'manual_recovery_notes'             => array(),
		);

		if ( ! $result['dry_run_checks_passed'] ) {
			$result['failure_step']          = 'restore-dry-run';
			$result['manual_recovery_notes'] = array( 'No file restore was attempted because restore dry-run checks failed.' );
			return $this->write_restore_apply_report( $result );
		}

		$report_path = $this->restore_apply_report_path( (string) $result['package_id'] );
		if ( '' === $report_path ) {
			$result['failure_step']               = 'restore-apply-report-path';
			$result['restore_apply_report_error'] = 'Restore apply reports path is missing, unsafe, or not writable.';
			$result['manual_recovery_notes']      = array( 'No file restore was attempted because the restore apply report path was not ready.' );
			return $result;
		}
		$result['restore_apply_report_path'] = $report_path;

		$file_root_path = $this->restore_apply_file_root_path( $staged_path );
		$result['file_root_path'] = $file_root_path;
		if ( '' === $file_root_path || ! is_dir( $file_root_path ) || ! is_readable( $file_root_path ) ) {
			$result['failure_step']          = 'staged-file-root';
			$result['manual_recovery_notes'] = array( 'No file restore was attempted because the staged file root was not readable.' );
			return $this->write_restore_apply_report( $result );
		}

		$result['file_restore_attempted']        = true;
		$result['destructive_actions_performed'] = true;
		$result['file_restore_succeeded']        = $this->replace_target_files_from_staging( $file_root_path, (string) $result['target_wordpress_path'] );
		$result['live_files_overwritten']        = $result['file_restore_succeeded'];
		$result['status']                        = $result['file_restore_succeeded'] ? 'succeeded' : 'failed';

		if ( ! $result['file_restore_succeeded'] ) {
			$result['failure_step']          = 'file-restore';
			$result['manual_recovery_notes'] = array( 'Review pre-restore file backup evidence before attempting manual file recovery.' );
		}

		return $this->write_restore_apply_report( $result );
	}

	/**
	 * Builds a read-only restore dry-run result.
	 *
	 * @param string $staged_path Staged restore path.
	 * @param string $scope Restore scope.
	 * @param string $pre_backup_evidence_path Pre-restore evidence path.
	 * @return array<string,mixed>
	 */
	private function restore_dry_run_result( $staged_path, $scope, $pre_backup_evidence_path ) {
		$checks          = array();
		$restore_base    = $this->restore_path();
		$target_path     = $this->normalize_path( $this->config_string( 'restore_target_wordpress_path' ) );
		$wordpress_path  = $this->wordpress_path();
		$pre_backup_path = $this->normalize_path( $this->config_string( 'restore_pre_backup_path' ) );
		$report_path     = $staged_path . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json';
		$report          = $this->read_restore_report( $report_path );
		$package_id      = isset( $report['package_id'] ) ? (string) $report['package_id'] : basename( $staged_path );
		$pre_backup_evidence = $this->read_restore_report( $pre_backup_evidence_path );

		$this->add_restore_dry_run_check(
			$checks,
			'restore_apply_enabled',
			$this->config_bool( 'restore_apply_enabled' ),
			'Restore apply is explicitly enabled in runner config.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'restore_environment',
			'staging' === strtolower( $this->config_string( 'restore_environment' ) ),
			'Restore environment is staging.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'staged_path_under_restore_path',
			'' !== $restore_base && $this->path_is_within_directory( $restore_base, $staged_path ) && $this->normalize_path( $restore_base ) !== $staged_path,
			'Staged path is inside the configured restore path.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'staged_path_readable',
			is_dir( $staged_path ) && is_readable( $staged_path ),
			'Staged path exists and is readable.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'restore_report_readable',
			is_file( $report_path ) && is_readable( $report_path ),
			'RESTORE_REPORT.json exists and is readable.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'restore_report_valid_json',
			! empty( $report ),
			'RESTORE_REPORT.json is valid JSON.'
		);

		$this->add_restore_dry_run_report_check( $checks, $report, 'status', 'staged_for_inspection' );
		$this->add_restore_dry_run_report_check( $checks, $report, 'package_verified', true );
		$this->add_restore_dry_run_report_check( $checks, $report, 'archive_members_safe', true );
		$this->add_restore_dry_run_report_check( $checks, $report, 'extracted_for_inspection', true );
		$this->add_restore_dry_run_report_check( $checks, $report, 'database_imported', false );
		$this->add_restore_dry_run_report_check( $checks, $report, 'live_files_overwritten', false );
		$this->add_restore_dry_run_report_check( $checks, $report, 'manual_restore_required', true );

		if ( $this->restore_scope_includes_files( $scope ) ) {
			$file_root      = isset( $report['file_root'] ) ? (string) $report['file_root'] : '';
			$file_root_safe = $this->restore_report_relative_name_is_safe( $file_root );
			$this->add_restore_dry_run_check(
				$checks,
				'restore_report_file_root_safe',
				$file_root_safe,
				'RESTORE_REPORT.json file_root is a safe single path segment.'
			);
			$this->add_restore_dry_run_check(
				$checks,
				'staged_files_present',
				$file_root_safe && is_dir( $staged_path . DIRECTORY_SEPARATOR . $file_root ),
				'Staged WordPress files are present for file restore scope.'
			);
		}

		if ( $this->restore_scope_includes_database( $scope ) ) {
			$database_dump      = isset( $report['database_dump'] ) ? (string) $report['database_dump'] : '';
			$database_dump_safe = $this->restore_report_relative_name_is_safe( $database_dump );
			$this->add_restore_dry_run_check(
				$checks,
				'restore_report_database_dump_safe',
				$database_dump_safe,
				'RESTORE_REPORT.json database_dump is a safe single path segment.'
			);
			$this->add_restore_dry_run_check(
				$checks,
				'staged_database_present',
				$database_dump_safe && is_file( $staged_path . DIRECTORY_SEPARATOR . $database_dump ) && is_readable( $staged_path . DIRECTORY_SEPARATOR . $database_dump ),
				'Staged database dump is present for database restore scope.'
			);
		}

		$this->add_restore_dry_run_check(
			$checks,
			'target_wordpress_path_configured',
			'' !== $target_path,
			'Restore target WordPress path is configured.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'target_wordpress_path_matches_runner',
			'' !== $target_path && $target_path === $wordpress_path,
			'Restore target WordPress path matches the runner WordPress path.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'target_wordpress_path_safe',
			'' !== $target_path && ! $this->dangerous_restore_target_path( $target_path ),
			'Restore target WordPress path is not a broad system path.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'target_wordpress_path_readable',
			is_dir( $target_path ) && is_readable( $target_path ),
			'Restore target WordPress path exists and is readable.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'pre_restore_backup_path_ready',
			'' !== $pre_backup_path && $this->path_or_parent_is_writable_directory( $pre_backup_path ),
			'Pre-restore backup path exists or its parent is writable.'
		);
		$this->add_pre_restore_evidence_checks( $checks, $pre_backup_evidence_path, $pre_backup_path, $pre_backup_evidence, $package_id, $scope, $target_path );
		$this->add_restore_dry_run_check(
			$checks,
			'target_free_space',
			$this->has_minimum_free_space( $target_path ),
			'Target filesystem has the configured minimum free space.'
		);

		$failure_count = 0;
		foreach ( $checks as $check ) {
			if ( empty( $check['passed'] ) ) {
				++$failure_count;
			}
		}

		return array(
			'schema_version'                  => 1,
			'generated_at'                    => gmdate( 'c' ),
			'command'                         => 'restore-dry-run',
			'status'                          => 0 === $failure_count ? 'passed' : 'failed',
			'scope'                           => $scope,
			'package_id'                      => $package_id,
			'staged_path'                     => $staged_path,
			'target_wordpress_path'           => $target_path,
			'restore_environment'             => $this->config_string( 'restore_environment' ),
			'restore_apply_enabled'           => $this->config_bool( 'restore_apply_enabled' ),
			'pre_restore_backup_path'         => $pre_backup_path,
			'pre_restore_evidence_path'       => $pre_backup_evidence_path,
			'failure_count'                   => $failure_count,
			'checks'                          => $checks,
			'restore_apply_allowed'           => 0 === $failure_count,
			'destructive_actions_performed'   => false,
			'database_imported'               => false,
			'live_files_overwritten'          => false,
			'pre_restore_backup_created'      => false,
			'restore_apply_command_available' => in_array( $scope, array( 'database', 'files' ), true ),
			'report_write_requested'          => false,
			'report_written'                  => false,
			'report_path'                     => '',
			'report_write_error'              => '',
		);
	}

	/**
	 * Adds pre-restore backup evidence checks.
	 *
	 * @param array<int,array<string,mixed>> $checks Checks.
	 * @param string                         $evidence_path Evidence file path.
	 * @param string                         $pre_backup_path Pre-restore backup directory path.
	 * @param array<string,mixed>            $evidence Evidence payload.
	 * @param string                         $package_id Package ID.
	 * @param string                         $scope Restore scope.
	 * @param string                         $target_path Target WordPress path.
	 * @return void
	 */
	private function add_pre_restore_evidence_checks( array &$checks, $evidence_path, $pre_backup_path, array $evidence, $package_id, $scope, $target_path ) {
		$this->add_restore_dry_run_check(
			$checks,
			'pre_restore_evidence_path_configured',
			'' !== $evidence_path,
			'Pre-restore backup evidence path is configured.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'pre_restore_evidence_path_under_pre_backup_path',
			'' !== $evidence_path && '' !== $pre_backup_path && $this->path_is_within_directory( $pre_backup_path, $evidence_path ),
			'Pre-restore backup evidence path is inside the configured pre-restore backup path.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'pre_restore_evidence_readable',
			is_file( $evidence_path ) && is_readable( $evidence_path ),
			'Pre-restore backup evidence file exists and is readable.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'pre_restore_evidence_valid_json',
			! empty( $evidence ),
			'Pre-restore backup evidence file is valid JSON.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'pre_restore_evidence_type',
			isset( $evidence['evidence_type'] ) && 'pre_restore_backup' === (string) $evidence['evidence_type'],
			'Pre-restore backup evidence type is pre_restore_backup.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'pre_restore_evidence_package_id',
			isset( $evidence['package_id'] ) && (string) $evidence['package_id'] === $package_id,
			'Pre-restore backup evidence package ID matches the staged package.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'pre_restore_evidence_scope',
			isset( $evidence['scope'] ) && (string) $evidence['scope'] === $scope,
			'Pre-restore backup evidence scope matches the dry-run scope.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'pre_restore_evidence_target_path',
			isset( $evidence['target_wordpress_path'] ) && $this->normalize_path( (string) $evidence['target_wordpress_path'] ) === $target_path,
			'Pre-restore backup evidence target path matches the restore target.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'pre_restore_evidence_generated_at',
			! empty( $evidence['generated_at'] ),
			'Pre-restore backup evidence records when it was generated.'
		);

		if ( $this->restore_scope_includes_database( $scope ) ) {
			$this->add_pre_restore_artifact_check( $checks, $evidence, 'database_export_path', $pre_backup_path, 'database export' );
		}

		if ( $this->restore_scope_includes_files( $scope ) ) {
			$this->add_pre_restore_artifact_check( $checks, $evidence, 'file_backup_path', $pre_backup_path, 'file backup' );
		}
	}

	/**
	 * Adds pre-restore backup artifact checks.
	 *
	 * @param array<int,array<string,mixed>> $checks Checks.
	 * @param array<string,mixed>            $evidence Evidence payload.
	 * @param string                         $key Evidence key.
	 * @param string                         $pre_backup_path Pre-restore backup directory path.
	 * @param string                         $label Artifact label.
	 * @return void
	 */
	private function add_pre_restore_artifact_check( array &$checks, array $evidence, $key, $pre_backup_path, $label ) {
		$artifact_path = isset( $evidence[ $key ] ) ? $this->normalize_path( (string) $evidence[ $key ] ) : '';

		$this->add_restore_dry_run_check(
			$checks,
			'pre_restore_' . $key . '_configured',
			'' !== $artifact_path,
			'Pre-restore ' . $label . ' path is recorded.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'pre_restore_' . $key . '_under_pre_backup_path',
			'' !== $artifact_path && '' !== $pre_backup_path && $this->path_is_within_directory( $pre_backup_path, $artifact_path ),
			'Pre-restore ' . $label . ' path is inside the configured pre-restore backup path.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'pre_restore_' . $key . '_readable',
			is_file( $artifact_path ) && is_readable( $artifact_path ),
			'Pre-restore ' . $label . ' file exists and is readable.'
		);
	}

	/**
	 * Writes a successful restore dry-run evidence report.
	 *
	 * @param array<string,mixed> $result Dry-run result.
	 * @return array<string,mixed>
	 */
	private function write_restore_dry_run_report( array $result ) {
		$result['report_write_requested'] = true;

		if ( 0 !== (int) $result['failure_count'] ) {
			$result['report_write_error'] = 'Dry run failed; success evidence report was not written.';
			return $result;
		}

		$reports_path = $this->restore_reports_path();
		$this->add_restore_dry_run_check(
			$result['checks'],
			'dry_run_report_path_configured',
			'' !== $reports_path,
			'Restore dry-run reports path is configured.'
		);
		$this->add_restore_dry_run_check(
			$result['checks'],
			'dry_run_report_path_safe',
			'' !== $reports_path && ! $this->dangerous_restore_target_path( $reports_path ),
			'Restore dry-run reports path is not a broad system path.'
		);

		if ( '' !== $reports_path && ! $this->dangerous_restore_target_path( $reports_path ) && $this->ensure_directory( $reports_path ) && is_writable( $reports_path ) ) {
			$package_id            = isset( $result['package_id'] ) ? (string) $result['package_id'] : 'restore-package';
			$report_file           = 'RESTORE_DRY_RUN_REPORT-' . $this->safe_slug( $package_id ) . '-' . gmdate( 'Ymd-His' ) . '.json';
			$result['report_path'] = $reports_path . DIRECTORY_SEPARATOR . $report_file;
			$result['report_written'] = true;
			if ( ! $this->write_json_atomic( $result['report_path'], $result ) ) {
				$result['report_written'] = false;
			}
			$this->add_restore_dry_run_check(
				$result['checks'],
				'dry_run_report_written',
				(bool) $result['report_written'],
				'Restore dry-run evidence report was written.'
			);
			if ( ! $result['report_written'] ) {
				$result['report_write_error'] = 'Could not write restore dry-run evidence report.';
			}
		} else {
			$result['report_write_error'] = 'Restore dry-run reports path is missing, unsafe, or not writable.';
			$this->add_restore_dry_run_check(
				$result['checks'],
				'dry_run_report_path_writable',
				false,
				'Restore dry-run reports path exists and is writable.'
			);
		}

		return $this->finalize_restore_dry_run_result( $result );
	}

	/**
	 * Returns a restore apply report path if reporting is ready.
	 *
	 * @param string $package_id Package ID.
	 * @return string
	 */
	private function restore_apply_report_path( $package_id ) {
		$reports_path = $this->restore_reports_path();
		if ( '' === $reports_path || $this->dangerous_restore_target_path( $reports_path ) || ! $this->ensure_directory( $reports_path ) || ! is_writable( $reports_path ) ) {
			return '';
		}

		return $reports_path . DIRECTORY_SEPARATOR . 'RESTORE_APPLY_REPORT-' . $this->safe_slug( $package_id ) . '-' . gmdate( 'Ymd-His' ) . '.json';
	}

	/**
	 * Writes a restore apply report when a path was prepared.
	 *
	 * @param array<string,mixed> $result Apply result.
	 * @return array<string,mixed>
	 */
	private function write_restore_apply_report( array $result ) {
		if ( empty( $result['restore_apply_report_path'] ) ) {
			$result['restore_apply_report_path'] = $this->restore_apply_report_path( (string) $result['package_id'] );
		}

		if ( empty( $result['restore_apply_report_path'] ) ) {
			$result['restore_apply_report_error'] = 'Restore apply reports path is missing, unsafe, or not writable.';
			return $result;
		}

		$result['restore_apply_report_written'] = true;
		if ( ! $this->write_json_atomic( $result['restore_apply_report_path'], $result ) ) {
			$result['restore_apply_report_written'] = false;
			$result['restore_apply_report_error']   = 'Could not write restore apply report.';
			$result['status']                       = 'failed';
			if ( '' === $result['failure_step'] ) {
				$result['failure_step'] = 'restore-apply-report-write';
			}
		}

		return $result;
	}

	/**
	 * Returns the staged database dump path for a restore apply.
	 *
	 * @param string $staged_path Staged restore path.
	 * @return string
	 */
	private function restore_apply_database_dump_path( $staged_path ) {
		$report = $this->read_restore_report( $staged_path . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json' );
		$dump   = isset( $report['database_dump'] ) ? (string) $report['database_dump'] : '';

		if ( ! $this->restore_report_relative_name_is_safe( $dump ) ) {
			return '';
		}

		return $staged_path . DIRECTORY_SEPARATOR . $dump;
	}

	/**
	 * Returns the staged file root path for a restore apply.
	 *
	 * @param string $staged_path Staged restore path.
	 * @return string
	 */
	private function restore_apply_file_root_path( $staged_path ) {
		$report    = $this->read_restore_report( $staged_path . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json' );
		$file_root = isset( $report['file_root'] ) ? (string) $report['file_root'] : '';

		if ( ! $this->restore_report_relative_name_is_safe( $file_root ) ) {
			return '';
		}

		return $staged_path . DIRECTORY_SEPARATOR . $file_root;
	}

	/**
	 * Replaces the configured WordPress target files from staged files.
	 *
	 * @param string $source_path Staged file root.
	 * @param string $target_path Target WordPress path.
	 * @return bool
	 */
	private function replace_target_files_from_staging( $source_path, $target_path ) {
		$target_path = $this->normalize_path( $target_path );

		if ( '' === $source_path || '' === $target_path || $target_path !== $this->wordpress_path() || $this->dangerous_restore_target_path( $target_path ) ) {
			return false;
		}

		if ( ! is_dir( $source_path ) || ! is_readable( $source_path ) || ! $this->directory_has_entries( $source_path ) || ! is_dir( $target_path ) || ! is_writable( $target_path ) ) {
			return false;
		}

		return $this->remove_restore_target_contents( $target_path ) && $this->copy_restore_directory_contents( $source_path, $target_path );
	}

	/**
	 * Returns whether a directory has at least one child.
	 *
	 * @param string $path Directory path.
	 * @return bool
	 */
	private function directory_has_entries( $path ) {
		if ( ! is_dir( $path ) ) {
			return false;
		}

		$iterator = new FilesystemIterator( $path, FilesystemIterator::SKIP_DOTS );

		return $iterator->valid();
	}

	/**
	 * Removes target directory contents for a file restore.
	 *
	 * @param string $target_path Target WordPress path.
	 * @return bool
	 */
	private function remove_restore_target_contents( $target_path ) {
		if ( '' === $target_path || $this->dangerous_restore_target_path( $target_path ) || $target_path !== $this->wordpress_path() || ! is_dir( $target_path ) ) {
			return false;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $target_path, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isLink() || $item->isFile() ) {
				if ( ! unlink( $item->getPathname() ) ) {
					return false;
				}
				continue;
			}

			if ( $item->isDir() && ! rmdir( $item->getPathname() ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Copies staged directory contents into a target directory.
	 *
	 * @param string $source_path Source directory.
	 * @param string $target_path Target directory.
	 * @return bool
	 */
	private function copy_restore_directory_contents( $source_path, $target_path ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source_path, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isLink() ) {
				return false;
			}

			$relative_path = substr( $item->getPathname(), strlen( $source_path ) + 1 );
			$target_item   = $target_path . DIRECTORY_SEPARATOR . $relative_path;

			if ( $item->isDir() ) {
				if ( ! is_dir( $target_item ) && ! mkdir( $target_item, 0755, true ) ) {
					return false;
				}
				continue;
			}

			if ( ! copy( $item->getPathname(), $target_item ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Adds a restore dry-run check record.
	 *
	 * @param array<int,array<string,mixed>> $checks Checks.
	 * @param string                         $name Check name.
	 * @param bool                           $passed Whether check passed.
	 * @param string                         $message Check message.
	 * @return void
	 */
	private function add_restore_dry_run_check( array &$checks, $name, $passed, $message ) {
		$checks[] = array(
			'name'    => $name,
			'passed'  => (bool) $passed,
			'message' => $message,
		);
	}

	/**
	 * Adds a RESTORE_REPORT.json field check.
	 *
	 * @param array<int,array<string,mixed>> $checks Checks.
	 * @param array<string,mixed>            $report Restore report.
	 * @param string                         $key Report key.
	 * @param mixed                          $expected Expected value.
	 * @return void
	 */
	private function add_restore_dry_run_report_check( array &$checks, array $report, $key, $expected ) {
		$this->add_restore_dry_run_check(
			$checks,
			'restore_report_' . $key,
			array_key_exists( $key, $report ) && $report[ $key ] === $expected,
			'RESTORE_REPORT.json records ' . $key . ' as ' . var_export( $expected, true ) . '.'
		);
	}

	/**
	 * Reads a staged restore report.
	 *
	 * @param string $report_path Report path.
	 * @return array<string,mixed>
	 */
	private function read_restore_report( $report_path ) {
		if ( ! is_readable( $report_path ) ) {
			return array();
		}

		$report = json_decode( (string) file_get_contents( $report_path ), true );

		return is_array( $report ) ? $report : array();
	}

	/**
	 * Returns whether a restore scope includes files.
	 *
	 * @param string $scope Restore scope.
	 * @return bool
	 */
	private function restore_scope_includes_files( $scope ) {
		return in_array( $scope, array( 'files', 'files-and-database' ), true );
	}

	/**
	 * Returns whether a restore scope includes database.
	 *
	 * @param string $scope Restore scope.
	 * @return bool
	 */
	private function restore_scope_includes_database( $scope ) {
		return in_array( $scope, array( 'database', 'files-and-database' ), true );
	}

	/**
	 * Returns whether a restore report path name is a safe single segment.
	 *
	 * @param string $value Report value.
	 * @return bool
	 */
	private function restore_report_relative_name_is_safe( $value ) {
		$value = trim( $value );

		return '' !== $value && false === strpos( $value, '/' ) && false === strpos( $value, '\\' ) && '.' !== $value && '..' !== $value;
	}

	/**
	 * Returns whether a path is within a directory.
	 *
	 * @param string $base Base directory.
	 * @param string $path Child path.
	 * @return bool
	 */
	private function path_is_within_directory( $base, $path ) {
		$base = $this->normalize_path( $base );
		$path = $this->normalize_path( $path );

		return '' !== $base && ( $path === $base || 0 === strpos( $path, $base . '/' ) );
	}

	/**
	 * Returns whether a restore target path is too broad to restore into.
	 *
	 * @param string $path Target path.
	 * @return bool
	 */
	private function dangerous_restore_target_path( $path ) {
		$path  = $this->normalize_path( $path );
		$lower = strtolower( $path );

		if ( in_array( $lower, array( '', '/', '/var', '/var/www' ), true ) ) {
			return true;
		}

		return 1 === preg_match( '/^[a-z]:$/', $lower ) || 1 === preg_match( '/^[a-z]:\/$/', $lower );
	}

	/**
	 * Checks whether a path exists as writable directory, or its parent can receive it.
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	private function path_or_parent_is_writable_directory( $path ) {
		if ( is_dir( $path ) ) {
			return is_writable( $path );
		}

		$parent = dirname( $path );

		return '' !== $parent && is_dir( $parent ) && is_writable( $parent );
	}

	/**
	 * Recalculates dry-run status after optional report-writing checks.
	 *
	 * @param array<string,mixed> $result Dry-run result.
	 * @return array<string,mixed>
	 */
	private function finalize_restore_dry_run_result( array $result ) {
		$failure_count = 0;
		foreach ( $result['checks'] as $check ) {
			if ( empty( $check['passed'] ) ) {
				++$failure_count;
			}
		}

		$result['failure_count']         = $failure_count;
		$result['status']                = 0 === $failure_count ? 'passed' : 'failed';
		$result['restore_apply_allowed'] = 0 === $failure_count;

		return $result;
	}

	/**
	 * Prints a restore dry-run result.
	 *
	 * @param array<string,mixed> $result Dry-run result.
	 * @return void
	 */
	private function print_restore_dry_run_result( array $result ) {
		$this->line( 'passed' === $result['status'] ? 'Restore dry run passed.' : 'Restore dry run failed.' );
		$this->line( 'Scope: ' . $result['scope'] );
		$this->line( 'Staged path: ' . $result['staged_path'] );
		$this->line( 'Target WordPress path: ' . $result['target_wordpress_path'] );
		if ( ! empty( $result['report_write_requested'] ) ) {
			$this->line( 'Report written: ' . ( ! empty( $result['report_written'] ) ? 'yes' : 'no' ) );
			if ( ! empty( $result['report_path'] ) ) {
				$this->line( 'Report path: ' . $result['report_path'] );
			}
			if ( ! empty( $result['report_write_error'] ) ) {
				$this->line( 'Report write error: ' . $result['report_write_error'] );
			}
		}
		$this->line( '' );

		foreach ( $result['checks'] as $check ) {
			$this->line( sprintf( '[%s] %s - %s', empty( $check['passed'] ) ? 'fail' : 'ok', $check['name'], $check['message'] ) );
		}

		$this->line( '' );
		$this->line( 'Safety boundary:' );
		$this->line( '- No destructive actions were performed.' );
		$this->line( '- No database import was performed.' );
		$this->line( '- No live WordPress files were overwritten.' );
	}

	/**
	 * Prints a restore apply result.
	 *
	 * @param array<string,mixed> $result Apply result.
	 * @return void
	 */
	private function print_restore_apply_result( array $result ) {
		$this->line( 'succeeded' === $result['status'] ? 'Restore apply succeeded.' : 'Restore apply failed.' );
		$this->line( 'Scope: ' . $result['scope'] );
		$this->line( 'Staged path: ' . $result['staged_path'] );
		$this->line( 'Target WordPress path: ' . $result['target_wordpress_path'] );
		$this->line( 'Dry-run checks passed: ' . ( ! empty( $result['dry_run_checks_passed'] ) ? 'yes' : 'no' ) );
		$this->line( 'Database import attempted: ' . ( ! empty( $result['database_import_attempted'] ) ? 'yes' : 'no' ) );
		$this->line( 'Database import succeeded: ' . ( ! empty( $result['database_import_succeeded'] ) ? 'yes' : 'no' ) );
		$this->line( 'File restore attempted: ' . ( ! empty( $result['file_restore_attempted'] ) ? 'yes' : 'no' ) );
		$this->line( 'File restore succeeded: ' . ( ! empty( $result['file_restore_succeeded'] ) ? 'yes' : 'no' ) );
		$this->line( 'Live files overwritten: ' . ( ! empty( $result['live_files_overwritten'] ) ? 'yes' : 'no' ) );
		if ( ! empty( $result['restore_apply_report_path'] ) ) {
			$this->line( 'Report path: ' . $result['restore_apply_report_path'] );
			$this->line( 'Report written: ' . ( ! empty( $result['restore_apply_report_written'] ) ? 'yes' : 'no' ) );
		}
		if ( ! empty( $result['failure_step'] ) ) {
			$this->line( 'Failure step: ' . $result['failure_step'] );
		}
		if ( ! empty( $result['restore_apply_report_error'] ) ) {
			$this->line( 'Report error: ' . $result['restore_apply_report_error'] );
		}
		$this->line( '' );
		$this->line( 'Safety boundary:' );
		$this->line( '- This command is staging-only and requires pre-restore evidence.' );
		$this->line( '- Database and file restore scopes run separately.' );
		$this->line( '- Combined files-and-database restore still requires a separate implementation slice.' );
	}

	/**
	 * Prints next steps after package verification.
	 *
	 * @param string $package Package path.
	 * @return void
	 */
	private function print_verify_next_steps( $package ) {
		$this->line( '' );
		$this->line( 'Restore guidance:' );
		$this->line( '- Package is intact: ' . basename( $package ) );
		$this->line( '- Next: run inspect to review metadata, timing, and archive preview.' );
		$this->line( '- Then: run stage-restore only if the package matches the intended site.' );
		$this->line( '- Do not import database.sql or overwrite live files without separate approval.' );
	}

	/**
	 * Prints next steps after package inspection.
	 *
	 * @param string              $package Package path.
	 * @param array<string,mixed> $manifest Manifest.
	 * @return void
	 */
	private function print_inspect_next_steps( $package, array $manifest ) {
		$this->line( '' );
		$this->line( 'Restore guidance:' );
		$this->line( '- Confirm package ID: ' . $this->manifest_value( $manifest, 'package_id' ) );
		$this->line( '- Confirm site URL: ' . $this->manifest_value( $manifest, 'site_url' ) );
		$this->line( '- Confirm consistency status: ' . $this->manifest_value( $manifest, 'consistency_status' ) );
		$this->line( '- Next: run stage-restore to extract into a private inspection directory.' );
		$this->line( '- Stop if the package, site URL, timing, or archive preview is unexpected.' );
		$this->line( '- No live restore has been approved by this inspection output.' );
	}

	/**
	 * Prints next steps after a Drime fetch.
	 *
	 * @param string $package Package path.
	 * @return void
	 */
	private function print_fetch_next_steps( $package ) {
		$this->line( '' );
		$this->line( 'Restore guidance:' );
		$this->line( '- Downloaded package and required sidecars are present locally.' );
		$this->line( '- Fetched package path: ' . $package );
		$this->line( '- Next: run inspect against this package path.' );
		$this->line( '- Then: run stage-restore only after the package metadata matches the intended site.' );
	}

	/**
	 * Prints next steps after non-destructive restore staging.
	 *
	 * @param string              $package Package path.
	 * @param string              $restore_dir Restore directory.
	 * @param array<string,mixed> $manifest Manifest.
	 * @return void
	 */
	private function print_stage_restore_next_steps( $package, $restore_dir, array $manifest ) {
		$this->line( 'Restore staging completed.' );
		$this->line( 'Package: ' . basename( $package ) );
		$this->line( 'Package ID: ' . $this->manifest_value( $manifest, 'package_id' ) );
		$this->line( 'Staged at: ' . $restore_dir );
		$this->line( '' );
		$this->line( 'Review next:' );
		$this->line( '1. Open RESTORE_NOTES.txt.' );
		$this->line( '2. Open RESTORE_REPORT.json.' );
		$this->line( '3. Inspect htdocs/ before any file replacement.' );
		$this->line( '4. Inspect database.sql before any database import.' );
		$this->line( '5. Keep production restore steps manual until separately approved.' );
		$this->line( '' );
		$this->line( 'Safety boundary:' );
		$this->line( '- No database import was performed.' );
		$this->line( '- No live WordPress files were overwritten.' );
		$this->line( '- This command staged files for inspection only.' );
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
	 * Reads a package manifest sidecar without writing errors.
	 *
	 * @param string $package Package path.
	 * @return array<string,mixed>
	 */
	private function read_package_manifest_quiet( $package ) {
		$manifest_path = $package . '.manifest.json';
		if ( ! is_readable( $manifest_path ) ) {
			return array();
		}

		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );

		return is_array( $manifest ) ? $manifest : array();
	}

	/**
	 * Reads a remote package index without writing errors.
	 *
	 * @param string $package Package path.
	 * @return array<string,mixed>
	 */
	private function read_remote_index_quiet( $package ) {
		$index_path = $package . '.remote-index.json';
		if ( ! is_readable( $index_path ) ) {
			return array();
		}

		$index = json_decode( (string) file_get_contents( $index_path ), true );

		return is_array( $index ) ? $index : array();
	}

	/**
	 * Checks whether a decoded remote package index has the expected shape.
	 *
	 * @param array<string,mixed> $index Index.
	 * @return bool
	 */
	private function remote_index_is_valid( array $index ) {
		return 1 === (int) ( isset( $index['schema_version'] ) ? $index['schema_version'] : 0 )
			&& isset( $index['index_type'] )
			&& 'single_package_restore_index' === (string) $index['index_type']
			&& ! empty( $index['packages'] )
			&& is_array( $index['packages'] );
	}

	/**
	 * Reads checksum sidecar metadata without hashing package bytes.
	 *
	 * @param string $package Package path.
	 * @return array<string,string>
	 */
	private function read_checksum_sidecar( $package ) {
		$checksum_path = $package . '.sha256';
		if ( ! is_readable( $checksum_path ) ) {
			return array();
		}

		$checksum_line = trim( (string) file_get_contents( $checksum_path ) );
		if ( ! preg_match( '/^([a-fA-F0-9]{64})\s+/', $checksum_line, $matches ) ) {
			return array();
		}

		return array(
			'algorithm' => 'sha256',
			'value'     => strtolower( $matches[1] ),
		);
	}

	/**
	 * Derives a package ID from the archive basename.
	 *
	 * @param string $archive_name Archive basename.
	 * @return string
	 */
	private function package_id_from_archive_name( $archive_name ) {
		$suffix = '.' . $this->archive_format();
		if ( substr( $archive_name, -strlen( $suffix ) ) === $suffix ) {
			return substr( $archive_name, 0, -strlen( $suffix ) );
		}

		return $archive_name;
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

		$result = $this->download_url_to_temp_path(
			$this->drime_api_url( '/file-entries/download/' . rawurlencode( $hash ) ),
			$temp_path,
			$token
		);

		if ( '' !== $result['redirect'] ) {
			$redirect_url = $this->validate_download_redirect_url( $result['redirect'] );
			if ( '' === $redirect_url ) {
				$this->error( 'Drime download redirect target is not a safe HTTPS URL.' );
				unlink( $temp_path );
				return false;
			}

			$result = $this->download_url_to_temp_path( $redirect_url, $temp_path, '' );
		}

		if ( ! $result['ok'] || $result['status'] < 200 || $result['status'] >= 300 ) {
			$this->error( 'Drime download failed with HTTP status ' . $result['status'] . '.' );
			if ( '' !== $result['error'] ) {
				$this->error( $result['error'] );
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
	 * Downloads a URL to a temporary path without automatically following redirects.
	 *
	 * @param string $url URL.
	 * @param string $temp_path Temporary path.
	 * @param string $token Optional bearer token for first-party Drime API requests.
	 * @return array{ok:bool,status:int,error:string,redirect:string}
	 */
	private function download_url_to_temp_path( $url, $temp_path, $token ) {
		if ( file_exists( $temp_path ) && ! unlink( $temp_path ) ) {
			return array(
				'ok'       => false,
				'status'   => 0,
				'error'    => 'Could not remove stale temporary download file.',
				'redirect' => '',
			);
		}

		$handle = fopen( $temp_path, 'wb' );
		if ( false === $handle ) {
			return array(
				'ok'       => false,
				'status'   => 0,
				'error'    => 'Could not create temporary download file.',
				'redirect' => '',
			);
		}

		$headers = array();
		$curl    = curl_init( $url );
		if ( false === $curl ) {
			fclose( $handle );
			unlink( $temp_path );
			return array(
				'ok'       => false,
				'status'   => 0,
				'error'    => 'Could not initialize Drime download request.',
				'redirect' => '',
			);
		}

		$http_headers = array();
		if ( '' !== $token ) {
			$http_headers[] = 'Authorization: Bearer ' . $token;
		}

		curl_setopt( $curl, CURLOPT_HTTPHEADER, $http_headers );
		curl_setopt( $curl, CURLOPT_FILE, $handle );
		curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, false );
		curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 15 );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 0 );
		curl_setopt(
			$curl,
			CURLOPT_HEADERFUNCTION,
			function ( $curl_handle, $header ) use ( &$headers ) {
				unset( $curl_handle );

				$length = strlen( $header );
				$parts  = explode( ':', $header, 2 );
				if ( 2 === count( $parts ) ) {
					$headers[ strtolower( trim( $parts[0] ) ) ] = trim( $parts[1] );
				}

				return $length;
			}
		);

		$ok     = curl_exec( $curl );
		$status = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
		$error  = curl_error( $curl );

		$this->close_curl( $curl );
		fclose( $handle );

		return array(
			'ok'       => true === $ok,
			'status'   => $status,
			'error'    => $error,
			'redirect' => $status >= 300 && $status < 400 && isset( $headers['location'] ) ? $headers['location'] : '',
		);
	}

	/**
	 * Validates a Drime download redirect target.
	 *
	 * @param string $url Redirect URL.
	 * @return string Safe URL or empty string.
	 */
	private function validate_download_redirect_url( $url ) {
		$url    = trim( $url );
		$scheme = parse_url( $url, PHP_URL_SCHEME );
		$host   = parse_url( $url, PHP_URL_HOST );
		$user   = parse_url( $url, PHP_URL_USER );
		$pass   = parse_url( $url, PHP_URL_PASS );

		if ( 'https' !== strtolower( (string) $scheme ) || '' === (string) $host || null !== $user || null !== $pass ) {
			return '';
		}

		return false !== filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
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
	return 'Usage: php alynt-backup-runner.php <health|run|list|cleanup-preview|cleanup|verify|inspect|fetch|stage-restore|restore-dry-run|restore-apply> '
		. '--config=/path/to/config.json [--format=json] [--package=/path/to/archive.tar.gz] '
		. '[--package-id=package-id --folder-hash=hash --download-path=/path] '
		. '[--restore-path=/path/to/restores] [--staged-path=/path/to/staged/package] [--scope=files-and-database] '
		. '[--pre-restore-evidence=/path/to/evidence.json] [--write-report=1] [--older-than-days=14] [--confirm=delete-local-artifacts|restore-staging-site]';
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
