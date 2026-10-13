<?php
/** Contract tests for the fixture-only cooperative writer, not runtime coverage. */
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ScheduleSafetyFixture.php';
require_once __DIR__ . '/Support/ScheduleSafetyCronWriterPrototype.php';

class ScheduleSafetyCronWriterPrototypeTest extends TestCase {
	private $backend;
	private $writer;
	protected function setUp(): void {
		$this->backend = new Alynt_Drime_Backups_Uploader_Test_Safety_Memory_CAS();
		$this->backend->rows['cron'] = array( 'option_value' => serialize( Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::cron( 1000 ) ), 'autoload' => 'yes' );
		$this->writer = new Alynt_Drime_Backups_Uploader_Test_Cron_Writer_Prototype( $this->backend );
	}
	private function event( $hook = 'alynt_coordination_fixture_first' ) { return array( 'hook' => $hook, 'timestamp' => 2000, 'args' => array(), 'event' => array( 'schedule' => 'hourly', 'args' => array(), 'interval' => 3600 ) ); }
	private function cron() { return unserialize( $this->backend->rows['cron']['option_value'] ); }

	public function test_competing_unrelated_writer_rebases_delta_without_losing_events() {
		$competitor = new Alynt_Drime_Backups_Uploader_Test_Cron_Writer_Prototype( $this->backend );
		$this->backend->before_swap = function () use ( $competitor ) { $this->assertTrue( $competitor->mutate( 'schedule', $this->event( 'alynt_coordination_fixture_second' ) ) ); };
		$this->assertTrue( $this->writer->mutate( 'schedule', $this->event() ) );
		$this->assertCount( 2, $this->cron()[2000] );
		$this->assertArrayHasKey( 'alynt_drime_backups_scan_event', $this->cron()[1000] );
		$this->assertArrayHasKey( 'unrelated_fixture_event', $this->cron()[9999999999] );
	}
	public function test_owned_event_mutations_are_rejected_before_storage() {
		foreach ( array( 'schedule', 'unschedule', 'clear', 'remove_hook' ) as $operation ) {
			$this->assertSame( 'coordination_prototype_owned_event_protected', $this->writer->mutate( $operation, $this->event( Alynt_Drime_Backups_Uploader_Schedule_Safety_Cron_CAS::HOOK ) )->get_error_code() );
		}
		$this->assertSame( 0, $this->backend->swaps );
	}
	public function test_removal_preserves_other_arguments_and_returns_counts() {
		$first = $this->event(); $second = $first; $second['args'] = array( 'other' ); $second['event']['args'] = $second['args'];
		$this->writer->mutate( 'schedule', $first ); $this->writer->mutate( 'schedule', $second );
		$this->assertSame( 1, $this->writer->mutate( 'clear', $first ) );
		$this->assertCount( 1, $this->cron()[2000]['alynt_coordination_fixture_first'] );
		$this->assertSame( 1, $this->writer->mutate( 'remove_hook', $first ) );
		$this->assertSame( 0, $this->writer->mutate( 'remove_hook', $first ) );
		$this->assertTrue( $this->writer->mutate( 'unschedule', $first ) );
	}
	public function test_failed_write_and_contention_do_not_report_success() {
		$this->backend->fail_write = true;
		$this->assertSame( 'schedule_safety_write_failed', $this->writer->mutate( 'schedule', $this->event() )->get_error_code() );
		$this->backend->fail_write = false; $this->backend->conflicts = 3;
		$this->assertSame( 'coordination_prototype_contention', $this->writer->mutate( 'schedule', $this->event() )->get_error_code() );
		$this->assertArrayNotHasKey( 2000, $this->cron() );
	}
	public function test_unknown_state_and_unsupported_input_are_not_repaired() {
		$this->backend->rows['cron']['option_value'] = serialize( array( 'version' => 3 ) );
		$this->assertInstanceOf( WP_Error::class, $this->writer->mutate( 'schedule', $this->event() ) );
		$spec = $this->event(); $spec['args'] = array( new stdClass() );
		$this->assertInstanceOf( WP_Error::class, $this->writer->mutate( 'schedule', $spec ) );
		$this->assertSame( 0, $this->backend->swaps );
	}
	public function test_another_filter_short_circuit_is_not_claimed_as_our_write() {
		$event = (object) $this->event();
		$this->assertTrue( $this->writer->schedule( true, $event, true ) );
		$this->assertSame( 0, $this->backend->swaps );
	}
	public function test_malformed_cron_and_oversized_arguments_fail_before_write() {
		$this->backend->rows['cron']['option_value'] = serialize( array( 1000 => array( 'invalid_hook' => 'not-events' ), 'version' => 2 ) );
		$this->assertInstanceOf( WP_Error::class, $this->writer->mutate( 'remove_hook', $this->event() ) );
		$spec = $this->event(); $spec['args'] = array( str_repeat( 'x', 1025 ) );
		$this->assertInstanceOf( WP_Error::class, $this->writer->mutate( 'schedule', $spec ) );
		$this->assertSame( 0, $this->backend->swaps );
	}
}
