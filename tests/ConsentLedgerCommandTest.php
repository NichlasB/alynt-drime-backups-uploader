<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ConsentLedgerFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ConsentLedgerCommandTest extends TestCase {
	use Alynt_Test_Consent_Ledger_Fixture;
	public function test_grant_and_closure_commit_epochs_and_receipts_but_never_execution() {
		$s = $this->migrate(); $c = $this->intent(); $swaps = $this->backend->swaps; $this->result( 'committed', $this->ledger->command( $c ) ); $grant = $this->ledger->snapshot();
		$this->assertSame( $swaps + 1, $this->backend->swaps ); $this->assertSame( $s['generation'] + 1, $grant['generation'] ); $this->assertSame( $s['schedule_revision'] + 1, $grant['schedule_revision'] );
		$this->assertSame( $c['expected_generation'] + 1, $grant['lifecycle']['permission_generation'] ); $this->assertSame( $c, $grant['ledger']['last_receipt']['command'] ); $this->assertSame( 'fixture_intent_v1', $grant['ledger']['source'] );
		$this->assertTrue( $grant['lifecycle']['consent'] ); $this->assertSame( 'preparing', $grant['lifecycle']['phase'] ); $this->assertFalse( $grant['authority']['enabled'] );
		$this->now += 90000; $close = $this->intent( 'revoke', 2 ); $this->result( 'committed', $this->ledger->command( $close ) ); $end = $this->ledger->snapshot();
		$this->assertFalse( $end['lifecycle']['consent'] ); $this->assertNull( $end['ledger']['grant_command_id'] ); $this->assertSame( 'disabled', $end['lifecycle']['phase'] );
		$this->assertSame( $s['authority'], $end['authority'] ); $this->assertSame( $s['claims'], $end['claims'] ); $this->assertSame( $grant['ledger']['last_receipt'], $end['ledger']['receipts'][$c['command_id']] );
	}
	public function test_duplicate_canonical_intent_is_observation_even_after_expiry_and_restart() {
		$this->migrate(); $c = $this->intent(); $this->result( 'committed', $this->ledger->command( $c ) ); $rows = $this->backend->rows; $swaps = $this->backend->swaps;
		$this->now += 600; $restart = $this->new_ledger(); $this->result( 'observed_current', $restart->command( array_reverse( $c, true ) ) );
		$this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $swaps, $this->backend->swaps );
	}
	public function test_old_grant_after_revoke_and_rebind_is_superseded_not_regranted() {
		$this->migrate(); $grant = $this->intent(); $this->ledger->command( $grant ); $this->ledger->command( $this->intent( 'rebind', 2 ) ); $rows = $this->backend->rows; $swaps = $this->backend->swaps;
		$this->result( 'observed_superseded', $this->ledger->command( $grant ) ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $swaps, $this->backend->swaps );
		$this->assertSame( str_repeat( 'b', 64 ), $this->ledger->snapshot()['enrollment_binding'] ); $this->assertFalse( $this->ledger->snapshot()['lifecycle']['consent'] );
		$changed = $grant; $changed['purpose'] = 'revoke'; $changed['issued_at'] = 0; $changed['expires_at'] = 0; $this->error( 'conflict', $this->ledger->command( $changed ) );
		$changed = $grant; $changed['command_id'] = '33333333-3333-4333-8333-333333333333'; $this->error( 'stale', $this->ledger->command( $changed ) );
	}
	public function test_same_grant_with_new_id_cannot_refresh_existing_consent() {
		$this->migrate(); $this->ledger->command( $this->intent() ); $rows = $this->backend->rows;
		$this->error( 'grant_blocked', $this->ledger->command( $this->intent( 'grant', 2 ) ) ); $this->assertSame( $rows, $this->backend->rows );
	}
	/** @dataProvider pending_kinds */
	public function test_closure_preserves_expired_uncertain_work_verbatim( $dispatch ) {
		$this->migrate(); $this->ledger->command( $this->intent( 'revoke', 8 ) ); $s = $this->pending_state( $this->ledger->snapshot(), $dispatch );
		$this->replace( $s ); // Synthetic uncertainty observed after a recorded closure, not a missing provenance repair.
		$this->now += 90000; $rows = $this->backend->rows;
		$this->error( 'grant_blocked', $this->ledger->command( $this->intent() ) ); $this->assertSame( $rows, $this->backend->rows );
		$this->result( 'committed', $this->ledger->command( $this->intent( 'rebind', 2 ) ) ); $end = $this->ledger->snapshot();
		$this->assertSame( $s['claims'], $end['claims'] ); $this->assertSame( $s['dispatch'], $end['dispatch'] ); $this->assertSame( $s['authority'], $end['authority'] ); $this->assertSame( 'draining', $end['lifecycle']['phase'] );
	}
	public function pending_kinds() { return array( array( false ), array( true ) ); }
}
