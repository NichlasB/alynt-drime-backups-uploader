<?php
/**
 * Production-simulation apply failure tests.
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
 * Runner that interrupts production file copying after one successful file.
 */
class Alynt_Drime_Backups_Uploader_Interrupting_Copy_Runner extends Alynt_Server_Backup_Runner {
	/**
	 * Number of attempted file copies.
	 *
	 * @var int
	 */
	private $copy_count = 0;

	/**
	 * Copies the first file and refuses the second.
	 *
	 * @param string $source Source file.
	 * @param string $target Target file.
	 * @return bool
	 */
	protected function copy_restore_file( $source, $target ) {
		++$this->copy_count;
		if ( 1 < $this->copy_count ) {
			return false;
		}

		return parent::copy_restore_file( $source, $target );
	}
}

/**
 * Covers deterministic maintenance failure handling during production apply.
 */
class ServerRunnerProductionApplyFailureTest extends Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case {
	use Alynt_Drime_Backups_Uploader_Server_Runner_Production_Restore_Fixture;

	/**
	 * Initial maintenance failure must stop before target writes.
	 *
	 * @return void
	 */
	public function test_initial_maintenance_activation_failure_stops_before_target_writes() {
		$fixture = $this->create_production_restore_fixture( true );
		$evidence = $this->create_production_restore_evidence( $fixture, 'files' );
		file_put_contents( $fixture['maintenance_activation_failure'], 'fail' );

		$result = $this->run_apply_failure_fixture( $fixture, $evidence );

		$this->assertSame( 1, $result['exit_code'] );
		$apply = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'maintenance-activation', $apply['failure_step'] );
		$this->assertTrue( $apply['maintenance_activation_attempted'] );
		$this->assertFalse( $apply['maintenance_activation_succeeded'] );
		$this->assertFalse( $apply['file_restore_attempted'] );
		$this->assertFalse( $apply['destructive_actions_performed'] );
		$this->assertSame( '<?php echo "before";', file_get_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );
		$this->assertFileDoesNotExist( $fixture['maintenance_state'] );
		$this->assertTrue( $apply['production_apply_report_written'] );
	}

	/**
	 * Reactivation failure after file replacement must remain rollback-oriented.
	 *
	 * @return void
	 */
	public function test_maintenance_reactivation_failure_keeps_controlled_failure_state() {
		$fixture = $this->create_production_restore_fixture( true );
		$evidence = $this->create_production_restore_evidence( $fixture, 'files' );
		file_put_contents( $fixture['maintenance_reactivation_failure'], 'fail' );

		$result = $this->run_apply_failure_fixture( $fixture, $evidence );

		$this->assertSame( 1, $result['exit_code'] );
		$apply = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'maintenance-reactivation', $apply['failure_step'] );
		$this->assertTrue( $apply['maintenance_activation_succeeded'] );
		$this->assertTrue( $apply['file_restore_attempted'] );
		$this->assertTrue( $apply['file_restore_succeeded'] );
		$this->assertTrue( $apply['destructive_actions_performed'] );
		$this->assertTrue( $apply['live_files_overwritten'] );
		$this->assertTrue( $apply['maintenance_reactivation_attempted'] );
		$this->assertFalse( $apply['maintenance_reactivation_succeeded'] );
		$this->assertTrue( $apply['maintenance_emergency_fallback_used'] );
		$this->assertFalse( $apply['maintenance_deactivation_attempted'] );
		$this->assertTrue( $apply['production_rollback_available'] );
		$this->assertSame( '<?php echo "staged";', file_get_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );
		$this->assertFileExists( $fixture['maintenance_state'] );
		$this->assertTrue( $apply['production_apply_report_written'] );
		$this->assertStringContainsString( 'restore-production-rollback', implode( ' ', $apply['manual_recovery_notes'] ) );
	}

	/**
	 * A partial file copy must be reported as destructive and rollback-ready.
	 *
	 * @return void
	 */
	public function test_file_copy_interruption_keeps_maintenance_and_rollback_guidance() {
		$fixture = $this->create_production_restore_fixture( true );
		$evidence = $this->create_production_restore_evidence( $fixture, 'files' );
		$config = json_decode( file_get_contents( $fixture['config'] ), true );
		$runner = new Alynt_Drime_Backups_Uploader_Interrupting_Copy_Runner( $config );

		$method = new ReflectionMethod( Alynt_Server_Backup_Runner::class, 'restore_production_apply_result' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		$apply = $method->invoke(
			$runner,
			$fixture['staged_path'],
			'files',
			'example.com',
			'example.com',
			$evidence
		);

		$this->assertSame( 'failed', $apply['status'] );
		$this->assertSame( 'file-restore', $apply['failure_step'] );
		$this->assertTrue( $apply['maintenance_activation_succeeded'] );
		$this->assertTrue( $apply['file_restore_attempted'] );
		$this->assertFalse( $apply['file_restore_succeeded'] );
		$this->assertTrue( $apply['destructive_actions_performed'] );
		$this->assertTrue( $apply['live_files_overwritten'] );
		$this->assertTrue( $apply['maintenance_reactivation_attempted'] );
		$this->assertTrue( $apply['maintenance_reactivation_succeeded'] );
		$this->assertFalse( $apply['maintenance_deactivation_attempted'] );
		$this->assertFileExists( $fixture['maintenance_state'] );
		$this->assertTrue( $apply['production_apply_report_written'] );
		$this->assertStringContainsString( 'restore-production-rollback', implode( ' ', $apply['manual_recovery_notes'] ) );
		$this->assertNotSame( '<?php echo "before";', @file_get_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );

		$persisted = json_decode( file_get_contents( $apply['production_apply_report_path'] ), true );
		$this->assertSame( 'file-restore', $persisted['failure_step'] );
		$this->assertTrue( $persisted['live_files_overwritten'] );

		$rollback_runner = new Alynt_Server_Backup_Runner( $config );
		$rollback_method = new ReflectionMethod( Alynt_Server_Backup_Runner::class, 'restore_production_rollback_result' );
		if ( PHP_VERSION_ID < 80100 ) {
			$rollback_method->setAccessible( true );
		}
		$rollback = $rollback_method->invoke(
			$rollback_runner,
			$apply['production_apply_report_path'],
			'example.com',
			'example.com'
		);

		$this->assertSame( 'succeeded', $rollback['status'], json_encode( $rollback ) );
		$this->assertGreaterThan( 0, $rollback['preflight_failure_count'] );
		$this->assertSame( 0, $rollback['preflight_blocking_failure_count'] );
		$this->assertTrue( $rollback['file_rollback_succeeded'] );
		$this->assertTrue( $rollback['post_rollback_verification_passed'] );
		$this->assertSame( '<?php echo "before";', file_get_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );
		$this->assertFileDoesNotExist( $fixture['maintenance_state'] );
	}

	/**
	 * Runs files-only production apply with exact confirmation values.
	 *
	 * @param array<string,string> $fixture Fixture.
	 * @param string               $evidence Evidence path.
	 * @return array{exit_code:int,output:array<int,string>,error:array<int,string>}
	 */
	private function run_apply_failure_fixture( array $fixture, $evidence ) {
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
}
