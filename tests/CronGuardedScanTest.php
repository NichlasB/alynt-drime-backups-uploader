<?php
/** Existing Cron callback integration, with fake plugin work only. */
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/GuardedScanCallbackFixture.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CronGuardedScanTest extends TestCase {
	use Alynt_Drime_Backups_Uploader_Test_Guarded_Scan_Fixture;
	protected function setUp(): void { parent::setUp(); Monkey\setUp(); Functions\when( 'wp_json_encode' )->alias( 'json_encode' ); $this->prepare_guarded_scan(); }
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }
	private function plugin( $runs, $failed = false ) {
		$plugin = $this->createMock( Alynt_Drime_Backups_Uploader_Plugin::class ); $health = $this->createMock( Alynt_Drime_Backups_Uploader_Cron_Health::class ); $logger = $this->createMock( Alynt_Drime_Backups_Uploader_Logger::class );
		$plugin->expects( $this->exactly( $runs ) )->method( 'scan_and_queue' )->willReturnCallback( function () use ( $failed ) { $this->callback_count++; return array( 'errors' => $failed ? array( 'Fixture queue save failed.' ) : array(), 'candidates' => array(), 'queued' => 0 ); } );
		$health->expects( $this->exactly( $runs ) )->method( 'record_scheduled_scan' )->willReturn( 'wp_cli' ); $health->method( 'last_record_persisted' )->willReturn( true );
		$logger->expects( $this->exactly( $runs * 2 ) )->method( 'event' ); $plugin->method( 'cron_health' )->willReturn( $health ); $plugin->method( 'logger' )->willReturn( $logger );
		$plugin->expects( $this->never() )->method( 'uploader' ); return $plugin;
	}
	public function test_default_legacy_scan_keeps_void_return_logs_and_no_model_reads_or_writes() {
		$before = $this->backend->rows; $cron = new Alynt_Drime_Backups_Uploader_Cron( $this->plugin( 1 ) );
		$this->assertNull( $cron->scan() ); $this->assertSame( $before, $this->backend->rows ); $this->assertSame( 0, $this->policy_count ); $this->assertSame( 1, $this->callback_count );
	}
	public function test_explicit_disabled_guard_never_falls_back_to_legacy_scan() {
		$before = $this->backend->rows; $cron = new Alynt_Drime_Backups_Uploader_Cron( $this->plugin( 0 ), $this->new_dispatcher( false ) );
		$this->assertNull( $cron->scan() ); $this->assertSame( $before, $this->backend->rows ); $this->assertSame( 0, $this->policy_count );
	}
	public function test_explicit_guard_invokes_existing_scan_callback_once_then_blocks_duplicate() {
		$cron = new Alynt_Drime_Backups_Uploader_Cron( $this->plugin( 1 ), $this->dispatcher ); $this->assertNull( $cron->scan() ); $this->assertNull( $cron->scan() );
		$this->assertSame( 'completed', $this->model->snapshot()['dispatch']['phase'] ); $this->assertSame( $this->now + 1800, $this->model->snapshot()['authority']['next_due_at'] );
	}
	public function test_scan_queue_failure_keeps_uncertainty_instead_of_advancing_or_retrying() {
		$cron = new Alynt_Drime_Backups_Uploader_Cron( $this->plugin( 1, true ), $this->dispatcher ); $cron->scan(); $cron->scan(); $this->assert_protected( 'recovery_required', 1 );
	}
}
