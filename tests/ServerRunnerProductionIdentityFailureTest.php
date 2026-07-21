<?php
/**
 * Production-simulation identity failure tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

/**
 * Covers post-write WP-CLI identity failures during apply and rollback.
 */
class ServerRunnerProductionIdentityFailureTest extends Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case {
	use Alynt_Drime_Backups_Uploader_Server_Runner_Production_Restore_Fixture;

	/**
	 * Apply verification failure must retain maintenance and support rollback.
	 *
	 * @return void
	 */
	public function test_post_apply_identity_failure_can_be_rolled_back() {
		$fixture = $this->create_production_restore_fixture( true );
		$evidence = $this->create_production_restore_evidence( $fixture, 'files' );
		file_put_contents( $fixture['post_write_identity_failure'], 'fail' );

		$apply_result = $this->run_files_apply( $fixture, $evidence );

		$this->assertSame( 1, $apply_result['exit_code'] );
		$apply = json_decode( implode( "\n", $apply_result['output'] ), true );
		$this->assertSame( 'post-apply-verification', $apply['failure_step'] );
		$this->assertFalse( $this->check_passed( $apply, 'post_apply_runtime_reads_passed' ) );
		$this->assertTrue( $apply['destructive_actions_performed'] );
		$this->assertTrue( $apply['production_rollback_available'] );
		$this->assertFalse( $apply['maintenance_deactivation_attempted'] );
		$this->assertFileExists( $fixture['maintenance_state'] );
		$this->assertTrue( $apply['production_apply_report_written'] );

		unlink( $fixture['post_write_identity_failure'] );
		$rollback_result = $this->run_files_rollback( $fixture, $apply['production_apply_report_path'] );
		$this->assertSame( 0, $rollback_result['exit_code'], implode( "\n", array_merge( $rollback_result['error'], $rollback_result['output'] ) ) );
		$rollback = json_decode( implode( "\n", $rollback_result['output'] ), true );
		$this->assertSame( 'succeeded', $rollback['status'] );
		$this->assertTrue( $rollback['file_rollback_succeeded'] );
		$this->assertFileDoesNotExist( $fixture['maintenance_state'] );
	}

	/**
	 * Rollback verification failure must retain maintenance and support retry.
	 *
	 * @return void
	 */
	public function test_post_rollback_identity_failure_can_be_retried() {
		$fixture = $this->create_production_restore_fixture( true );
		$evidence = $this->create_production_restore_evidence( $fixture, 'files' );
		$apply_result = $this->run_files_apply( $fixture, $evidence );
		$this->assertSame( 0, $apply_result['exit_code'], implode( "\n", array_merge( $apply_result['error'], $apply_result['output'] ) ) );
		$apply = json_decode( implode( "\n", $apply_result['output'] ), true );
		file_put_contents( $fixture['post_write_identity_failure'], 'fail' );

		$rollback_result = $this->run_files_rollback( $fixture, $apply['production_apply_report_path'] );

		$this->assertSame( 1, $rollback_result['exit_code'] );
		$rollback = json_decode( implode( "\n", $rollback_result['output'] ), true );
		$this->assertSame( 'post-rollback-verification', $rollback['failure_step'] );
		$this->assertFalse( $this->check_passed( $rollback, 'post_rollback_runtime_reads_passed' ) );
		$this->assertTrue( $rollback['destructive_actions_performed'] );
		$this->assertFalse( $rollback['maintenance_deactivation_attempted'] );
		$this->assertFileExists( $fixture['maintenance_state'] );
		$this->assertTrue( $rollback['rollback_report_written'] );

		unlink( $fixture['post_write_identity_failure'] );
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
