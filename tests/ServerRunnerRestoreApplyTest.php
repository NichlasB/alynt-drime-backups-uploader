<?php
/**
 * Server runner restore apply tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

/**
 * Covers gated database restore apply behavior.
 */
class ServerRunnerRestoreApplyTest extends Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case {
	public function test_restore_apply_requires_confirmation_phrase() {
		$config = $this->write_config( $this->make_directory( 'outbox-confirm' ) );

		$result = $this->run_runner(
			'restore-apply',
			$config,
			array(
				'--staged-path=' . escapeshellarg( $this->root . DIRECTORY_SEPARATOR . 'restores' . DIRECTORY_SEPARATOR . 'missing' ),
				'--scope=database',
				'--format=json',
			)
		);

		$this->assertSame( 1, $result['exit_code'] );
		$this->assertStringContainsString( 'Restore apply requires --confirm=restore-staging-site.', implode( "\n", $result['error'] ) );
		$this->assertSame( array(), $result['output'] );
	}

	public function test_restore_apply_rejects_unknown_scope() {
		$config = $this->write_config( $this->make_directory( 'outbox-scope' ) );

		$result = $this->run_runner(
			'restore-apply',
			$config,
			array(
				'--staged-path=' . escapeshellarg( $this->root . DIRECTORY_SEPARATOR . 'restores' . DIRECTORY_SEPARATOR . 'missing' ),
				'--scope=everything',
				'--confirm=restore-staging-site',
				'--format=json',
			)
		);

		$this->assertSame( 1, $result['exit_code'] );
		$this->assertStringContainsString( 'Only --scope=database, --scope=files, or --scope=files-and-database is implemented for restore-apply.', implode( "\n", $result['error'] ) );
		$this->assertSame( array(), $result['output'] );
	}

	public function test_restore_apply_database_imports_with_fake_wp_cli_and_writes_report() {
		if ( ! $this->tar_available() ) {
			$this->markTestSkipped( 'The tar command is required for restore apply coverage.' );
		}

		$wp_cli_log = $this->root . DIRECTORY_SEPARATOR . 'wp-cli.log';
		$wp_cli     = $this->create_fake_wp_cli( $wp_cli_log );
		$fixture    = $this->create_restore_fixture( 'example-com-20260627-160000', 'database', $wp_cli );

		$result = $this->run_restore_apply_fixture( $fixture );

		$this->assertSame( 0, $result['exit_code'], implode( "\n", $result['error'] ) );

		$apply = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'restore-apply', $apply['command'] );
		$this->assertSame( 'succeeded', $apply['status'] );
		$this->assertSame( 'database', $apply['scope'] );
		$this->assertTrue( $apply['confirmation_phrase_accepted'] );
		$this->assertTrue( $apply['dry_run_checks_passed'] );
		$this->assertTrue( $apply['database_import_attempted'] );
		$this->assertTrue( $apply['database_import_succeeded'] );
		$this->assertTrue( $apply['database_imported'] );
		$this->assertTrue( $apply['destructive_actions_performed'] );
		$this->assertFalse( $apply['file_restore_attempted'] );
		$this->assertFalse( $apply['file_restore_succeeded'] );
		$this->assertFalse( $apply['live_files_overwritten'] );
		$this->assertTrue( $apply['restore_apply_report_written'] );
		$this->assertFileExists( $apply['restore_apply_report_path'] );

		$wp_cli_args = (string) file_get_contents( $wp_cli_log );
		$this->assertStringContainsString( 'db import', $wp_cli_args );
		$this->assertStringContainsString( 'database.sql', $wp_cli_args );

		$report = json_decode( (string) file_get_contents( $apply['restore_apply_report_path'] ), true );
		$this->assertSame( 'restore-apply', $report['command'] );
		$this->assertSame( 'succeeded', $report['status'] );
		$this->assertTrue( $report['database_imported'] );
		$this->assertFalse( $report['live_files_overwritten'] );
	}

	public function test_restore_apply_files_replaces_target_files_and_writes_report() {
		if ( ! $this->tar_available() ) {
			$this->markTestSkipped( 'The tar command is required for restore apply coverage.' );
		}

		$fixture = $this->create_restore_fixture( 'example-com-20260627-160500', 'files' );

		$result = $this->run_restore_apply_fixture( $fixture, 'files' );

		$this->assertSame( 0, $result['exit_code'], implode( "\n", $result['error'] ) );

		$apply = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'restore-apply', $apply['command'] );
		$this->assertSame( 'succeeded', $apply['status'] );
		$this->assertSame( 'files', $apply['scope'] );
		$this->assertTrue( $apply['dry_run_checks_passed'] );
		$this->assertFalse( $apply['database_import_attempted'] );
		$this->assertFalse( $apply['database_imported'] );
		$this->assertTrue( $apply['file_restore_attempted'] );
		$this->assertTrue( $apply['file_restore_succeeded'] );
		$this->assertTrue( $apply['live_files_overwritten'] );
		$this->assertTrue( $apply['destructive_actions_performed'] );
		$this->assertTrue( $apply['restore_apply_report_written'] );
		$this->assertFileExists( $apply['restore_apply_report_path'] );

		$this->assertFileExists( $fixture['wordpress_path'] . DIRECTORY_SEPARATOR . 'index.php' );
		$this->assertSame( '<?php echo "ok";', file_get_contents( $fixture['wordpress_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );
		$this->assertFileExists( $fixture['wordpress_path'] . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'staged.txt' );
		$this->assertFileDoesNotExist( $fixture['wordpress_path'] . DIRECTORY_SEPARATOR . 'old.php' );
		$this->assertFileDoesNotExist( $fixture['wordpress_path'] . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'stale.txt' );

		$report = json_decode( (string) file_get_contents( $apply['restore_apply_report_path'] ), true );
		$this->assertSame( 'restore-apply', $report['command'] );
		$this->assertSame( 'succeeded', $report['status'] );
		$this->assertTrue( $report['file_restore_succeeded'] );
		$this->assertTrue( $report['live_files_overwritten'] );
	}

	public function test_restore_apply_database_refuses_when_dry_run_fails() {
		if ( ! $this->tar_available() ) {
			$this->markTestSkipped( 'The tar command is required for restore apply coverage.' );
		}

		$wp_cli_log = $this->root . DIRECTORY_SEPARATOR . 'wp-cli-fail.log';
		$wp_cli     = $this->create_fake_wp_cli( $wp_cli_log );
		$fixture    = $this->create_restore_fixture(
			'example-com-20260627-161000',
			'database',
			$wp_cli,
			array(
				'restore_apply_enabled' => false,
			)
		);

		$result = $this->run_restore_apply_fixture( $fixture );

		$this->assertSame( 1, $result['exit_code'] );

		$apply = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'failed', $apply['status'] );
		$this->assertSame( 'restore-dry-run', $apply['failure_step'] );
		$this->assertFalse( $apply['dry_run_checks_passed'] );
		$this->assertFalse( $apply['database_import_attempted'] );
		$this->assertFalse( $apply['destructive_actions_performed'] );
		$this->assertFileDoesNotExist( $wp_cli_log );
	}

	/**
	 * Creates a staged restore fixture.
	 *
	 * @param string              $package_id Package ID.
	 * @param string              $scope Restore scope.
	 * @param string              $wp_cli Fake WP-CLI path.
	 * @param array<string,mixed> $overrides Config overrides.
	 * @return array{config:string,staged_path:string,pre_restore_evidence_path:string,wordpress_path:string}
	 */
	private function create_restore_fixture( $package_id, $scope, $wp_cli = 'wp', array $overrides = array() ) {
		$outbox          = $this->make_directory( 'outbox-' . $package_id );
		$wordpress_path  = $this->make_directory( 'target-' . $package_id );
		$pre_backup_path = $this->make_directory( 'pre-restore-' . $package_id );
		$reports_path    = $this->make_directory( 'restore-reports-' . $package_id );
		$evidence_path   = $pre_backup_path . DIRECTORY_SEPARATOR . 'PRE_RESTORE_BACKUP_EVIDENCE-' . $package_id . '.json';
		$config          = $this->write_config(
			$outbox,
			array_merge(
				array(
					'wordpress_path'                   => $wordpress_path,
					'wp_cli_path'                      => $wp_cli,
					'restore_apply_enabled'            => true,
					'restore_environment'              => 'staging',
					'restore_target_wordpress_path'    => $wordpress_path,
					'restore_pre_backup_path'          => $pre_backup_path,
					'restore_pre_backup_evidence_path' => $evidence_path,
					'restore_reports_path'             => $reports_path,
					'minimum_free_space_bytes'         => 0,
				),
				$overrides
			)
		);
		$archive         = $outbox . DIRECTORY_SEPARATOR . $package_id . '.tar.gz';
		$source          = $this->make_directory( 'package-source-' . $package_id );

		mkdir( $source . DIRECTORY_SEPARATOR . 'htdocs' );
		mkdir( $source . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'wp-content' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "ok";' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'staged.txt', 'staged file' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'database.sql', '-- staged db' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'manifest.json', '{}' );
		$this->create_tar_archive( $archive, $source );
		mkdir( $wordpress_path . DIRECTORY_SEPARATOR . 'wp-content' );
		file_put_contents( $wordpress_path . DIRECTORY_SEPARATOR . 'old.php', 'old file' );
		file_put_contents( $wordpress_path . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'stale.txt', 'stale file' );

		$manifest = array(
			'package_id'         => $package_id,
			'site_url'           => 'https://example.com',
			'created_at'         => '2026-06-27T16:00:00+00:00',
			'producer'           => 'alynt_server_runner',
			'backup_type'        => 'logical_wordpress_backup',
			'archive_format'     => 'tar.gz',
			'file_root'          => 'htdocs',
			'database_dump'      => 'database.sql',
			'consistency_status' => 'clean',
		);
		file_put_contents( $archive . '.manifest.json', json_encode( $manifest ) );
		file_put_contents( $archive . '.sha256', hash_file( 'sha256', $archive ) . '  ' . basename( $archive ) );

		$database_export_path = $pre_backup_path . DIRECTORY_SEPARATOR . 'current-database.sql';
		$file_backup_path     = $pre_backup_path . DIRECTORY_SEPARATOR . 'current-files.tar.gz';
		file_put_contents( $database_export_path, '-- current db' );
		file_put_contents( $file_backup_path, 'current files' );
		file_put_contents(
			$evidence_path,
			json_encode(
				array(
					'schema_version'        => 1,
					'evidence_type'         => 'pre_restore_backup',
					'generated_at'          => '2026-06-27T16:05:00+00:00',
					'package_id'            => $package_id,
					'scope'                 => $scope,
					'target_wordpress_path' => $wordpress_path,
					'database_export_path'  => $database_export_path,
					'file_backup_path'      => $file_backup_path,
				)
			)
		);

		$stage = $this->run_runner( 'stage-restore', $config, array( '--package=' . escapeshellarg( $archive ) ) );
		$this->assertSame( 0, $stage['exit_code'], implode( "\n", $stage['error'] ) );

		return array(
			'config'       => $config,
			'staged_path'  => $this->root . DIRECTORY_SEPARATOR . 'restores' . DIRECTORY_SEPARATOR . $package_id,
			'pre_restore_evidence_path' => $evidence_path,
			'wordpress_path' => $wordpress_path,
		);
	}

	private function run_restore_apply_fixture( array $fixture, $scope = 'database' ) {
		return $this->run_runner(
			'restore-apply',
			$fixture['config'],
			array(
				'--staged-path=' . escapeshellarg( $fixture['staged_path'] ),
				'--scope=' . $scope,
				'--pre-restore-evidence=' . escapeshellarg( $fixture['pre_restore_evidence_path'] ),
				'--confirm=restore-staging-site',
				'--format=json',
			)
		);
	}

	private function create_fake_wp_cli( $log_path ) {
		$path = $this->root . DIRECTORY_SEPARATOR . ( '\\' === DIRECTORY_SEPARATOR ? 'fake-wp.bat' : 'fake-wp' );

		if ( '\\' === DIRECTORY_SEPARATOR ) {
			file_put_contents( $path, "@echo off\r\necho %* > \"" . str_replace( '"', '', $log_path ) . "\"\r\nexit /b 0\r\n" );
			return $path;
		}

		file_put_contents( $path, "#!/bin/sh\nprintf '%s\n' \"$*\" > " . escapeshellarg( $log_path ) . "\nexit 0\n" );
		chmod( $path, 0755 );

		return $path;
	}
}
