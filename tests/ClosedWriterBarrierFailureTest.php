<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ClosedWriterBarrierFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedWriterBarrierFailureTest extends TestCase {
	use Alynt_Test_Closed_Writer_Barrier_Fixture;
	public function test_definitive_failed_begin_keeps_closed_state_and_failed_terminal_keeps_pending() {
		$this->upgrade(); $c = $this->begin_command(); $rows = $this->backend->rows; $this->backend->fail_write = true;
		$this->barrier_error( 'unresolved', $this->barrier->begin( $c, $this->permitted() ) ); $this->assertSame( $rows, $this->backend->rows );
		$this->backend->fail_write = false; $this->barrier->begin( $c, $this->permitted() ); $rows = $this->backend->rows; $this->backend->fail_write = true;
		foreach ( array( 'finish', 'abandon' ) as $method ) { $this->barrier_error( 'unresolved', $this->barrier->$method( $this->terminal_command( $c ), $this->permitted() ) ); $this->assertSame( $rows, $this->backend->rows ); }
		$this->assertSame( $c['command_id'], $this->assert_closed_barrier()['barrier']['pending'] );
	}
	/** @dataProvider acknowledgement_faults */
	public function test_committed_then_lost_ack_is_observed_without_begin_replay( $fault ) {
		$this->upgrade(); $c = $this->begin_command(); $this->backend->ack_fault = $fault; $result = $this->barrier->begin( $c, $this->permitted() );
		if ( 'false' === $fault ) $this->barrier_result( 'observed_current', $result ); else $this->barrier_error( 'unresolved', $result );
		$s = $this->assert_closed_barrier(); $this->assertSame( $c['command_id'], $s['barrier']['pending'] ); $rows = $this->backend->rows; $swaps = $this->backend->swaps;
		$this->barrier_result( 'observed_current', $this->barrier->begin( $c, $this->permitted() ) ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $swaps, $this->backend->swaps );
	}
	public function acknowledgement_faults() { return array( array( 'false' ), array( 'throw' ), array( 'error' ) ); }
	public function test_terminal_lost_ack_retains_separate_original_closure_and_no_new_effect() {
		$this->upgrade(); $c = $this->begin_command(); $this->barrier->begin( $c, $this->permitted() ); $closure = $this->origin()['barrier']['closure']; $end = $this->terminal_command( $c ); $this->backend->ack_fault = 'throw';
		$this->barrier_error( 'unresolved', $this->barrier->finish( $end, $this->permitted() ) ); $s = $this->assert_closed_barrier(); $this->assertNull( $s['barrier']['pending'] ); $this->assertSame( $closure, $s['barrier']['closure'] );
		$swaps = $this->backend->swaps; $this->barrier_result( 'observed_current', $this->barrier->receipt( 'closed_writer_fixture_v1', $end['command_id'] ) ); $this->assertSame( $swaps, $this->backend->swaps );
	}
	public function test_read_or_clock_failure_does_not_mutate_and_provider_details_are_redacted() {
		$this->upgrade(); $c = $this->begin_command(); $rows = $this->backend->rows; $this->backend->throw_read = true; $this->barrier_error( 'unresolved', $this->barrier->snapshot() ); $this->barrier_error( 'unresolved', $this->barrier->begin( $c, $this->permitted() ) ); $this->backend->throw_read = false;
		foreach ( array( '1800000000', 0, PHP_INT_MAX, $this->now - 1 ) as $now ) { $this->now = $now; $this->barrier_error( 'clock', $this->barrier->begin( $c, $this->permitted() ) ); $this->assertSame( $rows, $this->backend->rows ); }
	}
	public function test_wrong_observed_hash_foreign_owner_or_renewed_finish_never_clears_pending() {
		$this->upgrade(); $c = $this->begin_command(); $this->barrier->begin( $c, $this->permitted() ); $end = $this->terminal_command( $c ); $rows = $this->backend->rows;
		foreach ( array( 'observed_hash' => str_repeat( 'e', 64 ), 'operation_id' => $this->site, 'closure_generation' => 999, 'expires_at' => $end['expires_at'] - 1 ) as $field => $value ) { $bad = $end; $bad[$field] = $value; $this->barrier_error( 'context', $this->barrier->finish( $bad, $this->permitted() ) ); $this->assertSame( $rows, $this->backend->rows ); }
	}
}
