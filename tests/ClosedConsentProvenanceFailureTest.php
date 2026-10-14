<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ClosedConsentProvenanceFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedConsentProvenanceFailureTest extends TestCase {
	use Alynt_Test_Closed_Consent_Provenance_Fixture;
	public function test_failed_write_retains_original_synthetic_grant_not_a_disabled_claim() {
		$this->migrate(); $this->ledger->command( $this->intent() ); $rows = $this->backend->rows; $this->backend->fail_write = true;
		$this->provenance_error( 'unresolved', $this->close_origin() ); $this->assertSame( $rows, $this->backend->rows ); $this->assertTrue( $this->origin()['lifecycle']['consent'] ); $this->assertSame( 4, $this->origin()['schema_version'] );
	}
	/** @dataProvider acknowledgements */
	public function test_ambiguous_commit_reconciles_by_snapshot_without_reapplying( $ack ) {
		$old = $this->origin(); $this->backend->ack_fault = $ack; $result = $this->close_origin(); $this->assertInstanceOf( WP_Error::class, $result );
		$s = $this->new_closed()->snapshot(); $this->closed_result( $s, $old ); $swaps = $this->backend->swaps; $this->assertSame( $s, $this->close_origin() ); $this->assertSame( $swaps, $this->backend->swaps );
	}
	public function acknowledgements() { return array( array( 'false' ), array( 'throw' ), array( 'error' ) ); }
	public function test_competing_migration_has_one_winner_at_original_epoch() {
		$old = $this->origin(); $other = $this->new_closed(); $winner = null; $this->backend->before_swap = function () use ( $other, $old, &$winner ) { $winner = $other->prepare_closed( $this->binding, $old['lifecycle']['permission_generation'], function () { return true; } ); };
		$this->provenance_error( 'stale', $this->close_origin() ); $this->closed_result( $winner, $old ); $this->assertSame( $winner, $this->closed->snapshot() );
	}
	public function test_conflicting_fixture_closure_fences_migration_without_adopting_new_epoch() {
		$this->migrate(); $old = $this->origin(); $this->backend->before_swap = function () { $this->ledger->command( $this->intent( 'revoke' ) ); };
		$this->provenance_error( 'stale', $this->closed->prepare_closed( $this->binding, $old['lifecycle']['permission_generation'], function () { return true; } ) ); $this->assertSame( 4, $this->origin()['schema_version'] );
	}
	public function test_bounded_contention_and_guard_loss_never_update_state() {
		$rows = $this->backend->rows; $swaps = $this->backend->swaps; $this->backend->conflicts = 10; $this->provenance_error( 'contention', $this->close_origin() ); $this->assertSame( $swaps + 3, $this->backend->swaps );
		$this->backend->conflicts = 0; $checks = 0; $this->provenance_error( 'denied', $this->close_origin( function () use ( &$checks ) { return ++$checks < 2; } ) ); $this->assertSame( $rows, $this->backend->rows );
	}
	/** @dataProvider invalid_clocks */
	public function test_invalid_or_regressed_clock_cannot_migrate( $clock ) {
		$rows = $this->backend->rows; $this->now = $clock; $this->provenance_error( 'clock', $this->close_origin() ); $this->assertSame( $rows, $this->backend->rows );
	}
	public function invalid_clocks() { return array( array( 0 ), array( -1 ), array( '1800000000' ), array( 1799999999 ), array( PHP_INT_MAX ) ); }
	public function test_read_throw_and_unknown_ack_redact_private_details() {
		$this->backend->throw_read = true; $this->provenance_error( 'unresolved', $this->close_origin() ); $this->provenance_error( 'unresolved', $this->closed->snapshot() );
	}
}
