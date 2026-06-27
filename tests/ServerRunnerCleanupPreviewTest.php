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
		$archive    = $outbox . DIRECTORY_SEPARATOR . $package_id . '.tar.gz';
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

		$restore_dir = $this->root . DIRECTORY_SEPARATOR . 'restores' . DIRECTORY_SEPARATOR . $package_id;
		mkdir( $restore_dir );
		file_put_contents( $restore_dir . DIRECTORY_SEPARATOR . 'RESTORE_NOTES.txt', 'notes' );
		file_put_contents( $restore_dir . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json', '{}' );

		$old_time = time() - ( 20 * 86400 );
		touch( $archive, $old_time );
		touch( $restore_dir, $old_time );

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
}
