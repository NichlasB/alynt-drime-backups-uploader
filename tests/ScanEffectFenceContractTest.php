<?php
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-scan-effect-fence-contract.php';

/** Pure traces, NOT native transactions, grants or WordPress integration. */
class ScanEffectFenceContractTest extends TestCase {
	private function event( $name, $changes = array() ) {
		$identity = array( 'operation' => str_repeat( 'a', 64 ), 'generation' => 1, 'intent' => str_repeat( 'b', 64 ), 'context' => str_repeat( 'c', 64 ) );
		$fields = array(
			'register' => $identity + array( 'entry' => 'scheduled' ),
			'prepare' => $identity + array( 'target' => 'queue' ),
			'effect' => $identity + array( 'target' => 'queue', 'ack' => 'confirmed' ),
			'terminal' => $identity,
			'close' => array( 'generation' => 1 ),
			'barrier' => array( 'generation' => 2, 'participants' => 'all_modeled' ),
			'drift' => array( 'target' => 'queue' ),
		);
		return array_replace( array( 'event' => $name ) + $fields[$name], $changes );
	}
	private function run_trace( $events, $generation = 1 ) {
		$result = Alynt_Drime_Backups_Uploader_Scan_Effect_Fence_Contract::simulate( $generation, $events );
		$this->assertTrue( $result['candidate_only'] );
		foreach ( array( 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $key ) $this->assertFalse( $result[$key] );
		return $result;
	}
	public function test_effect_before_closure_remains_draining_until_terminal_and_barrier() {
		$events = array_map( array( $this, 'event' ), array( 'register', 'prepare', 'effect', 'close', 'barrier' ) );
		$r = $this->run_trace( $events );
		$this->assertSame( array( 'registered_candidate', 'prepared_candidate', 'effect_committed_candidate', 'ingress_closed_candidate', 'rejected' ), $r['outcomes'] );
		$this->assertSame( 1, $r['committed_effects'] ); $this->assertSame( 1, $r['pending_count'] ); $this->assertFalse( $r['quiescent_candidate'] );
		$events[] = $this->event( 'terminal' ); $events[] = $this->event( 'barrier' );
		$r = $this->run_trace( $events ); $this->assertTrue( $r['quiescent_candidate'] ); $this->assertSame( 0, $r['pending_count'] );
	}
	public function test_closure_wins_before_snapshot_and_queue_effects() {
		foreach ( array( 'snapshot', 'queue' ) as $target ) {
			$r = $this->run_trace( array( $this->event( 'register' ), $this->event( 'prepare', array( 'target' => $target ) ), $this->event( 'close' ), $this->event( 'effect', array( 'target' => $target ) ), $this->event( 'register', array( 'operation' => str_repeat( 'd', 64 ), 'generation' => 2 ) ) ) );
			$this->assertSame( array( 'registered_candidate', 'prepared_candidate', 'ingress_closed_candidate', 'rejected', 'rejected' ), $r['outcomes'] ); $this->assertSame( 0, $r['committed_effects'] );
		}
	}
	public function test_both_target_effects_need_their_own_preparation_and_terminal_is_single_use() {
		$events = array( $this->event( 'register' ), $this->event( 'prepare', array( 'target' => 'snapshot' ) ), $this->event( 'effect' ), $this->event( 'effect', array( 'target' => 'snapshot' ) ), $this->event( 'prepare' ), $this->event( 'effect' ), $this->event( 'terminal' ), $this->event( 'terminal' ), $this->event( 'close' ), $this->event( 'barrier' ) );
		$r = $this->run_trace( $events );
		$this->assertSame( array( 'registered_candidate', 'prepared_candidate', 'rejected', 'effect_committed_candidate', 'prepared_candidate', 'effect_committed_candidate', 'terminal_candidate', 'rejected', 'ingress_closed_candidate', 'quiescent_candidate' ), $r['outcomes'] );
		$this->assertSame( 2, $r['committed_effects'] ); $this->assertTrue( $r['quiescent_candidate'] ); $this->assertFalse( $r['unresolved'] );
	}
	public function test_maximum_closing_generation_and_closed_before_start_are_bounded() {
		$r = $this->run_trace( array( $this->event( 'close', array( 'generation' => PHP_INT_MAX - 1 ) ), $this->event( 'barrier', array( 'generation' => PHP_INT_MAX ) ) ), PHP_INT_MAX - 1 );
		$this->assertSame( array( 'ingress_closed_candidate', 'quiescent_candidate' ), $r['outcomes'] );
		$r = $this->run_trace( array( $this->event( 'close' ), $this->event( 'register' ), $this->event( 'barrier' ) ) ); $this->assertSame( array( 'ingress_closed_candidate', 'rejected', 'quiescent_candidate' ), $r['outcomes'] );
	}
	public function test_exact_owned_revalidation_and_foreign_pending_work_are_distinct() {
		$prefix = array( $this->event( 'register' ) );
		$r = $this->run_trace( array_merge( $prefix, array( $this->event( 'register', array( 'operation' => str_repeat( 'd', 64 ) ) ), $this->event( 'prepare' ), $this->event( 'effect' ) ) ) );
		$this->assertSame( array( 'registered_candidate', 'rejected', 'prepared_candidate', 'effect_committed_candidate' ), $r['outcomes'] );
		foreach ( array( 'operation' => str_repeat( 'd', 64 ), 'intent' => str_repeat( 'd', 64 ), 'context' => str_repeat( 'd', 64 ), 'generation' => 2 ) as $key => $value ) {
			$r = $this->run_trace( array_merge( $prefix, array( $this->event( 'prepare' ), $this->event( 'effect', array( $key => $value ) ), $this->event( 'terminal', array( $key => $value ) ) ) ) );
			$this->assertSame( array( 'registered_candidate', 'prepared_candidate', 'rejected', 'rejected' ), $r['outcomes'], $key ); $this->assertSame( 1, $r['pending_count'] );
		}
	}
	public function test_unknown_commit_retains_registration_and_forbids_replay_or_terminal_guess() {
		$r = $this->run_trace( array( $this->event( 'register' ), $this->event( 'prepare' ), $this->event( 'effect', array( 'ack' => 'unknown' ) ), $this->event( 'effect' ), $this->event( 'terminal' ), $this->event( 'close' ), $this->event( 'barrier' ) ) );
		$this->assertSame( array( 'registered_candidate', 'prepared_candidate', 'effect_unresolved', 'rejected', 'rejected', 'rejected', 'rejected' ), $r['outcomes'] );
		$this->assertTrue( $r['unresolved'] ); $this->assertSame( 1, $r['pending_count'] ); $this->assertSame( 0, $r['committed_effects'] );
	}
	public function test_stale_prepared_target_cannot_resurrect_uploaded_queue_or_snapshot() {
		foreach ( array( 'snapshot', 'queue' ) as $target ) {
			$r = $this->run_trace( array( $this->event( 'register' ), $this->event( 'prepare', array( 'target' => $target ) ), $this->event( 'drift', array( 'target' => $target ) ), $this->event( 'effect', array( 'target' => $target ) ) ) );
			$this->assertSame( 'rejected', $r['outcomes'][3] ); $this->assertSame( 0, $r['committed_effects'] );
		}
	}
	public function test_no_effect_without_prepare_and_no_reuse_after_ack_or_rejection() {
		foreach ( array( 'confirmed', 'rejected' ) as $ack ) {
			$r = $this->run_trace( array( $this->event( 'register' ), $this->event( 'effect' ), $this->event( 'prepare' ), $this->event( 'effect', array( 'ack' => $ack ) ), $this->event( 'effect' ) ) );
			$this->assertSame( 'rejected', $r['outcomes'][1] ); $this->assertSame( 'rejected', $r['outcomes'][4] ); $this->assertSame( 'confirmed' === $ack ? 1 : 0, $r['committed_effects'] );
		}
	}
	public function test_no_aba_reopen_entrypoint_bypass_or_duplicate_identity() {
		foreach ( array( 'admin', 'cli', 'remote' ) as $entry ) {
			$r = $this->run_trace( array( $this->event( 'register', array( 'entry' => $entry ) ) ) ); $this->assertSame( array( 'rejected' ), $r['outcomes'] );
		}
		$r = $this->run_trace( array( $this->event( 'register', array( 'generation' => 2 ) ), $this->event( 'register' ), $this->event( 'terminal' ), $this->event( 'register' ) ) );
		$this->assertSame( array( 'rejected', 'registered_candidate', 'terminal_candidate', 'rejected' ), $r['outcomes'] );
		$r = $this->run_trace( array( $this->event( 'close' ), array( 'event' => 'reopen', 'generation' => 1 ), $this->event( 'register' ), $this->event( 'barrier' ) ) ); $this->assertTrue( $r['unresolved'] ); $this->assertFalse( $r['quiescent_candidate'] );
	}
	public function test_quiescence_requires_closed_ingress_complete_participants_and_current_barrier() {
		foreach ( array( array( $this->event( 'barrier' ) ), array( $this->event( 'close' ), $this->event( 'barrier', array( 'participants' => 'unproven' ) ) ), array( $this->event( 'close' ), $this->event( 'barrier', array( 'generation' => 1 ) ) ) ) as $events ) {
			$this->assertFalse( $this->run_trace( $events )['quiescent_candidate'] );
		}
		$r = $this->run_trace( array( $this->event( 'close' ), $this->event( 'barrier' ), $this->event( 'drift' ) ) ); $this->assertFalse( $r['quiescent_candidate'] );
		foreach ( array( 'cache_purged', 'lease_expired', 'queue_empty', 'cron_absent', 'receipt_found' ) as $event ) {
			$r = $this->run_trace( array( $this->event( 'close' ), array( 'event' => $event ), $this->event( 'barrier' ) ) ); $this->assertFalse( $r['quiescent_candidate'] ); $this->assertTrue( $r['unresolved'] );
		}
	}
	public function test_bounded_capacity_retains_history_without_ttl_or_pruning() {
		$events = array();
		for ( $i = 0; $i < 16; ++$i ) {
			$id = str_pad( dechex( $i ), 64, '0', STR_PAD_LEFT );
			$events[] = $this->event( 'register', array( 'operation' => $id ) ); $events[] = $this->event( 'terminal', array( 'operation' => $id ) );
		}
		$events[] = $this->event( 'register' );
		$r = $this->run_trace( $events ); $this->assertSame( 'rejected', $r['outcomes'][32] ); $this->assertSame( 0, $r['pending_count'] );
		$this->assertCount( 64, $this->run_trace( array_fill( 0, 64, $this->event( 'drift' ) ) )['outcomes'] );
		$this->assertSame( array( 'invalid_trace' ), $this->run_trace( array_fill( 0, 65, $this->event( 'drift' ) ) )['outcomes'] );
	}
	public function test_flat_input_types_limits_and_redacted_output() {
		foreach ( array( 0, -1, PHP_INT_MAX, '1', 1.0, null, new stdClass() ) as $generation ) $this->assertSame( array( 'invalid_trace' ), $this->run_trace( array(), $generation )['outcomes'] );
		foreach ( array( null, false, new stdClass(), array( 2 => $this->event( 'close' ) ) ) as $events ) $this->assertSame( array( 'invalid_trace' ), $this->run_trace( $events )['outcomes'] );
		foreach ( array( array(), null, new stdClass(), array( 'event' => array() ), $this->event( 'register', array( 'generation' => '1' ) ), $this->event( 'register', array( 'context' => str_repeat( 'x', 65536 ) ) ), $this->event( 'register', array( 'provider' => function() { throw new LogicException(); } ) ) ) as $event ) {
			$r = $this->run_trace( array( $event ) ); $this->assertSame( array( 'rejected' ), $r['outcomes'] ); $this->assertTrue( $r['unresolved'] );
		}
		$r = $this->run_trace( array( $this->event( 'register' ) ), PHP_INT_MAX - 1 ); $this->assertSame( array( 'rejected' ), $r['outcomes'] );
		$output = serialize( $this->run_trace( array( $this->event( 'register' ) ) ) );
		foreach ( array( str_repeat( 'a', 64 ), str_repeat( 'b', 64 ), str_repeat( 'c', 64 ) ) as $private ) $this->assertStringNotContainsString( $private, $output );
	}
}
