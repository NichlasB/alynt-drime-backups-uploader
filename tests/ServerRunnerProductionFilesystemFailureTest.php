<?php
/**
 * Production-simulation filesystem verification failure tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! class_exists( 'Alynt_Server_Backup_Runner' ) ) {
	define( 'ALYNT_SERVER_BACKUP_RUNNER_LIBRARY_ONLY', true );
	ob_start();
	require_once ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/server-runner/alynt-backup-runner.php';
	ob_end_clean();
}

/**
 * Runner that reports changed ownership after staged files appear.
 */
class Alynt_Drime_Backups_Uploader_Ownership_Mismatch_Runner extends Alynt_Server_Backup_Runner {
	/**
	 * Returns a mismatched owner/group after file replacement.
	 *
	 * @param string $target_path WordPress path.
	 * @return array{owner_id:?int,group_id:?int}
	 */
	protected function production_target_owner_group( $target_path ) {
		$ownership = parent::production_target_owner_group( $target_path );
		$index     = $target_path . DIRECTORY_SEPARATOR . 'index.php';
		if ( is_file( $index ) && '<?php echo "staged";' === file_get_contents( $index ) ) {
			$ownership['owner_id'] = null === $ownership['owner_id'] ? 1001 : $ownership['owner_id'] + 1;
			$ownership['group_id'] = null === $ownership['group_id'] ? 1001 : $ownership['group_id'] + 1;
		}

		return $ownership;
	}
}

/**
 * Covers ownership and drop-in mismatches after destructive writes.
 */
class ServerRunnerProductionFilesystemFailureTest extends Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case {
	use Alynt_Drime_Backups_Uploader_Server_Runner_Production_Restore_Fixture;

	/**
	 * Ownership mismatch after apply must retain maintenance and allow rollback.
	 *
	 * @return void
	 */
	public function test_post_apply_ownership_mismatch_can_be_rolled_back() {
		$fixture = $this->create_production_restore_fixture( true );
		$evidence = $this->create_production_restore_evidence( $fixture, 'files' );
		$config = json_decode( file_get_contents( $fixture['config'] ), true );
		$runner = new Alynt_Drime_Backups_Uploader_Ownership_Mismatch_Runner( $config );
		$method = new ReflectionMethod( Alynt_Server_Backup_Runner::class, 'restore_production_apply_result' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$apply = $method->invoke( $runner, $fixture['staged_path'], 'files', 'example.com', 'example.com', $evidence );

		$this->assertSame( 'post-apply-verification', $apply['failure_step'] );
		$this->assertFalse( $this->check_passed( $apply, 'post_apply_owner_matches' ) );
		$this->assertFalse( $this->check_passed( $apply, 'post_apply_group_matches' ) );
		$this->assertTrue( $apply['destructive_actions_performed'] );
		$this->assertTrue( $apply['production_rollback_available'] );
		$this->assertFalse( $apply['maintenance_deactivation_attempted'] );
		$this->assertFileExists( $fixture['maintenance_state'] );

		$rollback_runner = new Alynt_Server_Backup_Runner( $config );
		$rollback_method = new ReflectionMethod( Alynt_Server_Backup_Runner::class, 'restore_production_rollback_result' );
		if ( PHP_VERSION_ID < 80100 ) {
			$rollback_method->setAccessible( true );
		}
		$rollback = $rollback_method->invoke( $rollback_runner, $apply['production_apply_report_path'], 'example.com', 'example.com' );
		$this->assertSame( 'succeeded', $rollback['status'] );
		$this->assertTrue( $rollback['post_rollback_verification_passed'] );
		$this->assertFileDoesNotExist( $fixture['maintenance_state'] );
	}

	/**
	 * Missing drop-in after rollback must retain maintenance and allow retry.
	 *
	 * @return void
	 */
	public function test_post_rollback_drop_in_mismatch_can_be_retried() {
		$fixture = $this->create_production_restore_fixture( true );
		$evidence = $this->create_production_restore_evidence( $fixture, 'files' );
		$apply_result = $this->run_files_apply( $fixture, $evidence );
		$this->assertSame( 0, $apply_result['exit_code'], implode( "\n", array_merge( $apply_result['error'], $apply_result['output'] ) ) );
		$apply = json_decode( implode( "\n", $apply_result['output'] ), true );
		file_put_contents( $fixture['post_write_drop_in_failure'], 'fail' );

		$rollback_result = $this->run_files_rollback( $fixture, $apply['production_apply_report_path'] );

		$this->assertSame( 1, $rollback_result['exit_code'] );
		$rollback = json_decode( implode( "\n", $rollback_result['output'] ), true );
		$this->assertSame( 'post-rollback-verification', $rollback['failure_step'] );
		$this->assertFalse( $this->check_passed( $rollback, 'post_rollback_drop_ins_match' ) );
		$this->assertTrue( $rollback['destructive_actions_performed'] );
		$this->assertFalse( $rollback['maintenance_deactivation_attempted'] );
		$this->assertFileDoesNotExist( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'object-cache.php' );
		$this->assertFileExists( $fixture['maintenance_state'] );

		unlink( $fixture['post_write_drop_in_failure'] );
		$retry_result = $this->run_files_rollback( $fixture, $apply['production_apply_report_path'] );
		$this->assertSame( 0, $retry_result['exit_code'], implode( "\n", array_merge( $retry_result['error'], $retry_result['output'] ) ) );
		$retry = json_decode( implode( "\n", $retry_result['output'] ), true );
		$this->assertSame( 'succeeded', $retry['status'] );
		$this->assertTrue( $retry['post_rollback_verification_passed'] );
		$this->assertFileDoesNotExist( $fixture['maintenance_state'] );
	}

	/**
	 * Runs files-only production apply.
	 *
	 * @param array<string,string> $fixture Fixture.
	 * @param string               $evidence Evidence path.
	 * @return array{exit_code:int,output:array<int,string>,error:array<int,string>}
	 */
	private function run_files_apply( array $fixture, $evidence ) {
		return $this->run_runner(
			'restore-production-apply',
			$fixture['config'],
			array(
				'--staged-path=' . escapeshellarg( $fixture['staged_path'] ),
				'--scope=files',
				'--pre-restore-evidence=' . escapeshellarg( $evidence ),
				'--target-site=example.com',
				'--confirm=restore-production-site',
				'--confirm-site=example.com',
				'--format=json',
			)
		);
	}

	/**
	 * Runs files-only production rollback.
	 *
	 * @param array<string,string> $fixture Fixture.
	 * @param string               $apply_report Apply report path.
	 * @return array{exit_code:int,output:array<int,string>,error:array<int,string>}
	 */
	private function run_files_rollback( array $fixture, $apply_report ) {
		return $this->run_runner(
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
	}

	/**
	 * Returns one named check result.
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

		return false;
	}
}
