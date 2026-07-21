<?php
/**
 * Combined production-simulation restore tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

/**
 * Covers files-first combined apply and rollback recovery.
 */
class ServerRunnerProductionCombinedTest extends Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case {
	use Alynt_Drime_Backups_Uploader_Server_Runner_Production_Restore_Fixture;

	/**
	 * Combined apply and rollback complete under their shared safety gates.
	 *
	 * @return void
	 */
	public function test_combined_apply_and_rollback_succeed() {
		$fixture  = $this->create_production_restore_fixture( true );
		$evidence = $this->create_production_restore_evidence( $fixture, 'files-and-database' );

		$apply_result = $this->run_combined_apply( $fixture, $evidence );

		$this->assertSame( 0, $apply_result['exit_code'], implode( "\n", array_merge( $apply_result['error'], $apply_result['output'] ) ) );
		$apply = json_decode( implode( "\n", $apply_result['output'] ), true );
		$this->assertSame( 'succeeded', $apply['status'] );
		$this->assertSame( 'files-and-database', $apply['scope'] );
		$this->assertSame( array( 'files', 'database' ), $apply['combined_restore_order'] );
		$this->assertTrue( $apply['file_restore_succeeded'] );
		$this->assertTrue( $apply['database_import_succeeded'] );
		$this->assertTrue( $apply['database_imported'] );
		$this->assertTrue( $apply['maintenance_reactivation_succeeded'] );
		$this->assertTrue( $apply['post_apply_verification_passed'] );
		$this->assertTrue( $apply['maintenance_deactivation_succeeded'] );
		$this->assertTrue( $apply['production_rollback_available'] );
		$this->assertSame( '<?php echo "staged";', file_get_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );
		$this->assertFileDoesNotExist( $fixture['maintenance_state'] );

		$rollback_result = $this->run_combined_rollback( $fixture, $apply['production_apply_report_path'] );

		$this->assertSame( 0, $rollback_result['exit_code'], implode( "\n", array_merge( $rollback_result['error'], $rollback_result['output'] ) ) );
		$rollback = json_decode( implode( "\n", $rollback_result['output'] ), true );
		$this->assertSame( 'succeeded', $rollback['status'] );
		$this->assertTrue( $rollback['file_rollback_succeeded'] );
		$this->assertTrue( $rollback['database_rollback_succeeded'] );
		$this->assertTrue( $rollback['post_rollback_verification_passed'] );
		$this->assertTrue( $rollback['maintenance_deactivation_succeeded'] );
		$this->assertSame( '<?php echo "before";', file_get_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );
		$this->assertFileDoesNotExist( $fixture['maintenance_state'] );
		$this->assertGreaterThanOrEqual( 2, substr_count( (string) file_get_contents( $fixture['wp_cli_log'] ), 'db import' ) );
	}

	/**
	 * A database failure after file replacement remains fully rollback-ready.
	 *
	 * @return void
	 */
	public function test_combined_database_failure_after_files_can_be_rolled_back() {
		$fixture  = $this->create_production_restore_fixture( true );
		$evidence = $this->create_production_restore_evidence( $fixture, 'files-and-database' );
		file_put_contents( $fixture['database_import_failure'], 'fail' );

		$apply_result = $this->run_combined_apply( $fixture, $evidence );

		$this->assertSame( 1, $apply_result['exit_code'] );
		$apply = json_decode( implode( "\n", $apply_result['output'] ), true );
		$this->assertSame( 'database-import', $apply['failure_step'] );
		$this->assertTrue( $apply['file_restore_succeeded'] );
		$this->assertTrue( $apply['database_import_attempted'] );
		$this->assertFalse( $apply['database_import_succeeded'] );
		$this->assertTrue( $apply['database_may_be_modified'] );
		$this->assertTrue( $apply['production_rollback_available'] );
		$this->assertSame( '<?php echo "staged";', file_get_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );
		$this->assertFileExists( $fixture['maintenance_state'] );

		unlink( $fixture['database_import_failure'] );
		$rollback_result = $this->run_combined_rollback( $fixture, $apply['production_apply_report_path'] );

		$this->assertSame( 0, $rollback_result['exit_code'], implode( "\n", array_merge( $rollback_result['error'], $rollback_result['output'] ) ) );
		$rollback = json_decode( implode( "\n", $rollback_result['output'] ), true );
		$this->assertSame( 'succeeded', $rollback['status'] );
		$this->assertTrue( $rollback['file_rollback_succeeded'] );
		$this->assertTrue( $rollback['database_rollback_succeeded'] );
		$this->assertSame( '<?php echo "before";', file_get_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );
		$this->assertFileDoesNotExist( $fixture['maintenance_state'] );
	}

	/**
	 * Runs combined production apply.
	 *
	 * @param array<string,string> $fixture Fixture.
	 * @param string               $evidence Evidence path.
	 * @return array{exit_code:int,output:array<int,string>,error:array<int,string>}
	 */
	private function run_combined_apply( array $fixture, $evidence ) {
		return $this->run_runner(
			'restore-production-apply',
			$fixture['config'],
			array(
				'--staged-path=' . escapeshellarg( $fixture['staged_path'] ),
				'--scope=files-and-database',
				'--pre-restore-evidence=' . escapeshellarg( $evidence ),
				'--target-site=example.com',
				'--confirm=restore-production-site',
				'--confirm-site=example.com',
				'--format=json',
			)
		);
	}

	/**
	 * Runs combined production rollback.
	 *
	 * @param array<string,string> $fixture Fixture.
	 * @param string               $apply_report Apply report path.
	 * @return array{exit_code:int,output:array<int,string>,error:array<int,string>}
	 */
	private function run_combined_rollback( array $fixture, $apply_report ) {
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
