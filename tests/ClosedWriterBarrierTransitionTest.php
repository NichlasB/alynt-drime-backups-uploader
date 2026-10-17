<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ClosedWriterBarrierFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedWriterBarrierTransitionTest extends TestCase {
	use Alynt_Test_Closed_Writer_Barrier_Fixture;
	public function test_explicit_upgrade_freezes_checkpoint_and_advances_all_counters_once() {
		$s = $this->upgrade(); $this->assertSame( $s, $this->assert_closed_barrier() );
		foreach ( array( 'generation', 'schedule_revision' ) as $key ) $this->assertSame( $this->checkpoint[$key] + 1, $s[$key] );
		$this->assertSame( $this->checkpoint['lifecycle']['permission_generation'] + 1, $s['lifecycle']['permission_generation'] ); $this->assertNull( $s['barrier']['pending'] );
		$swaps = $this->backend->swaps; $this->assertSame( $s, $this->barrier->prepare_closed( $this->binding, $s['lifecycle']['permission_generation'], $this->permitted() ) ); $this->assertSame( $swaps, $this->backend->swaps );
		$this->assertInstanceOf( WP_Error::class, $this->closed->snapshot() ); $this->assertInstanceOf( WP_Error::class, $this->ledger->snapshot() );
	}
	public function test_begin_and_finish_keep_closure_distinct_from_fixture_completion() {
		$this->upgrade(); $c = $this->begin_command(); $this->barrier_result( 'committed', $this->barrier->begin( $c, $this->permitted() ) ); $pending = $this->assert_closed_barrier();
		$this->assertSame( $c['command_id'], $pending['barrier']['pending'] ); $closure = $pending['barrier']['closure']; $this->assertNull( $pending['barrier']['completion'] );
		$this->barrier_result( 'committed', $this->barrier->finish( $this->terminal_command( $c ), $this->permitted() ) ); $s = $this->assert_closed_barrier();
		$this->assertNull( $s['barrier']['pending'] ); $this->assertSame( $closure, $s['barrier']['closure'] ); $this->assertSame( 'finish', $s['barrier']['completion']['purpose'] );
		$this->assertSame( $pending['lifecycle']['permission_generation'] + 1, $s['lifecycle']['permission_generation'] );
	}
	public function test_expiry_never_clears_pending_but_fresh_abandon_can_close_without_effect() {
		$this->upgrade(); $c = $this->begin_command(); $this->barrier->begin( $c, $this->permitted() ); $this->now += 301; $rows = $this->backend->rows;
		$this->barrier_error( 'expired', $this->barrier->finish( $this->terminal_command( $c ), $this->permitted() ) ); $this->assertSame( $rows, $this->backend->rows );
		$end = $this->terminal_command( $c ); $end['issued_at'] = $this->now; $end['expires_at'] = $this->now + 300; $end['observed_hash'] = $c['before_hash'];
		$this->barrier_result( 'committed', $this->barrier->abandon( $end, $this->permitted() ) ); $s = $this->assert_closed_barrier(); $this->assertNull( $s['barrier']['pending'] );
		$this->assertSame( 'abandon', $s['barrier']['completion']['purpose'] );
	}
	public function test_allowlisted_connection_descriptor_contains_names_and_hashes_only() {
		$this->upgrade(); $c = $this->begin_command(); $c['kind'] = 'connection_patch'; $c['target'] = 'connection'; $c['fields'] = array( 'action_key_id', 'dashboard_origin' );
		$this->barrier_result( 'committed', $this->barrier->begin( $c, $this->permitted() ) ); $s = $this->assert_closed_barrier(); $this->assertSame( $c, $s['barrier']['closure']['command'] ); $this->assertArrayNotHasKey( 'values', $s['barrier']['closure']['command'] );
		$this->barrier_result( 'committed', $this->barrier->abandon( $this->terminal_command( $c ), $this->permitted() ) ); $this->assert_closed_barrier();
	}
}
