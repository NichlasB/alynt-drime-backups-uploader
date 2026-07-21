<?php
/**
 * Production-simulation rollback extraction cleanup tests.
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
 * Runner that simulates a final rollback report write failure.
 */
class Alynt_Drime_Backups_Uploader_Rollback_Report_Failure_Runner extends Alynt_Server_Backup_Runner {
	/**
	 * Refuses the durable rollback report write.
	 *
	 * @param array<string,mixed> $result Rollback result.
	 * @return array<string,mixed>
	 */
	protected function write_production_rollback_report( array $result ) {
		$result['rollback_report_written'] = false;
		$result['rollback_report_error']   = 'Simulated rollback report write failure.';
		$result['status']                  = 'failed';
		$result['failure_step']            = 'rollback-report-write';

		return $result;
	}
}

/**
 * Covers success-only cleanup of private rollback extraction trees.
 */
class ServerRunnerProductionRollbackCleanupTest extends Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case {
	use Alynt_Drime_Backups_Uploader_Server_Runner_Production_Restore_Fixture;

	/**
	 * A completely successful rollback removes its exact private extraction tree.
	 *
	 * @return void
	 */
	public function test_successful_rollback_removes_private_extraction_tree() {
		$fixture = $this->create_production_restore_fixture( true );
		$apply   = $this->create_successful_files_apply( $fixture );
		$result  = $this->run_files_rollback( $fixture, $apply['production_apply_report_path'] );

		$this->assertSame( 0, $result['exit_code'], implode( "\n", array_merge( $result['error'], $result['output'] ) ) );
		$rollback = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'succeeded', $rollback['status'] );
		$this->assertTrue( $rollback['rollback_report_written'] );
		$this->assertTrue( $rollback['rollback_extraction_cleanup_attempted'] );
		$this->assertTrue( $rollback['rollback_extraction_cleanup_succeeded'] );
		$this->assertFalse( $rollback['rollback_extraction_retained'] );
		$this->assertDirectoryDoesNotExist( $rollback['rollback_extraction_path'] );
		$this->assertSame( array(), glob( $fixture['restore_path'] . DIRECTORY_SEPARATOR . '.rollback-*' ) );
	}

	/**
	 * Report write failure retains the extraction tree for operator recovery.
	 *
	 * @return void
	 */
	public function test_report_write_failure_retains_private_extraction_tree() {
		$fixture = $this->create_production_restore_fixture( true );
		$apply   = $this->create_successful_files_apply( $fixture );
		$config  = json_decode( file_get_contents( $fixture['config'] ), true );
		$runner  = new Alynt_Drime_Backups_Uploader_Rollback_Report_Failure_Runner( $config );
		$method  = new ReflectionMethod( Alynt_Server_Backup_Runner::class, 'restore_production_rollback_result' );

		$rollback = $method->invoke( $runner, $apply['production_apply_report_path'], 'example.com', 'example.com' );

		$this->assertSame( 'failed', $rollback['status'] );
		$this->assertSame( 'rollback-report-write', $rollback['failure_step'] );
		$this->assertTrue( $rollback['file_rollback_succeeded'] );
		$this->assertFalse( $rollback['rollback_report_written'] );
		$this->assertFalse( $rollback['rollback_extraction_cleanup_attempted'] );
		$this->assertFalse( $rollback['rollback_extraction_cleanup_succeeded'] );
		$this->assertTrue( $rollback['rollback_extraction_retained'] );
		$this->assertDirectoryExists( $rollback['rollback_extraction_path'] );
	}

	/**
	 * Creates one successful files-only apply.
	 *
	 * @param array<string,string> $fixture Fixture.
	 * @return array<string,mixed>
	 */
	private function create_successful_files_apply( array $fixture ) {
		$evidence = $this->create_production_restore_evidence( $fixture, 'files' );
		$result   = $this->run_runner(
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
		$this->assertSame( 0, $result['exit_code'], implode( "\n", array_merge( $result['error'], $result['output'] ) ) );

		return json_decode( implode( "\n", $result['output'] ), true );
	}

	/**
	 * Runs one files-only rollback.
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
}
