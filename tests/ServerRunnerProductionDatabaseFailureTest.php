<?php
/**
 * Production-simulation database failure tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

/**
 * Covers failed database apply, rollback, and recovery retry behavior.
 */
class ServerRunnerProductionDatabaseFailureTest extends Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case {
	use Alynt_Drime_Backups_Uploader_Server_Runner_Production_Restore_Fixture;

	/**
	 * A failed apply import must remain rollback-ready.
	 *
	 * @return void
	 */
	public function test_database_apply_failure_can_be_rolled_back() {
		$fixture = $this->create_production_restore_fixture( true );
		$evidence = $this->create_production_restore_evidence( $fixture, 'database' );
		file_put_contents( $fixture['database_import_failure'], 'fail' );

		$apply_result = $this->run_database_apply( $fixture, $evidence );

		$this->assertSame( 1, $apply_result['exit_code'] );
		$apply = json_decode( implode( "\n", $apply_result['output'] ), true );
		$this->assertSame( 'database-import', $apply['failure_step'] );
		$this->assertTrue( $apply['database_import_attempted'] );
		$this->assertFalse( $apply['database_import_succeeded'] );
		$this->assertFalse( $apply['database_imported'] );
		$this->assertTrue( $apply['database_may_be_modified'] );
		$this->assertTrue( $apply['destructive_actions_performed'] );
		$this->assertFileExists( $fixture['maintenance_state'] );
		$this->assertTrue( $apply['production_apply_report_written'] );
		$this->assertStringContainsString( 'restore-production-rollback', implode( ' ', $apply['manual_recovery_notes'] ) );

		unlink( $fixture['database_import_failure'] );
		$rollback_result = $this->run_database_rollback( $fixture, $apply['production_apply_report_path'] );
		$this->assertSame( 0, $rollback_result['exit_code'], implode( "\n", array_merge( $rollback_result['error'], $rollback_result['output'] ) ) );
		$rollback = json_decode( implode( "\n", $rollback_result['output'] ), true );
		$this->assertSame( 'succeeded', $rollback['status'] );
		$this->assertTrue( $rollback['database_rollback_succeeded'] );
		$this->assertTrue( $rollback['database_may_be_modified'] );
		$this->assertFileDoesNotExist( $fixture['maintenance_state'] );
	}

	/**
	 * A failed rollback import must retain maintenance and support retry.
	 *
	 * @return void
	 */
	public function test_database_rollback_failure_can_be_retried() {
		$fixture = $this->create_production_restore_fixture( true );
		$evidence = $this->create_production_restore_evidence( $fixture, 'database' );
		$apply_result = $this->run_database_apply( $fixture, $evidence );
		$this->assertSame( 0, $apply_result['exit_code'], implode( "\n", array_merge( $apply_result['error'], $apply_result['output'] ) ) );
		$apply = json_decode( implode( "\n", $apply_result['output'] ), true );
		file_put_contents( $fixture['database_import_failure'], 'fail' );

		$rollback_result = $this->run_database_rollback( $fixture, $apply['production_apply_report_path'] );

		$this->assertSame( 1, $rollback_result['exit_code'] );
		$rollback = json_decode( implode( "\n", $rollback_result['output'] ), true );
		$this->assertSame( 'database-rollback', $rollback['failure_step'] );
		$this->assertTrue( $rollback['database_rollback_attempted'] );
		$this->assertFalse( $rollback['database_rollback_succeeded'] );
		$this->assertFalse( $rollback['database_imported'] );
		$this->assertTrue( $rollback['database_may_be_modified'] );
		$this->assertTrue( $rollback['destructive_actions_performed'] );
		$this->assertFileExists( $fixture['maintenance_state'] );
		$this->assertTrue( $rollback['rollback_report_written'] );

		unlink( $fixture['database_import_failure'] );
		$retry_result = $this->run_database_rollback( $fixture, $apply['production_apply_report_path'] );
		$this->assertSame( 0, $retry_result['exit_code'], implode( "\n", array_merge( $retry_result['error'], $retry_result['output'] ) ) );
		$retry = json_decode( implode( "\n", $retry_result['output'] ), true );
		$this->assertSame( 'succeeded', $retry['status'] );
		$this->assertTrue( $retry['database_rollback_succeeded'] );
		$this->assertFileDoesNotExist( $fixture['maintenance_state'] );
	}

	/**
	 * Runs database-only production apply.
	 *
	 * @param array<string,string> $fixture Fixture.
	 * @param string               $evidence Evidence path.
	 * @return array{exit_code:int,output:array<int,string>,error:array<int,string>}
	 */
	private function run_database_apply( array $fixture, $evidence ) {
		return $this->run_runner(
			'restore-production-apply',
			$fixture['config'],
			array(
				'--staged-path=' . escapeshellarg( $fixture['staged_path'] ),
				'--scope=database',
				'--pre-restore-evidence=' . escapeshellarg( $evidence ),
				'--target-site=example.com',
				'--confirm=restore-production-site',
				'--confirm-site=example.com',
				'--format=json',
			)
		);
	}

	/**
	 * Runs database-only production rollback.
	 *
	 * @param array<string,string> $fixture Fixture.
	 * @param string               $apply_report Apply report path.
	 * @return array{exit_code:int,output:array<int,string>,error:array<int,string>}
	 */
	private function run_database_rollback( array $fixture, $apply_report ) {
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
}
