<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ClosedWriterBarrierFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedWriterBarrierRaceTest extends TestCase {
	use Alynt_Test_Closed_Writer_Barrier_Fixture;
	public function test_competing_begin_keeps_winners_epoch_and_original_loser_intent() {
		$this->upgrade(); $a = $this->begin_command(); $b = $this->begin_command( 2 ); $other = $this->new_barrier();
		$this->backend->before_swap = function () use ( $other, $b ) { $this->barrier_result( 'committed', $other->begin( $b, $this->permitted() ) ); };
		$this->barrier_error( 'stale', $this->barrier->begin( $a, $this->permitted() ) ); $s = $this->assert_closed_barrier(); $this->assertSame( $b, $s['barrier']['closure']['command'] );
		$this->barrier_error( 'pending', $this->barrier->begin( $this->begin_command( 3 ), $this->permitted() ) );
	}
	public function test_finish_competing_with_abandon_has_one_model_winner_without_target_effects() {
		$this->upgrade(); $c = $this->begin_command(); $this->barrier->begin( $c, $this->permitted() ); $finish = $this->terminal_command( $c ); $abandon = $this->terminal_command( $c, 3 ); $other = $this->new_barrier();
		$this->backend->before_swap = function () use ( $other, $abandon ) { $this->barrier_result( 'committed', $other->abandon( $abandon, $this->permitted() ) ); };
		$this->barrier_error( 'stale', $this->barrier->finish( $finish, $this->permitted() ) ); $s = $this->assert_closed_barrier(); $this->assertSame( 'abandon', $s['barrier']['completion']['purpose'] );
	}
	public function test_late_finisher_after_abandon_and_another_begin_cannot_clear_new_pending() {
		$this->upgrade(); $old = $this->begin_command(); $this->barrier->begin( $old, $this->permitted() ); $this->barrier->abandon( $this->terminal_command( $old ), $this->permitted() );
		$new = $this->begin_command( 3 ); $this->barrier->begin( $new, $this->permitted() ); $rows = $this->backend->rows;
		$this->barrier_error( 'stale', $this->barrier->finish( $this->terminal_command( $old, 4 ), $this->permitted() ) ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $new['command_id'], $this->assert_closed_barrier()['barrier']['pending'] );
	}
	public function test_same_hash_after_change_away_and_back_does_not_reset_epoch_or_revive_intent() {
		$this->upgrade(); $old = $this->begin_command(); $this->barrier->begin( $old, $this->permitted() ); $this->barrier->finish( $this->terminal_command( $old ), $this->permitted() );
		$back = $this->begin_command( 3 ); $back['before_hash'] = $old['after_hash']; $back['after_hash'] = $old['before_hash']; $this->barrier->begin( $back, $this->permitted() ); $this->barrier->finish( $this->terminal_command( $back, 4 ), $this->permitted() );
		$rows = $this->backend->rows; $this->barrier_result( 'observed_superseded', $this->barrier->begin( $old, $this->permitted() ) ); $this->assertSame( $rows, $this->backend->rows );
		$old['command_id'] = '55555555-5555-4555-8555-000000000005'; $this->barrier_error( 'stale', $this->barrier->begin( $old, $this->permitted() ) );
	}
	public function test_contention_is_bounded_and_never_synthesizes_another_command() {
		$this->upgrade(); $c = $this->begin_command(); $this->backend->conflicts = 3; $rows = $this->backend->rows; $swaps = $this->backend->swaps;
		$this->barrier_error( 'contention', $this->barrier->begin( $c, $this->permitted() ) ); $this->assertSame( 3, $this->backend->swaps - $swaps ); $this->assertSame( $rows, $this->backend->rows );
	}
	public function test_final_guard_loss_or_deadline_change_cannot_persist_begin() {
		$this->upgrade(); $c = $this->begin_command(); $rows = $this->backend->rows; $checks = 0;
		$this->barrier_error( 'denied', $this->barrier->begin( $c, function () use ( &$checks ) { return 2 !== ++$checks; } ) ); $this->assertSame( $rows, $this->backend->rows ); $checks = 0;
		$this->barrier_error( 'expired', $this->barrier->begin( $c, function () use ( &$checks ) { if ( 2 === ++$checks ) $this->now += 300; return true; } ) ); $this->assertSame( $rows, $this->backend->rows );
	}
	public function test_expiry_during_candidate_encoding_is_rejected_before_cas() {
		$this->upgrade(); $c = $this->begin_command(); $rows = $this->backend->rows;
		\Brain\Monkey\Functions\when( 'wp_json_encode' )->alias( function ( $value ) use ( $c ) { if ( isset( $value['schema_version'], $value['barrier']['pending'] ) && 6 === $value['schema_version'] && null !== $value['barrier']['pending'] ) $this->now = $c['expires_at']; return json_encode( $value ); } );
		$this->barrier_error( 'expired', $this->barrier->begin( $c, $this->permitted() ) ); $this->assertSame( $rows, $this->backend->rows );
	}
}
