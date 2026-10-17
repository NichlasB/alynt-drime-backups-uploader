<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ClosedWriterBarrierFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedWriterBarrierUpgradeTest extends TestCase {
	use Alynt_Test_Closed_Writer_Barrier_Fixture;
	public function test_competing_upgrade_has_one_winner_without_resetting_its_checkpoint() {
		$other = $this->new_barrier(); $expected = $this->checkpoint['lifecycle']['permission_generation'];
		$this->backend->before_swap = function () use ( $other, $expected ) { $this->assertIsArray( $other->prepare_closed( $this->binding, $expected, $this->permitted() ) ); };
		$this->barrier_error( 'stale', $this->upgrade() ); $s = $this->assert_closed_barrier(); $this->assertSame( $expected + 1, $s['lifecycle']['permission_generation'] );
	}
	public function test_failed_and_committed_but_unacknowledged_upgrade_are_not_claimed_successful() {
		$rows = $this->backend->rows; $this->backend->fail_write = true; $this->barrier_error( 'unresolved', $this->upgrade() ); $this->assertSame( $rows, $this->backend->rows ); $this->backend->fail_write = false;
		$this->backend->ack_fault = 'throw'; $this->barrier_error( 'unresolved', $this->upgrade() ); $s = $this->assert_closed_barrier(); $swaps = $this->backend->swaps;
		$this->assertSame( $s, $this->barrier->prepare_closed( $this->binding, $s['lifecycle']['permission_generation'], $this->permitted() ) ); $this->assertSame( $swaps, $this->backend->swaps );
	}
	public function test_upgrade_final_guard_and_clock_recheck_precede_the_write() {
		$rows = $this->backend->rows; $calls = 0; $this->barrier_error( 'denied', $this->upgrade( function () use ( &$calls ) { return 2 !== ++$calls; } ) ); $this->assertSame( $rows, $this->backend->rows );
		$calls = 0; $this->barrier_error( 'clock', $this->upgrade( function () use ( &$calls ) { if ( 2 === ++$calls ) $this->now--; return true; } ) ); $this->assertSame( $rows, $this->backend->rows ); $this->now++;
		$calls = 0; $s = $this->upgrade( function () use ( &$calls ) { if ( 2 === ++$calls ) $this->now++; return true; } ); $this->assertIsArray( $s ); $this->assertSame( $this->now, $s['barrier']['upgrade']['committed_at'] ); $this->assert_closed_barrier();
	}
	public function test_invalid_scope_epoch_and_guard_reject_without_reads_or_identity_generation() {
		$reads = $this->backend->reads; $rows = $this->backend->rows; $creates = $this->backend->creates;
		foreach ( array( array( $this->binding, '1', $this->permitted() ), array( 'bad', 1, $this->permitted() ), array( $this->binding, 1, true ) ) as $args ) $this->barrier_error( 'invalid', $this->barrier->prepare_closed( ...$args ) );
		$this->barrier_error( 'denied', $this->upgrade( function () { return 'true'; } ) ); $this->assertSame( $reads, $this->backend->reads ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $creates, $this->backend->creates );
	}
	public function test_clock_tick_during_upgrade_encoding_cannot_commit_an_old_timestamp() {
		$rows = $this->backend->rows;
		\Brain\Monkey\Functions\when( 'wp_json_encode' )->alias( function ( $value ) { if ( isset( $value['schema_version'] ) && 6 === $value['schema_version'] ) $this->now++; return json_encode( $value ); } );
		$this->barrier_error( 'clock', $this->upgrade() ); $this->assertSame( $rows, $this->backend->rows );
	}
}
