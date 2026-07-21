<?php
/**
 * Shared production-simulation server runner fixture.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

/**
 * Builds an enrolled production-simulation target for runner CLI tests.
 */
trait Alynt_Drime_Backups_Uploader_Server_Runner_Production_Restore_Fixture {
	/**
	 * Creates one complete production-simulation fixture.
	 *
	 * @param bool $restore_enabled Whether production apply is enabled.
	 * @return array<string,string>
	 */
	protected function create_production_restore_fixture( $restore_enabled = false ) {
		$uuid          = '12345678-1234-4234-9234-123456789abc';
		$site_root     = $this->make_directory( 'site-root' );
		$target_path   = $site_root . DIRECTORY_SEPARATOR . 'htdocs';
		$restore_path  = $this->make_directory( 'production-restores' );
		$staged_path   = $restore_path . DIRECTORY_SEPARATOR . 'example-com-20260720-010000';
		$reports_path  = $this->make_directory( 'production-reports' );
		$pre_backups   = $this->make_directory( 'production-pre-backups' );
		$outbox        = $this->make_directory( 'outbox' );
		$native        = $this->root . DIRECTORY_SEPARATOR . 'native-backup-evidence.json';
		$wp_cli_log    = $this->root . DIRECTORY_SEPARATOR . 'wp-cli.log';
		$maintenance   = $target_path . DIRECTORY_SEPARATOR . '.maintenance';
		$maintenance_activation_count = $this->root . DIRECTORY_SEPARATOR . 'maintenance-activation-count.state';
		$post_write_state = $this->root . DIRECTORY_SEPARATOR . 'post-write.state';
		$maintenance_tamper_trigger = $this->root . DIRECTORY_SEPARATOR . 'maintenance-tamper.trigger';
		$maintenance_activation_failure = $this->root . DIRECTORY_SEPARATOR . 'maintenance-activation-failure.trigger';
		$maintenance_reactivation_failure = $this->root . DIRECTORY_SEPARATOR . 'maintenance-reactivation-failure.trigger';
		$maintenance_deactivation_failure = $this->root . DIRECTORY_SEPARATOR . 'maintenance-deactivation-failure.trigger';
		$database_import_failure = $this->root . DIRECTORY_SEPARATOR . 'database-import-failure.trigger';
		$post_write_identity_failure = $this->root . DIRECTORY_SEPARATOR . 'post-write-identity-failure.trigger';
		$post_write_drop_in_failure = $this->root . DIRECTORY_SEPARATOR . 'post-write-drop-in-failure.trigger';
		$maintenance_tamper_path    = $staged_path . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'index.php';
		$post_write_drop_in_path = $target_path . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'object-cache.php';
		$wp_cli        = $this->create_production_restore_fake_wp_cli( $uuid, $wp_cli_log, $maintenance, $maintenance_activation_count, $post_write_state, $maintenance_tamper_trigger, $maintenance_tamper_path, $maintenance_activation_failure, $maintenance_reactivation_failure, $maintenance_deactivation_failure, $database_import_failure, $post_write_identity_failure, $post_write_drop_in_failure, $post_write_drop_in_path );

		mkdir( $target_path );
		mkdir( $target_path . DIRECTORY_SEPARATOR . 'wp-content' );
		file_put_contents( $target_path . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "before";' );
		file_put_contents( $target_path . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'object-cache.php', '<?php // cache.' );
		file_put_contents( $site_root . DIRECTORY_SEPARATOR . 'wp-config.php', '<?php // external config.' );
		mkdir( $staged_path );
		mkdir( $staged_path . DIRECTORY_SEPARATOR . 'htdocs' );
		mkdir( $staged_path . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'wp-content' );
		file_put_contents( $staged_path . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "staged";' );
		file_put_contents( $staged_path . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'object-cache.php', '<?php // cache.' );
		file_put_contents( $staged_path . DIRECTORY_SEPARATOR . 'database.sql', '-- staged database' );
		file_put_contents(
			$staged_path . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json',
			json_encode(
				array(
					'status'                 => 'staged_for_inspection',
					'package_id'             => 'example-com-20260720-010000',
					'site_id'                => 'example.com',
					'site_uuid'              => $uuid,
					'site_url'               => 'https://example.com',
					'created_at'             => '2026-07-20T01:00:00+00:00',
					'producer'               => 'alynt_server_runner',
					'producer_version'       => '0.3.0',
					'archive_format'         => 'tar.gz',
					'file_root'              => 'htdocs',
					'database_dump'          => 'database.sql',
					'staged_integrity'       => $this->staged_integrity_fixture( $staged_path . DIRECTORY_SEPARATOR . 'htdocs', $staged_path . DIRECTORY_SEPARATOR . 'database.sql' ),
					'package_verified'       => true,
					'archive_members_safe'   => true,
					'database_imported'      => false,
					'live_files_overwritten' => false,
				)
			)
		);
		file_put_contents(
			$native,
			json_encode(
				array(
					'evidence_type'    => 'gridpane_native_backup',
					'status'           => 'completed',
					'target_site'      => 'example.com',
					'target_site_uuid' => $uuid,
					'revision_id'      => 'test-revision',
					'completed_at'     => gmdate( 'c', time() - 60 ),
				)
			)
		);

		$config = $this->write_config(
			$outbox,
			array(
				'wordpress_path'                           => $target_path,
				'wp_cli_path'                              => $wp_cli,
				'production_restore_enabled'               => (bool) $restore_enabled,
				'production_rollback_enabled'              => true,
				'production_restore_environment'           => 'production-simulation',
				'production_target_site_url'               => 'https://example.com',
				'production_target_site_uuid'              => $uuid,
				'production_target_site_root'              => $site_root,
				'production_target_wordpress_path'         => $target_path,
				'production_target_wp_config_path'         => $site_root . DIRECTORY_SEPARATOR . 'wp-config.php',
				'production_restore_path'                  => $restore_path,
				'production_pre_backup_path'               => $pre_backups,
				'production_reports_path'                  => $reports_path,
				'production_native_backup_required'        => true,
				'production_native_backup_evidence_path'   => $native,
				'production_native_backup_max_age_seconds' => 86400,
				'production_disk_safety_margin_bytes'      => 0,
				'production_maintenance_strategy'          => 'wp-maintenance-mode',
				'production_cron_control_reviewed'         => true,
				'production_external_writers_reviewed'     => true,
				'production_cache_purge_reviewed'          => true,
				'production_expected_active_plugins'       => array( 'alynt-drime-backups-uploader' ),
				'production_expected_active_theme'         => 'example-theme',
				'production_expected_drop_ins'             => array( 'wp-content/object-cache.php' ),
				'production_symlink_inventory_reviewed'    => true,
				'production_expected_symlink_paths'        => array(),
				'production_expected_symlink_targets'      => array(),
				'minimum_free_space_bytes'                 => 0,
			)
		);

		return array(
			'config'            => $config,
			'target_path'       => $target_path,
			'restore_path'      => $restore_path,
			'staged_path'       => $staged_path,
			'reports_path'      => $reports_path,
			'uuid'              => $uuid,
			'wp_cli_log'        => $wp_cli_log,
			'wp_cli'            => $wp_cli,
			'maintenance_state' => $maintenance,
			'maintenance_activation_count' => $maintenance_activation_count,
			'maintenance_tamper_trigger' => $maintenance_tamper_trigger,
			'maintenance_activation_failure' => $maintenance_activation_failure,
			'maintenance_reactivation_failure' => $maintenance_reactivation_failure,
			'maintenance_deactivation_failure' => $maintenance_deactivation_failure,
			'database_import_failure' => $database_import_failure,
			'post_write_identity_failure' => $post_write_identity_failure,
			'post_write_drop_in_failure' => $post_write_drop_in_failure,
		);
	}

	/**
	 * Creates production pre-restore evidence for one scope.
	 *
	 * @param array<string,string> $fixture Fixture.
	 * @param string               $scope Scope.
	 * @return string
	 */
	protected function create_production_restore_evidence( array $fixture, $scope ) {
		$data = json_decode( file_get_contents( $fixture['config'] ), true );
		$data['production_restore_enabled'] = false;
		file_put_contents( $fixture['config'], json_encode( $data ) );
		$result = $this->run_runner(
			'restore-production-create-pre-backup',
			$fixture['config'],
			array(
				'--staged-path=' . escapeshellarg( $fixture['staged_path'] ),
				'--scope=' . escapeshellarg( $scope ),
				'--target-site=example.com',
				'--confirm=create-production-pre-restore-backup',
				'--format=json',
			)
		);
		$this->assertSame( 0, $result['exit_code'], implode( "\n", array_merge( $result['error'], $result['output'] ) ) );
		$backup = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'succeeded', $backup['status'] );
		$data['production_restore_enabled'] = true;
		file_put_contents( $fixture['config'], json_encode( $data ) );

		return $backup['pre_restore_evidence_path'];
	}

	/**
	 * Creates a deterministic WP-CLI with persistent maintenance state.
	 *
	 * @param string $uuid Site UUID.
	 * @param string $log_path Log path.
	 * @param string $state_path Maintenance state path.
	 * @param string $activation_count_path Persistent activation-count state.
	 * @param string $post_write_state Post-write state marker.
	 * @param string $tamper_trigger Optional maintenance-activation tamper trigger.
	 * @param string $tamper_path Optional staged file to alter when triggered.
	 * @param string $activation_failure Initial maintenance failure trigger.
	 * @param string $reactivation_failure Post-copy maintenance failure trigger.
	 * @param string $deactivation_failure Maintenance deactivation failure trigger.
	 * @param string $database_import_failure Database import failure trigger.
	 * @param string $post_write_identity_failure Post-write identity failure trigger.
	 * @param string $post_write_drop_in_failure Post-write drop-in failure trigger.
	 * @param string $post_write_drop_in_path Expected drop-in to remove.
	 * @return string
	 */
	private function create_production_restore_fake_wp_cli( $uuid, $log_path, $state_path, $activation_count_path, $post_write_state, $tamper_trigger, $tamper_path, $activation_failure, $reactivation_failure, $deactivation_failure, $database_import_failure, $post_write_identity_failure, $post_write_drop_in_failure, $post_write_drop_in_path ) {
		$path = $this->root . DIRECTORY_SEPARATOR . ( '\\' === DIRECTORY_SEPARATOR ? 'production-fake-wp.bat' : 'production-fake-wp' );
		if ( '\\' === DIRECTORY_SEPARATOR ) {
			$commands = array(
				'@echo off',
				'echo %* >> "' . str_replace( '"', '', $log_path ) . '"',
				'set "ARGS=%*"',
				'echo %ARGS% | findstr /C:"maintenance-mode activate" >nul && goto alynt_maintenance_activate',
				'echo %ARGS% | findstr /C:"maintenance-mode deactivate" >nul && goto alynt_maintenance_deactivate',
				'echo %ARGS% | findstr /C:"maintenance-mode status" >nul && (if exist "' . $state_path . '" (echo Maintenance mode is active.& exit /b 0) else (echo Maintenance mode is not active.& exit /b 1))',
				'echo %ARGS% | findstr /C:"option get home" >nul && goto alynt_option_home',
				'echo %ARGS% | findstr /C:"option get siteurl" >nul && (echo https://example.com& exit /b 0)',
				'echo %ARGS% | findstr /C:"option pluck alynt_drime_backups_settings site_uuid" >nul && (echo ' . $uuid . '& exit /b 0)',
				'echo %ARGS% | findstr /C:"config get DB_NAME" >nul && (echo example_database& exit /b 0)',
				'echo %ARGS% | findstr /C:"db prefix" >nul && (echo wp_& exit /b 0)',
				'echo %ARGS% | findstr /C:"db size --size_format=b" >nul && (echo 1024& exit /b 0)',
				'echo %ARGS% | findstr /C:"core version" >nul && (echo 7.0.1& exit /b 0)',
				'echo %ARGS% | findstr /C:"cli version" >nul && (echo WP-CLI 2.12.0& exit /b 0)',
				'echo %ARGS% | findstr /C:"plugin list --status=active --field=name" >nul && (echo alynt-drime-backups-uploader& exit /b 0)',
				'echo %ARGS% | findstr /C:"theme list --status=active --field=name" >nul && (echo example-theme& exit /b 0)',
				'echo %ARGS% | findstr /C:"db export" >nul && (set "ARG="& set "PREV="& goto alynt_arg_loop)',
				'echo %ARGS% | findstr /C:"db import" >nul && (if exist "' . $database_import_failure . '" (exit /b 1) else (exit /b 0))',
				'exit /b 1',
				':alynt_maintenance_activate',
				'if exist "' . $activation_failure . '" exit /b 1',
				'if exist "' . $activation_count_path . '" echo post-write> "' . $post_write_state . '"',
				'if exist "' . $reactivation_failure . '" if exist "' . $activation_count_path . '" exit /b 1',
				'if exist "' . $post_write_drop_in_failure . '" if exist "' . $activation_count_path . '" del /q "' . $post_write_drop_in_path . '" 2>nul',
				'echo activated> "' . $activation_count_path . '"',
				'if exist "' . $tamper_trigger . '" echo tampered>> "' . $tamper_path . '"',
				'echo active> "' . $state_path . '"',
				'echo Success',
				'exit /b 0',
				':alynt_maintenance_deactivate',
				'if exist "' . $deactivation_failure . '" exit /b 1',
				'del /q "' . $state_path . '" 2>nul',
				'echo Success',
				'exit /b 0',
				':alynt_option_home',
				'if exist "' . $post_write_identity_failure . '" if exist "' . $post_write_state . '" exit /b 1',
				'echo https://example.com',
				'exit /b 0',
				':alynt_arg_loop',
				'if "%~1"=="" goto alynt_arg_done',
				'set "PREV=%ARG%"',
				'set "ARG=%~1"',
				'shift',
				'goto alynt_arg_loop',
				':alynt_arg_done',
				'if /I "%ARG%"=="--quiet" set "ARG=%PREV%"',
				'echo -- before db> "%ARG%"',
				'exit /b 0',
			);
			file_put_contents( $path, implode( "\r\n", $commands ) . "\r\n" );

			return $path;
		}

		$script = "#!/bin/sh\nprintf '%s\\n' \"\$*\" >> " . escapeshellarg( $log_path ) . "\nargs=\"\$*\"\n"
			. "case \"\$args\" in\n"
			. "*\"maintenance-mode activate\"*) if [ -f " . escapeshellarg( $activation_failure ) . " ]; then exit 1; fi; if [ -f " . escapeshellarg( $activation_count_path ) . " ]; then echo post-write > " . escapeshellarg( $post_write_state ) . "; fi; if [ -f " . escapeshellarg( $reactivation_failure ) . " ] && [ -f " . escapeshellarg( $activation_count_path ) . " ]; then exit 1; fi; if [ -f " . escapeshellarg( $post_write_drop_in_failure ) . " ] && [ -f " . escapeshellarg( $activation_count_path ) . " ]; then rm -f " . escapeshellarg( $post_write_drop_in_path ) . "; fi; echo activated > " . escapeshellarg( $activation_count_path ) . "; if [ -f " . escapeshellarg( $tamper_trigger ) . " ]; then printf '\\ntampered' >> " . escapeshellarg( $tamper_path ) . "; fi; echo active > " . escapeshellarg( $state_path ) . ";;\n"
			. "*\"maintenance-mode deactivate\"*) if [ -f " . escapeshellarg( $deactivation_failure ) . " ]; then exit 1; fi; rm -f " . escapeshellarg( $state_path ) . ";;\n"
			. "*\"maintenance-mode status\"*) if [ -f " . escapeshellarg( $state_path ) . " ]; then echo 'Maintenance mode is active.'; else echo 'Maintenance mode is not active.'; exit 1; fi;;\n"
			. "*\"option get home\"*) if [ -f " . escapeshellarg( $post_write_identity_failure ) . " ] && [ -f " . escapeshellarg( $post_write_state ) . " ]; then exit 1; fi; echo https://example.com;;\n*\"option get siteurl\"*) echo https://example.com;;\n"
			. "*\"option pluck alynt_drime_backups_settings site_uuid\"*) echo '" . $uuid . "';;\n"
			. "*\"config get DB_NAME\"*) echo example_database;;\n*\"db prefix\"*) echo wp_;;\n*\"db size --size_format=b\"*) echo 1024;;\n"
			. "*\"core version\"*) echo 7.0.1;;\n*\"cli version\"*) echo 'WP-CLI 2.12.0';;\n"
			. "*\"plugin list --status=active --field=name\"*) echo alynt-drime-backups-uploader;;\n*\"theme list --status=active --field=name\"*) echo example-theme;;\n"
			. "*\"db export\"*) for arg in \"\$@\"; do case \"\$arg\" in *.sql) echo '-- before db' > \"\$arg\";; esac; done;;\n"
			. "*\"db import\"*) if [ -f " . escapeshellarg( $database_import_failure ) . " ]; then exit 1; fi;;\n*) exit 1;;\nesac\nexit 0\n";
		file_put_contents( $path, $script );
		chmod( $path, 0755 );

		return $path;
	}
}
