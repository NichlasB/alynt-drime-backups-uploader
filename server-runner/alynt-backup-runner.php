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
	const VERSION = '0.4.7';
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
			case 'restore-production-preflight':
				return $this->restore_production_preflight_command( $options );
			case 'restore-production-create-pre-backup':
				return $this->restore_production_create_pre_backup_command( $options );
			case 'restore-production-apply':
				return $this->restore_production_apply_command( $options );
			case 'restore-production-rollback':
				return $this->restore_production_rollback_command( $options );
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
	private function remove_restore_staging_directory( $directory, $restore_path = '' ) {
		$restore_path = '' !== $restore_path ? $restore_path : $this->restore_path();
		$normalized   = $this->normalize_path( $directory );
		$base         = $this->normalize_path( $restore_path );
		if ( '' === $normalized || '' === $base || 0 !== strpos( $normalized, $base . '/' ) || ! is_dir( $directory ) || is_link( $directory ) || ! $this->path_is_within_directory_canonical( $base, $normalized ) ) {
			return false;
		}

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
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
		} catch ( Throwable $exception ) {
			unset( $exception );
			return false;
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
		$checksum      = $this->read_checksum_sidecar( $package );
		$file_root     = $this->manifest_value( $manifest, 'file_root' );
		$database_dump = $this->manifest_value( $manifest, 'database_dump' );
		$staged_integrity = array(
			'schema_version' => 1,
			'algorithm'      => 'sha256',
			'file_tree'     => $this->staged_file_tree_integrity( $restore_dir . DIRECTORY_SEPARATOR . $file_root ),
			'database_dump' => $this->staged_file_integrity( $restore_dir . DIRECTORY_SEPARATOR . $database_dump ),
		);

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
			'site_id'                    => $this->manifest_value( $manifest, 'site_id' ),
			'site_uuid'                  => $this->manifest_value( $manifest, 'site_uuid' ),
			'site_url'                   => $this->manifest_value( $manifest, 'site_url' ),
			'created_at'                 => $this->manifest_value( $manifest, 'created_at' ),
			'producer'                   => $this->manifest_value( $manifest, 'producer' ),
			'producer_version'           => $this->manifest_value( $manifest, 'producer_version' ),
			'backup_type'                => $this->manifest_value( $manifest, 'backup_type' ),
			'archive_format'             => $this->manifest_value( $manifest, 'archive_format' ),
			'file_root'                  => $file_root,
			'database_dump'              => $database_dump,
			'staged_integrity'           => $staged_integrity,
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
	 * Returns a deterministic integrity record for an extracted directory tree.
	 *
	 * @param string $root Directory root.
	 * @return array<string,mixed>
	 */
	private function staged_file_tree_integrity( $root ) {
		$result = array(
			'valid'           => false,
			'sha256'          => '',
			'file_count'      => 0,
			'directory_count' => 0,
			'symlink_count'   => 0,
			'total_bytes'     => 0,
		);
		if ( '' === $root || ! is_dir( $root ) || ! is_readable( $root ) ) {
			return $result;
		}

		$records = array();
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $iterator as $item ) {
				$path     = $item->getPathname();
				$relative = ltrim( substr( $this->normalize_path( $path ), strlen( $this->normalize_path( $root ) ) ), '/' );
				if ( '' === $relative ) {
					return $result;
				}

				if ( $item->isLink() ) {
					$target = readlink( $path );
					if ( false === $target || false !== strpos( (string) $target, "\0" ) ) {
						return $result;
					}
					$records[] = "link\0" . $relative . "\0" . (string) $target;
					++$result['symlink_count'];
					continue;
				}

				if ( $item->isDir() ) {
					$records[] = "directory\0" . $relative;
					++$result['directory_count'];
					continue;
				}

				if ( ! $item->isFile() || ! is_readable( $path ) ) {
					return $result;
				}
				$file_hash = hash_file( 'sha256', $path );
				if ( false === $file_hash ) {
					return $result;
				}
				$size      = (int) $item->getSize();
				$records[] = "file\0" . $relative . "\0" . $size . "\0" . $file_hash;
				++$result['file_count'];
				$result['total_bytes'] += $size;
			}
		} catch ( RuntimeException $exception ) {
			return $result;
		}

		sort( $records, SORT_STRING );
		$context = hash_init( 'sha256' );
		foreach ( $records as $record ) {
			hash_update( $context, strlen( $record ) . ':' . $record );
		}
		$result['sha256'] = hash_final( $context );
		$result['valid']  = true;

		return $result;
	}

	/**
	 * Returns an integrity record for one extracted regular file.
	 *
	 * @param string $path File path.
	 * @return array<string,mixed>
	 */
	private function staged_file_integrity( $path ) {
		$result = array(
			'valid'       => false,
			'sha256'      => '',
			'total_bytes' => 0,
		);
		if ( '' === $path || ! is_file( $path ) || is_link( $path ) || ! is_readable( $path ) ) {
			return $result;
		}

		$hash = hash_file( 'sha256', $path );
		if ( false === $hash ) {
			return $result;
		}

		$result['valid']       = true;
		$result['sha256']      = $hash;
		$result['total_bytes'] = (int) filesize( $path );

		return $result;
	}

	/**
	 * Runs the read-only production restore preflight.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function restore_production_preflight_command( array $options ) {
		$staged_path  = isset( $options['staged-path'] ) ? $this->normalize_path( (string) $options['staged-path'] ) : '';
		$scope        = isset( $options['scope'] ) ? strtolower( trim( (string) $options['scope'] ) ) : 'files-and-database';
		$target_site  = isset( $options['target-site'] ) ? strtolower( trim( (string) $options['target-site'] ) ) : '';
		$write_report = isset( $options['write-report'] ) && $this->truthy_value( $options['write-report'] );

		$result = $this->restore_production_preflight_result( $staged_path, $scope, $target_site );
		if ( $write_report ) {
			$result = $this->write_restore_production_preflight_report( $result );
		}

		$result = $this->redact_restore_report_data( $result );
		if ( isset( $options['format'] ) && 'json' === strtolower( (string) $options['format'] ) ) {
			$this->line( json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		} else {
			$this->print_restore_production_preflight_result( $result );
		}

		return 0 === (int) $result['failure_count'] ? 0 : 1;
	}

	/**
	 * Creates fresh private recovery evidence for a production-simulation attempt.
	 *
	 * This command never imports a database, replaces target files, changes
	 * maintenance mode, or enables production apply. It records the recovery
	 * artifacts that a later, separately gated rollback command must consume.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function restore_production_create_pre_backup_command( array $options ) {
		$staged_path = isset( $options['staged-path'] ) ? $this->normalize_path( (string) $options['staged-path'] ) : '';
		$scope       = isset( $options['scope'] ) ? strtolower( trim( (string) $options['scope'] ) ) : 'files-and-database';
		$target_site = isset( $options['target-site'] ) ? strtolower( trim( (string) $options['target-site'] ) ) : '';
		$confirm     = isset( $options['confirm'] ) ? (string) $options['confirm'] : '';

		if ( 'create-production-pre-restore-backup' !== $confirm ) {
			$this->error( 'Production pre-restore backup creation requires --confirm=create-production-pre-restore-backup.' );
			return 1;
		}

		$result = $this->create_production_pre_restore_backup_result( $staged_path, $scope, $target_site );
		$result = $this->redact_restore_report_data( $result );
		if ( isset( $options['format'] ) && 'json' === strtolower( (string) $options['format'] ) ) {
			$this->line( json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		} else {
			$this->print_restore_production_preflight_result( $result );
		}

		return 'succeeded' === $result['status'] ? 0 : 1;
	}

	/**
	 * Builds pre-restore evidence after a complete production-simulation preflight.
	 *
	 * @param string $staged_path Staged package path.
	 * @param string $scope Restore scope.
	 * @param string $target_site Operator-supplied hostname.
	 * @return array<string,mixed>
	 */
	private function create_production_pre_restore_backup_result( $staged_path, $scope, $target_site ) {
		$preflight       = $this->restore_production_preflight_result( $staged_path, $scope, $target_site );
		$target_path     = $this->normalize_path( $this->config_string( 'production_target_wordpress_path' ) );
		$pre_backup_path = $this->production_pre_backup_path();
		$package_id      = isset( $preflight['package_id'] ) ? (string) $preflight['package_id'] : basename( $staged_path );
		$timestamp       = gmdate( 'Ymd-His' );
		$evidence_path   = '' !== $pre_backup_path
			? $pre_backup_path . DIRECTORY_SEPARATOR . 'PRODUCTION_PRE_RESTORE_EVIDENCE-' . $this->safe_slug( $package_id ) . '-' . str_replace( '-', '_', $scope ) . '-' . $timestamp . '.json'
			: '';
		$checks          = isset( $preflight['checks'] ) && is_array( $preflight['checks'] ) ? $preflight['checks'] : array();

		$result = array(
			'schema_version'                => 1,
			'generated_at'                  => gmdate( 'c' ),
			'command'                       => 'restore-production-create-pre-backup',
			'safety_classification'         => 'recovery-evidence-creation',
			'status'                        => 'failed',
			'scope'                         => $scope,
			'target_site'                   => $target_site,
			'package_id'                    => $package_id,
			'staged_path'                   => $staged_path,
			'target_wordpress_path'         => $target_path,
			'pre_restore_backup_path'       => $pre_backup_path,
			'pre_restore_evidence_path'     => $evidence_path,
			'database_export_path'          => '',
			'database_export_sha256'        => '',
			'file_backup_path'              => '',
			'file_backup_sha256'            => '',
			'preflight_failure_count'       => (int) $preflight['failure_count'],
			'preflight_checks'              => $checks,
			'checks'                        => array(),
			'failure_count'                 => 0,
			'pre_restore_backup_created'    => false,
			'database_export_created'       => false,
			'file_backup_created'           => false,
			'evidence_written'              => false,
			'destructive_actions_performed' => false,
			'database_imported'             => false,
			'live_files_overwritten'        => false,
			'maintenance_state_changed'     => false,
		);

		$this->add_restore_dry_run_check( $result['checks'], 'production_preflight_passed', 0 === (int) $preflight['failure_count'], 'The complete read-only production preflight passed before recovery evidence creation.' );
		$this->add_restore_dry_run_check( $result['checks'], 'production_pre_backup_path_ready', '' !== $pre_backup_path && ! $this->dangerous_restore_target_path( $pre_backup_path ) && $this->path_is_outside_directory_canonical( $target_path, $pre_backup_path ) && $this->ensure_directory( $pre_backup_path ) && is_writable( $pre_backup_path ), 'Production pre-restore backup path is private, outside WordPress, and writable.' );
		$this->add_restore_dry_run_check( $result['checks'], 'production_pre_backup_evidence_path_safe', '' !== $evidence_path && $this->path_is_within_directory( $pre_backup_path, $evidence_path ) && ! file_exists( $evidence_path ), 'Production pre-restore evidence path is safe and does not already exist.' );
		$this->add_restore_dry_run_check( $result['checks'], 'production_pre_backup_free_space', '' !== $pre_backup_path && is_dir( $pre_backup_path ) && $this->has_minimum_free_space( $pre_backup_path ), 'Production pre-restore backup path has the configured minimum free space.' );

		$result['failure_count'] = $this->restore_check_failure_count( $result['checks'] );
		if ( 0 !== (int) $result['failure_count'] ) {
			return $result;
		}

		if ( $this->restore_scope_includes_database( $scope ) ) {
			$result['database_export_path']    = $pre_backup_path . DIRECTORY_SEPARATOR . 'production-database-before-' . $this->safe_slug( $package_id ) . '-' . $timestamp . '.sql';
			$result['database_export_created'] = $this->export_database_from_path( $result['database_export_path'], $target_path );
			if ( $result['database_export_created'] ) {
				$result['database_export_created'] = chmod( $result['database_export_path'], 0640 );
			}
			$this->add_restore_dry_run_check( $result['checks'], 'production_database_export_created', (bool) $result['database_export_created'], 'Production-simulation database export was created.' );
			if ( $result['database_export_created'] ) {
				$result['database_export_sha256'] = hash_file( 'sha256', $result['database_export_path'] );
			}
		}

		if ( $this->restore_scope_includes_files( $scope ) ) {
			$result['file_backup_path']    = $pre_backup_path . DIRECTORY_SEPARATOR . 'production-files-before-' . $this->safe_slug( $package_id ) . '-' . $timestamp . '.tar.gz';
			$result['file_backup_created'] = $this->create_pre_restore_file_backup( $result['file_backup_path'], $target_path );
			if ( $result['file_backup_created'] ) {
				$result['file_backup_created'] = chmod( $result['file_backup_path'], 0640 );
			}
			$this->add_restore_dry_run_check( $result['checks'], 'production_file_backup_created', (bool) $result['file_backup_created'], 'Production-simulation file archive was created.' );
			if ( $result['file_backup_created'] ) {
				$result['file_backup_sha256'] = hash_file( 'sha256', $result['file_backup_path'] );
			}
		}

		$result['failure_count'] = $this->restore_check_failure_count( $result['checks'] );
		if ( 0 !== (int) $result['failure_count'] ) {
			return $result;
		}

		$evidence = array(
			'schema_version'              => 1,
			'evidence_type'               => 'production_pre_restore_backup',
			'generated_at'                => $result['generated_at'],
			'package_id'                  => $package_id,
			'scope'                       => $scope,
			'target_site'                 => $target_site,
			'target_site_url'             => $this->normalize_site_url( $this->config_string( 'production_target_site_url' ) ),
			'target_site_uuid'            => strtolower( $this->config_string( 'production_target_site_uuid' ) ),
			'target_wordpress_path'       => $target_path,
			'preflight_generated_at'      => isset( $preflight['generated_at'] ) ? $preflight['generated_at'] : '',
			'preflight_target_fingerprint' => isset( $preflight['target_fingerprint'] ) ? $preflight['target_fingerprint'] : array(),
			'preflight_package_identity'  => isset( $preflight['package_identity'] ) ? $preflight['package_identity'] : array(),
		);
		if ( '' !== $result['database_export_path'] ) {
			$evidence['database_export_path']   = $result['database_export_path'];
			$evidence['database_export_sha256'] = $result['database_export_sha256'];
		}
		if ( '' !== $result['file_backup_path'] ) {
			$evidence['file_backup_path']   = $result['file_backup_path'];
			$evidence['file_backup_sha256'] = $result['file_backup_sha256'];
		}

		$result['evidence_written'] = $this->write_json_atomic( $evidence_path, $evidence );
		if ( $result['evidence_written'] ) {
			$result['evidence_written'] = chmod( $evidence_path, 0640 );
		}
		$this->add_restore_dry_run_check( $result['checks'], 'production_pre_restore_evidence_written', (bool) $result['evidence_written'], 'Production pre-restore evidence JSON was written.' );
		$result['failure_count']              = $this->restore_check_failure_count( $result['checks'] );
		$result['pre_restore_backup_created'] = 0 === (int) $result['failure_count'];
		$result['status']                     = $result['pre_restore_backup_created'] ? 'succeeded' : 'failed';

		return $result;
	}

	/**
	 * Runs an explicitly gated production-simulation apply.
	 *
	 * Supports files-only, database-only, and combined files-and-database
	 * scopes under one maintenance-protected transaction.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function restore_production_apply_command( array $options ) {
		$staged_path  = isset( $options['staged-path'] ) ? $this->normalize_path( (string) $options['staged-path'] ) : '';
		$scope        = isset( $options['scope'] ) ? strtolower( trim( (string) $options['scope'] ) ) : '';
		$target_site  = isset( $options['target-site'] ) ? strtolower( trim( (string) $options['target-site'] ) ) : '';
		$evidence     = isset( $options['pre-restore-evidence'] ) ? $this->normalize_path( (string) $options['pre-restore-evidence'] ) : '';
		$confirm      = isset( $options['confirm'] ) ? (string) $options['confirm'] : '';
		$confirm_site = isset( $options['confirm-site'] ) ? strtolower( trim( (string) $options['confirm-site'] ) ) : '';

		if ( 'restore-production-site' !== $confirm ) {
			$this->error( 'Production apply requires --confirm=restore-production-site.' );
			return 1;
		}

		$result = $this->restore_production_apply_result( $staged_path, $scope, $target_site, $confirm_site, $evidence );
		$result = $this->redact_restore_report_data( $result );
		if ( isset( $options['format'] ) && 'json' === strtolower( (string) $options['format'] ) ) {
			$this->line( json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		} else {
			$this->print_restore_production_preflight_result( $result );
		}

		return 'succeeded' === $result['status'] ? 0 : 1;
	}

	/**
	 * Validates and applies one production-simulation restore scope.
	 *
	 * @param string $staged_path Staged package path.
	 * @param string $scope Restore scope.
	 * @param string $target_site Requested target hostname.
	 * @param string $confirm_site Exact confirmation hostname.
	 * @param string $evidence_path Pre-restore evidence path.
	 * @return array<string,mixed>
	 */
	private function restore_production_apply_result( $staged_path, $scope, $target_site, $confirm_site, $evidence_path ) {
		$preflight       = $this->restore_production_preflight_result( $staged_path, $scope, $target_site, 'enabled' );
		$target_path     = $this->normalize_path( $this->config_string( 'production_target_wordpress_path' ) );
		$configured_url  = $this->normalize_site_url( $this->config_string( 'production_target_site_url' ) );
		$configured_host = $this->site_host( $configured_url );
		$configured_uuid = strtolower( $this->config_string( 'production_target_site_uuid' ) );
		$package_id      = isset( $preflight['package_id'] ) ? (string) $preflight['package_id'] : basename( $staged_path );
		$evidence        = $this->read_restore_report( $evidence_path );
		$report_path     = $this->production_apply_report_path( $package_id, $scope );

		$result = array(
			'schema_version'                    => 1,
			'generated_at'                      => gmdate( 'c' ),
			'command'                           => 'restore-production-apply',
			'safety_classification'             => 'explicit-production-simulation-apply',
			'status'                            => 'failed',
			'restore_environment'               => strtolower( $this->config_string( 'production_restore_environment' ) ),
			'scope'                             => $scope,
			'target_site'                       => $target_site,
			'target_site_uuid'                  => $configured_uuid,
			'package_id'                        => $package_id,
			'staged_path'                       => $staged_path,
			'target_wordpress_path'             => $target_path,
			'pre_restore_evidence_path'         => $evidence_path,
			'preflight_failure_count'           => (int) $preflight['failure_count'],
			'preflight_checks'                  => isset( $preflight['checks'] ) ? $preflight['checks'] : array(),
			'checks'                            => array(),
			'failure_count'                     => 0,
			'confirmation_phrase_accepted'      => true,
			'confirmation_site_accepted'        => false,
			'maintenance_activation_attempted'  => false,
			'maintenance_activation_succeeded'  => false,
			'maintenance_reactivation_attempted' => false,
			'maintenance_reactivation_succeeded' => false,
			'maintenance_emergency_fallback_used' => false,
			'maintenance_deactivation_attempted' => false,
			'maintenance_deactivation_succeeded' => false,
			'maintenance_state_changed'         => false,
			'enrolled_symlink_restore_attempted' => false,
			'enrolled_symlink_restore_succeeded' => false,
			'enrolled_symlink_restore_count'     => 0,
			'file_restore_attempted'            => false,
			'file_restore_succeeded'            => false,
			'database_import_attempted'         => false,
			'database_import_succeeded'         => false,
			'database_imported'                 => false,
			'database_may_be_modified'           => false,
			'live_files_overwritten'            => false,
			'combined_restore_order'             => 'files-and-database' === $scope ? array( 'files', 'database' ) : array(),
			'destructive_actions_performed'     => false,
			'post_apply_verification_passed'    => false,
			'production_rollback_available'     => false,
			'production_apply_report_written'   => false,
			'production_apply_report_path'      => $report_path,
			'production_apply_report_error'     => '',
			'failure_step'                      => '',
			'manual_recovery_notes'             => array(),
		);

		$result['confirmation_site_accepted'] = '' !== $confirm_site && $confirm_site === $configured_host && $confirm_site === $target_site;
		$this->add_restore_dry_run_check( $result['checks'], 'phase_five_scope_supported', in_array( $scope, array( 'files', 'database', 'files-and-database' ), true ), 'Production apply supports files-only, database-only, or combined files-and-database scope.' );
		$this->add_restore_dry_run_check( $result['checks'], 'production_restore_enabled', $this->config_bool( 'production_restore_enabled' ), 'Production restore is explicitly enabled in private runner configuration.' );
		$this->add_restore_dry_run_check( $result['checks'], 'production_rollback_enabled', $this->config_bool( 'production_rollback_enabled' ), 'Production rollback is explicitly enabled before production apply.' );
		$this->add_restore_dry_run_check( $result['checks'], 'production_environment', 'production-simulation' === $result['restore_environment'], 'Production apply environment is production-simulation.' );
		$this->add_restore_dry_run_check( $result['checks'], 'target_site_matches_config', '' !== $target_site && $target_site === $configured_host, 'The operator target hostname matches the enrolled target.' );
		$this->add_restore_dry_run_check( $result['checks'], 'confirmation_site_matches_target', $result['confirmation_site_accepted'], 'The exact --confirm-site hostname matches the enrolled and requested target.' );
		$this->add_restore_dry_run_check( $result['checks'], 'production_preflight_passed', 0 === (int) $preflight['failure_count'], 'The complete production preflight passed immediately before apply.' );
		$this->add_production_pre_restore_evidence_checks( $result['checks'], $evidence, $evidence_path, $package_id, $scope, $target_path, $configured_host, $configured_uuid );
		$this->add_restore_dry_run_check( $result['checks'], 'target_fingerprint_unchanged_since_pre_backup', isset( $evidence['preflight_target_fingerprint'] ) && $evidence['preflight_target_fingerprint'] === $preflight['target_fingerprint'], 'The enrolled target fingerprint has not changed since pre-restore evidence creation.' );
		$this->add_restore_dry_run_check( $result['checks'], 'staged_package_identity_unchanged_since_pre_backup', isset( $evidence['preflight_package_identity'] ) && $evidence['preflight_package_identity'] === $preflight['package_identity'], 'The staged package identity and integrity record have not changed since pre-restore evidence creation.' );
		$this->add_restore_dry_run_check( $result['checks'], 'production_apply_report_path_ready', '' !== $report_path, 'Private production apply report path is ready before target changes.' );

		$result['failure_count'] = $this->restore_check_failure_count( $result['checks'] );
		if ( 0 !== (int) $result['failure_count'] ) {
			$result['failure_step']          = 'production-apply-preflight';
			$result['manual_recovery_notes'] = array( 'No target-changing action was attempted because production apply checks failed.' );
			return $this->write_production_apply_report( $result );
		}

		$result['production_apply_report_written'] = $this->write_json_atomic( $report_path, $result ) && chmod( $report_path, 0640 );
		if ( ! $result['production_apply_report_written'] ) {
			$result['failure_step']                  = 'production-apply-report-prepare';
			$result['production_apply_report_error'] = 'Could not prepare the private production apply report before maintenance.';
			return $result;
		}

		$result['maintenance_activation_attempted'] = true;
		$maintenance = $this->run_wp_cli_action( $target_path, 'maintenance-mode activate' );
		$result['maintenance_activation_succeeded'] = 0 === (int) $maintenance['exit_code'];
		$result['maintenance_state_changed']        = $result['maintenance_activation_succeeded'];
		if ( ! $result['maintenance_activation_succeeded'] ) {
			$result['failure_step']          = 'maintenance-activation';
			$result['manual_recovery_notes'] = array( 'Production apply stopped before target writes because maintenance mode could not be activated.' );
			return $this->write_production_apply_report( $result );
		}

		$current_package = $this->read_restore_report( $staged_path . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json' );
		$current_identity = $this->production_package_identity( $current_package, $package_id );
		$this->add_restore_dry_run_check( $result['checks'], 'immediate_staged_package_identity_matches_evidence', isset( $evidence['preflight_package_identity'] ) && $evidence['preflight_package_identity'] === $current_identity, 'Immediately before target writes, the staged package identity matches private pre-restore evidence.' );
		$this->add_production_staged_integrity_checks( $result['checks'], $current_package, $staged_path, $scope, 'immediate_' );
		$result['failure_count'] = $this->restore_check_failure_count( $result['checks'] );
		if ( 0 !== (int) $result['failure_count'] ) {
			$result['failure_step']          = 'staged-input-integrity';
			$result['manual_recovery_notes'] = array( 'Production apply stopped before target writes because staged input integrity changed. Keep maintenance active while the staged package and private evidence are inspected.' );
			return $this->write_production_apply_report( $result );
		}

		if ( in_array( $scope, array( 'files', 'files-and-database' ), true ) ) {
			$file_root = $this->restore_apply_file_root_path( $staged_path );
			$symlinks  = $this->production_expected_symlink_snapshot( $target_path );
			$this->add_restore_dry_run_check( $result['checks'], 'enrolled_symlink_snapshot_complete', count( $symlinks ) === count( $this->config_array( 'production_expected_symlink_paths' ) ), 'Every enrolled target symlink was captured before file replacement.' );
			$result['failure_count'] = $this->restore_check_failure_count( $result['checks'] );
			if ( 0 !== (int) $result['failure_count'] ) {
				$result['failure_step']          = 'enrolled-symlink-snapshot';
				$result['manual_recovery_notes'] = array( 'No file replacement was attempted because the enrolled symlink snapshot was incomplete.' );
				return $this->write_production_apply_report( $result );
			}
			$result['file_restore_attempted']        = true;
			$result['destructive_actions_performed'] = true;
			$result['live_files_overwritten']        = true;
			$result['production_rollback_available'] = true;
			$result['file_restore_succeeded']        = $this->replace_target_files_from_staging( $file_root, $target_path );
			if ( ! $result['file_restore_succeeded'] ) {
				$result['maintenance_reactivation_attempted'] = true;
				$maintenance = $this->run_wp_cli_action( $target_path, 'maintenance-mode activate' );
				$result['maintenance_reactivation_succeeded'] = 0 === (int) $maintenance['exit_code'];
				if ( ! $result['maintenance_reactivation_succeeded'] ) {
					$result['maintenance_emergency_fallback_used'] = $this->ensure_production_maintenance_marker( $target_path );
				}
				$result['failure_step']          = 'file-restore';
				$result['manual_recovery_notes'] = array( $result['maintenance_reactivation_succeeded'] || $result['maintenance_emergency_fallback_used'] ? 'Maintenance protection was re-established. Use restore-production-rollback with this apply report.' : 'Maintenance protection could not be re-established. Restrict access immediately and use restore-production-rollback with this apply report.' );
				return $this->write_production_apply_report( $result );
			}
			$result['maintenance_reactivation_attempted'] = true;
			$maintenance = $this->run_wp_cli_action( $target_path, 'maintenance-mode activate' );
			$result['maintenance_reactivation_succeeded'] = 0 === (int) $maintenance['exit_code'];
			if ( ! $result['maintenance_reactivation_succeeded'] ) {
				$result['maintenance_emergency_fallback_used'] = $this->ensure_production_maintenance_marker( $target_path );
				$result['failure_step']          = 'maintenance-reactivation';
				$result['manual_recovery_notes'] = array( 'Files were replaced, but maintenance mode could not be reactivated. Restrict access and use restore-production-rollback with this apply report.' );
				return $this->write_production_apply_report( $result );
			}
			$result['enrolled_symlink_restore_attempted'] = true;
			$result['enrolled_symlink_restore_count']     = count( $symlinks );
			$result['enrolled_symlink_restore_succeeded'] = $this->restore_production_expected_symlinks( $target_path, $symlinks );
			if ( ! $result['enrolled_symlink_restore_succeeded'] ) {
				$result['failure_step']          = 'enrolled-symlink-restore';
				$result['manual_recovery_notes'] = array( 'Files were replaced, but enrolled symlinks could not be restored. Keep maintenance active and use restore-production-rollback with this apply report.' );
				return $this->write_production_apply_report( $result );
			}
		}

		if ( in_array( $scope, array( 'database', 'files-and-database' ), true ) ) {
			$database_dump = $this->restore_apply_database_dump_path( $staged_path );
			$result['database_import_attempted']     = true;
			$result['destructive_actions_performed'] = true;
			$result['database_may_be_modified']      = true;
			$result['production_rollback_available'] = true;
			$import                                  = $this->import_database( $database_dump, $target_path );
			$result['database_import_succeeded']     = 0 === (int) $import['exit_code'];
			$result['database_imported']             = $result['database_import_succeeded'];
			if ( ! $result['database_import_succeeded'] ) {
				$result['failure_step']          = 'database-import';
				$result['manual_recovery_notes'] = array( 'Keep maintenance active and use restore-production-rollback with this apply report.' );
				return $this->write_production_apply_report( $result );
			}
		}

		$post_runtime            = $this->production_runtime_fingerprint( $target_path );
		$post_filesystem_markers = $this->production_filesystem_markers( $target_path );
		$post_ownership          = $this->production_target_owner_group( $target_path );
		$expected_fingerprint    = isset( $evidence['preflight_target_fingerprint'] ) && is_array( $evidence['preflight_target_fingerprint'] ) ? $evidence['preflight_target_fingerprint'] : array();
		$this->add_restore_dry_run_check( $result['checks'], 'post_apply_runtime_reads_passed', 0 === (int) $post_runtime['failure_count'], 'Required post-apply WP-CLI identity queries succeeded.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_apply_maintenance_active', ! empty( $post_runtime['maintenance_active'] ), 'WordPress maintenance mode remains active during post-apply verification.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_apply_home_matches_target', $configured_url === $post_runtime['home'], 'Post-apply home matches the enrolled target URL.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_apply_siteurl_matches_target', $configured_url === $post_runtime['siteurl'], 'Post-apply siteurl matches the enrolled target URL.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_apply_uuid_matches_target', $configured_uuid === $post_runtime['site_uuid'], 'Post-apply plugin site UUID matches the enrolled target.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_apply_active_plugins_match', $this->config_list_matches_actual( 'production_expected_active_plugins', $post_runtime['active_plugins'] ), 'Post-apply active plugin inventory exactly matches enrollment.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_apply_active_theme_matches', in_array( $this->config_string( 'production_expected_active_theme' ), $post_runtime['active_themes'], true ), 'Post-apply active theme matches enrollment.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_apply_drop_ins_match', $this->config_list_matches_actual( 'production_expected_drop_ins', $post_filesystem_markers['drop_ins'] ), 'Post-apply WordPress drop-in inventory exactly matches enrollment.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_apply_owner_matches', isset( $expected_fingerprint['filesystem_owner_id'] ) && null !== $expected_fingerprint['filesystem_owner_id'] && (int) $expected_fingerprint['filesystem_owner_id'] === $post_ownership['owner_id'], 'Post-apply WordPress root owner matches private pre-restore evidence.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_apply_group_matches', isset( $expected_fingerprint['filesystem_group_id'] ) && null !== $expected_fingerprint['filesystem_group_id'] && (int) $expected_fingerprint['filesystem_group_id'] === $post_ownership['group_id'], 'Post-apply WordPress root group matches private pre-restore evidence.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_apply_filesystem_inventory_complete', ! empty( $post_filesystem_markers['scan_complete'] ) && empty( $post_filesystem_markers['symlink_samples_truncated'] ), 'Post-apply filesystem identity scan completed without truncation.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_apply_symlinks_match', $this->config_list_matches_actual( 'production_expected_symlink_paths', $post_filesystem_markers['symlink_samples'] ), 'Post-apply symlink inventory exactly matches enrollment.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_apply_symlink_targets_match', $this->config_string_map_matches_actual( 'production_expected_symlink_targets', $post_filesystem_markers['symlink_targets'] ), 'Post-apply symlink targets exactly match enrollment.' );
		$result['failure_count']                  = $this->restore_check_failure_count( $result['checks'] );
		$result['post_apply_verification_passed'] = 0 === (int) $result['failure_count'];
		if ( ! $result['post_apply_verification_passed'] ) {
			$result['failure_step']          = 'post-apply-verification';
			$result['manual_recovery_notes'] = array( 'Post-apply verification failed. Keep maintenance active and use restore-production-rollback with this apply report.' );
			return $this->write_production_apply_report( $result );
		}

		$result['maintenance_deactivation_attempted'] = true;
		$maintenance = $this->run_wp_cli_action( $target_path, 'maintenance-mode deactivate' );
		$result['maintenance_deactivation_succeeded'] = 0 === (int) $maintenance['exit_code'];
		if ( ! $result['maintenance_deactivation_succeeded'] ) {
			$result['failure_step']          = 'maintenance-deactivation';
			$result['manual_recovery_notes'] = array( 'Restore verification passed, but maintenance mode could not be deactivated. Inspect the target before manual deactivation.' );
			return $this->write_production_apply_report( $result );
		}

		$result['status']                        = 'succeeded';
		$result['production_rollback_available'] = true;

		return $this->write_production_apply_report( $result );
	}

	/**
	 * Returns a private production apply report destination.
	 *
	 * @param string $package_id Package identifier.
	 * @param string $scope Restore scope.
	 * @return string
	 */
	private function production_apply_report_path( $package_id, $scope ) {
		$reports_path = $this->normalize_path( $this->config_string( 'production_reports_path' ) );
		$target_path  = $this->normalize_path( $this->config_string( 'production_target_wordpress_path' ) );
		if ( '' === $reports_path || $this->dangerous_restore_target_path( $reports_path ) || ! $this->path_is_outside_directory_canonical( $target_path, $reports_path ) || ! $this->ensure_directory( $reports_path ) || ! is_writable( $reports_path ) ) {
			return '';
		}

		$suffix = gmdate( 'Ymd-His' ) . '-' . substr( hash( 'sha256', uniqid( '', true ) ), 0, 8 );

		return $reports_path . DIRECTORY_SEPARATOR . 'RESTORE_PRODUCTION_APPLY_REPORT-' . $this->safe_slug( $package_id ) . '-' . $this->safe_slug( $scope ) . '-' . $suffix . '.json';
	}

	/**
	 * Writes or updates the private production apply report.
	 *
	 * @param array<string,mixed> $result Apply result.
	 * @return array<string,mixed>
	 */
	private function write_production_apply_report( array $result ) {
		if ( empty( $result['production_apply_report_path'] ) ) {
			$result['production_apply_report_error'] = 'Production apply reports path is missing, unsafe, or not writable.';
			return $result;
		}

		$result['production_apply_report_written'] = true;
		$written = $this->write_json_atomic( $result['production_apply_report_path'], $result ) && chmod( $result['production_apply_report_path'], 0640 );
		if ( ! $written ) {
			$result['production_apply_report_written'] = false;
			$result['production_apply_report_error'] = 'Could not write the production apply report.';
			$result['status']                        = 'failed';
			if ( '' === $result['failure_step'] ) {
				$result['failure_step'] = 'production-apply-report-write';
			}
		}

		return $result;
	}

	/**
	 * Restores one verified production-simulation recovery snapshot.
	 *
	 * The command is intentionally separate from future production apply. It can
	 * only be enabled for an enrolled production-simulation target and requires a
	 * particular apply report and both confirmation values.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function restore_production_rollback_command( array $options ) {
		$apply_report_path = isset( $options['apply-report'] ) ? $this->normalize_path( (string) $options['apply-report'] ) : '';
		$target_site       = isset( $options['target-site'] ) ? strtolower( trim( (string) $options['target-site'] ) ) : '';
		$confirm           = isset( $options['confirm'] ) ? (string) $options['confirm'] : '';
		$confirm_site      = isset( $options['confirm-site'] ) ? strtolower( trim( (string) $options['confirm-site'] ) ) : '';

		if ( 'rollback-production-site' !== $confirm ) {
			$this->error( 'Production rollback requires --confirm=rollback-production-site.' );
			return 1;
		}

		$result = $this->restore_production_rollback_result( $apply_report_path, $target_site, $confirm_site );
		$result = $this->redact_restore_report_data( $result );
		if ( isset( $options['format'] ) && 'json' === strtolower( (string) $options['format'] ) ) {
			$this->line( json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		} else {
			$this->print_restore_production_preflight_result( $result );
		}

		return 'succeeded' === $result['status'] ? 0 : 1;
	}

	/**
	 * Validates and performs an explicit production-simulation rollback.
	 *
	 * @param string $apply_report_path Apply report path.
	 * @param string $target_site Operator-supplied hostname.
	 * @param string $confirm_site Exact confirmation hostname.
	 * @return array<string,mixed>
	 */
	private function restore_production_rollback_result( $apply_report_path, $target_site, $confirm_site ) {
		$reports_path      = $this->normalize_path( $this->config_string( 'production_reports_path' ) );
		$apply_report      = $this->read_restore_report( $apply_report_path );
		$staged_path       = isset( $apply_report['staged_path'] ) ? $this->normalize_path( (string) $apply_report['staged_path'] ) : '';
		$scope             = isset( $apply_report['scope'] ) ? strtolower( (string) $apply_report['scope'] ) : '';
		$preflight         = $this->restore_production_preflight_result( $staged_path, $scope, $target_site, 'either' );
		$blocking_preflight_failures = $this->production_rollback_blocking_preflight_failures( isset( $preflight['checks'] ) ? $preflight['checks'] : array() );
		$target_path       = $this->normalize_path( $this->config_string( 'production_target_wordpress_path' ) );
		$configured_host   = $this->site_host( $this->normalize_site_url( $this->config_string( 'production_target_site_url' ) ) );
		$configured_uuid   = strtolower( $this->config_string( 'production_target_site_uuid' ) );
		$package_id        = isset( $apply_report['package_id'] ) ? (string) $apply_report['package_id'] : '';
		$evidence_path     = isset( $apply_report['pre_restore_evidence_path'] ) ? $this->normalize_path( (string) $apply_report['pre_restore_evidence_path'] ) : '';
		$evidence          = $this->read_restore_report( $evidence_path );
		$checks            = array();
		$report_path       = $this->production_rollback_report_path( $package_id );

		$result = array(
			'schema_version'                    => 1,
			'generated_at'                      => gmdate( 'c' ),
			'command'                           => 'restore-production-rollback',
			'safety_classification'             => 'explicit-production-simulation-rollback',
			'status'                            => 'failed',
			'scope'                             => $scope,
			'target_site'                       => $target_site,
			'apply_report_path'                 => $apply_report_path,
			'package_id'                        => $package_id,
			'pre_restore_evidence_path'         => $evidence_path,
			'preflight_failure_count'           => (int) $preflight['failure_count'],
			'preflight_blocking_failure_count'  => count( $blocking_preflight_failures ),
			'preflight_blocking_failures'       => $blocking_preflight_failures,
			'preflight_checks'                  => isset( $preflight['checks'] ) ? $preflight['checks'] : array(),
			'checks'                            => $checks,
			'failure_count'                     => 0,
			'confirmation_phrase_accepted'      => true,
			'confirmation_site_accepted'        => false,
			'file_rollback_attempted'           => false,
			'file_rollback_succeeded'           => false,
			'database_rollback_attempted'       => false,
			'database_rollback_succeeded'       => false,
			'database_imported'                 => false,
			'database_may_be_modified'           => false,
			'live_files_overwritten'            => false,
			'destructive_actions_performed'     => false,
			'maintenance_activation_attempted'  => false,
			'maintenance_activation_succeeded'  => false,
			'maintenance_reactivation_attempted' => false,
			'maintenance_reactivation_succeeded' => false,
			'maintenance_emergency_fallback_used' => false,
			'maintenance_deactivation_attempted' => false,
			'maintenance_deactivation_succeeded' => false,
			'maintenance_state_changed'         => false,
			'post_rollback_verification_passed' => false,
			'rollback_extraction_path'           => '',
			'rollback_extraction_retained'       => false,
			'rollback_extraction_cleanup_attempted' => false,
			'rollback_extraction_cleanup_succeeded' => false,
			'rollback_report_written'           => false,
			'rollback_report_path'              => $report_path,
			'rollback_report_error'             => '',
			'failure_step'                      => '',
			'manual_recovery_notes'             => array(),
		);

		$this->add_restore_dry_run_check( $result['checks'], 'production_rollback_enabled', $this->config_bool( 'production_rollback_enabled' ), 'Production rollback is explicitly enabled in private runner configuration.' );
		$this->add_restore_dry_run_check( $result['checks'], 'production_environment', 'production-simulation' === strtolower( $this->config_string( 'production_restore_environment' ) ), 'Production rollback environment is production-simulation.' );
		$this->add_restore_dry_run_check( $result['checks'], 'target_site_matches_config', '' !== $target_site && $target_site === $configured_host, 'The operator target hostname matches the enrolled target.' );
		$result['confirmation_site_accepted'] = '' !== $confirm_site && $confirm_site === $configured_host && $confirm_site === $target_site;
		$this->add_restore_dry_run_check( $result['checks'], 'confirmation_site_matches_target', $result['confirmation_site_accepted'], 'The exact --confirm-site hostname matches the enrolled and requested target.' );
		$this->add_restore_dry_run_check( $result['checks'], 'apply_report_path_safe', '' !== $apply_report_path && $this->path_is_within_directory_canonical( $reports_path, $apply_report_path ) && $reports_path !== $apply_report_path && is_file( $apply_report_path ) && is_readable( $apply_report_path ), 'Apply report is a readable file inside the private production reports path.' );
		$this->add_restore_dry_run_check( $result['checks'], 'apply_report_valid', ! empty( $apply_report ), 'Apply report is valid JSON.' );
		$this->add_restore_dry_run_check( $result['checks'], 'apply_report_command', isset( $apply_report['command'] ) && 'restore-production-apply' === (string) $apply_report['command'], 'Apply report was created by the production apply command.' );
		$this->add_restore_dry_run_check( $result['checks'], 'apply_report_environment', isset( $apply_report['restore_environment'] ) && 'production-simulation' === strtolower( (string) $apply_report['restore_environment'] ), 'Apply report records the production-simulation environment.' );
		$this->add_restore_dry_run_check( $result['checks'], 'apply_report_target_site', isset( $apply_report['target_site'] ) && $configured_host === strtolower( (string) $apply_report['target_site'] ), 'Apply report target hostname matches the enrolled target.' );
		$this->add_restore_dry_run_check( $result['checks'], 'apply_report_target_uuid', isset( $apply_report['target_site_uuid'] ) && $configured_uuid === strtolower( (string) $apply_report['target_site_uuid'] ), 'Apply report target UUID matches the enrolled target.' );
		$this->add_restore_dry_run_check( $result['checks'], 'apply_report_scope', in_array( $scope, array( 'files', 'database', 'files-and-database' ), true ), 'Apply report records a supported rollback scope.' );
		$this->add_restore_dry_run_check( $result['checks'], 'apply_report_destructive_action', ! empty( $apply_report['destructive_actions_performed'] ), 'Apply report records that a target-changing apply action occurred.' );
		$this->add_restore_dry_run_check( $result['checks'], 'apply_report_evidence_path', '' !== $evidence_path && $this->path_is_within_directory_canonical( $this->production_pre_backup_path(), $evidence_path ), 'Apply report references evidence inside the private production pre-backup path.' );
		$this->add_restore_dry_run_check( $result['checks'], 'production_rollback_preflight_passed', empty( $blocking_preflight_failures ), 'Rollback safety checks passed; target runtime and inventory drift caused by a failed apply may be recovered.' );
		$this->add_production_pre_restore_evidence_checks( $result['checks'], $evidence, $evidence_path, $package_id, $scope, $target_path, $configured_host, $configured_uuid );
		$this->add_restore_dry_run_check( $result['checks'], 'rollback_report_path_ready', '' !== $report_path, 'Private production rollback report path is ready before target changes.' );

		$result['failure_count'] = $this->restore_check_failure_count( $result['checks'] );
		if ( 0 !== (int) $result['failure_count'] ) {
			$result['failure_step']          = 'rollback-preflight';
			$result['manual_recovery_notes'] = array( 'No rollback action was attempted because the production-simulation rollback checks failed.' );
			return $this->write_production_rollback_report( $result );
		}

		$result['maintenance_activation_attempted'] = true;
		$maintenance = $this->run_wp_cli_action( $target_path, 'maintenance-mode activate' );
		$result['maintenance_activation_succeeded'] = 0 === (int) $maintenance['exit_code'];
		if ( ! $result['maintenance_activation_succeeded'] ) {
			$result['maintenance_emergency_fallback_used'] = $this->ensure_production_maintenance_marker( $target_path );
			$result['maintenance_activation_succeeded']    = $result['maintenance_emergency_fallback_used'];
		}
		$result['maintenance_state_changed']        = $result['maintenance_activation_succeeded'];
		if ( ! $result['maintenance_activation_succeeded'] ) {
			$result['failure_step']          = 'rollback-maintenance-activation';
			$result['manual_recovery_notes'] = array( 'Rollback stopped before target writes because maintenance mode could not be activated.' );
			return $this->write_production_rollback_report( $result );
		}

		if ( $this->restore_scope_includes_files( $scope ) ) {
			$rollback_extraction_path                 = '';
			$result['file_rollback_attempted']       = true;
			$result['destructive_actions_performed'] = true;
			$result['file_rollback_succeeded']       = $this->restore_production_files_from_pre_backup( (string) $evidence['file_backup_path'], $target_path, $package_id, $rollback_extraction_path );
			$result['rollback_extraction_path']      = $rollback_extraction_path;
			$result['rollback_extraction_retained']  = '' !== $rollback_extraction_path && is_dir( $rollback_extraction_path );
			$result['live_files_overwritten']        = $result['file_rollback_succeeded'];
			if ( ! $result['file_rollback_succeeded'] ) {
				$result['failure_step']          = 'file-rollback';
				$result['manual_recovery_notes'] = array( 'File rollback failed. Keep the target controlled and inspect the verified pre-restore evidence.' );
				return $this->write_production_rollback_report( $result );
			}
			$result['maintenance_reactivation_attempted'] = true;
			$maintenance = $this->run_wp_cli_action( $target_path, 'maintenance-mode activate' );
			$result['maintenance_reactivation_succeeded'] = 0 === (int) $maintenance['exit_code'];
			if ( ! $result['maintenance_reactivation_succeeded'] ) {
				$result['maintenance_emergency_fallback_used'] = $this->ensure_production_maintenance_marker( $target_path );
				$result['failure_step']          = 'rollback-maintenance-reactivation';
				$result['manual_recovery_notes'] = array( 'Files were rolled back, but maintenance mode could not be reactivated. Restrict access and inspect the verified recovery evidence.' );
				return $this->write_production_rollback_report( $result );
			}
		}

		if ( $this->restore_scope_includes_database( $scope ) ) {
			$result['database_rollback_attempted']   = true;
			$result['destructive_actions_performed'] = true;
			$result['database_may_be_modified']      = true;
			$import                                = $this->import_database( (string) $evidence['database_export_path'], $target_path );
			$result['database_rollback_succeeded']   = 0 === (int) $import['exit_code'];
			$result['database_imported']             = $result['database_rollback_succeeded'];
			if ( ! $result['database_rollback_succeeded'] ) {
				$result['failure_step']          = 'database-rollback';
				$result['manual_recovery_notes'] = array( 'Database rollback failed. Keep the target controlled and inspect the verified pre-restore evidence.' );
				return $this->write_production_rollback_report( $result );
			}
		}

		$post_runtime            = $this->production_runtime_fingerprint( $target_path );
		$post_filesystem_markers = $this->production_filesystem_markers( $target_path );
		$post_ownership          = $this->production_target_owner_group( $target_path );
		$expected_fingerprint    = isset( $evidence['preflight_target_fingerprint'] ) && is_array( $evidence['preflight_target_fingerprint'] ) ? $evidence['preflight_target_fingerprint'] : array();
		$this->add_restore_dry_run_check( $result['checks'], 'post_rollback_runtime_reads_passed', 0 === (int) $post_runtime['failure_count'], 'Required post-rollback WP-CLI identity queries succeeded.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_rollback_maintenance_active', ! empty( $post_runtime['maintenance_active'] ), 'WordPress maintenance mode remains active during post-rollback verification.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_rollback_home_matches_target', $this->normalize_site_url( $this->config_string( 'production_target_site_url' ) ) === $post_runtime['home'], 'Post-rollback home matches the enrolled target URL.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_rollback_siteurl_matches_target', $this->normalize_site_url( $this->config_string( 'production_target_site_url' ) ) === $post_runtime['siteurl'], 'Post-rollback siteurl matches the enrolled target URL.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_rollback_uuid_matches_target', $configured_uuid === $post_runtime['site_uuid'], 'Post-rollback plugin site UUID matches the enrolled target.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_rollback_active_plugins_match', $this->config_list_matches_actual( 'production_expected_active_plugins', $post_runtime['active_plugins'] ), 'Post-rollback active plugin inventory exactly matches enrollment.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_rollback_active_theme_matches', in_array( $this->config_string( 'production_expected_active_theme' ), $post_runtime['active_themes'], true ), 'Post-rollback active theme matches enrollment.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_rollback_drop_ins_match', $this->config_list_matches_actual( 'production_expected_drop_ins', $post_filesystem_markers['drop_ins'] ), 'Post-rollback WordPress drop-in inventory exactly matches enrollment.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_rollback_owner_matches', isset( $expected_fingerprint['filesystem_owner_id'] ) && null !== $expected_fingerprint['filesystem_owner_id'] && (int) $expected_fingerprint['filesystem_owner_id'] === $post_ownership['owner_id'], 'Post-rollback WordPress root owner matches private pre-restore evidence.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_rollback_group_matches', isset( $expected_fingerprint['filesystem_group_id'] ) && null !== $expected_fingerprint['filesystem_group_id'] && (int) $expected_fingerprint['filesystem_group_id'] === $post_ownership['group_id'], 'Post-rollback WordPress root group matches private pre-restore evidence.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_rollback_filesystem_inventory_complete', ! empty( $post_filesystem_markers['scan_complete'] ) && empty( $post_filesystem_markers['symlink_samples_truncated'] ), 'Post-rollback filesystem identity scan completed without truncation.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_rollback_symlinks_match', $this->config_list_matches_actual( 'production_expected_symlink_paths', $post_filesystem_markers['symlink_samples'] ), 'Post-rollback symlink inventory exactly matches enrollment.' );
		$this->add_restore_dry_run_check( $result['checks'], 'post_rollback_symlink_targets_match', $this->config_string_map_matches_actual( 'production_expected_symlink_targets', $post_filesystem_markers['symlink_targets'] ), 'Post-rollback symlink targets exactly match enrollment.' );
		$result['failure_count']                    = $this->restore_check_failure_count( $result['checks'] );
		$result['post_rollback_verification_passed'] = 0 === (int) $result['failure_count'];
		if ( ! $result['post_rollback_verification_passed'] ) {
			$result['failure_step']          = 'post-rollback-verification';
			$result['manual_recovery_notes'] = array( 'Post-rollback verification failed. Keep maintenance active and inspect the verified recovery evidence.' );
			return $this->write_production_rollback_report( $result );
		}

		$result['maintenance_deactivation_attempted'] = true;
		$maintenance = $this->run_wp_cli_action( $target_path, 'maintenance-mode deactivate' );
		$result['maintenance_deactivation_succeeded'] = 0 === (int) $maintenance['exit_code'];
		if ( ! $result['maintenance_deactivation_succeeded'] ) {
			$result['failure_step']          = 'rollback-maintenance-deactivation';
			$result['manual_recovery_notes'] = array( 'Rollback verification passed, but maintenance mode could not be deactivated. Inspect the target before manual deactivation.' );
			return $this->write_production_rollback_report( $result );
		}

		$result['status'] = 'succeeded';
		$result           = $this->write_production_rollback_report( $result );
		if ( empty( $result['rollback_report_written'] ) || '' === $result['rollback_extraction_path'] ) {
			return $result;
		}

		// Retain recovery material until the private rollback report is durable.
		$result['rollback_extraction_cleanup_attempted'] = true;
		$result['rollback_extraction_cleanup_succeeded'] = $this->remove_production_rollback_staging_directory( $result['rollback_extraction_path'] );
		$result['rollback_extraction_retained']          = is_dir( $result['rollback_extraction_path'] );
		if ( ! $result['rollback_extraction_cleanup_succeeded'] ) {
			$result['manual_recovery_notes'][] = 'Rollback succeeded, but its private extraction directory could not be removed. Review and clean it manually after confirming the rollback report.';
		}

		return $result;
	}

	/**
	 * Returns rollback-blocking failures while permitting damaged target state.
	 *
	 * @param array<int,array<string,mixed>> $checks Production preflight checks.
	 * @return array<int,string>
	 */
	private function production_rollback_blocking_preflight_failures( array $checks ) {
		$recoverable = array(
			'runtime_wp_cli_reads_passed',
			'runtime_home_matches_config',
			'runtime_siteurl_matches_config',
			'runtime_site_uuid_matches_config',
			'expected_active_plugins_match',
			'expected_active_theme_matches',
			'expected_drop_ins_match',
			'filesystem_inventory_complete',
			'expected_symlinks_match',
			'expected_symlink_targets_match',
			'maintenance_status_detected',
		);
		$failures = array();
		foreach ( $checks as $check ) {
			if ( ! empty( $check['passed'] ) || empty( $check['name'] ) || in_array( (string) $check['name'], $recoverable, true ) ) {
				continue;
			}
			$failures[] = (string) $check['name'];
		}

		return $failures;
	}

	/**
	 * Adds checks for production-specific pre-restore evidence and its artifacts.
	 *
	 * @param array<int,array<string,mixed>> $checks Checks.
	 * @param array<string,mixed>            $evidence Evidence.
	 * @param string                         $evidence_path Evidence path.
	 * @param string                         $package_id Package ID.
	 * @param string                         $scope Restore scope.
	 * @param string                         $target_path Target WordPress path.
	 * @param string                         $target_host Target hostname.
	 * @param string                         $target_uuid Target UUID.
	 * @return void
	 */
	private function add_production_pre_restore_evidence_checks( array &$checks, array $evidence, $evidence_path, $package_id, $scope, $target_path, $target_host, $target_uuid ) {
		$pre_backup_path = $this->production_pre_backup_path();
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_readable', '' !== $evidence_path && is_file( $evidence_path ) && is_readable( $evidence_path ), 'Production pre-restore evidence is readable.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_valid', ! empty( $evidence ), 'Production pre-restore evidence is valid JSON.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_type', isset( $evidence['evidence_type'] ) && 'production_pre_restore_backup' === (string) $evidence['evidence_type'], 'Evidence type is production_pre_restore_backup.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_package_id', '' !== $package_id && isset( $evidence['package_id'] ) && $package_id === (string) $evidence['package_id'], 'Evidence package ID matches the apply report.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_scope', isset( $evidence['scope'] ) && $scope === (string) $evidence['scope'], 'Evidence scope matches the apply report.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_target_path', isset( $evidence['target_wordpress_path'] ) && $target_path === $this->normalize_path( (string) $evidence['target_wordpress_path'] ), 'Evidence target path matches the enrolled target.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_target_site', isset( $evidence['target_site'] ) && $target_host === strtolower( (string) $evidence['target_site'] ), 'Evidence target hostname matches the enrolled target.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_target_uuid', isset( $evidence['target_site_uuid'] ) && $target_uuid === strtolower( (string) $evidence['target_site_uuid'] ), 'Evidence target UUID matches the enrolled target.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_timestamp', $this->valid_iso_timestamp( isset( $evidence['generated_at'] ) ? (string) $evidence['generated_at'] : '' ), 'Evidence records a valid generation timestamp.' );
		$evidence_time = isset( $evidence['generated_at'] ) ? strtotime( (string) $evidence['generated_at'] ) : false;
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_fresh', false !== $evidence_time && $evidence_time <= time() + 300 && time() - $evidence_time <= $this->production_pre_backup_max_age_seconds(), 'Evidence is within the configured production pre-backup freshness window.' );

		if ( $this->restore_scope_includes_database( $scope ) ) {
			$this->add_production_pre_restore_artifact_check( $checks, $evidence, 'database_export_path', 'database_export_sha256', $pre_backup_path, 'database export' );
		}
		if ( $this->restore_scope_includes_files( $scope ) ) {
			$this->add_production_pre_restore_artifact_check( $checks, $evidence, 'file_backup_path', 'file_backup_sha256', $pre_backup_path, 'file backup' );
		}
	}

	/**
	 * Adds a contained and hash-verified production recovery artifact check.
	 *
	 * @param array<int,array<string,mixed>> $checks Checks.
	 * @param array<string,mixed>            $evidence Evidence.
	 * @param string                         $path_key Artifact path key.
	 * @param string                         $hash_key Artifact hash key.
	 * @param string                         $pre_backup_path Private evidence root.
	 * @param string                         $label Artifact label.
	 * @return void
	 */
	private function add_production_pre_restore_artifact_check( array &$checks, array $evidence, $path_key, $hash_key, $pre_backup_path, $label ) {
		$artifact_path = isset( $evidence[ $path_key ] ) ? $this->normalize_path( (string) $evidence[ $path_key ] ) : '';
		$expected_hash = isset( $evidence[ $hash_key ] ) ? strtolower( (string) $evidence[ $hash_key ] ) : '';
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_' . $path_key . '_contained', '' !== $artifact_path && $this->path_is_within_directory_canonical( $pre_backup_path, $artifact_path ), 'Production pre-restore ' . $label . ' is inside the private pre-backup path.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_' . $path_key . '_readable', '' !== $artifact_path && is_file( $artifact_path ) && is_readable( $artifact_path ), 'Production pre-restore ' . $label . ' is readable.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_' . $hash_key . '_valid', 1 === preg_match( '/^[a-f0-9]{64}$/', $expected_hash ) && '' !== $artifact_path && is_file( $artifact_path ) && hash_equals( $expected_hash, hash_file( 'sha256', $artifact_path ) ), 'Production pre-restore ' . $label . ' SHA-256 matches the evidence record.' );
	}

	/**
	 * Extracts a verified pre-restore archive into private staging then restores it.
	 *
	 * @param string $archive_path Pre-restore archive path.
	 * @param string $target_path Target WordPress path.
	 * @param string $package_id Package identifier.
	 * @param string $staging_path Generated private extraction path.
	 * @return bool
	 */
	private function restore_production_files_from_pre_backup( $archive_path, $target_path, $package_id, &$staging_path ) {
		$restore_path = $this->normalize_path( $this->config_string( 'production_restore_path' ) );
		$target_path  = $this->normalize_path( $target_path );
		$staging_path = '';
		if ( '' === $archive_path || '' === $restore_path || ! is_file( $archive_path ) || ! is_readable( $archive_path ) || ! $this->ensure_directory( $restore_path ) || ! is_writable( $restore_path ) || $this->dangerous_restore_target_path( $target_path ) ) {
			return false;
		}

		$staging_path = $restore_path . DIRECTORY_SEPARATOR . '.rollback-' . $this->safe_slug( $package_id ) . '-' . gmdate( 'Ymd-His' );
		if ( file_exists( $staging_path ) || ! mkdir( $staging_path, 0750, true ) ) {
			return false;
		}

		$extract = $this->run_shell_command( 'tar -xzf ' . escapeshellarg( $archive_path ) . ' -C ' . escapeshellarg( $staging_path ) );
		$file_root = $staging_path . DIRECTORY_SEPARATOR . basename( $target_path );
		if ( 0 !== (int) $extract['exit_code'] || ! is_dir( $file_root ) || ! is_readable( $file_root ) || ! $this->directory_has_entries( $file_root ) ) {
			return false;
		}
		$source_symlinks = $this->production_symlink_map( $file_root );
		if ( false === $source_symlinks || ! $this->config_string_map_matches_actual( 'production_expected_symlink_targets', $source_symlinks ) ) {
			return false;
		}

		return $this->replace_target_files_from_staging( $file_root, $target_path, $source_symlinks );
	}

	/**
	 * Removes one exact successful-rollback extraction directory.
	 *
	 * @param string $staging_path Generated private extraction path.
	 * @return bool
	 */
	private function remove_production_rollback_staging_directory( $staging_path ) {
		$restore_path = $this->normalize_path( $this->config_string( 'production_restore_path' ) );
		$staging_path = $this->normalize_path( $staging_path );
		$name         = basename( $staging_path );
		if ( '' === $restore_path || 0 !== strpos( $name, '.rollback-' ) || ! $this->safe_cleanup_child_name( $name ) || ! $this->path_is_expected_child( $restore_path, $staging_path, $name ) ) {
			return false;
		}

		return $this->remove_restore_staging_directory( $staging_path, $restore_path );
	}

	/**
	 * Returns a private production rollback report destination.
	 *
	 * @param string $package_id Package identifier.
	 * @return string
	 */
	private function production_rollback_report_path( $package_id ) {
		$reports_path = $this->normalize_path( $this->config_string( 'production_reports_path' ) );
		$target_path  = $this->normalize_path( $this->config_string( 'production_target_wordpress_path' ) );
		if ( '' === $reports_path || $this->dangerous_restore_target_path( $reports_path ) || ! $this->path_is_outside_directory_canonical( $target_path, $reports_path ) || ! $this->ensure_directory( $reports_path ) || ! is_writable( $reports_path ) ) {
			return '';
		}

		return $reports_path . DIRECTORY_SEPARATOR . 'RESTORE_PRODUCTION_ROLLBACK_REPORT-' . $this->safe_slug( $package_id ) . '-' . gmdate( 'Ymd-His' ) . '.json';
	}

	/**
	 * Writes an immutable rollback report after the report destination is prepared.
	 *
	 * @param array<string,mixed> $result Rollback result.
	 * @return array<string,mixed>
	 */
	protected function write_production_rollback_report( array $result ) {
		if ( empty( $result['rollback_report_path'] ) ) {
			$result['rollback_report_error'] = 'Production rollback reports path is missing, unsafe, or not writable.';
			return $result;
		}

		$result['rollback_report_written'] = $this->write_json_atomic( $result['rollback_report_path'], $result ) && chmod( $result['rollback_report_path'], 0640 );
		if ( ! $result['rollback_report_written'] ) {
			$result['rollback_report_error'] = 'Could not write the production rollback report.';
			$result['status']                = 'failed';
			if ( '' === $result['failure_step'] ) {
				$result['failure_step'] = 'rollback-report-write';
			}
		}

		return $result;
	}

	/**
	 * Builds the read-only production restore preflight result.
	 *
	 * @param string $staged_path Staged package path.
	 * @param string $scope Restore scope.
	 * @param string $target_site Target hostname supplied by the operator.
	 * @param string $apply_state Required apply flag state: disabled, enabled, or either.
	 * @return array<string,mixed>
	 */
	private function restore_production_preflight_result( $staged_path, $scope, $target_site, $apply_state = 'disabled' ) {
		$checks              = array();
		$target_path         = $this->normalize_path( $this->config_string( 'production_target_wordpress_path' ) );
		$site_root           = $this->production_target_site_root( $target_path );
		$wp_config_path      = $this->production_target_wp_config_path( $site_root );
		$configured_url      = $this->normalize_site_url( $this->config_string( 'production_target_site_url' ) );
		$configured_host     = $this->site_host( $configured_url );
		$configured_uuid     = strtolower( $this->config_string( 'production_target_site_uuid' ) );
		$restore_base        = $this->normalize_path( $this->config_string( 'production_restore_path' ) );
		$reports_path        = $this->normalize_path( $this->config_string( 'production_reports_path' ) );
		$native_evidence_path = $this->normalize_path( $this->config_string( 'production_native_backup_evidence_path' ) );
		$report_path         = '' !== $staged_path ? $staged_path . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json' : '';
		$package             = $this->read_restore_report( $report_path );
		$package_id          = isset( $package['package_id'] ) ? (string) $package['package_id'] : basename( $staged_path );
		$runtime             = $this->production_runtime_fingerprint( $target_path );
		$filesystem_markers  = $this->production_filesystem_markers( $target_path );
		$native_evidence     = $this->read_restore_report( $native_evidence_path );
		$disk                = $this->production_restore_disk_budget( $target_path, $staged_path, $scope, $runtime, $filesystem_markers );

		if ( 'enabled' === $apply_state ) {
			$this->add_restore_dry_run_check( $checks, 'production_apply_enabled', $this->config_bool( 'production_restore_enabled' ), 'Production restore apply is explicitly enabled in private runner configuration.' );
		} elseif ( 'disabled' === $apply_state ) {
			$this->add_restore_dry_run_check( $checks, 'production_apply_disabled', ! $this->config_bool( 'production_restore_enabled' ), 'Production restore apply remains disabled during the preflight phase.' );
		}
		$this->add_restore_dry_run_check( $checks, 'production_environment', 'production-simulation' === strtolower( $this->config_string( 'production_restore_environment' ) ), 'Production restore environment is production-simulation.' );
		$this->add_restore_dry_run_check( $checks, 'scope_supported', in_array( $scope, array( 'files', 'database', 'files-and-database' ), true ), 'Scope is files, database, or files-and-database.' );
		$this->add_restore_dry_run_check( $checks, 'target_site_supplied', '' !== $target_site, 'The operator supplied --target-site.' );
		$this->add_restore_dry_run_check( $checks, 'target_site_matches_config', '' !== $target_site && $target_site === $configured_host, 'The operator target hostname matches the enrolled target URL.' );
		$this->add_restore_dry_run_check( $checks, 'target_url_configured', '' !== $configured_url && '' !== $configured_host, 'The enrolled target URL is valid.' );
		$this->add_restore_dry_run_check( $checks, 'target_url_https', 0 === strpos( $configured_url, 'https://' ), 'The enrolled target URL uses HTTPS.' );
		$this->add_restore_dry_run_check( $checks, 'target_uuid_configured', $this->valid_uuid( $configured_uuid ), 'The enrolled target UUID is valid.' );
		$this->add_restore_dry_run_check( $checks, 'target_wordpress_path_matches_runner', '' !== $target_path && $target_path === $this->wordpress_path(), 'The production target path matches the runner WordPress path.' );
		$this->add_restore_dry_run_check( $checks, 'target_wordpress_path_safe', '' !== $target_path && ! $this->dangerous_restore_target_path( $target_path ), 'The production target is not a broad system path.' );
		$this->add_restore_dry_run_check( $checks, 'target_wordpress_path_readable', is_dir( $target_path ) && is_readable( $target_path ), 'The production target exists and is readable.' );
		$this->add_restore_dry_run_check( $checks, 'target_within_site_root', '' !== $site_root && $this->path_is_within_directory( $site_root, $target_path ) && $site_root !== $target_path, 'The WordPress target is contained by the enrolled site root.' );
		$this->add_restore_dry_run_check( $checks, 'canonical_target_within_site_root', $this->path_is_within_directory_canonical( $site_root, $target_path ) && $this->canonical_paths_differ( $site_root, $target_path ), 'The canonical WordPress target remains inside the canonical site root.' );
		$this->add_restore_dry_run_check( $checks, 'external_wp_config_readable', '' !== $wp_config_path && is_file( $wp_config_path ) && is_readable( $wp_config_path ) && $this->path_is_outside_directory_canonical( $target_path, $wp_config_path ), 'The external wp-config.php path is readable and canonically outside the WordPress target.' );
		$this->add_restore_dry_run_check( $checks, 'runtime_wp_cli_reads_passed', 0 === (int) $runtime['failure_count'], 'Required read-only WP-CLI fingerprint queries succeeded.' );
		$this->add_restore_dry_run_check( $checks, 'runtime_home_matches_config', $configured_url === $runtime['home'], 'Runtime home matches the enrolled target URL.' );
		$this->add_restore_dry_run_check( $checks, 'runtime_siteurl_matches_config', $configured_url === $runtime['siteurl'], 'Runtime siteurl matches the enrolled target URL.' );
		$this->add_restore_dry_run_check( $checks, 'runtime_site_uuid_matches_config', '' !== $runtime['site_uuid'] && $configured_uuid === $runtime['site_uuid'], 'Runtime plugin site UUID matches the enrolled UUID.' );
		$this->add_restore_dry_run_check( $checks, 'expected_active_plugins_match', $this->config_list_matches_actual( 'production_expected_active_plugins', $runtime['active_plugins'] ), 'The active plugin inventory exactly matches enrollment.' );
		$this->add_restore_dry_run_check( $checks, 'expected_active_theme_matches', in_array( $this->config_string( 'production_expected_active_theme' ), $runtime['active_themes'], true ), 'The enrolled active theme is active.' );
		$this->add_restore_dry_run_check( $checks, 'expected_drop_ins_match', $this->config_list_matches_actual( 'production_expected_drop_ins', $filesystem_markers['drop_ins'] ), 'The WordPress drop-in inventory exactly matches enrollment.' );
		$this->add_restore_dry_run_check( $checks, 'filesystem_inventory_complete', ! empty( $filesystem_markers['scan_complete'] ) && empty( $filesystem_markers['symlink_samples_truncated'] ), 'The filesystem identity scan completed without truncating symlink evidence.' );
		$this->add_restore_dry_run_check( $checks, 'symlink_inventory_reviewed', $this->config_bool( 'production_symlink_inventory_reviewed' ), 'The target symlink inventory has been reviewed.' );
		$this->add_restore_dry_run_check( $checks, 'expected_symlinks_match', $this->config_list_matches_actual( 'production_expected_symlink_paths', $filesystem_markers['symlink_samples'] ), 'The WordPress symlink inventory exactly matches enrollment.' );
		$this->add_restore_dry_run_check( $checks, 'expected_symlink_targets_match', $this->config_string_map_matches_actual( 'production_expected_symlink_targets', $filesystem_markers['symlink_targets'] ), 'The WordPress symlink targets exactly match enrollment.' );

		$this->add_restore_dry_run_check( $checks, 'staged_path_under_production_restore_path', '' !== $restore_base && '' !== $staged_path && $this->path_is_within_directory( $restore_base, $staged_path ) && $restore_base !== $staged_path, 'The staged package is inside the production restore path.' );
		$this->add_restore_dry_run_check( $checks, 'canonical_staged_path_under_restore_path', $this->path_is_within_directory_canonical( $restore_base, $staged_path ) && $this->canonical_paths_differ( $restore_base, $staged_path ), 'The canonical staged package remains inside the canonical production restore path.' );
		$this->add_restore_dry_run_check( $checks, 'staged_path_readable', is_dir( $staged_path ) && is_readable( $staged_path ), 'The staged package exists and is readable.' );
		$this->add_restore_dry_run_check( $checks, 'restore_report_valid', ! empty( $package ), 'RESTORE_REPORT.json is readable valid JSON.' );
		$this->add_restore_dry_run_report_check( $checks, $package, 'status', 'staged_for_inspection' );
		$this->add_restore_dry_run_report_check( $checks, $package, 'package_verified', true );
		$this->add_restore_dry_run_report_check( $checks, $package, 'archive_members_safe', true );
		$this->add_restore_dry_run_report_check( $checks, $package, 'database_imported', false );
		$this->add_restore_dry_run_report_check( $checks, $package, 'live_files_overwritten', false );
		$this->add_restore_dry_run_check( $checks, 'package_id_safe', $this->restore_report_relative_name_is_safe( $package_id ), 'The staged package ID is a safe path segment.' );
		$this->add_restore_dry_run_check( $checks, 'package_site_url_matches_target', $configured_url === $this->normalize_site_url( isset( $package['site_url'] ) ? (string) $package['site_url'] : '' ), 'The package site URL matches the enrolled target.' );
		$this->add_restore_dry_run_check( $checks, 'package_site_identity_matches_target', $this->package_site_identity_matches( $package, $configured_host ), 'The package site identity matches the enrolled target hostname.' );
		$this->add_restore_dry_run_check( $checks, 'package_site_uuid_matches_target', isset( $package['site_uuid'] ) && strtolower( (string) $package['site_uuid'] ) === $configured_uuid, 'The package site UUID matches the enrolled target UUID.' );
		$this->add_restore_dry_run_check( $checks, 'package_created_at_valid', $this->valid_iso_timestamp( isset( $package['created_at'] ) ? (string) $package['created_at'] : '' ), 'The package records a valid creation time.' );
		$this->add_restore_dry_run_check( $checks, 'package_producer_recorded', ! empty( $package['producer'] ), 'The package records its producer.' );
		$this->add_restore_dry_run_check( $checks, 'package_producer_version_recorded', ! empty( $package['producer_version'] ), 'The package records its producer version.' );
		$this->add_restore_dry_run_check( $checks, 'package_archive_format', isset( $package['archive_format'] ) && 'tar.gz' === (string) $package['archive_format'], 'The package archive format is tar.gz.' );
		$this->add_production_staged_scope_checks( $checks, $package, $staged_path, $scope );

		$this->add_restore_dry_run_check( $checks, 'disk_measurements_available', ! empty( $disk['measurements_available'] ), 'Target, staged-package, and free-space measurements are available.' );
		$this->add_restore_dry_run_check( $checks, 'disk_budget_sufficient', ! empty( $disk['sufficient'] ), 'Free space meets the conservative production restore budget.' );
		$this->add_restore_dry_run_check( $checks, 'production_reports_path_ready', '' !== $reports_path && ! $this->dangerous_restore_target_path( $reports_path ) && $this->path_is_outside_directory_canonical( $target_path, $reports_path ) && $this->path_or_parent_is_writable_directory( $reports_path ), 'Production reports path is canonically outside the WordPress target and writable or creatable.' );

		$this->add_restore_dry_run_check( $checks, 'maintenance_strategy_reviewed', 'wp-maintenance-mode' === strtolower( $this->config_string( 'production_maintenance_strategy' ) ), 'The WordPress maintenance-mode strategy is selected.' );
		$this->add_restore_dry_run_check( $checks, 'cron_control_reviewed', $this->config_bool( 'production_cron_control_reviewed' ), 'GridPane and site-user cron control has been reviewed.' );
		$this->add_restore_dry_run_check( $checks, 'external_writers_reviewed', $this->config_bool( 'production_external_writers_reviewed' ), 'Queues, webhooks, forms, and external writers have been reviewed.' );
		$this->add_restore_dry_run_check( $checks, 'cache_purge_reviewed', $this->config_bool( 'production_cache_purge_reviewed' ), 'Object and page cache purge steps have been reviewed.' );
		$this->add_restore_dry_run_check( $checks, 'maintenance_status_detected', ! empty( $runtime['maintenance_status_detected'] ), 'Current WordPress maintenance state was detected without changing it.' );

		$this->add_production_native_backup_checks( $checks, $native_evidence_path, $native_evidence, $configured_host, $configured_uuid );

		$failure_count = $this->restore_check_failure_count( $checks );

		return array(
			'schema_version'                => 1,
			'generated_at'                  => gmdate( 'c' ),
			'command'                       => 'restore-production-preflight',
			'safety_classification'         => 'read-only-target-inspection',
			'status'                        => 0 === $failure_count ? 'passed' : 'refused',
			'scope'                         => $scope,
			'target_site'                   => $target_site,
			'package_id'                    => $package_id,
			'staged_path'                   => $staged_path,
			'target_fingerprint'            => $this->production_target_fingerprint( $target_path, $site_root, $wp_config_path, $configured_url, $configured_uuid, $runtime, $filesystem_markers ),
			'package_identity'              => $this->production_package_identity( $package, $package_id ),
			'disk_budget'                   => $disk,
			'maintenance_readiness'         => array(
				'strategy'                    => $this->config_string( 'production_maintenance_strategy' ),
				'currently_active'            => $runtime['maintenance_active'],
				'status_detected'             => $runtime['maintenance_status_detected'],
				'cron_control_reviewed'       => $this->config_bool( 'production_cron_control_reviewed' ),
				'external_writers_reviewed'   => $this->config_bool( 'production_external_writers_reviewed' ),
				'cache_purge_reviewed'        => $this->config_bool( 'production_cache_purge_reviewed' ),
			),
			'native_backup_readiness'       => $this->production_native_backup_summary( $native_evidence_path, $native_evidence ),
			'failure_count'                 => $failure_count,
			'checks'                        => $checks,
			'production_apply_allowed'      => 'enabled' === $apply_state && 0 === $failure_count,
			'production_apply_available'    => $this->config_bool( 'production_restore_enabled' ) && 'production-simulation' === strtolower( $this->config_string( 'production_restore_environment' ) ),
			'production_rollback_available' => $this->config_bool( 'production_rollback_enabled' ) && 'production-simulation' === strtolower( $this->config_string( 'production_restore_environment' ) ),
			'destructive_actions_performed' => false,
			'database_imported'             => false,
			'live_files_overwritten'        => false,
			'pre_restore_backup_created'    => false,
			'native_backup_created'         => false,
			'maintenance_state_changed'     => false,
			'report_write_requested'        => false,
			'report_written'                => false,
			'report_path'                   => '',
			'report_write_error'            => '',
		);
	}

	/**
	 * Reads the target fingerprint through read-only WP-CLI commands.
	 *
	 * @param string $target_path WordPress path.
	 * @return array<string,mixed>
	 */
	private function production_runtime_fingerprint( $target_path ) {
		$home         = $this->run_wp_cli_read( $target_path, 'option get home' );
		$siteurl      = $this->run_wp_cli_read( $target_path, 'option get siteurl' );
		$site_uuid    = $this->run_wp_cli_read( $target_path, 'option pluck alynt_drime_backups_settings site_uuid' );
		$db_name      = $this->run_wp_cli_read( $target_path, 'config get DB_NAME' );
		$table_prefix = $this->run_wp_cli_read( $target_path, 'db prefix' );
		$db_size      = $this->run_wp_cli_read( $target_path, 'db size --size_format=b' );
		$core_version = $this->run_wp_cli_read( $target_path, 'core version' );
		$wp_cli       = $this->run_wp_cli_read( $target_path, 'cli version' );
		$plugins      = $this->run_wp_cli_read_lines( $target_path, 'plugin list --status=active --field=name' );
		$themes       = $this->run_wp_cli_read_lines( $target_path, 'theme list --status=active --field=name' );
		$maintenance  = $this->run_wp_cli_read( $target_path, 'maintenance-mode status' );
		$maintenance_text = strtolower( (string) $maintenance['value'] );
		$maintenance_detected = false !== strpos( $maintenance_text, 'maintenance mode' ) || false !== strpos( $maintenance_text, 'maintenance-mode' );
		$required_reads = array( $home, $siteurl, $site_uuid, $db_name, $table_prefix, $db_size, $core_version, $wp_cli );
		$failure_count = 0;
		foreach ( $required_reads as $read ) {
			if ( 0 !== (int) $read['exit_code'] || '' === (string) $read['value'] ) {
				++$failure_count;
			}
		}
		if ( ! $maintenance_detected ) {
			++$failure_count;
		}
		if ( 0 !== (int) $plugins['exit_code'] || 0 !== (int) $themes['exit_code'] ) {
			++$failure_count;
		}

		$db_size_bytes = ctype_digit( trim( (string) $db_size['value'] ) ) ? (int) trim( (string) $db_size['value'] ) : 0;
		return array(
			'failure_count'              => $failure_count,
			'home'                       => $this->normalize_site_url( (string) $home['value'] ),
			'siteurl'                    => $this->normalize_site_url( (string) $siteurl['value'] ),
			'site_uuid'                  => strtolower( trim( (string) $site_uuid['value'] ) ),
			'database_name_sha256'       => '' !== (string) $db_name['value'] ? hash( 'sha256', (string) $db_name['value'] ) : '',
			'database_size_bytes'        => max( 0, $db_size_bytes ),
			'table_prefix'               => trim( (string) $table_prefix['value'] ),
			'wordpress_version'          => trim( (string) $core_version['value'] ),
			'wp_cli_version'             => trim( (string) $wp_cli['value'] ),
			'active_plugins'             => $plugins['values'],
			'active_themes'              => $themes['values'],
			'php_version'                => PHP_VERSION,
			'maintenance_active'         => $maintenance_detected && false === strpos( $maintenance_text, 'not active' ) && false !== strpos( $maintenance_text, 'active' ),
			'maintenance_status_detected' => $maintenance_detected,
		);
	}

	/**
	 * Runs one fixed read-only WP-CLI query and returns its first non-empty output.
	 *
	 * @param string $target_path WordPress path.
	 * @param string $subcommand Fixed read-only subcommand.
	 * @return array{exit_code:int,value:string}
	 */
	private function run_wp_cli_read( $target_path, $subcommand ) {
		$result = $this->run_wp_cli_read_lines( $target_path, $subcommand );

		return array(
			'exit_code' => (int) $result['exit_code'],
			'value'     => isset( $result['values'][0] ) ? (string) $result['values'][0] : '',
		);
	}

	/**
	 * Runs one fixed read-only WP-CLI query and returns non-empty output lines.
	 *
	 * @param string $target_path WordPress path.
	 * @param string $subcommand Fixed read-only subcommand.
	 * @return array{exit_code:int,values:array<int,string>}
	 */
	private function run_wp_cli_read_lines( $target_path, $subcommand ) {
		$command = escapeshellarg( $this->wp_cli_path() )
			. ' --path=' . escapeshellarg( $target_path )
			. ' ' . $subcommand
			. ' --skip-plugins --skip-themes';
		$result  = $this->run_shell_command( $command );
		$values  = array();

		foreach ( $result['output'] as $line ) {
			$line = trim( (string) $line );
			if ( '' !== $line ) {
				$values[] = $line;
			}
		}

		return array(
			'exit_code' => (int) $result['exit_code'],
			'values'    => $values,
		);
	}

	/**
	 * Runs one fixed target-changing WP-CLI action used by restore control.
	 *
	 * @param string $target_path WordPress path.
	 * @param string $subcommand Fixed action subcommand.
	 * @return array{exit_code:int,output:array<int,string>}
	 */
	private function run_wp_cli_action( $target_path, $subcommand ) {
		$allowed = array( 'maintenance-mode activate', 'maintenance-mode deactivate' );
		if ( ! in_array( $subcommand, $allowed, true ) ) {
			return array( 'exit_code' => 1, 'output' => array( 'Unsupported WP-CLI restore action.' ) );
		}

		$command = escapeshellarg( $this->wp_cli_path() )
			. ' --path=' . escapeshellarg( $target_path )
			. ' ' . $subcommand
			. ' --skip-plugins --skip-themes';

		return $this->run_shell_command( $command );
	}

	/**
	 * Re-establishes the core maintenance marker after a failed file write.
	 *
	 * @param string $target_path WordPress path.
	 * @return bool
	 */
	protected function ensure_production_maintenance_marker( $target_path ) {
		$target_path = $this->normalize_path( $target_path );
		if ( '' === $target_path || $target_path !== $this->wordpress_path() || $this->dangerous_restore_target_path( $target_path ) || ! is_dir( $target_path ) || ! is_writable( $target_path ) ) {
			return false;
		}

		$marker = $target_path . DIRECTORY_SEPARATOR . '.maintenance';
		if ( is_link( $marker ) ) {
			return false;
		}
		if ( is_file( $marker ) ) {
			return true;
		}

		$content = "<?php\n\$upgrading = " . time() . ";\n";
		$temp    = $marker . '.alynt-' . str_replace( '.', '', uniqid( '', true ) );
		$written = file_put_contents( $temp, $content );
		if ( strlen( $content ) !== $written || ! chmod( $temp, 0644 ) || ! rename( $temp, $marker ) ) {
			if ( is_file( $temp ) && ! is_link( $temp ) ) {
				unlink( $temp );
			}
			return false;
		}

		return true;
	}

	/**
	 * Adds staged package checks for the requested scope.
	 *
	 * @param array<int,array<string,mixed>> $checks Checks.
	 * @param array<string,mixed>            $package Package report.
	 * @param string                         $staged_path Staged path.
	 * @param string                         $scope Restore scope.
	 * @return void
	 */
	private function add_production_staged_scope_checks( array &$checks, array $package, $staged_path, $scope ) {
		if ( $this->restore_scope_includes_files( $scope ) ) {
			$file_root = isset( $package['file_root'] ) ? (string) $package['file_root'] : '';
			$file_safe = $this->restore_report_relative_name_is_safe( $file_root );
			$this->add_restore_dry_run_check( $checks, 'package_file_root_safe', $file_safe, 'The staged file root is a safe path segment.' );
			$this->add_restore_dry_run_check( $checks, 'staged_files_present', $file_safe && is_dir( $staged_path . DIRECTORY_SEPARATOR . $file_root ) && is_readable( $staged_path . DIRECTORY_SEPARATOR . $file_root ), 'Staged WordPress files are present and readable.' );
		}

		if ( $this->restore_scope_includes_database( $scope ) ) {
			$database_dump = isset( $package['database_dump'] ) ? (string) $package['database_dump'] : '';
			$dump_safe     = $this->restore_report_relative_name_is_safe( $database_dump );
			$this->add_restore_dry_run_check( $checks, 'package_database_dump_safe', $dump_safe, 'The staged database dump is a safe path segment.' );
			$this->add_restore_dry_run_check( $checks, 'staged_database_present', $dump_safe && is_file( $staged_path . DIRECTORY_SEPARATOR . $database_dump ) && is_readable( $staged_path . DIRECTORY_SEPARATOR . $database_dump ), 'The staged database dump is present and readable.' );
		}

		$this->add_production_staged_integrity_checks( $checks, $package, $staged_path, $scope );
	}

	/**
	 * Adds digest checks for extracted restore inputs.
	 *
	 * @param array<int,array<string,mixed>> $checks Checks.
	 * @param array<string,mixed>            $package Package report.
	 * @param string                         $staged_path Staged path.
	 * @param string                         $scope Restore scope.
	 * @param string                         $prefix Check-name prefix.
	 * @return void
	 */
	private function add_production_staged_integrity_checks( array &$checks, array $package, $staged_path, $scope, $prefix = '' ) {
		$integrity = isset( $package['staged_integrity'] ) && is_array( $package['staged_integrity'] ) ? $package['staged_integrity'] : array();
		$this->add_restore_dry_run_check( $checks, $prefix . 'staged_integrity_schema_valid', isset( $integrity['schema_version'] ) && 1 === (int) $integrity['schema_version'] && isset( $integrity['algorithm'] ) && 'sha256' === (string) $integrity['algorithm'], 'The staged-input integrity record uses supported schema 1 and SHA-256.' );

		if ( $this->restore_scope_includes_files( $scope ) ) {
			$file_root = isset( $package['file_root'] ) ? (string) $package['file_root'] : '';
			$expected  = isset( $integrity['file_tree'] ) && is_array( $integrity['file_tree'] ) ? $integrity['file_tree'] : array();
			$actual    = $this->restore_report_relative_name_is_safe( $file_root )
				? $this->staged_file_tree_integrity( $staged_path . DIRECTORY_SEPARATOR . $file_root )
				: array();
			$this->add_restore_dry_run_check( $checks, $prefix . 'staged_file_integrity_recorded', $this->valid_staged_integrity_record( $expected, true ), 'The staged WordPress tree has a complete SHA-256 integrity record.' );
			$this->add_restore_dry_run_check( $checks, $prefix . 'staged_file_integrity_matches', $this->valid_staged_integrity_record( $expected, true ) && $expected === $actual, 'The current staged WordPress tree exactly matches its recorded digest and counts.' );
		}

		if ( $this->restore_scope_includes_database( $scope ) ) {
			$database_dump = isset( $package['database_dump'] ) ? (string) $package['database_dump'] : '';
			$expected      = isset( $integrity['database_dump'] ) && is_array( $integrity['database_dump'] ) ? $integrity['database_dump'] : array();
			$actual        = $this->restore_report_relative_name_is_safe( $database_dump )
				? $this->staged_file_integrity( $staged_path . DIRECTORY_SEPARATOR . $database_dump )
				: array();
			$this->add_restore_dry_run_check( $checks, $prefix . 'staged_database_integrity_recorded', $this->valid_staged_integrity_record( $expected, false ), 'The staged database dump has a complete SHA-256 integrity record.' );
			$this->add_restore_dry_run_check( $checks, $prefix . 'staged_database_integrity_matches', $this->valid_staged_integrity_record( $expected, false ) && $expected === $actual, 'The current staged database dump exactly matches its recorded digest and size.' );
		}
	}

	/**
	 * Returns whether a staged-input integrity record has the expected shape.
	 *
	 * @param array<string,mixed> $record Integrity record.
	 * @param bool                $tree Whether the record describes a directory tree.
	 * @return bool
	 */
	private function valid_staged_integrity_record( array $record, $tree ) {
		if ( empty( $record['valid'] ) || empty( $record['sha256'] ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', (string) $record['sha256'] ) || ! isset( $record['total_bytes'] ) || (int) $record['total_bytes'] < 0 ) {
			return false;
		}

		if ( ! $tree ) {
			return true;
		}

		foreach ( array( 'file_count', 'directory_count', 'symlink_count' ) as $key ) {
			if ( ! isset( $record[ $key ] ) || (int) $record[ $key ] < 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns a conservative production restore disk budget.
	 *
	 * @param string              $target_path Target path.
	 * @param string              $staged_path Staged path.
	 * @param string              $scope Restore scope.
	 * @param array<string,mixed> $runtime Runtime fingerprint.
	 * @param array<string,mixed> $filesystem_markers Filesystem markers.
	 * @return array<string,mixed>
	 */
	private function production_restore_disk_budget( $target_path, $staged_path, $scope, array $runtime, array $filesystem_markers ) {
		$target_files = $this->restore_scope_includes_files( $scope ) ? (int) $filesystem_markers['regular_file_bytes'] : 0;
		$database     = $this->restore_scope_includes_database( $scope ) ? (int) $runtime['database_size_bytes'] : 0;
		$staged       = $this->directory_size_bytes( $staged_path );
		$free         = is_dir( $target_path ) ? disk_free_space( $target_path ) : false;
		$margin       = $this->production_disk_safety_margin_bytes();
		$current      = max( 0, $target_files ) + max( 0, $database );
		$pre_backup   = $current;
		$failed_state = $current;
		$native       = $this->production_native_backup_required() ? $current : 0;
		$required     = $pre_backup + $failed_state + $native + $margin;
		$measured     = false !== $free && $staged >= 0 && ( ! $this->restore_scope_includes_files( $scope ) || $target_files >= 0 ) && ( ! $this->restore_scope_includes_database( $scope ) || $database > 0 );

		return array(
			'target_files_bytes'                 => max( 0, $target_files ),
			'target_database_bytes'              => max( 0, $database ),
			'staged_package_bytes'               => max( 0, $staged ),
			'fresh_pre_restore_backup_bytes'     => $pre_backup,
			'failed_state_preservation_bytes'    => $failed_state,
			'native_backup_estimate_bytes'       => $native,
			'safety_margin_bytes'                => $margin,
			'required_additional_free_bytes'     => $required,
			'available_free_bytes'               => false === $free ? 0 : (int) $free,
			'measurements_available'             => $measured,
			'sufficient'                         => $measured && (int) $free >= $required,
		);
	}

	/**
	 * Returns a directory's regular-file byte count without following links.
	 *
	 * @param string $path Directory path.
	 * @return int
	 */
	private function directory_size_bytes( $path ) {
		if ( '' === $path || ! is_dir( $path ) || ! is_readable( $path ) ) {
			return -1;
		}

		try {
			$bytes    = 0;
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $iterator as $item ) {
				if ( $item->isLink() || ! $item->isFile() ) {
					continue;
				}
				$bytes += (int) $item->getSize();
			}
		} catch ( UnexpectedValueException $exception ) {
			return -1;
		}

		return $bytes;
	}

	/**
	 * Adds host-native backup evidence checks.
	 *
	 * @param array<int,array<string,mixed>> $checks Checks.
	 * @param string                         $evidence_path Evidence path.
	 * @param array<string,mixed>            $evidence Evidence.
	 * @param string                         $target_host Target host.
	 * @param string                         $target_uuid Target UUID.
	 * @return void
	 */
	private function add_production_native_backup_checks( array &$checks, $evidence_path, array $evidence, $target_host, $target_uuid ) {
		$completed_at = isset( $evidence['completed_at'] ) ? (string) $evidence['completed_at'] : '';
		$completed_ts = '' !== $completed_at ? strtotime( $completed_at ) : false;
		$fresh        = false !== $completed_ts && $completed_ts <= time() && ( time() - $completed_ts ) <= $this->production_native_backup_max_age_seconds();

		$this->add_restore_dry_run_check( $checks, 'native_backup_required', $this->production_native_backup_required(), 'A fresh independent host-native backup is required.' );
		$this->add_restore_dry_run_check( $checks, 'native_backup_evidence_path_configured', '' !== $evidence_path, 'The native backup evidence path is configured.' );
		$this->add_restore_dry_run_check( $checks, 'native_backup_evidence_path_private', '' !== $evidence_path && $this->path_is_outside_directory_canonical( $this->normalize_path( $this->config_string( 'production_target_wordpress_path' ) ), $evidence_path ), 'The native backup evidence file is canonically outside the WordPress target.' );
		$this->add_restore_dry_run_check( $checks, 'native_backup_evidence_readable', is_file( $evidence_path ) && is_readable( $evidence_path ), 'The native backup evidence file is readable.' );
		$this->add_restore_dry_run_check( $checks, 'native_backup_evidence_valid', ! empty( $evidence ), 'The native backup evidence is valid JSON.' );
		$this->add_restore_dry_run_check( $checks, 'native_backup_evidence_type', isset( $evidence['evidence_type'] ) && 'gridpane_native_backup' === (string) $evidence['evidence_type'], 'The evidence type is gridpane_native_backup.' );
		$this->add_restore_dry_run_check( $checks, 'native_backup_status', isset( $evidence['status'] ) && 'completed' === (string) $evidence['status'], 'The native backup status is completed.' );
		$this->add_restore_dry_run_check( $checks, 'native_backup_target_site', isset( $evidence['target_site'] ) && strtolower( (string) $evidence['target_site'] ) === $target_host, 'The native backup target matches the enrolled hostname.' );
		$this->add_restore_dry_run_check( $checks, 'native_backup_target_uuid', isset( $evidence['target_site_uuid'] ) && strtolower( (string) $evidence['target_site_uuid'] ) === $target_uuid, 'The native backup target UUID matches the enrollment.' );
		$this->add_restore_dry_run_check( $checks, 'native_backup_revision_recorded', ! empty( $evidence['revision_id'] ), 'The native backup revision identifier is recorded.' );
		$this->add_restore_dry_run_check( $checks, 'native_backup_fresh', $fresh, 'The native backup completed within the configured freshness window.' );
	}

	/**
	 * Builds a redacted native backup readiness summary.
	 *
	 * @param string              $evidence_path Evidence path.
	 * @param array<string,mixed> $evidence Evidence.
	 * @return array<string,mixed>
	 */
	private function production_native_backup_summary( $evidence_path, array $evidence ) {
		$revision_id = isset( $evidence['revision_id'] ) ? (string) $evidence['revision_id'] : '';

		return array(
			'required'           => $this->production_native_backup_required(),
			'evidence_path'      => $evidence_path,
			'evidence_type'      => isset( $evidence['evidence_type'] ) ? (string) $evidence['evidence_type'] : '',
			'status'             => isset( $evidence['status'] ) ? (string) $evidence['status'] : '',
			'revision_recorded'  => '' !== $revision_id,
			'revision_id_sha256' => '' !== $revision_id ? hash( 'sha256', $revision_id ) : '',
			'completed_at'       => isset( $evidence['completed_at'] ) ? (string) $evidence['completed_at'] : '',
		);
	}

	/**
	 * Returns safe WordPress drop-in and symlink identity markers.
	 *
	 * @param string $target_path Target WordPress path.
	 * @return array<string,mixed>
	 */
	private function production_filesystem_markers( $target_path ) {
		$drop_ins = array();
		foreach ( array( 'wp-content/advanced-cache.php', 'wp-content/db.php', 'wp-content/object-cache.php', 'wp-content/sunrise.php' ) as $relative ) {
			if ( is_file( $target_path . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative ) ) ) {
				$drop_ins[] = $relative;
			}
		}

		$symlinks       = array();
		$symlink_targets = array();
		$symlink_count = 0;
		$regular_bytes = 0;
		$scan_complete = false;
		if ( is_dir( $target_path ) && is_readable( $target_path ) ) {
			try {
				$iterator = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $target_path, FilesystemIterator::SKIP_DOTS ),
					RecursiveIteratorIterator::SELF_FIRST
				);
				foreach ( $iterator as $item ) {
					if ( $item->isLink() ) {
						++$symlink_count;
						if ( count( $symlinks ) < 100 ) {
							$relative   = ltrim( substr( $this->normalize_path( $item->getPathname() ), strlen( $this->normalize_path( $target_path ) ) ), '/' );
							$symlinks[] = $relative;
							$link_target = readlink( $item->getPathname() );
							$symlink_targets[ $relative ] = false === $link_target ? '' : (string) $link_target;
						}
						continue;
					}
					if ( $item->isFile() ) {
						$regular_bytes += (int) $item->getSize();
					}
				}
				$scan_complete = true;
			} catch ( UnexpectedValueException $exception ) {
				$symlinks       = array();
				$symlink_targets = array();
				$symlink_count  = 0;
				$regular_bytes  = -1;
			}
		}

		sort( $drop_ins );
		sort( $symlinks );
		ksort( $symlink_targets );

		return array(
			'drop_ins'                  => $drop_ins,
			'regular_file_bytes'         => $regular_bytes,
			'scan_complete'              => $scan_complete,
			'symlink_count'              => $symlink_count,
			'symlink_samples'            => $symlinks,
			'symlink_targets'            => $symlink_targets,
			'symlink_samples_truncated'  => $symlink_count > count( $symlinks ),
		);
	}

	/**
	 * Captures the target values of every enrolled symlink before replacement.
	 *
	 * @param string $target_path Target WordPress path.
	 * @return array<string,string>
	 */
	private function production_expected_symlink_snapshot( $target_path ) {
		$snapshot = array();
		$expected = $this->config_string_map( 'production_expected_symlink_targets' );
		foreach ( $expected as $relative => $expected_target ) {
			if ( ! $this->restore_report_relative_path_is_safe( $relative ) ) {
				continue;
			}

			$link_path = $target_path . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative );
			$link_target = is_link( $link_path ) ? readlink( $link_path ) : false;
			if ( false === $link_target || $expected_target !== (string) $link_target ) {
				continue;
			}

			$snapshot[ $relative ] = $expected_target;
		}

		return $snapshot;
	}

	/**
	 * Recreates enrolled symlinks after a production file replacement.
	 *
	 * @param string               $target_path Target WordPress path.
	 * @param array<string,string> $snapshot Captured symlink targets.
	 * @return bool
	 */
	private function restore_production_expected_symlinks( $target_path, array $snapshot ) {
		foreach ( $snapshot as $relative => $link_target ) {
			if ( ! $this->restore_report_relative_path_is_safe( $relative ) || '' === trim( $link_target ) || false !== strpos( $link_target, "\0" ) ) {
				return false;
			}

			$link_path = $target_path . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative );
			$parent    = dirname( $link_path );
			if ( ! $this->ensure_directory( $parent ) || ! $this->path_is_within_directory_canonical( $target_path, $parent ) ) {
				return false;
			}

			if ( is_link( $link_path ) ) {
				if ( $link_target !== readlink( $link_path ) ) {
					return false;
				}
				continue;
			}
			if ( file_exists( $link_path ) || ! symlink( $link_target, $link_path ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Builds the safe target fingerprint report section.
	 *
	 * @return array<string,mixed>
	 */
	private function production_target_fingerprint( $target_path, $site_root, $wp_config_path, $target_url, $target_uuid, array $runtime, array $filesystem_markers ) {
		$ownership = $this->production_target_owner_group( $target_path );

		return array(
			'target_site_uuid'      => $target_uuid,
			'target_url'            => $target_url,
			'home'                  => $runtime['home'],
			'siteurl'               => $runtime['siteurl'],
			'wordpress_path'        => $target_path,
			'canonical_wordpress_path' => false !== realpath( $target_path ) ? $this->normalize_path( realpath( $target_path ) ) : '',
			'site_root'             => $site_root,
			'external_wp_config_path' => $wp_config_path,
			'database_name_sha256'  => $runtime['database_name_sha256'],
			'database_size_bytes'   => $runtime['database_size_bytes'],
			'table_prefix'          => $runtime['table_prefix'],
			'filesystem_owner_id'   => $ownership['owner_id'],
			'filesystem_group_id'   => $ownership['group_id'],
			'runner_version'        => self::VERSION,
			'php_version'           => $runtime['php_version'],
			'wp_cli_version'        => $runtime['wp_cli_version'],
			'wordpress_version'     => $runtime['wordpress_version'],
			'active_plugins'        => $runtime['active_plugins'],
			'active_themes'         => $runtime['active_themes'],
			'drop_ins'              => $filesystem_markers['drop_ins'],
			'symlink_count'         => $filesystem_markers['symlink_count'],
			'symlink_samples'       => $filesystem_markers['symlink_samples'],
			'symlink_target_sha256' => $this->hash_string_map( $filesystem_markers['symlink_targets'] ),
			'symlink_samples_truncated' => $filesystem_markers['symlink_samples_truncated'],
		);
	}

	/**
	 * Returns the WordPress root owner and group IDs.
	 *
	 * @param string $target_path WordPress path.
	 * @return array{owner_id:?int,group_id:?int}
	 */
	protected function production_target_owner_group( $target_path ) {
		$owner = is_dir( $target_path ) ? fileowner( $target_path ) : false;
		$group = is_dir( $target_path ) ? filegroup( $target_path ) : false;

		return array(
			'owner_id' => false === $owner ? null : (int) $owner,
			'group_id' => false === $group ? null : (int) $group,
		);
	}

	/**
	 * Builds the safe package identity report section.
	 *
	 * @param array<string,mixed> $package Package report.
	 * @param string              $package_id Package ID.
	 * @return array<string,mixed>
	 */
	private function production_package_identity( array $package, $package_id ) {
		$keys   = array( 'site_id', 'site_uuid', 'site_url', 'created_at', 'producer', 'producer_version', 'backup_type', 'archive_format', 'consistency_mode', 'consistency_status', 'archive_name', 'archive_size', 'checksum_algorithm', 'checksum_value', 'file_root', 'database_dump', 'staged_integrity' );
		$result = array( 'package_id' => $package_id );
		foreach ( $keys as $key ) {
			$result[ $key ] = isset( $package[ $key ] ) ? $package[ $key ] : '';
		}

		return $result;
	}

	/**
	 * Writes an optional production preflight audit report.
	 *
	 * @param array<string,mixed> $result Preflight result.
	 * @return array<string,mixed>
	 */
	private function write_restore_production_preflight_report( array $result ) {
		$result['report_write_requested'] = true;
		$reports_path = $this->normalize_path( $this->config_string( 'production_reports_path' ) );
		$target_path  = $this->normalize_path( $this->config_string( 'production_target_wordpress_path' ) );
		if ( '' === $reports_path || $this->dangerous_restore_target_path( $reports_path ) || ! $this->path_is_outside_directory_canonical( $target_path, $reports_path ) || ! $this->ensure_directory( $reports_path ) || ! is_writable( $reports_path ) ) {
			$result['report_write_error'] = 'Production preflight reports path is missing, unsafe, or not writable.';
			$result['status']             = 'refused';
			++$result['failure_count'];
			return $result;
		}

		$report_file          = 'RESTORE_PRODUCTION_PREFLIGHT-' . $this->safe_slug( (string) $result['package_id'] ) . '-' . gmdate( 'Ymd-His' ) . '.json';
		$result['report_path'] = $reports_path . DIRECTORY_SEPARATOR . $report_file;
		$result['report_written'] = true;
		$report_data = $this->redact_restore_report_data( $result );
		if ( ! $this->write_json_atomic( $result['report_path'], $report_data ) ) {
			$result['report_written']    = false;
			$result['report_write_error'] = 'Could not write production preflight report.';
			$result['status']             = 'refused';
			++$result['failure_count'];
		}

		return $result;
	}

	/**
	 * Redacts sensitive keys and values from restore reports.
	 *
	 * @param mixed $value Value.
	 * @param string $key Current key.
	 * @return mixed
	 */
	private function redact_restore_report_data( $value, $key = '' ) {
		if ( '' !== $key && preg_match( '/(?:token|secret|password|authorization|cookie|nonce|salt|private[_-]?key|signed[_-]?url)/i', $key ) ) {
			return '[redacted]';
		}

		if ( is_array( $value ) ) {
			$redacted = array();
			foreach ( $value as $item_key => $item_value ) {
				$redacted[ $item_key ] = $this->redact_restore_report_data( $item_value, (string) $item_key );
			}
			return $redacted;
		}

		if ( is_string( $value ) ) {
			$value = preg_replace( '/\bBearer\s+[A-Za-z0-9._~-]+/i', 'Bearer [redacted]', $value );
			$value = preg_replace( '/([?&](?:token|key|signature|sig|expires)=)[^&\s]+/i', '$1[redacted]', $value );
		}

		return $value;
	}

	/**
	 * Prints a concise production preflight summary.
	 *
	 * @param array<string,mixed> $result Result.
	 * @return void
	 */
	private function print_restore_production_preflight_result( array $result ) {
		$this->line( 'passed' === $result['status'] ? 'Production restore preflight passed.' : 'Production restore preflight refused.' );
		$this->line( 'Target: ' . (string) $result['target_site'] );
		$this->line( 'Package: ' . (string) $result['package_id'] );
		$this->line( 'Scope: ' . (string) $result['scope'] );
		$this->line( 'Failures: ' . (string) $result['failure_count'] );
		$this->line( 'Production apply available: no' );
		if ( ! empty( $result['report_write_requested'] ) ) {
			$this->line( 'Report written: ' . ( ! empty( $result['report_written'] ) ? 'yes' : 'no' ) );
			if ( ! empty( $result['report_path'] ) ) {
				$this->line( 'Report path: ' . (string) $result['report_path'] );
			}
		}
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
		$create_pre_backup        = isset( $options['create-pre-restore-backup'] ) && $this->truthy_value( $options['create-pre-restore-backup'] );

		if ( '' === $staged_path ) {
			$this->error( 'Missing --staged-path=/path/to/staged/package.' );
			return 1;
		}

		if ( 'restore-staging-site' !== $confirm ) {
			$this->error( 'Restore apply requires --confirm=restore-staging-site.' );
			return 1;
		}

		if ( ! in_array( $scope, array( 'database', 'files', 'files-and-database' ), true ) ) {
			$this->error( 'Only --scope=database, --scope=files, or --scope=files-and-database is implemented for restore-apply.' );
			return 1;
		}

		$pre_backup_creation = array(
			'attempted' => false,
			'created'   => false,
		);
		if ( $create_pre_backup ) {
			$pre_backup_creation = $this->create_pre_restore_backup_for_apply(
				$staged_path,
				$scope,
				$pre_backup_evidence_path,
				isset( $options['pre-restore-evidence'] )
			);
			if ( empty( $pre_backup_creation['ok'] ) ) {
				if ( isset( $options['format'] ) && 'json' === strtolower( (string) $options['format'] ) ) {
					$this->line( json_encode( $pre_backup_creation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
				} else {
					$this->line( 'Pre-restore backup creation failed.' );
					$this->line( 'Failures: ' . (string) $pre_backup_creation['failure_count'] );
				}
				return 1;
			}

			$pre_backup_evidence_path = (string) $pre_backup_creation['evidence_path'];
		}

		if ( 'database' === $scope ) {
			$result = $this->restore_apply_database_result( $staged_path, $pre_backup_evidence_path, ! empty( $pre_backup_creation['created'] ), $pre_backup_creation );
		} elseif ( 'files' === $scope ) {
			$result = $this->restore_apply_files_result( $staged_path, $pre_backup_evidence_path, ! empty( $pre_backup_creation['created'] ), $pre_backup_creation );
		} else {
			$result = $this->restore_apply_combined_result( $staged_path, $pre_backup_evidence_path, ! empty( $pre_backup_creation['created'] ), $pre_backup_creation );
		}

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
	private function restore_apply_database_result( $staged_path, $pre_backup_evidence_path, $pre_restore_backup_created = false, array $pre_backup_creation = array() ) {
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
			'file_restore_missing_symlink_count' => 0,
			'file_restore_missing_symlink_samples' => array(),
			'file_restore_manual_review_required' => false,
			'post_restore_manual_review_required' => false,
			'post_restore_cleanup_required'    => false,
			'post_restore_manual_review_items' => array(),
			'destructive_actions_performed'     => false,
			'pre_restore_backup_created'        => (bool) $pre_restore_backup_created,
			'pre_restore_backup_creation'       => $pre_backup_creation,
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
	private function restore_apply_files_result( $staged_path, $pre_backup_evidence_path, $pre_restore_backup_created = false, array $pre_backup_creation = array() ) {
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
			'file_restore_missing_symlink_count' => 0,
			'file_restore_missing_symlink_samples' => array(),
			'file_restore_manual_review_required' => false,
			'post_restore_manual_review_required' => false,
			'post_restore_cleanup_required'    => false,
			'post_restore_manual_review_items' => array(),
			'destructive_actions_performed'     => false,
			'pre_restore_backup_created'        => (bool) $pre_restore_backup_created,
			'pre_restore_backup_creation'       => $pre_backup_creation,
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

		$missing_symlinks = $this->restore_apply_missing_symlink_warnings( $file_root_path, $pre_backup_evidence_path );
		if ( ! empty( $missing_symlinks ) ) {
			$result['file_restore_missing_symlink_count']     = count( $missing_symlinks );
			$result['file_restore_missing_symlink_samples']   = array_slice( $missing_symlinks, 0, 10 );
			$result['file_restore_manual_review_required']    = true;
			$result['manual_recovery_notes'][]                = 'Pre-restore file backup includes symlink entries that are absent from the staged files. Inspect drop-ins after apply.';
		}

		$result['file_restore_attempted']        = true;
		$result['destructive_actions_performed'] = true;
		$result['file_restore_succeeded']        = $this->replace_target_files_from_staging( $file_root_path, (string) $result['target_wordpress_path'] );
		$result['live_files_overwritten']        = $result['file_restore_succeeded'];
		$result['status']                        = $result['file_restore_succeeded'] ? 'succeeded' : 'failed';

		if ( ! $result['file_restore_succeeded'] ) {
			$result['failure_step']          = 'file-restore';
			$result['manual_recovery_notes'] = array( 'Review pre-restore file backup evidence before attempting manual file recovery.' );
		} else {
			$result = $this->add_post_restore_manual_review_items( $result, (string) $result['target_wordpress_path'], $pre_backup_evidence_path );
		}

		return $this->write_restore_apply_report( $result );
	}

	/**
	 * Replaces staged files and imports the staged database after dry-run gates pass.
	 *
	 * @param string $staged_path Staged restore path.
	 * @param string $pre_backup_evidence_path Pre-restore evidence path.
	 * @return array<string,mixed>
	 */
	private function restore_apply_combined_result( $staged_path, $pre_backup_evidence_path, $pre_restore_backup_created = false, array $pre_backup_creation = array() ) {
		$scope   = 'files-and-database';
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
			'file_restore_missing_symlink_count' => 0,
			'file_restore_missing_symlink_samples' => array(),
			'file_restore_manual_review_required' => false,
			'post_restore_manual_review_required' => false,
			'post_restore_cleanup_required'    => false,
			'post_restore_manual_review_items' => array(),
			'combined_restore_order'            => array( 'files', 'database' ),
			'destructive_actions_performed'     => false,
			'pre_restore_backup_created'        => (bool) $pre_restore_backup_created,
			'pre_restore_backup_creation'       => $pre_backup_creation,
			'restore_apply_report_written'      => false,
			'restore_apply_report_path'         => '',
			'restore_apply_report_error'        => '',
			'failure_step'                      => '',
			'manual_recovery_notes'             => array(),
		);

		if ( ! $result['dry_run_checks_passed'] ) {
			$result['failure_step']          = 'restore-dry-run';
			$result['manual_recovery_notes'] = array( 'No combined restore was attempted because restore dry-run checks failed.' );
			return $this->write_restore_apply_report( $result );
		}

		$report_path = $this->restore_apply_report_path( (string) $result['package_id'] );
		if ( '' === $report_path ) {
			$result['failure_step']               = 'restore-apply-report-path';
			$result['restore_apply_report_error'] = 'Restore apply reports path is missing, unsafe, or not writable.';
			$result['manual_recovery_notes']      = array( 'No combined restore was attempted because the restore apply report path was not ready.' );
			return $result;
		}
		$result['restore_apply_report_path'] = $report_path;

		$file_root_path = $this->restore_apply_file_root_path( $staged_path );
		$result['file_root_path'] = $file_root_path;
		if ( '' === $file_root_path || ! is_dir( $file_root_path ) || ! is_readable( $file_root_path ) ) {
			$result['failure_step']          = 'staged-file-root';
			$result['manual_recovery_notes'] = array( 'No combined restore was attempted because the staged file root was not readable.' );
			return $this->write_restore_apply_report( $result );
		}

		$database_dump_path = $this->restore_apply_database_dump_path( $staged_path );
		$result['database_dump_path'] = $database_dump_path;
		if ( '' === $database_dump_path || ! is_readable( $database_dump_path ) ) {
			$result['failure_step']          = 'staged-database-dump';
			$result['manual_recovery_notes'] = array( 'No combined restore was attempted because the staged database dump was not readable.' );
			return $this->write_restore_apply_report( $result );
		}

		$missing_symlinks = $this->restore_apply_missing_symlink_warnings( $file_root_path, $pre_backup_evidence_path );
		if ( ! empty( $missing_symlinks ) ) {
			$result['file_restore_missing_symlink_count']     = count( $missing_symlinks );
			$result['file_restore_missing_symlink_samples']   = array_slice( $missing_symlinks, 0, 10 );
			$result['file_restore_manual_review_required']    = true;
			$result['manual_recovery_notes'][]                = 'Pre-restore file backup includes symlink entries that are absent from the staged files. Inspect drop-ins after apply.';
		}

		$result['file_restore_attempted']        = true;
		$result['destructive_actions_performed'] = true;
		$result['file_restore_succeeded']        = $this->replace_target_files_from_staging( $file_root_path, (string) $result['target_wordpress_path'] );
		$result['live_files_overwritten']        = $result['file_restore_succeeded'];
		if ( ! $result['file_restore_succeeded'] ) {
			$result['failure_step']             = 'file-restore';
			$result['manual_recovery_notes'][]  = 'Database import was not attempted because file restore failed.';
			return $this->write_restore_apply_report( $result );
		}
		$result = $this->add_post_restore_manual_review_items( $result, (string) $result['target_wordpress_path'], $pre_backup_evidence_path );

		$result['database_import_attempted']     = true;
		$import_result                           = $this->import_database( $database_dump_path, (string) $result['target_wordpress_path'] );
		$result['database_import_exit_code']     = (int) $import_result['exit_code'];
		$result['database_import_output']        = array_slice( $import_result['output'], 0, 20 );
		$result['database_import_succeeded']     = 0 === (int) $import_result['exit_code'];
		$result['database_imported']             = $result['database_import_succeeded'];
		$result['status']                        = $result['database_import_succeeded'] ? 'succeeded' : 'failed';

		if ( ! $result['database_import_succeeded'] ) {
			$result['failure_step']             = 'database-import';
			$result['manual_recovery_notes'][]  = 'Files were restored, but database import failed. Review pre-restore backup evidence before manual recovery.';
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
			'restore_apply_command_available' => in_array( $scope, array( 'database', 'files', 'files-and-database' ), true ),
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
	 * Creates pre-restore backup evidence before a destructive staging apply.
	 *
	 * @param string $staged_path Staged restore path.
	 * @param string $scope Restore scope.
	 * @param string $requested_evidence_path Requested evidence path.
	 * @param bool   $use_requested_evidence_path Whether the path came from CLI.
	 * @return array<string,mixed>
	 */
	private function create_pre_restore_backup_for_apply( $staged_path, $scope, $requested_evidence_path, $use_requested_evidence_path ) {
		$checks          = array();
		$restore_base    = $this->restore_path();
		$pre_backup_path = $this->normalize_path( $this->config_string( 'restore_pre_backup_path' ) );
		$target_path     = $this->normalize_path( $this->config_string( 'restore_target_wordpress_path' ) );
		$wordpress_path  = $this->wordpress_path();
		$report_path     = $staged_path . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json';
		$report          = $this->read_restore_report( $report_path );
		$package_id      = isset( $report['package_id'] ) ? (string) $report['package_id'] : basename( $staged_path );
		$timestamp       = gmdate( 'Ymd-His' );
		$evidence_path   = $use_requested_evidence_path ? $requested_evidence_path : '';

		if ( '' === $evidence_path && '' !== $pre_backup_path ) {
			$evidence_path = $pre_backup_path . DIRECTORY_SEPARATOR . 'PRE_RESTORE_BACKUP_EVIDENCE-' . $this->safe_slug( $package_id ) . '-' . str_replace( '-', '_', $scope ) . '-' . $timestamp . '.json';
		}

		$result = array(
			'schema_version'                 => 1,
			'generated_at'                   => gmdate( 'c' ),
			'command'                        => 'restore-apply',
			'status'                         => 'failed',
			'step'                           => 'create-pre-restore-backup',
			'scope'                          => $scope,
			'package_id'                     => $package_id,
			'staged_path'                    => $staged_path,
			'target_wordpress_path'          => $target_path,
			'restore_environment'            => $this->config_string( 'restore_environment' ),
			'pre_restore_backup_path'        => $pre_backup_path,
			'evidence_path'                  => $evidence_path,
			'database_export_path'           => '',
			'file_backup_path'               => '',
			'attempted'                      => true,
			'created'                        => false,
			'ok'                             => false,
			'failure_count'                  => 0,
			'checks'                         => array(),
			'destructive_actions_performed'  => false,
			'database_export_created'        => false,
			'file_backup_created'            => false,
			'evidence_written'               => false,
		);

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
			'restore_report_valid_json',
			! empty( $report ),
			'RESTORE_REPORT.json is valid JSON.'
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
			'' !== $pre_backup_path && ! $this->dangerous_restore_target_path( $pre_backup_path ) && $this->ensure_directory( $pre_backup_path ) && is_writable( $pre_backup_path ),
			'Pre-restore backup path exists, is safe, and is writable.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'evidence_path_under_pre_backup_path',
			'' !== $evidence_path && '' !== $pre_backup_path && $this->path_is_within_directory( $pre_backup_path, $evidence_path ),
			'Pre-restore backup evidence path is inside the configured pre-restore backup path.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'evidence_path_not_existing',
			'' !== $evidence_path && ! file_exists( $evidence_path ),
			'Pre-restore backup evidence path does not already exist.'
		);
		$this->add_restore_dry_run_check(
			$checks,
			'pre_restore_free_space',
			'' !== $pre_backup_path && is_dir( $pre_backup_path ) && $this->has_minimum_free_space( $pre_backup_path ),
			'Pre-restore backup path has the configured minimum free space.'
		);

		$result['checks']        = $checks;
		$result['failure_count'] = $this->restore_check_failure_count( $checks );
		if ( 0 !== (int) $result['failure_count'] ) {
			return $result;
		}

		if ( $this->restore_scope_includes_database( $scope ) ) {
			$database_export_path = $pre_backup_path . DIRECTORY_SEPARATOR . 'current-database-before-' . $this->safe_slug( $package_id ) . '-' . $timestamp . '.sql';
			$result['database_export_path'] = $database_export_path;
			$result['database_export_created'] = $this->export_database_from_path( $database_export_path, $target_path );
			$this->add_restore_dry_run_check(
				$result['checks'],
				'database_export_created',
				(bool) $result['database_export_created'],
				'Pre-restore database export was created.'
			);
		}

		if ( $this->restore_scope_includes_files( $scope ) ) {
			$file_backup_path = $pre_backup_path . DIRECTORY_SEPARATOR . 'current-files-before-' . $this->safe_slug( $package_id ) . '-' . $timestamp . '.tar.gz';
			$result['file_backup_path'] = $file_backup_path;
			$result['file_backup_created'] = $this->create_pre_restore_file_backup( $file_backup_path, $target_path );
			$this->add_restore_dry_run_check(
				$result['checks'],
				'file_backup_created',
				(bool) $result['file_backup_created'],
				'Pre-restore file backup was created.'
			);
		}

		$result['failure_count'] = $this->restore_check_failure_count( $result['checks'] );
		if ( 0 !== (int) $result['failure_count'] ) {
			return $result;
		}

		$evidence = array(
			'schema_version'        => 1,
			'evidence_type'         => 'pre_restore_backup',
			'generated_at'          => gmdate( 'c' ),
			'package_id'            => $package_id,
			'scope'                 => $scope,
			'target_wordpress_path' => $target_path,
		);
		if ( '' !== $result['database_export_path'] ) {
			$evidence['database_export_path'] = $result['database_export_path'];
		}
		if ( '' !== $result['file_backup_path'] ) {
			$evidence['file_backup_path'] = $result['file_backup_path'];
		}

		$result['evidence_written'] = $this->write_json_atomic( $evidence_path, $evidence );
		$this->add_restore_dry_run_check(
			$result['checks'],
			'evidence_written',
			(bool) $result['evidence_written'],
			'Pre-restore backup evidence JSON was written.'
		);
		$result['failure_count'] = $this->restore_check_failure_count( $result['checks'] );
		$result['created']       = 0 === (int) $result['failure_count'];
		$result['ok']            = $result['created'];
		$result['status']        = $result['created'] ? 'succeeded' : 'failed';

		return $result;
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
	 * @param string               $source_path Staged file root.
	 * @param string               $target_path Target WordPress path.
	 * @param array<string,string> $allowed_symlinks Exact symlinks permitted during production rollback.
	 * @return bool
	 */
	private function replace_target_files_from_staging( $source_path, $target_path, array $allowed_symlinks = array() ) {
		$target_path = $this->normalize_path( $target_path );

		if ( '' === $source_path || '' === $target_path || $target_path !== $this->wordpress_path() || $this->dangerous_restore_target_path( $target_path ) ) {
			return false;
		}

		if ( ! is_dir( $source_path ) || ! is_readable( $source_path ) || ! $this->directory_has_entries( $source_path ) || ! is_dir( $target_path ) || ! is_writable( $target_path ) ) {
			return false;
		}

		return $this->remove_restore_target_contents( $target_path ) && $this->copy_restore_directory_contents( $source_path, $target_path, $allowed_symlinks );
	}

	/**
	 * Reports pre-restore symlinks that a file apply will not recreate.
	 *
	 * @param string $file_root_path Staged file root path.
	 * @param string $pre_backup_evidence_path Pre-restore evidence path.
	 * @return array<int,string>
	 */
	private function restore_apply_missing_symlink_warnings( $file_root_path, $pre_backup_evidence_path ) {
		$evidence         = $this->read_restore_report( $pre_backup_evidence_path );
		$file_backup_path = isset( $evidence['file_backup_path'] ) ? $this->normalize_path( (string) $evidence['file_backup_path'] ) : '';

		if ( '' === $file_backup_path || ! is_file( $file_backup_path ) || ! is_readable( $file_backup_path ) ) {
			return array();
		}

		$result = $this->run_shell_command( 'tar -tvzf ' . escapeshellarg( $file_backup_path ) );
		if ( 0 !== (int) $result['exit_code'] ) {
			return array( 'Could not inspect pre-restore file backup symlink entries.' );
		}

		$warnings = array();
		foreach ( $result['output'] as $line ) {
			$entry = $this->parse_tar_symlink_entry( $line );
			if ( empty( $entry ) || 0 !== strpos( $entry['path'], 'htdocs/' ) ) {
				continue;
			}

			$relative_path = substr( $entry['path'], strlen( 'htdocs/' ) );
			if ( ! $this->restore_report_relative_path_is_safe( $relative_path ) ) {
				continue;
			}

			$staged_path = $file_root_path . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );
			if ( ! file_exists( $staged_path ) && ! is_link( $staged_path ) ) {
				$warnings[] = $entry['path'] . ' -> ' . $entry['target'];
			}
		}

		return $warnings;
	}

	/**
	 * Adds known post-restore drop-in review items to an apply result.
	 *
	 * @param array<string,mixed> $result Apply result.
	 * @param string              $target_wordpress_path Target WordPress path.
	 * @param string              $pre_backup_evidence_path Pre-restore evidence path.
	 * @return array<string,mixed>
	 */
	private function add_post_restore_manual_review_items( array $result, $target_wordpress_path, $pre_backup_evidence_path ) {
		$items = $this->restore_apply_post_restore_manual_review_items( $target_wordpress_path, $pre_backup_evidence_path );
		if ( empty( $items ) ) {
			return $result;
		}

		$result['post_restore_manual_review_items']    = $items;
		$result['post_restore_manual_review_required'] = true;
		foreach ( $items as $item ) {
			if ( ! empty( $item['cleanup_required'] ) ) {
				$result['post_restore_cleanup_required'] = true;
				break;
			}
		}
		$result['manual_recovery_notes'][] = 'Post-restore manual review is required for known drop-in paths.';

		return $result;
	}

	/**
	 * Builds post-restore review items for known symlinked drop-ins.
	 *
	 * @param string $target_wordpress_path Target WordPress path.
	 * @param string $pre_backup_evidence_path Pre-restore evidence path.
	 * @return array<int,array<string,mixed>>
	 */
	private function restore_apply_post_restore_manual_review_items( $target_wordpress_path, $pre_backup_evidence_path ) {
		$target_wordpress_path = $this->normalize_path( $target_wordpress_path );
		$known_drop_ins        = $this->known_restore_drop_in_relative_paths();
		$entries               = $this->restore_apply_pre_restore_symlink_entries( $pre_backup_evidence_path );
		$items                 = array();

		foreach ( $entries as $entry ) {
			if ( empty( $entry['path'] ) || 0 !== strpos( $entry['path'], 'htdocs/' ) ) {
				continue;
			}

			$relative_path = substr( $entry['path'], strlen( 'htdocs/' ) );
			if ( ! isset( $known_drop_ins[ $relative_path ] ) ) {
				continue;
			}

			$path        = $target_wordpress_path . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );
			$exists      = file_exists( $path ) || is_link( $path );
			$is_link     = is_link( $path );
			$link_target = $is_link ? (string) readlink( $path ) : '';
			$target_path = '' !== $link_target && 0 === strpos( $link_target, '/' )
				? $link_target
				: ( '' !== $link_target ? dirname( $path ) . DIRECTORY_SEPARATOR . $link_target : '' );
			$target_exists = '' !== $target_path && file_exists( $target_path );

			if ( $exists && ( ! $is_link || $target_exists ) ) {
				continue;
			}

			$definition = $known_drop_ins[ $relative_path ];
			$items[]    = array(
				'type'                         => $is_link ? 'known_drop_in_broken_symlink' : 'known_drop_in_missing_after_restore',
				'path'                         => $relative_path,
				'post_restore_path'            => $path,
				'post_restore_exists'          => $exists,
				'post_restore_is_symlink'      => $is_link,
				'post_restore_symlink_target'  => $link_target,
				'post_restore_target_exists'   => $target_exists,
				'previous_symlink_target'      => $entry['target'],
				'owner_hint'                   => $this->restore_drop_in_owner_hint( $relative_path, $entry['target'], (string) $definition['owner_hint'] ),
				'cleanup_required'             => $is_link && ! $target_exists,
				'recommended_action'           => (string) $definition['recommended_action'],
			);
		}

		return $items;
	}

	/**
	 * Returns known WordPress drop-in paths that need manual post-restore review.
	 *
	 * @return array<string,array<string,string>>
	 */
	private function known_restore_drop_in_relative_paths() {
		return array(
			'wp-content/db.php' => array(
				'owner_hint'         => 'Database drop-in. Query Monitor can own this path when it is enabled.',
				'recommended_action' => 'Inspect the owning plugin after restore. For Query Monitor, regenerate the drop-in through the plugin workflow if needed; remove only broken stale links after operator review.',
			),
		);
	}

	/**
	 * Returns an owner hint for a known drop-in.
	 *
	 * @param string $relative_path Relative path.
	 * @param string $previous_target Previous symlink target.
	 * @param string $fallback Fallback hint.
	 * @return string
	 */
	private function restore_drop_in_owner_hint( $relative_path, $previous_target, $fallback ) {
		if ( 'wp-content/db.php' === $relative_path && false !== strpos( strtolower( $previous_target ), 'query-monitor' ) ) {
			return 'Query Monitor likely owns this database drop-in.';
		}

		return $fallback;
	}

	/**
	 * Returns symlink entries from the pre-restore file backup.
	 *
	 * @param string $pre_backup_evidence_path Pre-restore evidence path.
	 * @return array<int,array{path:string,target:string}>
	 */
	private function restore_apply_pre_restore_symlink_entries( $pre_backup_evidence_path ) {
		$evidence         = $this->read_restore_report( $pre_backup_evidence_path );
		$file_backup_path = isset( $evidence['file_backup_path'] ) ? $this->normalize_path( (string) $evidence['file_backup_path'] ) : '';

		if ( '' === $file_backup_path || ! is_file( $file_backup_path ) || ! is_readable( $file_backup_path ) ) {
			return array();
		}

		$result = $this->run_shell_command( 'tar -tvzf ' . escapeshellarg( $file_backup_path ) );
		if ( 0 !== (int) $result['exit_code'] ) {
			return array();
		}

		$entries = array();
		foreach ( $result['output'] as $line ) {
			$entry = $this->parse_tar_symlink_entry( $line );
			if ( ! empty( $entry ) ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Parses a tar verbose symlink entry.
	 *
	 * @param string $line Tar verbose output line.
	 * @return array{path:string,target:string}|array<string,string>
	 */
	private function parse_tar_symlink_entry( $line ) {
		$line = trim( $line );
		if ( '' === $line || 'l' !== $line[0] || false === strpos( $line, ' -> ' ) ) {
			return array();
		}

		list( $left, $target ) = explode( ' -> ', $line, 2 );
		if ( 1 !== preg_match( '/^\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+(.+)$/', trim( $left ), $matches ) ) {
			return array();
		}

		return array(
			'path'   => trim( $matches[1] ),
			'target' => trim( $target ),
		);
	}

	/**
	 * Returns whether a restore report nested relative path is safe.
	 *
	 * @param string $value Report value.
	 * @return bool
	 */
	private function restore_report_relative_path_is_safe( $value ) {
		$value = trim( str_replace( '\\', '/', $value ) );
		if ( '' === $value || '/' === $value[0] || false !== strpos( $value, ':' ) ) {
			return false;
		}

		foreach ( explode( '/', $value ) as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				return false;
			}
		}

		return true;
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
	 * @param string               $source_path Source directory.
	 * @param string               $target_path Target directory.
	 * @param array<string,string> $allowed_symlinks Exact symlinks permitted during production rollback.
	 * @return bool
	 */
	private function copy_restore_directory_contents( $source_path, $target_path, array $allowed_symlinks = array() ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source_path, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$relative_path = str_replace( DIRECTORY_SEPARATOR, '/', substr( $item->getPathname(), strlen( $source_path ) + 1 ) );
			$target_item   = $target_path . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );
			if ( $item->isLink() ) {
				$link_target = readlink( $item->getPathname() );
				if ( ! $this->restore_report_relative_path_is_safe( $relative_path ) || false === $link_target || ! isset( $allowed_symlinks[ $relative_path ] ) || (string) $link_target !== $allowed_symlinks[ $relative_path ] || file_exists( $target_item ) || is_link( $target_item ) || ! symlink( (string) $link_target, $target_item ) ) {
					return false;
				}
				continue;
			}

			if ( $item->isDir() ) {
				if ( ! is_dir( $target_item ) && ! mkdir( $target_item, 0755, true ) ) {
					return false;
				}
				continue;
			}

			if ( ! $this->copy_restore_file( $item->getPathname(), $target_item ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Copies one staged restore file.
	 *
	 * Kept as a narrow override boundary for deterministic filesystem failure
	 * testing without exposing a runtime failure switch.
	 *
	 * @param string $source Source file.
	 * @param string $target Target file.
	 * @return bool
	 */
	protected function copy_restore_file( $source, $target ) {
		return copy( $source, $target );
	}

	/**
	 * Returns every symlink in a private rollback tree after safe path validation.
	 *
	 * @param string $source_path Extracted rollback file root.
	 * @return array<string,string>|false
	 */
	private function production_symlink_map( $source_path ) {
		$symlinks = array();
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $source_path, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $iterator as $item ) {
				if ( ! $item->isLink() ) {
					continue;
				}

				$relative_path = str_replace( DIRECTORY_SEPARATOR, '/', substr( $item->getPathname(), strlen( $source_path ) + 1 ) );
				$link_target   = readlink( $item->getPathname() );
				if ( ! $this->restore_report_relative_path_is_safe( $relative_path ) || false === $link_target || '' === trim( (string) $link_target ) || false !== strpos( (string) $link_target, "\0" ) ) {
					return false;
				}
				$symlinks[ $relative_path ] = (string) $link_target;
			}
		} catch ( UnexpectedValueException $exception ) {
			return false;
		}
		ksort( $symlinks );

		return $symlinks;
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
	 * Returns whether a path resolves within a canonical directory.
	 *
	 * @param string $base Base directory.
	 * @param string $path Candidate path.
	 * @return bool
	 */
	private function path_is_within_directory_canonical( $base, $path ) {
		$base = $this->canonical_path( $base );
		$path = $this->canonical_path( $path );

		return '' !== $base && '' !== $path && $this->path_is_within_directory( $base, $path );
	}

	/**
	 * Returns whether a path resolves outside a canonical directory.
	 *
	 * @param string $base Base directory.
	 * @param string $path Candidate path.
	 * @return bool
	 */
	private function path_is_outside_directory_canonical( $base, $path ) {
		$base = $this->canonical_path( $base );
		$path = $this->canonical_path( $path );

		return '' !== $base && '' !== $path && ! $this->path_is_within_directory( $base, $path );
	}

	/**
	 * Returns whether two paths resolve to different canonical locations.
	 *
	 * @param string $left Left path.
	 * @param string $right Right path.
	 * @return bool
	 */
	private function canonical_paths_differ( $left, $right ) {
		$left  = $this->canonical_path( $left );
		$right = $this->canonical_path( $right );

		return '' !== $left && '' !== $right && $left !== $right;
	}

	/**
	 * Resolves an existing path or a path whose immediate parent exists.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function canonical_path( $path ) {
		$resolved = realpath( $path );
		if ( false !== $resolved ) {
			return $this->path_comparison_value( $resolved );
		}

		$parent = realpath( dirname( $path ) );
		if ( false === $parent ) {
			return '';
		}

		return $this->path_comparison_value( $parent . DIRECTORY_SEPARATOR . basename( $path ) );
	}

	/**
	 * Normalizes a path for platform-aware comparisons.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function path_comparison_value( $path ) {
		$path = $this->normalize_path( $path );

		return 'Windows' === PHP_OS_FAMILY ? strtolower( $path ) : $path;
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
	 * Counts failed restore checks.
	 *
	 * @param array<int,array<string,mixed>> $checks Checks.
	 * @return int
	 */
	private function restore_check_failure_count( array $checks ) {
		$failure_count = 0;
		foreach ( $checks as $check ) {
			if ( empty( $check['passed'] ) ) {
				++$failure_count;
			}
		}

		return $failure_count;
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
		if ( isset( $result['file_restore_missing_symlink_count'] ) ) {
			$this->line( 'Missing symlink/drop-in warnings: ' . (int) $result['file_restore_missing_symlink_count'] );
			$this->line( 'File restore manual review required: ' . ( ! empty( $result['file_restore_manual_review_required'] ) ? 'yes' : 'no' ) );
		}
		if ( isset( $result['post_restore_manual_review_items'] ) ) {
			$this->line( 'Post-restore manual review items: ' . count( $result['post_restore_manual_review_items'] ) );
			$this->line( 'Post-restore cleanup required: ' . ( ! empty( $result['post_restore_cleanup_required'] ) ? 'yes' : 'no' ) );
		}
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
		$this->line( '- Combined files-and-database restore runs file replacement before database import.' );
		$this->line( '- Production restore still requires a separate approved workflow.' );
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
		curl_setopt( $curl, CURLOPT_TIMEOUT, $this->drime_download_timeout_seconds() );
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
	 * Returns the bounded timeout for one Drime package-file download.
	 *
	 * @return int
	 */
	private function drime_download_timeout_seconds() {
		$timeout = isset( $this->config['drime_download_timeout_seconds'] ) ? (int) $this->config['drime_download_timeout_seconds'] : 21600;

		return max( 300, min( 86400, $timeout ) );
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
	return 'Usage: php alynt-backup-runner.php <health|run|list|cleanup-preview|cleanup|verify|inspect|fetch|stage-restore|restore-production-preflight|restore-production-create-pre-backup|restore-production-apply|restore-production-rollback|restore-dry-run|restore-apply> '
		. '--config=/path/to/config.json [--format=json] [--package=/path/to/archive.tar.gz] '
		. '[--package-id=package-id --folder-hash=hash --download-path=/path] '
		. '[--restore-path=/path/to/restores] [--staged-path=/path/to/staged/package] [--scope=files-and-database] [--target-site=example.com] '
		. '[--pre-restore-evidence=/path/to/evidence.json] [--create-pre-restore-backup=1] '
		. '[--apply-report=/path/to/report.json] [--confirm-site=example.com] [--write-report=1] [--older-than-days=14] '
		. '[--confirm=delete-local-artifacts|restore-staging-site|create-production-pre-restore-backup|restore-production-site|rollback-production-site]';
}

if ( defined( 'ALYNT_SERVER_BACKUP_RUNNER_LIBRARY_ONLY' ) && ALYNT_SERVER_BACKUP_RUNNER_LIBRARY_ONLY ) {
	return;
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
