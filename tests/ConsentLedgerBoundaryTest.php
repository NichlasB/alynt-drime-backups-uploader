<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ConsentLedgerFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ConsentLedgerBoundaryTest extends TestCase {
	use Alynt_Test_Consent_Ledger_Fixture;
	public function test_old_lifecycle_reader_cannot_promote_or_mutate_fixture_schema_four() {
		$this->migrate(); $this->ledger->command( $this->intent() ); $rows = $this->backend->rows;
		$this->assertInstanceOf( WP_Error::class, $this->lifecycle->snapshot() ); $this->assertInstanceOf( WP_Error::class, $this->lifecycle->record_consent( $this->binding, 2, true ) ); $this->assertSame( $rows, $this->backend->rows );
		foreach ( array( 'claim_dispatch', 'start_dispatch', 'complete_dispatch', 'reserve_change', 'commit_change', 'record_consent' ) as $method ) $this->assertFalse( method_exists( $this->ledger, $method ) );
	}
	public function test_successful_grant_cannot_be_erased_by_preparation_rerun() {
		$this->migrate(); $this->ledger->command( $this->intent() ); $s = $this->ledger->snapshot(); $rows = $this->backend->rows; $swaps = $this->backend->swaps;
		$this->assertSame( $s, $this->ledger->prepare_ledger( $this->binding, 2 ) ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $swaps, $this->backend->swaps );
		$this->error( 'stale', $this->ledger->prepare_ledger( $this->binding, 0 ) ); $this->assertSame( $rows, $this->backend->rows );
	}
	public function test_concurrent_migration_advances_once_without_erasing_winner() {
		$other = $this->new_ledger(); $before = $this->lifecycle->snapshot(); $this->backend->before_swap = function () use ( $other ) { $this->assertIsArray( $other->prepare_ledger( $this->binding, 0 ) ); };
		$this->error( 'stale', $this->ledger->prepare_ledger( $this->binding, 0 ) ); $s = $this->ledger->snapshot();
		$this->assertSame( $before['generation'] + 1, $s['generation'] ); $this->assertSame( 1, $s['lifecycle']['permission_generation'] ); $this->assertFalse( $s['lifecycle']['consent'] ); $this->assertNull( $s['ledger']['last_receipt'] );
	}
	public function test_migration_commit_without_acknowledgement_can_be_read_without_reapplying() {
		$this->backend->ack_fault = 'throw'; $this->error( 'unresolved', $this->ledger->prepare_ledger( $this->binding, 0 ) ); $rows = $this->backend->rows; $swaps = $this->backend->swaps; $restart = $this->new_ledger();
		$this->assertSame( 4, $restart->snapshot()['schema_version'] ); $this->assertFalse( $restart->snapshot()['lifecycle']['consent'] );
		$this->error( 'stale', $restart->prepare_ledger( $this->binding, 0 ) ); $this->assertIsArray( $restart->prepare_ledger( $this->binding, 1 ) ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $swaps, $this->backend->swaps );
	}
	public function test_competing_rebind_closes_then_rejects_old_context_grant() {
		$this->migrate(); $grant = $this->intent(); $rebind = $this->intent( 'rebind', 2 ); $other = $this->new_ledger();
		$this->backend->before_swap = function () use ( $other, $rebind ) { $this->result( 'committed', $other->command( $rebind ) ); };
		$this->error( 'stale', $this->ledger->command( $grant ) ); $this->assertSame( str_repeat( 'b', 64 ), $this->ledger->snapshot()['enrollment_binding'] ); $this->assertFalse( $this->ledger->snapshot()['lifecycle']['consent'] );
	}
	public function test_missing_retained_old_receipt_still_cannot_regrant_at_old_epoch() {
		$this->migrate(); $grant = $this->intent(); $this->ledger->command( $grant ); $this->ledger->command( $this->intent( 'revoke', 2 ) ); $s = $this->ledger->snapshot();
		$s['ledger']['receipts'] = array(); $this->replace( $s ); $rows = $this->backend->rows; $this->error( 'stale', $this->ledger->command( $grant ) ); $this->assertSame( $rows, $this->backend->rows ); $this->assertFalse( $this->ledger->snapshot()['lifecycle']['consent'] );
	}
}
