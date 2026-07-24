<?php
/**
 * Server runner staging restore dry-run, apply, evidence, and reporting.
 *
 * @package Alynt_Drime_Backups_Uploader
 */
trait Alynt_Server_Backup_Runner_Staging_Restore {
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

}
