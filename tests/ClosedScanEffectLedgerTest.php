<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/class-closed-scan-effect-ledger.php';
require_once __DIR__ . '/Support/ClosedScanLedgerFixture.php';

class ClosedScanEffectLedgerTest extends TestCase {
	use Alynt_Test_Closed_Scan_Ledger_Fixture;
	public function test_positive_empty_reserved_and_terminal_closed_records_are_never_authority() {
		foreach ( array( $this->ledger(), $this->ledger( 1 ), $this->ledger( 1, true ), $this->ledger( 16, true ) ) as $s ) {
			$r = $this->inspect( $s ); $this->assertIsArray( $r ); $this->assertTrue( $r['closed_candidate'] ); $this->assertSame( count( $s['operations'] ), $r['operation_count'] );
			foreach ( array( 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $key ) $this->assertFalse( $r[$key] );
			$this->assertStringNotContainsString( $s['site_uuid'], json_encode( $r ) ); $this->assertArrayNotHasKey( 'after', $r );
		}
		$this->assertTrue( $this->inspect( $this->ledger() )['registration_room_candidate'] );
		$this->assertFalse( $this->inspect( $this->ledger( 1 ) )['registration_room_candidate'] );
		$this->assertFalse( $this->inspect( $this->ledger( 16, true ) )['registration_room_candidate'] );
	}
	public function test_closed_root_scope_canonical_encoding_and_manifest_cannot_be_bypassed() {
		foreach ( array( 'schema_version' => 8, 'domain' => 'authenticated_closed_writer_v1', 'closed' => false, 'authority_generation' => '10', 'ledger_revision' => PHP_INT_MAX, 'participant_digest' => str_repeat( 'f', 64 ), 'grant' => true ) as $key => $value ) { $s = $this->ledger(); $s[$key] = $value; $this->assertFalse( $this->inspect( $s ), $key ); }
		$s = $this->ledger(); $scope = array( 'site_uuid' => $this->uuid( 9998 ), 'enrollment_binding' => $s['enrollment_binding'], 'authority_generation' => 10 );
		$this->assertFalse( Alynt_Drime_Backups_Uploader_Closed_Scan_Effect_Ledger::inspect( json_encode( $s ), $scope ) );
		foreach ( array( ' ' . json_encode( $s ), '{"schema_version":1,' . substr( json_encode( $s ), 1 ), json_encode( $s ) . 'x', str_repeat( ' ', 131073 ) ) as $raw ) $this->assertFalse( Alynt_Drime_Backups_Uploader_Closed_Scan_Effect_Ledger::inspect( $raw, $scope ) );
	}
	public function test_identity_reuse_owner_mismatch_and_foreign_pending_work_refuse() {
		foreach ( array( 'command_id', 'terminal_id', 'resolution_id' ) as $key ) { $s = $this->ledger( 1 ); $id = key( $s['operations'] ); $s['operations'][$id][$key] = $id; $this->assertFalse( $this->inspect( $s ), $key ); }
		$s = $this->ledger( 1 ); $id = key( $s['operations'] ); $s['operations'][$id]['effects']['queue_addition']['effect_id'] = $s['operations'][$id]['effects']['wpvivid_snapshot']['effect_id']; $this->assertFalse( $this->inspect( $s ) );
		$this->assertFalse( $this->inspect( $this->ledger( 2 ) ) ); $this->assertFalse( $this->inspect( $this->ledger( 17, true ) ) );
		foreach ( array( 'operation_id' => $this->uuid( 9998 ), 'generation' => 11, 'entry_point' => 'admin', 'expires_at' => 401 ) as $key => $value ) { $s = $this->ledger( 1 ); $id = key( $s['operations'] ); $s['operations'][$id][$key] = $value; $this->assertFalse( $this->inspect( $s ), $key ); }
	}
	public function test_unresolved_intent_is_not_a_receipt_or_terminal_fact() {
		$s = $this->ledger( 1 ); $id = key( $s['operations'] ); $o =& $s['operations'][$id]; $o['phase'] = 'unresolved'; $e =& $o['effects']['queue_addition']; $e['state'] = 'unresolved'; $e['dependency_digest'] = str_repeat( 'c', 64 ); $e['before_hash'] = str_repeat( 'd', 64 ); $e['after_hash'] = str_repeat( 'e', 64 );
		$this->assertSame( 1, $this->inspect( $s )['pending_count'] ); $o['phase'] = 'terminal'; $this->assertFalse( $this->inspect( $s ) ); $o['phase'] = 'unresolved'; $e['result_revision'] = 2; $this->assertFalse( $this->inspect( $s ) );
	}
	public function test_receipt_hashes_types_domains_and_revisions_are_bound() {
		foreach ( array( 'context_digest' => str_repeat( 'f', 64 ), 'domain' => 'closed_writer_admission_v1', 'classification' => 'no_change', 'result_revision' => '1', 'effect_id' => $this->uuid( 9998 ) ) as $key => $value ) {
			$s = $this->ledger( 1, true ); $id = key( $s['operations'] ); $s['operations'][$id]['effects']['wpvivid_snapshot']['receipt'][$key] = $value; $this->assertFalse( $this->inspect( $s ), $key );
		}
		$s = $this->ledger( 1, true ); $id = key( $s['operations'] ); $s['operations'][$id]['terminal']['effect_receipts']['queue_addition'] = str_repeat( 'f', 64 ); $this->assertFalse( $this->inspect( $s ) );
		$s = $this->ledger( 1, true ); $id = key( $s['operations'] ); $s['operations'][$id]['terminal']['result_revision'] = 3; $this->assertFalse( $this->inspect( $s ) );
	}
	public function test_cancelled_and_no_change_receipts_are_distinct_positive_candidates() {
		foreach ( array( 'cancelled', 'no_change' ) as $classification ) {
			$s = $this->ledger( 1, true ); $id = key( $s['operations'] ); $o =& $s['operations'][$id]; $e =& $o['effects']['queue_addition'];
			$e['state'] = 'cancelled' === $classification ? 'cancelled' : 'committed'; $e['after_hash'] = 'cancelled' === $classification ? null : $e['before_hash']; if ( 'cancelled' === $classification ) $e['before_hash'] = null;
			$e['receipt']['classification'] = $classification; $e['receipt']['purpose'] = 'cancelled' === $classification ? 'cancel_queue_addition' : 'queue_addition'; $e['receipt']['before_hash'] = $e['before_hash']; $e['receipt']['after_hash'] = $e['after_hash']; $o['terminal']['effect_receipts']['queue_addition'] = hash( 'sha256', json_encode( $e['receipt'] ) );
			$this->assertIsArray( $this->inspect( $s ) ); $e['receipt']['classification'] = 'committed'; $this->assertFalse( $this->inspect( $s ) ); unset( $o, $e );
		}
	}
	public function test_terminal_reserve_arithmetic_is_discriminating_not_an_admission_flag() {
		$c = 'Alynt_Drime_Backups_Uploader_Closed_Scan_Effect_Ledger';
		$this->assertTrue( $c::budget_candidate( 108544, 15, 0 ) ); $this->assertFalse( $c::budget_candidate( 108545, 15, 0 ) );
		foreach ( array( array( 1, 16, 0 ), array( 1, 15, 1 ), array( '1', 0, 0 ), array( PHP_INT_MAX, 0, 0 ), array( 0, 0, 0 ), array( 1, -1, 0 ) ) as $args ) $this->assertFalse( $c::budget_candidate( ...$args ) );
	}
	public function test_intent_and_draining_shapes_keep_pending_and_never_become_terminal_by_defaults() {
		$s = $this->ledger( 1 ); $id = key( $s['operations'] ); $o =& $s['operations'][$id]; $o['phase'] = 'intent_reserved'; $e =& $o['effects']['wpvivid_snapshot']; $e['state'] = 'intent_reserved'; $e['dependency_digest'] = str_repeat( 'c', 64 ); $e['before_hash'] = str_repeat( 'd', 64 ); $e['after_hash'] = str_repeat( 'e', 64 );
		$this->assertSame( 1, $this->inspect( $s )['pending_count'] ); $o['phase'] = 'draining'; $this->assertSame( 1, $this->inspect( $s )['pending_count'] );
		$o['phase'] = 'registered'; $this->assertFalse( $this->inspect( $s ) ); $o['phase'] = 'draining'; $e['receipt'] = array(); $this->assertFalse( $this->inspect( $s ) );
	}
	public function test_resource_and_malformed_inputs_fail_without_object_or_active_schema_promotion() {
		$s = $this->ledger(); $scope = array( 'site_uuid' => $s['site_uuid'], 'enrollment_binding' => $s['enrollment_binding'], 'authority_generation' => 10 );
		foreach ( array( null, false, new stdClass(), '{"schema_version":1,"schema_version":1}', str_repeat( 'x', 131073 ), '{"x":1e309}', str_repeat( '[', 17 ) . '1' . str_repeat( ']', 17 ) ) as $raw ) $this->assertFalse( Alynt_Drime_Backups_Uploader_Closed_Scan_Effect_Ledger::inspect( $raw, $scope ) );
		$s['authenticated_grant'] = array( 'active' => true ); $this->assertFalse( $this->inspect( $s ) );
		$s = $this->ledger(); $s['operations'] = array_fill( 0, 1500, array( 1 ) ); $this->assertFalse( $this->inspect( $s ) );
	}
	public function test_record_context_cannot_be_relocated_to_a_different_root_enrollment() {
		$s = $this->ledger( 1, true ); $s['enrollment_binding'] = str_repeat( 'f', 64 );
		$this->assertFalse( $this->inspect( $s ) );
	}
}
