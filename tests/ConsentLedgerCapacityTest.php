<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ConsentLedgerFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ConsentLedgerCapacityTest extends TestCase {
	use Alynt_Test_Consent_Ledger_Fixture;
	public function test_history_capacity_blocks_new_grants_but_always_keeps_a_closure_receipt() {
		$this->migrate(); $first = null;
		for ( $id = 1; $id <= 50; $id++ ) { $c = $this->intent( $id % 2 ? 'grant' : 'revoke', $id ); if ( 1 === $id ) $first = $c; $this->result( 'committed', $this->ledger->command( $c ) ); }
		$s = $this->ledger->snapshot(); $this->assertCount( 49, $s['ledger']['receipts'] ); $rows = $this->backend->rows;
		$this->error( 'capacity', $this->ledger->command( $this->intent( 'grant', 51 ) ) ); $this->assertSame( $rows, $this->backend->rows );
		$this->result( 'committed', $this->ledger->command( $this->intent( 'revoke', 52 ) ) ); $s = $this->ledger->snapshot(); $this->assertCount( 50, $s['ledger']['receipts'] ); $history = $s['ledger']['receipts'];
		$this->result( 'committed', $this->ledger->command( $this->intent( 'revoke', 53 ) ) ); $s = $this->ledger->snapshot(); $this->assertSame( $history, $s['ledger']['receipts'] ); $this->assertSame( 53, $s['lifecycle']['permission_generation'] );
		$this->result( 'observed_superseded', $this->ledger->command( $first ) ); $this->assertFalse( $s['authority']['enabled'] ); $this->assertLessThanOrEqual( 1024, strlen( json_encode( $s['ledger']['last_receipt'] ) ) );
	}
	/** @dataProvider counter_fields */
	public function test_grant_reserves_counter_headroom_for_closure_without_overflow( $field ) {
		$s = $this->migrate(); if ( 'permission_generation' === $field ) { $s['lifecycle'][$field] = PHP_INT_MAX - 2; $s['generation'] = PHP_INT_MAX - 2; $s['schedule_revision'] = PHP_INT_MAX - 2; } else $s[$field] = PHP_INT_MAX - 2;
		$this->replace( $s ); $c = $this->intent(); $rows = $this->backend->rows; $this->error( 'counter', $this->ledger->command( $c ) ); $this->assertSame( $rows, $this->backend->rows );
		$close = $this->intent( 'revoke', 2 ); $this->result( 'committed', $this->ledger->command( $close ) ); $end = $this->ledger->snapshot(); $this->assertFalse( $end['lifecycle']['consent'] );
		$this->result( 'observed_current', $this->ledger->command( $close ) ); $rows = $this->backend->rows; $this->error( 'counter', $this->ledger->command( $this->intent( 'revoke', 3 ) ) ); $this->assertSame( $rows, $this->backend->rows );
	}
	public function counter_fields() { return array( array( 'generation' ), array( 'schedule_revision' ), array( 'permission_generation' ) ); }
	public function test_migration_reserves_counter_headroom_instead_of_resetting_epochs() {
		$s = $this->lifecycle->snapshot(); $s['generation'] = PHP_INT_MAX - 2; $this->replace( $s ); $rows = $this->backend->rows;
		$this->error( 'migration_blocked', $this->ledger->prepare_ledger( $this->binding, 0 ) ); $this->assertSame( $rows, $this->backend->rows );
	}
	public function test_maximum_journal_and_receipt_population_preserves_byte_reserve_and_closes() {
		$s = $this->pending_state( $this->lifecycle->snapshot() ); $record = reset( $s['claims'] ); $record['phase'] = 'failed_closed'; $record['completed_at'] = $this->now;
		$s['lifecycle']['phase'] = 'disabled'; $s['claims'] = array();
		for ( $i = 1; $i <= 50; $i++ ) $s['claims'][sprintf( '55555555-5555-4555-8555-%012d', $i )] = $record;
		$this->replace( $s ); $before = $s['claims']; $this->migrate();
		for ( $id = 1; $id <= 49; $id++ ) $this->result( 'committed', $this->ledger->command( $this->intent( $id % 2 ? 'grant' : 'revoke', $id ) ) );
		$s = $this->ledger->snapshot(); $this->assertSame( $before, $s['claims'] ); $this->assertCount( 48, $s['ledger']['receipts'] );
		$this->assertLessThanOrEqual( 131072 - 4096, strlen( json_encode( $s ) ) ); $this->assertTrue( $s['lifecycle']['consent'] );
		$this->result( 'committed', $this->ledger->command( $this->intent( 'rebind', 50 ) ) ); $s = $this->ledger->snapshot();
		$this->assertSame( $before, $s['claims'] ); $this->assertLessThanOrEqual( 131072, strlen( json_encode( $s ) ) ); $this->assertFalse( $s['authority']['enabled'] ); $this->assertFalse( $s['lifecycle']['consent'] );
	}
}
