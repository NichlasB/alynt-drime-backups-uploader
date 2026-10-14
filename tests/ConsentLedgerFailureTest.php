<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ConsentLedgerFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ConsentLedgerFailureTest extends TestCase {
	use Alynt_Test_Consent_Ledger_Fixture;
	public function test_failed_revoke_retains_grant_and_does_not_report_disabled() {
		$this->migrate(); $this->ledger->command( $this->intent() ); $c = $this->intent( 'rebind', 2 ); $rows = $this->backend->rows;
		$this->backend->fail_write = true; $this->error( 'unresolved', $this->ledger->command( $c ) ); $this->assertSame( $rows, $this->backend->rows );
		$this->assertTrue( $this->ledger->snapshot()['lifecycle']['consent'] ); $this->assertSame( $this->binding, $this->ledger->snapshot()['enrollment_binding'] );
	}
	/** @dataProvider acknowledgement_faults */
	public function test_committed_but_unacknowledged_grant_is_observed_without_second_write( $fault ) {
		$this->migrate(); $c = $this->intent(); $swaps = $this->backend->swaps; $this->backend->ack_fault = $fault; $result = $this->ledger->command( $c );
		if ( 'false' === $fault ) $this->result( 'observed_current', $result ); else $this->error( 'unresolved', $result );
		$this->assertSame( $swaps + 1, $this->backend->swaps ); $rows = $this->backend->rows; $this->now += 600;
		$this->result( 'observed_current', $this->new_ledger()->command( $c ) ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $swaps + 1, $this->backend->swaps );
	}
	public function acknowledgement_faults() { return array( array( 'throw' ), array( 'error' ), array( 'false' ) ); }
	public function test_committed_but_unacknowledged_closure_remains_closed_on_restart() {
		$this->migrate(); $grant = $this->intent(); $this->ledger->command( $grant ); $c = $this->intent( 'revoke', 2 ); $this->backend->ack_fault = 'throw';
		$this->error( 'unresolved', $this->ledger->command( $c ) ); $rows = $this->backend->rows; $swaps = $this->backend->swaps;
		$this->result( 'observed_current', $this->new_ledger()->command( $c ) ); $this->result( 'observed_superseded', $this->ledger->command( $grant ) );
		$this->assertFalse( $this->ledger->snapshot()['lifecycle']['consent'] ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $swaps, $this->backend->swaps );
	}
	public function test_competing_closure_blocks_stale_grant_without_refreshing_expected_epoch() {
		$this->migrate(); $grant = $this->intent(); $close = $this->intent( 'revoke', 2 ); $other = $this->new_ledger();
		$this->backend->before_swap = function () use ( $other, $close ) { $this->result( 'committed', $other->command( $close ) ); };
		$this->error( 'stale', $this->ledger->command( $grant ) ); $s = $this->ledger->snapshot(); $this->assertFalse( $s['lifecycle']['consent'] ); $this->assertSame( 2, $s['lifecycle']['permission_generation'] );
	}
	public function test_duplicate_winning_competing_cas_is_observed_once() {
		$this->migrate(); $c = $this->intent(); $other = $this->new_ledger(); $swaps = $this->backend->swaps;
		$this->backend->before_swap = function () use ( $other, $c ) { $this->result( 'committed', $other->command( $c ) ); };
		$this->result( 'observed_current', $this->ledger->command( $c ) ); $this->assertSame( $swaps + 2, $this->backend->swaps ); $this->assertSame( 2, $this->ledger->snapshot()['lifecycle']['permission_generation'] );
	}
	public function test_read_and_clock_exceptions_are_redacted_and_never_repair_storage() {
		$this->migrate(); $c = $this->intent(); $rows = $this->backend->rows; $swaps = $this->backend->swaps;
		$this->backend->throw_read = true; $this->error( 'unresolved', $this->ledger->command( $c ) ); $this->error( 'unresolved', $this->ledger->snapshot() );
		$this->backend->throw_read = false; $this->backend->fail_read = true; $this->error( 'unavailable', $this->ledger->command( $c ) ); $this->backend->fail_read = false;
		$throwing = new Alynt_Drime_Backups_Uploader_Cadence_Consent_Ledger( $this->backend, $this->site, function () { throw new RuntimeException( 'secret clock detail' ); }, true );
		$this->error( 'unresolved', $throwing->command( $c ) ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $swaps, $this->backend->swaps );
	}
	public function test_three_attempt_contention_is_bounded_and_does_not_persist_partial_receipt() {
		$this->migrate(); $c = $this->intent(); $rows = $this->backend->rows; $swaps = $this->backend->swaps; $this->backend->conflicts = 3;
		$this->error( 'contention', $this->ledger->command( $c ) ); $this->assertSame( $swaps + 3, $this->backend->swaps ); $this->assertSame( $rows, $this->backend->rows );
	}
}
