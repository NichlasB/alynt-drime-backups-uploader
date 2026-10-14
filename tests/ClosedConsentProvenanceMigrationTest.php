<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ClosedConsentProvenanceFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedConsentProvenanceMigrationTest extends TestCase {
	use Alynt_Test_Closed_Consent_Provenance_Fixture;
	public function test_schema_three_synthetic_consent_closes_and_keeps_unknown_history() {
		$this->lifecycle->record_consent( $this->binding, $this->lifecycle->snapshot()['lifecycle']['permission_generation'], true ); $old = $this->origin();
		$this->assertTrue( $old['lifecycle']['consent'] ); $s = $this->close_origin(); $this->closed_result( $s, $old ); $this->assertSame( $old['lifecycle']['history'], $s['lifecycle']['history'] );
		$this->assertSame( $s, $this->closed->snapshot() ); $swaps = $this->backend->swaps; $this->assertSame( $s, $this->close_origin() ); $this->assertSame( $swaps, $this->backend->swaps );
	}
	public function test_schema_four_grant_and_all_receipts_are_archived_never_promoted() {
		$this->migrate(); $grant = $this->intent(); $this->ledger->command( $grant ); $this->ledger->command( $this->intent( 'revoke', 2 ) ); $this->ledger->command( $this->intent( 'grant', 3 ) ); $old = $this->origin();
		$s = $this->close_origin(); $this->closed_result( $s, $old ); $this->assertSame( $old['ledger'], $s['provenance']['legacy']['ledger'] ); $this->assertTrue( $s['provenance']['legacy']['lifecycle']['consent'] );
		$this->assertSame( 'fixture_intent_v1', $s['provenance']['legacy']['ledger']['source'] ); $this->assertFalse( $s['lifecycle']['consent'] ); $this->assertSame( $old['lifecycle']['history'], $s['lifecycle']['history'] );
		$this->assertInstanceOf( WP_Error::class, $this->ledger->snapshot() ); $this->assertInstanceOf( WP_Error::class, $this->lifecycle->snapshot() );
	}
	public function test_stale_rerun_does_not_reset_epochs_or_archive() {
		$old = $this->origin(); $this->close_origin(); $rows = $this->backend->rows; $swaps = $this->backend->swaps;
		$this->provenance_error( 'stale', $this->closed->prepare_closed( $this->binding, $old['lifecycle']['permission_generation'], function () { return true; } ) );
		$this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $swaps, $this->backend->swaps );
	}
	public function test_default_false_truthy_and_bad_scope_have_zero_reads_guard_calls_and_mutations() {
		$reads = $this->backend->reads; $swaps = $this->backend->swaps; $guards = 0; $guard = function () use ( &$guards ) { $guards++; return true; };
		foreach ( array( false, 1, 'true' ) as $mode ) {
			$m = $this->new_closed( $mode ); $this->provenance_error( 'disabled', $m->snapshot() ); $this->provenance_error( 'disabled', $m->prepare_closed( $this->binding, 0, $guard ) ); $this->provenance_error( 'disabled', $m->receipt( 'fixture_intent_v1', '22222222-2222-4222-8222-000000000001' ) );
		}
		$this->provenance_error( 'disabled', $this->new_closed( true, 'bad' )->snapshot() ); $default = new Alynt_Drime_Backups_Uploader_Closed_Consent_Provenance_Store( $this->backend, $this->site, function () { return $this->now; } ); $this->provenance_error( 'disabled', $default->snapshot() );
		$this->assertSame( $reads, $this->backend->reads ); $this->assertSame( $swaps, $this->backend->swaps ); $this->assertSame( 0, $guards );
	}
	public function test_denied_invalid_and_throwing_guard_never_reads_or_writes() {
		$reads = $this->backend->reads; $rows = $this->backend->rows;
		$this->provenance_error( 'invalid', $this->closed->prepare_closed( $this->binding, '0', function () { return true; } ) ); $this->provenance_error( 'invalid', $this->closed->prepare_closed( $this->binding, 0, true ) );
		foreach ( array( false, 1, 'true' ) as $value ) $this->provenance_error( 'denied', $this->close_origin( function () use ( $value ) { return $value; } ) );
		$this->provenance_error( 'unresolved', $this->close_origin( function () { throw new RuntimeException( 'secret auth' ); } ) ); $this->assertSame( $reads, $this->backend->reads ); $this->assertSame( $rows, $this->backend->rows );
	}
	/** @dataProvider unfinished_kinds */
	public function test_expired_unfinished_work_is_retained_without_migration( $dispatch ) {
		$this->migrate(); $this->ledger->command( $this->intent( 'revoke' ) ); $old = $this->pending_state( $this->origin(), $dispatch ); $this->replace( $old ); $this->now += 90000; $rows = $this->backend->rows;
		$this->provenance_error( 'blocked', $this->close_origin() ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( 4, $this->origin()['schema_version'] );
	}
	public function unfinished_kinds() { return array( array( false ), array( true ) ); }
}
