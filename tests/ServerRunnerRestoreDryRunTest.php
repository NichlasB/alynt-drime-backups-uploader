<?php
/**
 * Server runner restore dry-run tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

/**
 * Covers read-only restore dry-run gates.
 */
class ServerRunnerRestoreDryRunTest extends Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case {
	public function test_restore_dry_run_passes_for_verified_staged_package() {
		if ( ! $this->tar_available() ) {
			$this->markTestSkipped( 'The tar command is required for restore dry-run coverage.' );
		}

		$fixture = $this->create_staged_restore_fixture( 'example-com-20260627-150000' );

		$result = $this->run_runner(
			'restore-dry-run',
			$fixture['config'],
			array(
				'--staged-path=' . escapeshellarg( $fixture['staged_path'] ),
				'--scope=files-and-database',
				'--format=json',
			)
		);

		$this->assertSame( 0, $result['exit_code'], implode( "\n", $result['error'] ) );

		$dry_run = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertIsArray( $dry_run );
		$this->assertSame( 1, $dry_run['schema_version'] );
		$this->assertSame( 'restore-dry-run', $dry_run['command'] );
		$this->assertSame( 'passed', $dry_run['status'] );
		$this->assertSame( 'files-and-database', $dry_run['scope'] );
		$this->assertSame( 0, $dry_run['failure_count'] );
		$this->assertTrue( $dry_run['restore_apply_allowed'] );
		$this->assertFalse( $dry_run['destructive_actions_performed'] );
		$this->assertFalse( $dry_run['database_imported'] );
		$this->assertFalse( $dry_run['live_files_overwritten'] );
		$this->assertFalse( $dry_run['pre_restore_backup_created'] );
		$this->assertFalse( $dry_run['restore_apply_command_available'] );

		foreach ( $dry_run['checks'] as $check ) {
			$this->assertTrue( $check['passed'], $check['name'] );
		}
	}

	public function test_restore_dry_run_fails_when_restore_apply_is_not_enabled() {
		if ( ! $this->tar_available() ) {
			$this->markTestSkipped( 'The tar command is required for restore dry-run coverage.' );
		}

		$fixture = $this->create_staged_restore_fixture(
			'example-com-20260627-151500',
			array(
				'restore_apply_enabled' => false,
			)
		);

		$result = $this->run_runner(
			'restore-dry-run',
			$fixture['config'],
			array(
				'--staged-path=' . escapeshellarg( $fixture['staged_path'] ),
				'--scope=files-and-database',
				'--format=json',
			)
		);

		$this->assertSame( 1, $result['exit_code'] );

		$dry_run = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertIsArray( $dry_run );
		$this->assertSame( 'failed', $dry_run['status'] );
		$this->assertGreaterThan( 0, $dry_run['failure_count'] );
		$this->assertFalse( $dry_run['restore_apply_allowed'] );
		$this->assertFalse( $dry_run['destructive_actions_performed'] );
		$this->assertFalse( $this->check_passed( $dry_run, 'restore_apply_enabled' ) );
	}

	public function test_restore_dry_run_fails_when_restore_report_is_missing() {
		if ( ! $this->tar_available() ) {
			$this->markTestSkipped( 'The tar command is required for restore dry-run coverage.' );
		}

		$fixture     = $this->create_staged_restore_fixture( 'example-com-20260627-153000' );
		$report_path = $fixture['staged_path'] . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json';
		unlink( $report_path );

		$result = $this->run_runner(
			'restore-dry-run',
			$fixture['config'],
			array(
				'--staged-path=' . escapeshellarg( $fixture['staged_path'] ),
				'--scope=files-and-database',
				'--format=json',
			)
		);

		$this->assertSame( 1, $result['exit_code'] );

		$dry_run = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertIsArray( $dry_run );
		$this->assertSame( 'failed', $dry_run['status'] );
		$this->assertFalse( $dry_run['restore_apply_allowed'] );
		$this->assertFalse( $this->check_passed( $dry_run, 'restore_report_readable' ) );
		$this->assertFalse( $this->check_passed( $dry_run, 'restore_report_valid_json' ) );
	}

	public function test_restore_dry_run_fails_when_report_paths_are_not_safe_segments() {
		if ( ! $this->tar_available() ) {
			$this->markTestSkipped( 'The tar command is required for restore dry-run coverage.' );
		}

		$fixture     = $this->create_staged_restore_fixture( 'example-com-20260627-154500' );
		$report_path = $fixture['staged_path'] . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json';
		$report      = json_decode( (string) file_get_contents( $report_path ), true );

		$this->assertIsArray( $report );

		$report['file_root']     = '../htdocs';
		$report['database_dump'] = 'nested/database.sql';
		file_put_contents( $report_path, json_encode( $report ) );

		$result = $this->run_runner(
			'restore-dry-run',
			$fixture['config'],
			array(
				'--staged-path=' . escapeshellarg( $fixture['staged_path'] ),
				'--scope=files-and-database',
				'--format=json',
			)
		);

		$this->assertSame( 1, $result['exit_code'] );

		$dry_run = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertIsArray( $dry_run );
		$this->assertSame( 'failed', $dry_run['status'] );
		$this->assertFalse( $this->check_passed( $dry_run, 'restore_report_file_root_safe' ) );
		$this->assertFalse( $this->check_passed( $dry_run, 'restore_report_database_dump_safe' ) );
		$this->assertFalse( $this->check_passed( $dry_run, 'staged_files_present' ) );
		$this->assertFalse( $this->check_passed( $dry_run, 'staged_database_present' ) );
	}

	/**
	 * Creates a staged restore fixture.
	 *
	 * @param string              $package_id Package ID.
	 * @param array<string,mixed> $overrides Config overrides.
	 * @return array{config:string,archive:string,staged_path:string}
	 */
	private function create_staged_restore_fixture( $package_id, array $overrides = array() ) {
		$outbox          = $this->make_directory( 'outbox-' . $package_id );
		$wordpress_path  = $this->make_directory( 'target-' . $package_id );
		$pre_backup_path = $this->make_directory( 'pre-restore-' . $package_id );
		$config          = $this->write_config(
			$outbox,
			array_merge(
				array(
					'wordpress_path'                 => $wordpress_path,
					'restore_apply_enabled'          => true,
					'restore_environment'            => 'staging',
					'restore_target_wordpress_path'  => $wordpress_path,
					'restore_pre_backup_path'        => $pre_backup_path,
					'minimum_free_space_bytes'       => 0,
				),
				$overrides
			)
		);
		$archive         = $outbox . DIRECTORY_SEPARATOR . $package_id . '.tar.gz';
		$source          = $this->make_directory( 'package-source-' . $package_id );

		mkdir( $source . DIRECTORY_SEPARATOR . 'htdocs' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "ok";' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'database.sql', '-- db' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'manifest.json', '{}' );

		$this->create_tar_archive( $archive, $source );

		$manifest = array(
			'package_id'         => $package_id,
			'site_url'           => 'https://example.com',
			'created_at'         => '2026-06-27T15:00:00+00:00',
			'producer'           => 'alynt_server_runner',
			'backup_type'        => 'logical_wordpress_backup',
			'archive_format'     => 'tar.gz',
			'file_root'          => 'htdocs',
			'database_dump'      => 'database.sql',
			'consistency_status' => 'clean',
		);
		file_put_contents( $archive . '.manifest.json', json_encode( $manifest ) );
		file_put_contents( $archive . '.sha256', hash_file( 'sha256', $archive ) . '  ' . basename( $archive ) );

		$stage = $this->run_runner( 'stage-restore', $config, array( '--package=' . escapeshellarg( $archive ) ) );
		$this->assertSame( 0, $stage['exit_code'], implode( "\n", $stage['error'] ) );

		return array(
			'config'      => $config,
			'archive'     => $archive,
			'staged_path' => $this->root . DIRECTORY_SEPARATOR . 'restores' . DIRECTORY_SEPARATOR . $package_id,
		);
	}

	/**
	 * Returns whether a named dry-run check passed.
	 *
	 * @param array<string,mixed> $dry_run Dry-run result.
	 * @param string              $name Check name.
	 * @return bool
	 */
	private function check_passed( array $dry_run, $name ) {
		foreach ( $dry_run['checks'] as $check ) {
			if ( $name === $check['name'] ) {
				return (bool) $check['passed'];
			}
		}

		$this->fail( 'Missing dry-run check: ' . $name );
	}
}
