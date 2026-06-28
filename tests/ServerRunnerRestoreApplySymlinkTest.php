<?php
/**
 * Server runner restore apply symlink reporting tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

/**
 * Covers file restore reporting for symlinked drop-ins.
 */
class ServerRunnerRestoreApplySymlinkTest extends Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case {
	public function test_file_restore_reports_pre_restore_symlink_drop_ins_missing_from_staged_files() {
		if ( '\\' === DIRECTORY_SEPARATOR ) {
			$this->markTestSkipped( 'Symlink archive coverage runs on Unix-like test environments.' );
		}

		if ( ! function_exists( 'symlink' ) || ! $this->tar_available() ) {
			$this->markTestSkipped( 'Symlink and tar support are required for this coverage.' );
		}

		$fixture = $this->create_symlink_restore_fixture();
		$result  = $this->run_runner(
			'restore-apply',
			$fixture['config'],
			array(
				'--staged-path=' . escapeshellarg( $fixture['staged_path'] ),
				'--scope=files',
				'--pre-restore-evidence=' . escapeshellarg( $fixture['pre_restore_evidence_path'] ),
				'--confirm=restore-staging-site',
				'--format=json',
			)
		);

		$this->assertSame( 0, $result['exit_code'], implode( "\n", $result['error'] ) );

		$apply = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'succeeded', $apply['status'] );
		$this->assertTrue( $apply['file_restore_succeeded'] );
		$this->assertSame( 1, $apply['file_restore_missing_symlink_count'] );
		$this->assertTrue( $apply['file_restore_manual_review_required'] );
		$this->assertStringContainsString( 'htdocs/wp-content/db.php -> ', $apply['file_restore_missing_symlink_samples'][0] );
		$this->assertTrue( $apply['post_restore_manual_review_required'] );
		$this->assertFalse( $apply['post_restore_cleanup_required'] );
		$this->assertCount( 1, $apply['post_restore_manual_review_items'] );
		$this->assertSame( 'known_drop_in_missing_after_restore', $apply['post_restore_manual_review_items'][0]['type'] );
		$this->assertSame( 'wp-content/db.php', $apply['post_restore_manual_review_items'][0]['path'] );
		$this->assertFalse( $apply['post_restore_manual_review_items'][0]['post_restore_exists'] );
		$this->assertFalse( $apply['post_restore_manual_review_items'][0]['cleanup_required'] );
		$this->assertStringContainsString( 'Query Monitor', $apply['post_restore_manual_review_items'][0]['owner_hint'] );
		$this->assertStringContainsString( 'Pre-restore file backup includes symlink entries', implode( "\n", $apply['manual_recovery_notes'] ) );
		$this->assertStringContainsString( 'Post-restore manual review is required', implode( "\n", $apply['manual_recovery_notes'] ) );

		$this->assertFileExists( $fixture['wordpress_path'] . DIRECTORY_SEPARATOR . 'index.php' );
		$this->assertFileDoesNotExist( $fixture['wordpress_path'] . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'db.php' );
	}

	/**
	 * Creates a file restore fixture whose pre-restore backup includes a symlinked drop-in.
	 *
	 * @return array{config:string,staged_path:string,pre_restore_evidence_path:string,wordpress_path:string}
	 */
	private function create_symlink_restore_fixture() {
		$package_id      = 'example-com-20260628-090000';
		$outbox          = $this->make_directory( 'outbox-' . $package_id );
		$wordpress_path  = $this->make_directory( 'target-' . $package_id );
		$pre_backup_path = $this->make_directory( 'pre-restore-' . $package_id );
		$reports_path    = $this->make_directory( 'restore-reports-' . $package_id );
		$evidence_path   = $pre_backup_path . DIRECTORY_SEPARATOR . 'PRE_RESTORE_BACKUP_EVIDENCE-' . $package_id . '.json';
		$config          = $this->write_config(
			$outbox,
			array(
				'wordpress_path'                   => $wordpress_path,
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

		$file_backup_path = $pre_backup_path . DIRECTORY_SEPARATOR . 'current-files.tar.gz';
		$pre_source       = $this->make_directory( 'pre-source-' . $package_id );
		$this->write_restore_source( $pre_source );
		$this->create_drop_in_symlink( $pre_source . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'db.php' );
		$this->create_tar_archive( $file_backup_path, $pre_source );

		$database_export_path = $pre_backup_path . DIRECTORY_SEPARATOR . 'current-database.sql';
		file_put_contents( $database_export_path, '-- current db' );
		file_put_contents(
			$evidence_path,
			json_encode(
				array(
					'schema_version'        => 1,
					'evidence_type'         => 'pre_restore_backup',
					'generated_at'          => '2026-06-28T09:00:00+00:00',
					'package_id'            => $package_id,
					'scope'                 => 'files',
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
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "ok";' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'staged.txt', 'staged file' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'database.sql', '-- staged db' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'manifest.json', '{}' );
	}

	private function write_restore_sidecars( $archive, $package_id ) {
		$manifest = array(
			'package_id'         => $package_id,
			'site_url'           => 'https://example.com',
			'created_at'         => '2026-06-28T09:00:00+00:00',
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

	private function create_drop_in_symlink( $path ) {
		if ( ! @symlink( '/var/www/example.com/htdocs/wp-content/plugins/query-monitor/wp-content/db.php', $path ) ) {
			$this->markTestSkipped( 'This environment cannot create the symlink fixture.' );
		}
	}
}
