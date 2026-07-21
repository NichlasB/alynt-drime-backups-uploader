<?php
/**
 * Production-simulation rollback maintenance failure tests.
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
 * Runner that cannot write the emergency maintenance marker.
 */
class Alynt_Drime_Backups_Uploader_Maintenance_Fallback_Failure_Runner extends Alynt_Server_Backup_Runner {
	/**
	 * Simulates an unavailable emergency maintenance marker.
	 *
	 * @param string $target_path WordPress path.
	 * @return bool
	 */
	protected function ensure_production_maintenance_marker( $target_path ) {
		unset( $target_path );
		return false;
	}
}

/**
 * Covers rollback maintenance activation, reactivation, and deactivation.
 */
class ServerRunnerProductionRollbackMaintenanceFailureTest extends Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case {
	use Alynt_Drime_Backups_Uploader_Server_Runner_Production_Restore_Fixture;

	/**
	 * Total activation failure must stop rollback before target writes.
	 *
	 * @return void
	 */
	public function test_rollback_activation_failure_stops_before_writes() {
		$fixture = $this->create_production_restore_fixture( true );
		$apply = $this->create_successful_files_apply( $fixture );
		file_put_contents( $fixture['maintenance_activation_failure'], 'fail' );
		$config = json_decode( file_get_contents( $fixture['config'] ), true );
		$runner = new Alynt_Drime_Backups_Uploader_Maintenance_Fallback_Failure_Runner( $config );
		$method = new ReflectionMethod( Alynt_Server_Backup_Runner::class, 'restore_production_rollback_result' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$rollback = $method->invoke( $runner, $apply['production_apply_report_path'], 'example.com', 'example.com' );

		$this->assertSame( 'rollback-maintenance-activation', $rollback['failure_step'] );
		$this->assertTrue( $rollback['maintenance_activation_attempted'] );
		$this->assertFalse( $rollback['maintenance_activation_succeeded'] );
		$this->assertFalse( $rollback['maintenance_emergency_fallback_used'] );
		$this->assertFalse( $rollback['file_rollback_attempted'] );
		$this->assertFalse( $rollback['destructive_actions_performed'] );
		$this->assertSame( '<?php echo "staged";', file_get_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );
		$this->assertFileDoesNotExist( $fixture['maintenance_state'] );

		unlink( $fixture['maintenance_activation_failure'] );
		$retry = $this->run_files_rollback( $fixture, $apply['production_apply_report_path'] );
		$this->assertSame( 0, $retry['exit_code'], implode( "\n", array_merge( $retry['error'], $retry['output'] ) ) );
		$this->assertFileDoesNotExist( $fixture['maintenance_state'] );
	}

	/**
	 * Reactivation failure must preserve maintenance through fallback.
	 *
	 * @return void
	 */
	public function test_rollback_reactivation_failure_uses_emergency_marker() {
		$fixture = $this->create_production_restore_fixture( true );
		$apply = $this->create_successful_files_apply( $fixture );
		if ( is_file( $fixture['maintenance_activation_count'] ) ) {
			unlink( $fixture['maintenance_activation_count'] );
		}
		file_put_contents( $fixture['maintenance_reactivation_failure'], 'fail' );

		$result = $this->run_files_rollback( $fixture, $apply['production_apply_report_path'] );

		$this->assertSame( 1, $result['exit_code'] );
		$rollback = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'rollback-maintenance-reactivation', $rollback['failure_step'] );
		$this->assertTrue( $rollback['file_rollback_succeeded'] );
		$this->assertTrue( $rollback['maintenance_reactivation_attempted'] );
		$this->assertFalse( $rollback['maintenance_reactivation_succeeded'] );
		$this->assertTrue( $rollback['maintenance_emergency_fallback_used'] );
		$this->assertFalse( $rollback['maintenance_deactivation_attempted'] );
		$this->assertFalse( $rollback['rollback_extraction_cleanup_attempted'] );
		$this->assertTrue( $rollback['rollback_extraction_retained'] );
		$this->assertDirectoryExists( $rollback['rollback_extraction_path'] );
		$this->assertFileExists( $fixture['maintenance_state'] );

		unlink( $fixture['maintenance_reactivation_failure'] );
		$retry = $this->run_files_rollback( $fixture, $apply['production_apply_report_path'] );
		$this->assertSame( 0, $retry['exit_code'], implode( "\n", array_merge( $retry['error'], $retry['output'] ) ) );
		$this->assertFileDoesNotExist( $fixture['maintenance_state'] );
	}

	/**
	 * Deactivation failure must retain maintenance after successful checks.
	 *
	 * @return void
	 */
	public function test_rollback_deactivation_failure_can_be_retried() {
		$fixture = $this->create_production_restore_fixture( true );
		$apply = $this->create_successful_files_apply( $fixture );
		file_put_contents( $fixture['maintenance_deactivation_failure'], 'fail' );

		$result = $this->run_files_rollback( $fixture, $apply['production_apply_report_path'] );

		$this->assertSame( 1, $result['exit_code'] );
		$rollback = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'rollback-maintenance-deactivation', $rollback['failure_step'] );
		$this->assertTrue( $rollback['file_rollback_succeeded'] );
		$this->assertTrue( $rollback['post_rollback_verification_passed'] );
		$this->assertTrue( $rollback['maintenance_deactivation_attempted'] );
		$this->assertFalse( $rollback['maintenance_deactivation_succeeded'] );
		$this->assertFalse( $rollback['rollback_extraction_cleanup_attempted'] );
		$this->assertTrue( $rollback['rollback_extraction_retained'] );
		$this->assertDirectoryExists( $rollback['rollback_extraction_path'] );
		$this->assertFileExists( $fixture['maintenance_state'] );

		unlink( $fixture['maintenance_deactivation_failure'] );
		$retry = $this->run_files_rollback( $fixture, $apply['production_apply_report_path'] );
		$this->assertSame( 0, $retry['exit_code'], implode( "\n", array_merge( $retry['error'], $retry['output'] ) ) );
		$this->assertFileDoesNotExist( $fixture['maintenance_state'] );
	}

	/**
	 * Creates one successful files apply and returns its report.
	 *
	 * @param array<string,string> $fixture Fixture.
	 * @return array<string,mixed>
	 */
	private function create_successful_files_apply( array $fixture ) {
		$evidence = $this->create_production_restore_evidence( $fixture, 'files' );
		$result = $this->run_runner(
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
}
