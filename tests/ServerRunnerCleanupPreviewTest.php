<?php
/**
 * Server runner cleanup preview tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

/**
 * Covers read-only local cleanup preview output.
 */
class ServerRunnerCleanupPreviewTest extends Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case {
	public function test_cleanup_preview_reports_candidates_without_deleting_files() {
		$outbox = $this->make_directory( 'outbox' );
		$config = $this->write_config( $outbox );

		$package_id = 'example-com-20260627-140000';
		$archive    = $this->create_package_fixture( $outbox, $package_id, 20 );
		$restore_dir = $this->create_restore_fixture( $package_id, 20 );

		$result = $this->run_runner( 'cleanup-preview', $config, array( '--older-than-days=14', '--format=json' ) );

		$this->assertSame( 0, $result['exit_code'], implode( "\n", $result['error'] ) );

		$preview = json_decode( implode( "\n", $result['output'] ), true );

		$this->assertIsArray( $preview );
		$this->assertSame( 1, $preview['schema_version'] );
		$this->assertSame( 14, $preview['older_than_days'] );
		$this->assertSame( 1, $preview['outbox']['candidate_count'] );
		$this->assertSame( 1, $preview['restore_staging']['candidate_count'] );
		$this->assertSame( 2, $preview['total_candidate_count'] );
		$this->assertFalse( $preview['destructive_actions_performed'] );

		$outbox_candidate = $preview['outbox']['candidates'][0];
		$this->assertSame( $package_id, $outbox_candidate['package_id'] );
		$this->assertSame( $package_id . '.tar.gz', $outbox_candidate['archive_name'] );
		$this->assertGreaterThanOrEqual( 14, $outbox_candidate['age_days'] );
		$this->assertSame( 'operator_review', $outbox_candidate['suggested_action'] );

		$restore_candidate = $preview['restore_staging']['candidates'][0];
		$this->assertSame( $package_id, $restore_candidate['directory_name'] );
		$this->assertGreaterThanOrEqual( 14, $restore_candidate['age_days'] );
		$this->assertTrue( $restore_candidate['restore_notes_present'] );
		$this->assertTrue( $restore_candidate['restore_report_present'] );
		$this->assertSame( 'operator_review', $restore_candidate['suggested_action'] );

		$this->assertFileExists( $archive );
		$this->assertFileExists( $archive . '.manifest.json' );
		$this->assertFileExists( $archive . '.sha256' );
		$this->assertDirectoryExists( $restore_dir );
	}

	public function test_cleanup_requires_explicit_confirmation() {
		$outbox = $this->make_directory( 'outbox' );
		$config = $this->write_config( $outbox );

		$archive = $this->create_package_fixture( $outbox, 'example-com-20260627-150000', 20 );

		$result = $this->run_runner( 'cleanup', $config, array( '--older-than-days=14', '--format=json' ) );

		$this->assertSame( 1, $result['exit_code'] );
		$this->assertStringContainsString( 'Refusing cleanup without --confirm=delete-local-artifacts.', implode( "\n", $result['error'] ) );
		$this->assertFileExists( $archive );
		$this->assertFileExists( $archive . '.manifest.json' );
		$this->assertFileExists( $archive . '.sha256' );
	}

	public function test_cleanup_deletes_old_candidates_after_confirmation() {
		$outbox = $this->make_directory( 'outbox' );
		$config = $this->write_config( $outbox );

		$old_archive   = $this->create_package_fixture( $outbox, 'example-com-20260627-160000', 20 );
		$fresh_archive = $this->create_package_fixture( $outbox, 'example-com-20260627-170000', 1 );
		$restore_dir   = $this->create_restore_fixture( 'example-com-20260627-160000', 20 );

		$result = $this->run_runner(
			'cleanup',
			$config,
			array(
				'--older-than-days=14',
				'--confirm=delete-local-artifacts',
				'--format=json',
			)
		);

		$this->assertSame( 0, $result['exit_code'], implode( "\n", $result['error'] ) );

		$cleanup = json_decode( implode( "\n", $result['output'] ), true );

		$this->assertIsArray( $cleanup );
		$this->assertSame( 1, $cleanup['schema_version'] );
		$this->assertSame( 2, $cleanup['total_candidate_count'] );
		$this->assertSame( 0, $cleanup['failure_count'] );
		$this->assertTrue( $cleanup['destructive_actions_performed'] );
		$this->assertSame( 1, $cleanup['outbox']['deleted_package_count'] );
		$this->assertSame( 5, $cleanup['outbox']['deleted_file_count'] );
		$this->assertSame( 1, $cleanup['restore_staging']['deleted_directory_count'] );

		$this->assertFileDoesNotExist( $old_archive );
		$this->assertFileDoesNotExist( $old_archive . '.manifest.json' );
		$this->assertFileDoesNotExist( $old_archive . '.sha256' );
		$this->assertFileDoesNotExist( $old_archive . '.remote-index.json' );
		$this->assertFileDoesNotExist( $old_archive . '.remote-catalog.json' );
		$this->assertDirectoryDoesNotExist( $restore_dir );

		$this->assertFileExists( $fresh_archive );
		$this->assertFileExists( $fresh_archive . '.manifest.json' );
		$this->assertFileExists( $fresh_archive . '.sha256' );
	}

	/**
	 * Creates a package fixture with standard server-runner sidecars.
	 *
	 * @param string $outbox Outbox path.
	 * @param string $package_id Package ID.
	 * @param int    $age_days Age in days.
	 * @return string
	 */
	private function create_package_fixture( $outbox, $package_id, $age_days ) {
		$archive = $outbox . DIRECTORY_SEPARATOR . $package_id . '.tar.gz';
		file_put_contents( $archive, 'fake archive' );
		file_put_contents(
			$archive . '.manifest.json',
			json_encode(
				array(
					'package_id'     => $package_id,
					'site_url'       => 'https://example.com',
					'created_at'     => '2026-06-27T14:00:00+00:00',
					'producer'       => 'alynt_server_runner',
					'backup_type'    => 'logical_wordpress_backup',
					'archive_format' => 'tar.gz',
					'file_root'      => 'htdocs',
					'database_dump'  => 'database.sql',
				)
			)
		);
		file_put_contents( $archive . '.sha256', str_repeat( 'b', 64 ) . '  ' . basename( $archive ) );
		file_put_contents( $archive . '.remote-index.json', '{}' );
		file_put_contents( $archive . '.remote-catalog.json', '{}' );

		$time = time() - ( $age_days * 86400 );
		foreach ( array( $archive, $archive . '.manifest.json', $archive . '.sha256', $archive . '.remote-index.json', $archive . '.remote-catalog.json' ) as $path ) {
			touch( $path, $time );
		}

		return $archive;
	}

	/**
	 * Creates a restore staging fixture.
	 *
	 * @param string $package_id Package ID.
	 * @param int    $age_days Age in days.
	 * @return string
	 */
	private function create_restore_fixture( $package_id, $age_days ) {
		$restore_dir = $this->root . DIRECTORY_SEPARATOR . 'restores' . DIRECTORY_SEPARATOR . $package_id;
		mkdir( $restore_dir );
		file_put_contents( $restore_dir . DIRECTORY_SEPARATOR . 'RESTORE_NOTES.txt', 'notes' );
		file_put_contents( $restore_dir . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json', '{}' );

		$time = time() - ( $age_days * 86400 );
		touch( $restore_dir . DIRECTORY_SEPARATOR . 'RESTORE_NOTES.txt', $time );
		touch( $restore_dir . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json', $time );
		touch( $restore_dir, $time );

		return $restore_dir;
	}
}
