<?php
/**
 * Server runner production rollback validation, recovery, cleanup, and reporting.
 *
 * @package Alynt_Drime_Backups_Uploader
 */
trait Alynt_Server_Backup_Runner_Production_Rollback {
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

		// A rollback retry can occur within the same second as a retained failed attempt.
		$staging_path = $restore_path . DIRECTORY_SEPARATOR . '.rollback-' . $this->safe_slug( $package_id ) . '-' . gmdate( 'Ymd-His' ) . '-' . substr( hash( 'sha256', uniqid( '', true ) ), 0, 12 );
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

}
