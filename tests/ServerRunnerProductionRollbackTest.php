<?php
/**
 * Server runner production rollback tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

/**
 * Covers production-simulation recovery evidence and explicit rollback gates.
 */
class ServerRunnerProductionRollbackTest extends Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case {
	/**
	 * Recovery evidence must restore a controlled changed state only after every gate passes.
	 *
	 * @return void
	 */
	public function test_production_rollback_restores_verified_files_and_database() {
		if ( ! $this->tar_available() ) {
			$this->markTestSkipped( 'The tar command is required for production rollback coverage.' );
		}

		$fixture  = $this->create_fixture();
		$evidence = $this->create_production_pre_backup( $fixture );

		file_put_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "failed";' );
		file_put_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'failed-state.txt', 'failed state' );
		$apply_report = $this->write_apply_report( $fixture, $evidence );

		$result = $this->run_runner(
			'restore-production-rollback',
			$fixture['config'],
			array(
				'--apply-report=' . escapeshellarg( $apply_report ),
				'--target-site=example.com',
				'--confirm=rollback-production-site',
				'--confirm-site=example.com',
				'--format=json',
			)
		);

		$this->assertSame( 0, $result['exit_code'], implode( "\n", array_merge( $result['error'], $result['output'] ) ) );
		$rollback = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'restore-production-rollback', $rollback['command'] );
		$this->assertSame( 'succeeded', $rollback['status'] );
		$this->assertTrue( $rollback['confirmation_site_accepted'] );
		$this->assertTrue( $rollback['file_rollback_succeeded'] );
		$this->assertTrue( $rollback['database_rollback_succeeded'] );
		$this->assertTrue( $rollback['destructive_actions_performed'] );
		$this->assertTrue( $rollback['rollback_report_written'] );
		$this->assertFileExists( $rollback['rollback_report_path'] );

		$this->assertSame( '<?php echo "before";', file_get_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );
		$this->assertFileDoesNotExist( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'failed-state.txt' );
		$this->assertStringContainsString( 'db import', (string) file_get_contents( $fixture['wp_cli_log'] ) );
	}

	/**
	 * A changed recovery artifact must refuse rollback before target files change.
	 *
	 * @return void
	 */
	public function test_production_rollback_refuses_tampered_evidence() {
		if ( ! $this->tar_available() ) {
			$this->markTestSkipped( 'The tar command is required for production rollback coverage.' );
		}

		$fixture  = $this->create_fixture();
		$evidence = $this->create_production_pre_backup( $fixture );
		$record   = json_decode( (string) file_get_contents( $evidence ), true );
		file_put_contents( $record['file_backup_path'], 'tampered', FILE_APPEND );
		file_put_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "failed";' );
		$apply_report = $this->write_apply_report( $fixture, $evidence );

		$result = $this->run_runner(
			'restore-production-rollback',
			$fixture['config'],
			array(
				'--apply-report=' . escapeshellarg( $apply_report ),
				'--target-site=example.com',
				'--confirm=rollback-production-site',
				'--confirm-site=example.com',
				'--format=json',
			)
		);

		$this->assertSame( 1, $result['exit_code'] );
		$rollback = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'failed', $rollback['status'] );
		$this->assertSame( 'rollback-preflight', $rollback['failure_step'] );
		$this->assertFalse( $rollback['destructive_actions_performed'] );
		$this->assertFalse( $this->check_passed( $rollback, 'production_pre_restore_file_backup_sha256_valid' ) );
		$this->assertSame( '<?php echo "failed";', file_get_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );
	}

	/**
	 * Rollback remains inert unless its separate private configuration flag is enabled.
	 *
	 * @return void
	 */
	public function test_production_rollback_refuses_when_private_flag_is_disabled() {
		if ( ! $this->tar_available() ) {
			$this->markTestSkipped( 'The tar command is required for production rollback coverage.' );
		}

		$fixture  = $this->create_fixture();
		$evidence = $this->create_production_pre_backup( $fixture );
		$config   = json_decode( (string) file_get_contents( $fixture['config'] ), true );
		$config['production_rollback_enabled'] = false;
		file_put_contents( $fixture['config'], json_encode( $config ) );
		file_put_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "failed";' );
		$apply_report = $this->write_apply_report( $fixture, $evidence );

		$result = $this->run_runner(
			'restore-production-rollback',
			$fixture['config'],
			array(
				'--apply-report=' . escapeshellarg( $apply_report ),
				'--target-site=example.com',
				'--confirm=rollback-production-site',
				'--confirm-site=example.com',
				'--format=json',
			)
		);

		$this->assertSame( 1, $result['exit_code'] );
		$rollback = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertFalse( $rollback['destructive_actions_performed'] );
		$this->assertFalse( $this->check_passed( $rollback, 'production_rollback_enabled' ) );
		$this->assertSame( '<?php echo "failed";', file_get_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );
	}

	/**
	 * Creates one complete production-simulation fixture.
	 *
	 * @return array<string,string>
	 */
	private function create_fixture() {
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
		$wp_cli        = $this->create_fake_wp_cli( $uuid, $wp_cli_log );

		mkdir( $target_path );
		mkdir( $target_path . DIRECTORY_SEPARATOR . 'wp-content' );
		file_put_contents( $target_path . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "before";' );
		file_put_contents( $target_path . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'object-cache.php', '<?php // cache.' );
		file_put_contents( $site_root . DIRECTORY_SEPARATOR . 'wp-config.php', '<?php // external config.' );
		mkdir( $staged_path );
		mkdir( $staged_path . DIRECTORY_SEPARATOR . 'htdocs' );
		file_put_contents( $staged_path . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "staged";' );
		file_put_contents( $staged_path . DIRECTORY_SEPARATOR . 'database.sql', '-- staged database' );
		file_put_contents(
			$staged_path . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json',
			json_encode(
				array(
					'status'                   => 'staged_for_inspection',
					'package_id'               => 'example-com-20260720-010000',
					'site_id'                  => 'example.com',
					'site_uuid'                => $uuid,
					'site_url'                 => 'https://example.com',
					'created_at'               => '2026-07-20T01:00:00+00:00',
					'producer'                 => 'alynt_server_runner',
					'producer_version'         => '0.2.0',
					'archive_format'           => 'tar.gz',
					'file_root'                => 'htdocs',
					'database_dump'            => 'database.sql',
					'package_verified'         => true,
					'archive_members_safe'     => true,
					'database_imported'        => false,
					'live_files_overwritten'   => false,
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
				'production_restore_enabled'               => false,
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
				'minimum_free_space_bytes'                 => 0,
			)
		);

		return array(
			'config'       => $config,
			'target_path'  => $target_path,
			'staged_path'  => $staged_path,
			'reports_path' => $reports_path,
			'uuid'         => $uuid,
			'wp_cli_log'   => $wp_cli_log,
		);
	}

	/**
	 * Creates verified production-simulation pre-restore evidence.
	 *
	 * @param array<string,string> $fixture Fixture.
	 * @return string
	 */
	private function create_production_pre_backup( array $fixture ) {
		$result = $this->run_runner(
			'restore-production-create-pre-backup',
			$fixture['config'],
			array(
				'--staged-path=' . escapeshellarg( $fixture['staged_path'] ),
				'--scope=files-and-database',
				'--target-site=example.com',
				'--confirm=create-production-pre-restore-backup',
				'--format=json',
			)
		);

		$this->assertSame( 0, $result['exit_code'], implode( "\n", array_merge( $result['error'], $result['output'] ) ) );
		$backup = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'succeeded', $backup['status'] );
		$this->assertTrue( $backup['pre_restore_backup_created'] );
		$this->assertTrue( $backup['database_export_created'] );
		$this->assertTrue( $backup['file_backup_created'] );
		$this->assertFileExists( $backup['pre_restore_evidence_path'] );
		$this->assertSame( hash_file( 'sha256', $backup['database_export_path'] ), $backup['database_export_sha256'] );
		$this->assertSame( hash_file( 'sha256', $backup['file_backup_path'] ), $backup['file_backup_sha256'] );

		return $backup['pre_restore_evidence_path'];
	}

	/**
	 * Writes a controlled production-apply report fixture for rollback tests.
	 *
	 * @param array<string,string> $fixture Fixture.
	 * @param string               $evidence_path Evidence path.
	 * @return string
	 */
	private function write_apply_report( array $fixture, $evidence_path ) {
		$path = $fixture['reports_path'] . DIRECTORY_SEPARATOR . 'RESTORE_PRODUCTION_APPLY_REPORT-example-com-20260720-010000.json';
		file_put_contents(
			$path,
			json_encode(
				array(
					'command'                       => 'restore-production-apply',
					'restore_environment'           => 'production-simulation',
					'target_site'                   => 'example.com',
					'target_site_uuid'              => $fixture['uuid'],
					'package_id'                    => 'example-com-20260720-010000',
					'scope'                         => 'files-and-database',
					'staged_path'                   => $fixture['staged_path'],
					'pre_restore_evidence_path'     => $evidence_path,
					'destructive_actions_performed' => true,
				)
			)
		);

		return $path;
	}

	/**
	 * Creates a deterministic fake WP-CLI for read, export, and import paths.
	 *
	 * @param string $uuid Site UUID.
	 * @param string $log_path WP-CLI log path.
	 * @return string
	 */
	private function create_fake_wp_cli( $uuid, $log_path ) {
		$path = $this->root . DIRECTORY_SEPARATOR . ( '\\' === DIRECTORY_SEPARATOR ? 'fake-wp.bat' : 'fake-wp' );
		if ( '\\' === DIRECTORY_SEPARATOR ) {
			$commands = array(
				'@echo off',
				'echo %* >> "' . str_replace( '"', '', $log_path ) . '"',
				'set "ARGS=%*"',
				'echo %ARGS% | findstr /C:"option get home" >nul && (echo https://example.com& exit /b 0)',
				'echo %ARGS% | findstr /C:"option get siteurl" >nul && (echo https://example.com& exit /b 0)',
				'echo %ARGS% | findstr /C:"option pluck alynt_drime_backups_settings site_uuid" >nul && (echo ' . $uuid . '& exit /b 0)',
				'echo %ARGS% | findstr /C:"config get DB_NAME" >nul && (echo example_database& exit /b 0)',
				'echo %ARGS% | findstr /C:"db prefix" >nul && (echo wp_& exit /b 0)',
				'echo %ARGS% | findstr /C:"db size --size_format=b" >nul && (echo 1024& exit /b 0)',
				'echo %ARGS% | findstr /C:"core version" >nul && (echo 7.0.1& exit /b 0)',
				'echo %ARGS% | findstr /C:"cli version" >nul && (echo WP-CLI 2.12.0& exit /b 0)',
				'echo %ARGS% | findstr /C:"plugin list --status=active --field=name" >nul && (echo alynt-drime-backups-uploader& exit /b 0)',
				'echo %ARGS% | findstr /C:"theme list --status=active --field=name" >nul && (echo example-theme& exit /b 0)',
				'echo %ARGS% | findstr /C:"maintenance-mode status" >nul && (echo Maintenance mode is not active.& exit /b 1)',
				'echo %ARGS% | findstr /C:"db export" >nul && (set "ARG="& set "PREV="& goto alynt_arg_loop)',
				'echo %ARGS% | findstr /C:"db import" >nul && exit /b 0',
				'exit /b 1',
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

		$script = "#!/bin/sh\n"
			. "printf '%s\\n' \"$*\" >> " . escapeshellarg( $log_path ) . "\n"
			. "args=\"$*\"\n"
			. "case \"$args\" in\n"
			. "*\"option get home\"*) echo https://example.com;;\n"
			. "*\"option get siteurl\"*) echo https://example.com;;\n"
			. "*\"option pluck alynt_drime_backups_settings site_uuid\"*) echo '" . $uuid . "';;\n"
			. "*\"config get DB_NAME\"*) echo example_database;;\n"
			. "*\"db prefix\"*) echo wp_;;\n"
			. "*\"db size --size_format=b\"*) echo 1024;;\n"
			. "*\"core version\"*) echo 7.0.1;;\n"
			. "*\"cli version\"*) echo 'WP-CLI 2.12.0';;\n"
			. "*\"plugin list --status=active --field=name\"*) echo alynt-drime-backups-uploader;;\n"
			. "*\"theme list --status=active --field=name\"*) echo example-theme;;\n"
			. "*\"maintenance-mode status\"*) echo 'Maintenance mode is not active.'; exit 1;;\n"
			. "*\"db export\"*) for arg in \"$@\"; do case \"$arg\" in *.sql) echo '-- before db' > \"$arg\";; esac; done;;\n"
			. "*\"db import\"*) :;;\n"
			. "*) exit 1;;\n"
			. "esac\nexit 0\n";
		file_put_contents( $path, $script );
		chmod( $path, 0755 );

		return $path;
	}

	/**
	 * Returns whether a named check passed.
	 *
	 * @param array<string,mixed> $result Result.
	 * @param string              $name Check name.
	 * @return bool
	 */
	private function check_passed( array $result, $name ) {
		foreach ( $result['checks'] as $check ) {
			if ( $name === $check['name'] ) {
				return (bool) $check['passed'];
			}
		}

		$this->fail( 'Missing check: ' . $name );
	}
}
