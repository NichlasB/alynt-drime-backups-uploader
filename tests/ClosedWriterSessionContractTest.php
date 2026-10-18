<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RawObservationFixture.php';

class ClosedWriterSessionContractTest extends TestCase {
	use Alynt_Test_Raw_Observation_Fixture;
	public function test_ordered_pair_commit_is_simulation_not_native_admission() {
		$s = $this->session(); $this->assertTrue( $s->matches_command( $this->real_id( 90 ) ) ); $this->assertFalse( $s->matches_command( $this->real_id( 91 ) ) ); $this->locked( $s );
		$this->assertTrue( $this->step( $s, 'pair_verified' ) ); $this->assertTrue( $this->step( $s, 'commit_sent' ) );
		$this->assertTrue( $this->step( $s, 'commit_confirmed', false ) ); $this->assertSame( 'confirmed_idle', $s->phase() );
		$this->assertFalse( $s->disposition()['storage_supported'] ); $this->assertFalse( $s->disposition()['execution_supported'] );
		$this->assertTrue( $this->step( $s, 'close', false ) ); $this->assertFalse( $this->step( $s, 'open_idle', false ) );
	}
	public function test_lock_and_commit_guards_have_independent_negative_controls() {
		foreach ( array( 'target_locked', 'pair_verified', 'commit_sent', 'commit_confirmed' ) as $bad ) {
			$s = $this->session(); $this->assertTrue( $this->step( $s, 'open_idle', false ) ); $this->assertTrue( $this->step( $s, 'begin_sent', false ) ); $this->assertTrue( $this->step( $s, 'begin_confirmed' ) );
			$this->assertFalse( $this->step( $s, $bad ) ); $this->assertSame( 'poisoned', $s->phase() );
		}
	}
	public function test_reconciliation_requires_both_locks_and_no_pair_write() {
		$s = $this->session(); $this->locked( $s ); $this->assertTrue( $this->step( $s, 'resolution_ready' ) );
		$this->assertFalse( $this->step( $s, 'commit_sent' ) ); $this->assertSame( 'poisoned', $s->phase() );
		$s = $this->session(); $this->locked( $s ); $this->assertTrue( $this->step( $s, 'resolution_ready' ) );
		$this->assertTrue( $this->step( $s, 'rollback_sent' ) ); $this->assertTrue( $this->step( $s, 'rollback_confirmed', false ) );
	}
	public function test_unknown_commit_is_latched_and_never_retryable() {
		$s = $this->session(); $this->locked( $s ); $this->step( $s, 'pair_verified' ); $this->step( $s, 'commit_sent' );
		$this->assertFalse( $this->step( $s, 'uncertain' ) ); $this->assertTrue( $s->commit_unknown() );
		$this->assertFalse( $this->step( $s, 'rollback_confirmed', false ) ); $this->assertTrue( $this->step( $s, 'close', false ) );
		$this->assertTrue( $s->commit_unknown() ); $this->assertFalse( $this->step( $s, 'begin_sent', false ) );
	}
	public function test_invalid_or_stale_evidence_poisons_instead_of_reconnecting_or_refreshing() {
		foreach ( array( array( 99, 1001 ), array( 400, 1001 ), array( 101, 999 ), array( 101, 11000 ) ) as $clock ) {
			$s = $this->session(); $this->assertFalse( $this->step( $s, 'open_idle', false, $clock[0], $clock[1] ) ); $this->assertSame( 'poisoned', $s->phase() );
		}
		foreach ( array( 'session_id' => str_repeat( 'e', 64 ), 'autocommit' => 1, 'in_transaction' => 0 ) as $key => $value ) {
			$s = $this->session(); $facts = $this->facts(); $facts[$key] = $value; $this->assertFalse( $s->step( 'open_idle', $facts, 100, 1000 ) );
		}
		$s = $this->session(); $this->locked( $s ); $this->assertTrue( $this->step( $s, 'rollback_sent' ) ); $this->assertFalse( $this->step( $s, 'rollback_confirmed', true ) );
	}
	public function test_private_facts_are_not_debugged_serialized_or_copied() {
		foreach ( array( $this->observation(), $this->session() ) as $object ) {
			ob_start(); var_dump( $object ); $debug = ob_get_clean();
			foreach ( array( 'fixture_only', 'private-test-token', $this->scope['session_id'] ) as $secret ) $this->assertStringNotContainsString( $secret, $debug );
			try { serialize( $object ); $this->fail( 'Serialization must refuse.' ); } catch ( LogicException $expected ) { $this->assertSame( 'Closed contract data cannot be transferred.', $expected->getMessage() ); }
			try { $copy = clone $object; $this->fail( 'Clone must refuse.' ); } catch ( Error $expected ) { $this->assertStringContainsString( '__clone', $expected->getMessage() ); }
		}
	}
	public function test_precommit_timestamp_cannot_refresh_but_late_ack_cannot_erase_commit() {
		$s = $this->session(); $this->locked( $s ); $this->assertTrue( $this->step( $s, 'pair_verified' ) );
		$this->assertFalse( $this->step( $s, 'commit_sent', true, 101, 1001 ) ); $this->assertFalse( $s->commit_unknown() );
		$s = $this->session(); $this->locked( $s ); $this->step( $s, 'pair_verified' ); $this->assertTrue( $this->step( $s, 'commit_sent' ) );
		$this->assertTrue( $this->step( $s, 'commit_confirmed', false, 401, 12000 ) ); $this->assertSame( 'confirmed_idle', $s->phase() );
		$this->assertFalse( $s->commit_unknown() ); $this->assertFalse( $this->step( $s, 'begin_sent', false, 401, 12000 ) );
	}
}
