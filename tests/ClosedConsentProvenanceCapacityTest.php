<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ClosedConsentProvenanceFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedConsentProvenanceCapacityTest extends TestCase {
	use Alynt_Test_Closed_Consent_Provenance_Fixture;
	public function test_full_fixture_receipt_history_and_native_terminal_journal_survive() {
		$this->migrate(); for ( $i = 1; $i <= 51; $i++ ) $this->assertIsArray( $this->ledger->command( $this->intent( 'revoke', $i ) ) );
		$old = $this->pending_state( $this->origin() ); foreach ( $old['claims'] as &$claim ) { $claim['phase'] = 'succeeded'; $claim['completed_at'] = $this->now; $claim['authority_evidence_version'] = 1; } unset( $claim );
		$old['lifecycle']['phase'] = 'disabled'; $this->replace( $old ); $this->assertIsArray( $this->ledger->snapshot() ); $this->assertCount( 50, $old['ledger']['receipts'] );
		$s = $this->close_origin(); $this->closed_result( $s, $old ); $this->assertCount( 50, $s['provenance']['legacy']['ledger']['receipts'] ); $this->assertLessThanOrEqual( 131072 - 4096, strlen( json_encode( $s ) ) );
	}
	public function test_counter_headroom_is_reserved_and_never_reset() {
		$old = $this->origin(); $old['generation'] = PHP_INT_MAX - 3; $old['schedule_revision'] = PHP_INT_MAX - 3; $old['lifecycle']['permission_generation'] = PHP_INT_MAX - 3; $this->replace( $old );
		$s = $this->close_origin(); $this->closed_result( $s, $old ); $this->assertSame( PHP_INT_MAX - 2, $s['lifecycle']['permission_generation'] );
		$old['generation']++; $old['schedule_revision']++; $old['lifecycle']['permission_generation']++; $this->replace( $old ); $rows = $this->backend->rows; $this->provenance_error( 'blocked', $this->close_origin() ); $this->assertSame( $rows, $this->backend->rows );
	}
	/** @dataProvider bad_rows */
	public function test_missing_corrupt_autoloaded_or_unsupported_state_is_not_repaired( $case ) {
		$name = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION;
		if ( 'missing' === $case ) unset( $this->backend->rows[$name] );
		elseif ( 'autoload' === $case ) $this->backend->rows[$name]['autoload'] = 'yes';
		elseif ( 'corrupt' === $case ) $this->backend->rows[$name]['option_value'] = '{';
		elseif ( 'oversize' === $case ) $this->backend->rows[$name]['option_value'] = str_repeat( 'x', 131073 );
		else { $old = $this->origin(); $old['schema_version'] = 6; $this->replace( $old ); }
		$rows = $this->backend->rows; $creates = $this->backend->creates; $swaps = $this->backend->swaps;
		$this->provenance_error( 'unavailable', $this->closed->prepare_closed( $this->binding, 0, function () { return true; } ) ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $creates, $this->backend->creates ); $this->assertSame( $swaps, $this->backend->swaps );
	}
	public function bad_rows() { return array( array( 'missing' ), array( 'autoload' ), array( 'corrupt' ), array( 'oversize' ), array( 'unsupported' ) ); }
	public function test_encoder_failure_or_lost_byte_reserve_never_overwrites_source() {
		$rows = $this->backend->rows;
		foreach ( array( false, str_repeat( 'x', 131072 - 4095 ) ) as $encoded ) {
			\Brain\Monkey\Functions\when( 'wp_json_encode' )->alias( function ( $value ) use ( $encoded ) { return isset( $value['schema_version'] ) && 5 === $value['schema_version'] ? $encoded : json_encode( $value ); } );
			$this->provenance_error( 'capacity', $this->close_origin() ); $this->assertSame( $rows, $this->backend->rows );
		}
	}
	public function test_clock_rechecked_at_write_and_migration_receipt_records_later_valid_time() {
		$old = $this->origin(); $checks = 0; $s = $this->close_origin( function () use ( &$checks ) { if ( 2 === ++$checks ) $this->now++; return true; } ); $this->closed_result( $s, $old ); $this->assertSame( $this->now, $s['provenance']['migration']['committed_at'] );
		$this->replace( $old ); $rows = $this->backend->rows; $checks = 0; $this->provenance_error( 'clock', $this->close_origin( function () use ( &$checks ) { if ( 2 === ++$checks ) $this->now = 'bad'; return true; } ) ); $this->assertSame( $rows, $this->backend->rows );
	}
}
