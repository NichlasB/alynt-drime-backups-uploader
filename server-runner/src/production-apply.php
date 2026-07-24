<?php
/**
 * Server runner production pre-restore backup creation and apply behavior.
 *
 * @package Alynt_Drime_Backups_Uploader
 */
trait Alynt_Server_Backup_Runner_Production_Apply {
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

}
