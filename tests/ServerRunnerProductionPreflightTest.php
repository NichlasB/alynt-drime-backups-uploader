<?php
/**
 * Server runner production preflight tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

/**
 * Covers the read-only production restore preflight.
 */
class ServerRunnerProductionPreflightTest extends Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case {
	/**
	 * A complete enrolled fixture should pass without enabling apply or rollback.
	 *
	 * @return void
	 */
	public function test_production_preflight_passes_and_writes_redacted_report() {
		$fixture = $this->create_production_preflight_fixture();
		$result  = $this->run_preflight( $fixture, array( '--write-report=1' ) );

		$this->assertSame( 0, $result['exit_code'], implode( "\n", $result['error'] ) );
		$preflight = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertIsArray( $preflight );
		$this->assertSame( 'restore-production-preflight', $preflight['command'] );
		$this->assertSame( 'passed', $preflight['status'] );
		$this->assertSame( 0, $preflight['failure_count'] );
		$this->assertFalse( $preflight['production_apply_allowed'] );
		$this->assertFalse( $preflight['production_apply_available'] );
		$this->assertFalse( $preflight['production_rollback_available'] );
		$this->assertFalse( $preflight['destructive_actions_performed'] );
		$this->assertFalse( $preflight['maintenance_state_changed'] );
		$this->assertFalse( $preflight['native_backup_created'] );
		$this->assertTrue( $preflight['report_written'] );
		$this->assertFileExists( $preflight['report_path'] );
		$this->assertSame( hash( 'sha256', 'super_secret_database' ), $preflight['target_fingerprint']['database_name_sha256'] );
		$this->assertTrue( $preflight['native_backup_readiness']['revision_recorded'] );
		$this->assertSame( hash( 'sha256', 'Bearer native-secret-token' ), $preflight['native_backup_readiness']['revision_id_sha256'] );

		$output = implode( "\n", $result['output'] );
		$this->assertStringNotContainsString( 'super_secret_database', $output );
		$this->assertStringNotContainsString( 'runner-api-secret', $output );
		$this->assertStringNotContainsString( 'native-secret-token', $output );

		$written = (string) file_get_contents( $preflight['report_path'] );
		$this->assertStringNotContainsString( 'super_secret_database', $written );
		$this->assertStringNotContainsString( 'runner-api-secret', $written );
		$this->assertStringNotContainsString( 'native-secret-token', $written );

		foreach ( $preflight['checks'] as $check ) {
			$this->assertTrue( $check['passed'], $check['name'] );
		}
	}

	/**
	 * Wrong operator target and package identity must refuse the preflight.
	 *
	 * @return void
	 */
	public function test_production_preflight_refuses_wrong_target_and_package_identity() {
		$fixture    = $this->create_production_preflight_fixture();
		$report_path = $fixture['staged_path'] . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json';
		$report      = json_decode( (string) file_get_contents( $report_path ), true );
		$report['site_id']  = 'other.example';
		$report['site_url'] = 'https://other.example';
		file_put_contents( $report_path, json_encode( $report ) );

		$result = $this->run_preflight( $fixture, array(), 'wrong.example' );

		$this->assertSame( 1, $result['exit_code'] );
		$preflight = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'refused', $preflight['status'] );
		$this->assertFalse( $this->check_passed( $preflight, 'target_site_matches_config' ) );
		$this->assertFalse( $this->check_passed( $preflight, 'package_site_url_matches_target' ) );
		$this->assertFalse( $this->check_passed( $preflight, 'package_site_identity_matches_target' ) );
		$this->assertFalse( $preflight['production_apply_available'] );
		$this->assertFalse( $preflight['destructive_actions_performed'] );
	}

	/**
	 * Missing native backup evidence and operator reviews must refuse safely.
	 *
	 * @return void
	 */
	public function test_production_preflight_refuses_missing_recovery_and_write_control_evidence() {
		$fixture = $this->create_production_preflight_fixture(
			array(
				'production_cron_control_reviewed'     => false,
				'production_external_writers_reviewed' => false,
				'production_cache_purge_reviewed'      => false,
			)
		);
		unlink( $fixture['native_evidence_path'] );

		$result = $this->run_preflight( $fixture );

		$this->assertSame( 1, $result['exit_code'] );
		$preflight = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'refused', $preflight['status'] );
		$this->assertFalse( $this->check_passed( $preflight, 'cron_control_reviewed' ) );
		$this->assertFalse( $this->check_passed( $preflight, 'external_writers_reviewed' ) );
		$this->assertFalse( $this->check_passed( $preflight, 'cache_purge_reviewed' ) );
		$this->assertFalse( $this->check_passed( $preflight, 'native_backup_evidence_readable' ) );
		$this->assertFalse( $preflight['production_apply_allowed'] );
		$this->assertFalse( $preflight['destructive_actions_performed'] );
	}

	/**
	 * Report paths inside htdocs must be rejected without writing a report.
	 *
	 * @return void
	 */
	public function test_production_preflight_refuses_report_path_inside_target() {
		$fixture = $this->create_production_preflight_fixture();
		$config  = json_decode( (string) file_get_contents( $fixture['config'] ), true );
		$config['production_reports_path'] = $fixture['target_path'] . DIRECTORY_SEPARATOR . 'reports';
		file_put_contents( $fixture['config'], json_encode( $config ) );

		$result = $this->run_preflight( $fixture, array( '--write-report=1' ) );

		$this->assertSame( 1, $result['exit_code'] );
		$preflight = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'refused', $preflight['status'] );
		$this->assertFalse( $preflight['report_written'] );
		$this->assertFalse( $this->check_passed( $preflight, 'production_reports_path_ready' ) );
		$this->assertDirectoryDoesNotExist( $config['production_reports_path'] );
	}

	/**
	 * Creates a complete production preflight fixture.
	 *
	 * @param array<string,mixed> $overrides Config overrides.
	 * @return array<string,string>
	 */
	private function create_production_preflight_fixture( array $overrides = array() ) {
		$uuid                 = '12345678-1234-4234-9234-123456789abc';
		$site_root            = $this->make_directory( 'site-root' );
		$target_path          = $site_root . DIRECTORY_SEPARATOR . 'htdocs';
		$restore_path         = $this->make_directory( 'production-restores' );
		$staged_path          = $restore_path . DIRECTORY_SEPARATOR . 'example-com-20260715-010000';
		$reports_path         = $this->make_directory( 'production-reports' );
		$native_evidence_path = $this->root . DIRECTORY_SEPARATOR . 'native-backup-evidence.json';
		$outbox               = $this->make_directory( 'outbox-production' );
		$wp_cli               = $this->create_fake_read_only_wp_cli( $uuid );

		mkdir( $target_path );
		mkdir( $target_path . DIRECTORY_SEPARATOR . 'wp-content' );
		file_put_contents( $target_path . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "target";' );
		file_put_contents( $target_path . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'object-cache.php', '<?php // object cache.' );
		file_put_contents( $site_root . DIRECTORY_SEPARATOR . 'wp-config.php', '<?php // external config fixture.' );
		mkdir( $staged_path );
		mkdir( $staged_path . DIRECTORY_SEPARATOR . 'htdocs' );
		file_put_contents( $staged_path . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "staged";' );
		file_put_contents( $staged_path . DIRECTORY_SEPARATOR . 'database.sql', '-- staged database' );
		file_put_contents(
			$staged_path . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json',
			json_encode(
				array(
					'schema_version'           => 1,
					'status'                   => 'staged_for_inspection',
					'package_id'               => 'example-com-20260715-010000',
					'site_id'                  => 'example.com',
					'site_uuid'                => $uuid,
					'site_url'                 => 'https://example.com',
					'created_at'               => '2026-07-15T01:00:00+00:00',
					'producer'                 => 'alynt_server_runner',
					'producer_version'         => '0.2.0',
					'backup_type'              => 'logical_wordpress_backup',
					'archive_format'           => 'tar.gz',
					'archive_name'             => 'example-com-20260715-010000.tar.gz',
					'archive_size'             => 2048,
					'checksum_algorithm'       => 'sha256',
					'checksum_value'           => str_repeat( 'a', 64 ),
					'file_root'                => 'htdocs',
					'database_dump'            => 'database.sql',
					'staged_integrity'         => $this->staged_integrity_fixture( $staged_path . DIRECTORY_SEPARATOR . 'htdocs', $staged_path . DIRECTORY_SEPARATOR . 'database.sql' ),
					'consistency_mode'         => 'light',
					'consistency_status'       => 'clean',
					'package_verified'         => true,
					'archive_members_safe'     => true,
					'extracted_for_inspection' => true,
					'database_imported'        => false,
					'live_files_overwritten'   => false,
				)
			)
		);
		file_put_contents(
			$native_evidence_path,
			json_encode(
				array(
					'schema_version'   => 1,
					'evidence_type'    => 'gridpane_native_backup',
					'status'           => 'completed',
					'target_site'      => 'example.com',
					'target_site_uuid' => $uuid,
					'revision_id'      => 'Bearer native-secret-token',
					'completed_at'     => gmdate( 'c', time() - 60 ),
				)
			)
		);

		$config = $this->write_config(
			$outbox,
			array_merge(
				array(
					'wordpress_path'                           => $target_path,
					'wp_cli_path'                              => $wp_cli,
					'api_token'                                => 'runner-api-secret',
					'production_restore_enabled'               => false,
					'production_restore_environment'           => 'production-simulation',
					'production_target_site_url'               => 'https://example.com',
					'production_target_site_uuid'              => $uuid,
					'production_target_site_root'              => $site_root,
					'production_target_wordpress_path'         => $target_path,
					'production_target_wp_config_path'         => $site_root . DIRECTORY_SEPARATOR . 'wp-config.php',
					'production_restore_path'                  => $restore_path,
					'production_reports_path'                  => $reports_path,
					'production_native_backup_required'        => true,
					'production_native_backup_evidence_path'   => $native_evidence_path,
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
				),
				$overrides
			)
		);

		return array(
			'config'               => $config,
			'staged_path'          => $staged_path,
			'target_path'          => $target_path,
			'native_evidence_path' => $native_evidence_path,
		);
	}

	/**
	 * Runs the production preflight.
	 *
	 * @param array<string,string> $fixture Fixture.
	 * @param array<int,string>    $extra_args Extra args.
	 * @param string               $target_site Target hostname.
	 * @return array{exit_code:int,output:array<int,string>,error:array<int,string>}
	 */
	private function run_preflight( array $fixture, array $extra_args = array(), $target_site = 'example.com' ) {
		return $this->run_runner(
			'restore-production-preflight',
			$fixture['config'],
			array_merge(
				array(
					'--staged-path=' . escapeshellarg( $fixture['staged_path'] ),
					'--scope=files-and-database',
					'--target-site=' . $target_site,
					'--format=json',
				),
				$extra_args
			)
		);
	}

	/**
	 * Creates a fake WP-CLI that exposes only deterministic read output.
	 *
	 * @param string $uuid Site UUID.
	 * @return string
	 */
	private function create_fake_read_only_wp_cli( $uuid ) {
		$path = $this->root . DIRECTORY_SEPARATOR . ( '\\' === DIRECTORY_SEPARATOR ? 'fake-wp-read.bat' : 'fake-wp-read' );

		if ( '\\' === DIRECTORY_SEPARATOR ) {
			$commands = array(
				'@echo off',
				'set "ARGS=%*"',
				'echo %ARGS% | findstr /C:"option get home" >nul && (echo https://example.com& exit /b 0)',
				'echo %ARGS% | findstr /C:"option get siteurl" >nul && (echo https://example.com& exit /b 0)',
				'echo %ARGS% | findstr /C:"option pluck alynt_drime_backups_settings site_uuid" >nul && (echo ' . $uuid . '& exit /b 0)',
				'echo %ARGS% | findstr /C:"config get DB_NAME" >nul && (echo super_secret_database& exit /b 0)',
				'echo %ARGS% | findstr /C:"db prefix" >nul && (echo wp_& exit /b 0)',
				'echo %ARGS% | findstr /C:"db size --size_format=b" >nul && (echo 1024& exit /b 0)',
				'echo %ARGS% | findstr /C:"core version" >nul && (echo 7.0.1& exit /b 0)',
				'echo %ARGS% | findstr /C:"cli version" >nul && (echo WP-CLI 2.12.0& exit /b 0)',
				'echo %ARGS% | findstr /C:"plugin list --status=active --field=name" >nul && (echo alynt-drime-backups-uploader& exit /b 0)',
				'echo %ARGS% | findstr /C:"theme list --status=active --field=name" >nul && (echo example-theme& exit /b 0)',
				'echo %ARGS% | findstr /C:"maintenance-mode status" >nul && (echo Maintenance mode is not active.& exit /b 1)',
				'exit /b 1',
			);
			file_put_contents( $path, implode( "\r\n", $commands ) . "\r\n" );
			return $path;
		}

		$script = "#!/bin/sh\nargs=\"\$*\"\ncase \"\$args\" in\n"
			. "*\"option get home\"*) echo https://example.com;;\n"
			. "*\"option get siteurl\"*) echo https://example.com;;\n"
			. "*\"option pluck alynt_drime_backups_settings site_uuid\"*) echo '" . $uuid . "';;\n"
			. "*\"config get DB_NAME\"*) echo super_secret_database;;\n"
			. "*\"db prefix\"*) echo wp_;;\n"
			. "*\"db size --size_format=b\"*) echo 1024;;\n"
			. "*\"core version\"*) echo 7.0.1;;\n"
			. "*\"cli version\"*) echo 'WP-CLI 2.12.0';;\n"
			. "*\"plugin list --status=active --field=name\"*) echo alynt-drime-backups-uploader;;\n"
			. "*\"theme list --status=active --field=name\"*) echo example-theme;;\n"
			. "*\"maintenance-mode status\"*) echo 'Maintenance mode is not active.'; exit 1;;\n"
			. "*) exit 1;;\nesac\nexit 0\n";
		file_put_contents( $path, $script );
		chmod( $path, 0755 );

		return $path;
	}

	/**
	 * Returns whether a named preflight check passed.
	 *
	 * @param array<string,mixed> $preflight Preflight result.
	 * @param string              $name Check name.
	 * @return bool
	 */
	private function check_passed( array $preflight, $name ) {
		foreach ( $preflight['checks'] as $check ) {
			if ( $name === $check['name'] ) {
				return (bool) $check['passed'];
			}
		}

		$this->fail( 'Missing production preflight check: ' . $name );
	}
}
