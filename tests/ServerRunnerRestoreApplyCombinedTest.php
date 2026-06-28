<?php
/**
 * Server runner combined restore apply tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

/**
 * Covers gated combined file and database restore apply behavior.
 */
class ServerRunnerRestoreApplyCombinedTest extends Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case {
	public function test_restore_apply_combined_replaces_files_then_imports_database_and_writes_report() {
		if ( ! $this->tar_available() ) {
			$this->markTestSkipped( 'The tar command is required for combined restore apply coverage.' );
		}

		$wp_cli_log = $this->root . DIRECTORY_SEPARATOR . 'wp-cli-combined.log';
		$wp_cli     = $this->create_fake_wp_cli( $wp_cli_log );
		$fixture    = $this->create_combined_restore_fixture( 'example-com-20260628-100000', $wp_cli );

		$result = $this->run_runner(
			'restore-apply',
			$fixture['config'],
			array(
				'--staged-path=' . escapeshellarg( $fixture['staged_path'] ),
				'--scope=files-and-database',
				'--pre-restore-evidence=' . escapeshellarg( $fixture['pre_restore_evidence_path'] ),
				'--confirm=restore-staging-site',
				'--format=json',
			)
		);

		$this->assertSame( 0, $result['exit_code'], implode( "\n", $result['error'] ) );

		$apply = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'restore-apply', $apply['command'] );
		$this->assertSame( 'succeeded', $apply['status'] );
		$this->assertSame( 'files-and-database', $apply['scope'] );
		$this->assertSame( array( 'files', 'database' ), $apply['combined_restore_order'] );
		$this->assertTrue( $apply['dry_run_checks_passed'] );
		$this->assertTrue( $apply['file_restore_attempted'] );
		$this->assertTrue( $apply['file_restore_succeeded'] );
		$this->assertTrue( $apply['live_files_overwritten'] );
		$this->assertTrue( $apply['database_import_attempted'] );
		$this->assertTrue( $apply['database_import_succeeded'] );
		$this->assertTrue( $apply['database_imported'] );
		$this->assertTrue( $apply['destructive_actions_performed'] );
		$this->assertSame( 0, $apply['file_restore_missing_symlink_count'] );
		$this->assertFalse( $apply['file_restore_manual_review_required'] );
		$this->assertTrue( $apply['restore_apply_report_written'] );
		$this->assertFileExists( $apply['restore_apply_report_path'] );

		$this->assertFileExists( $fixture['wordpress_path'] . DIRECTORY_SEPARATOR . 'index.php' );
		$this->assertSame( '<?php echo "combined";', file_get_contents( $fixture['wordpress_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );
		$this->assertFileExists( $fixture['wordpress_path'] . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'combined.txt' );
		$this->assertFileDoesNotExist( $fixture['wordpress_path'] . DIRECTORY_SEPARATOR . 'old.php' );

		$wp_cli_args = (string) file_get_contents( $wp_cli_log );
		$this->assertStringContainsString( 'db import', $wp_cli_args );
		$this->assertStringContainsString( 'database.sql', $wp_cli_args );

		$report = json_decode( (string) file_get_contents( $apply['restore_apply_report_path'] ), true );
		$this->assertSame( 'succeeded', $report['status'] );
		$this->assertTrue( $report['file_restore_succeeded'] );
		$this->assertTrue( $report['database_imported'] );
	}

	/**
	 * Creates a combined restore fixture.
	 *
	 * @param string $package_id Package ID.
	 * @param string $wp_cli Fake WP-CLI path.
	 * @return array{config:string,staged_path:string,pre_restore_evidence_path:string,wordpress_path:string}
	 */
	private function create_combined_restore_fixture( $package_id, $wp_cli ) {
		$outbox          = $this->make_directory( 'outbox-' . $package_id );
		$wordpress_path  = $this->make_directory( 'target-' . $package_id );
		$pre_backup_path = $this->make_directory( 'pre-restore-' . $package_id );
		$reports_path    = $this->make_directory( 'restore-reports-' . $package_id );
		$evidence_path   = $pre_backup_path . DIRECTORY_SEPARATOR . 'PRE_RESTORE_BACKUP_EVIDENCE-' . $package_id . '.json';
		$config          = $this->write_config(
			$outbox,
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
			)
		);

		$archive = $outbox . DIRECTORY_SEPARATOR . $package_id . '.tar.gz';
		$source  = $this->make_directory( 'package-source-' . $package_id );
		$this->write_restore_source( $source );
		$this->create_tar_archive( $archive, $source );
		$this->write_restore_sidecars( $archive, $package_id );

		mkdir( $wordpress_path . DIRECTORY_SEPARATOR . 'wp-content' );
		file_put_contents( $wordpress_path . DIRECTORY_SEPARATOR . 'old.php', 'old file' );
		file_put_contents( $wordpress_path . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'stale.txt', 'stale file' );

		$pre_source = $this->make_directory( 'pre-source-' . $package_id );
		$this->write_restore_source( $pre_source );
		$file_backup_path = $pre_backup_path . DIRECTORY_SEPARATOR . 'current-files.tar.gz';
		$this->create_tar_archive( $file_backup_path, $pre_source );

		$database_export_path = $pre_backup_path . DIRECTORY_SEPARATOR . 'current-database.sql';
		file_put_contents( $database_export_path, '-- current db' );
		file_put_contents(
			$evidence_path,
			json_encode(
				array(
					'schema_version'        => 1,
					'evidence_type'         => 'pre_restore_backup',
					'generated_at'          => '2026-06-28T10:00:00+00:00',
					'package_id'            => $package_id,
					'scope'                 => 'files-and-database',
					'target_wordpress_path' => $wordpress_path,
					'database_export_path'  => $database_export_path,
					'file_backup_path'      => $file_backup_path,
				)
			)
		);

		$stage = $this->run_runner( 'stage-restore', $config, array( '--package=' . escapeshellarg( $archive ) ) );
		$this->assertSame( 0, $stage['exit_code'], implode( "\n", $stage['error'] ) );

		return array(
			'config'                    => $config,
			'staged_path'               => $this->root . DIRECTORY_SEPARATOR . 'restores' . DIRECTORY_SEPARATOR . $package_id,
			'pre_restore_evidence_path' => $evidence_path,
			'wordpress_path'            => $wordpress_path,
		);
	}

	private function write_restore_source( $source ) {
		mkdir( $source . DIRECTORY_SEPARATOR . 'htdocs' );
		mkdir( $source . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'wp-content' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "combined";' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'combined.txt', 'combined file' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'database.sql', '-- combined db' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'manifest.json', '{}' );
	}

	private function write_restore_sidecars( $archive, $package_id ) {
		$manifest = array(
			'package_id'         => $package_id,
			'site_url'           => 'https://example.com',
			'created_at'         => '2026-06-28T10:00:00+00:00',
			'producer'           => 'alynt_server_runner',
			'backup_type'        => 'logical_wordpress_backup',
			'archive_format'     => 'tar.gz',
			'file_root'          => 'htdocs',
			'database_dump'      => 'database.sql',
			'consistency_status' => 'clean',
		);
		file_put_contents( $archive . '.manifest.json', json_encode( $manifest ) );
		file_put_contents( $archive . '.sha256', hash_file( 'sha256', $archive ) . '  ' . basename( $archive ) );
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
