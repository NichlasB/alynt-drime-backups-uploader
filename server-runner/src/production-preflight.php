<?php
/**
 * Server runner read-only production restore preflight and identity reporting.
 *
 * @package Alynt_Drime_Backups_Uploader
 */
trait Alynt_Server_Backup_Runner_Production_Preflight {
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

}
