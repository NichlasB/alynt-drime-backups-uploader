<?php
/** Native evidence and read-only safety checks; no site or runtime execution. */
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/CadenceAuthorityEvidenceFixture.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CadenceAuthorityEvidenceTest extends TestCase {
	use Alynt_Drime_Backups_Uploader_Test_Authority_Evidence_Fixture;
	protected function setUp(): void {
		parent::setUp(); Monkey\setUp(); Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		$this->prepare_authority_evidence();
	}
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

	public function test_positive_observation_is_redacted_repeatable_and_never_eligible() {
		$before = $this->backend->rows; $swaps = $this->backend->swaps;
		$status = $this->producer->status(); $preview = $this->inspection();
		$this->assertSame( $preview, $this->inspection() );
		$this->assertTrue( $preview['safety_checks_passed'] );
		foreach ( array( 'dispatch_supported', 'execution_supported' ) as $key ) {
			$this->assertFalse( $status[$key] ); $this->assertFalse( $preview[$key] );
		}
		$this->assertFalse( $preview['eligible'] );
		$this->assertSame( 'every_30_minutes', $preview['rollback_cadence'] );
		foreach ( array( 'enrollment_binding', 'claims', 'dispatch', 'action_public_key', 'polling_credential_verifier', 'lease_owner', 'request_hash' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $status ); $this->assertArrayNotHasKey( $key, $preview );
		}
		$this->assertSame( $before, $this->backend->rows ); $this->assertSame( $swaps, $this->backend->swaps );
	}
	public function test_shared_fixture_matches_actual_producer_projections() {
		$fixture = json_decode( file_get_contents( __DIR__ . '/fixtures/cadence-authority-evidence-v1.json' ), true );
		$this->assertSame( $fixture['status'], $this->producer->status() );
		$this->assertSame( $fixture['preview'], $this->inspection() );
	}
	public function test_native_marker_is_created_by_commit_and_cannot_be_injected_in_reservation() {
		$s = $this->model->snapshot();
		$this->assertSame( 1, $s['claims'][ Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE ]['authority_evidence_version'] );
		$e = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $this->now, 2 ); $e['authority_evidence_version'] = 1;
		$before = $this->backend->rows;
		$this->assertInstanceOf( WP_Error::class, $this->model->reserve_change( $this->binding, $this->preview_id, $e ) );
		$this->assertSame( $before, $this->backend->rows );
	}
	public function test_unmarked_legacy_record_never_becomes_native_even_with_matching_fingerprint() {
		$s = $this->model->snapshot(); unset( $s['claims'][ Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE ]['authority_evidence_version'] ); $this->replace_state( $s );
		$this->assert_inspection_error( 'source_not_native' );
		$s['schema_version'] = 1; unset( $s['authority'], $s['dispatch'] ); $this->replace_state( $s );
		$this->assertIsArray( $this->model->initialize_authority( $this->binding, 'every_15_minutes', $this->now + 900 ) );
		$this->assertArrayNotHasKey( 'authority_evidence_version', $this->model->snapshot()['claims'][ Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE ] );
		$this->assert_inspection_error( 'source_not_native' );
	}
	public function test_forged_marker_in_schema_one_migration_is_not_accepted_or_repaired() {
		$s = $this->model->snapshot(); $s['schema_version'] = 1; unset( $s['authority'], $s['dispatch'] ); $this->replace_state( $s ); $before = $this->backend->rows;
		$this->assertInstanceOf( WP_Error::class, $this->model->initialize_authority( $this->binding, 'every_15_minutes', $this->now + 900 ) );
		$this->assertSame( $before, $this->backend->rows );
	}
	/** @dataProvider pairing_changes */
	public function test_pairing_and_key_changes_fail_closed( $key, $value ) {
		$this->pairing[$key] = $value;
		$this->assert_inspection_error( in_array( $key, array( 'connection_status', 'status_endpoint_enabled', 'remote_actions_enabled' ), true ) ? 'unavailable' : 'binding_changed' );
	}
	public function pairing_changes() {
		return array(
			array( 'connection_status', 'revoked' ), array( 'status_endpoint_enabled', false ), array( 'remote_actions_enabled', false ),
			array( 'action_key_id', 'ak_rotated' ), array( 'action_public_key', str_repeat( 'B', 43 ) ), array( 'polling_key_id', 'pk_rotated' ),
			array( 'polling_credential_verifier', str_repeat( 'd', 64 ) ), array( 'paired_at', 1799999990 ), array( 'remote_actions_opted_in_at', 1799999991 ),
			array( 'dashboard_origin', 'https://other.example.com' ), array( 'expected_client_origin', 'https://other-client.example.com' ),
			array( 'dashboard_site_public_id', '66666666-6666-4666-8666-666666666666' ),
		);
	}
	public function test_local_preview_opt_out_and_disabled_model_block_inspection() {
		$this->pairing['schedule_rollback_preview_enabled'] = false; $this->assert_inspection_error( 'inspection_blocked' );
		$this->pairing['schedule_rollback_preview_enabled'] = true; $this->model->invalidate( $this->binding ); $this->assert_inspection_error( 'inspection_blocked' );
	}
	public function test_exact_source_expiry_and_backward_clock_block_inspection() {
		$this->now--; $this->assert_inspection_error( 'source_expired' ); $this->now += 3601; $this->assert_inspection_error( 'source_expired' );
	}
	/** @dataProvider stale_changes */
	public function test_revision_cadence_and_due_changes_make_source_stale( $field, $value ) {
		$s = $this->model->snapshot(); if ( 'schedule_revision' === $field ) { $s[$field] = $value; } else { $s['authority'][$field] = $value; }
		$this->replace_state( $s ); $this->assert_inspection_error( 'source_stale' );
	}
	public function stale_changes() { return array( array( 'schedule_revision', 2 ), array( 'cadence', 'hourly' ), array( 'next_due_at', 1800000901 ) ); }
	public function test_consumed_source_is_not_available_for_another_inspection() {
		$s = $this->model->snapshot(); $record = $s['claims'][ Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE ]; $record['action_id'] = $this->preview_id; $record['lease_owner'] = $this->preview_id;
		$s['claims'][$this->change['action_id']] = $record; $this->replace_state( $s ); $this->assert_inspection_error( 'source_consumed' );
	}
	public function test_pending_dispatch_and_unfinished_journal_are_not_execution_readiness() {
		$s = $this->model->snapshot(); $s['authority']['next_due_at'] = $this->now; $this->replace_state( $s );
		$this->assertIsArray( $this->model->claim_dispatch( $this->binding, 1, $this->model->authority_fingerprint( $s ), $this->preview_id, str_repeat( 'f', 64 ) ) );
		$this->assertSame( 'claimed', $this->producer->status()['pending'] ); $this->assert_inspection_error( 'inspection_blocked' );
		$s = $this->model->snapshot(); $s['dispatch'] = null; $record = &$s['claims'][ Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE ]; unset( $record['authority_evidence_version'] ); $record['phase'] = 'recovery_required'; $record['completed_at'] = 0; unset( $record );
		$this->replace_state( $s ); $this->assertSame( 'recovery_required', $this->producer->status()['pending'] ); $this->assert_inspection_error( 'inspection_blocked' );
	}
	/** @dataProvider invalid_intents */
	public function test_invalid_or_expired_intent_does_not_read_or_write_state( $source, $preview, $expiry ) {
		$before = $this->backend->rows; $result = $this->producer->inspect( $source, $preview, $expiry );
		$this->assertSame( 'authority_evidence_invalid_intent', $result->get_error_code() ); $this->assertSame( $before, $this->backend->rows );
	}
	public function invalid_intents() {
		$a = '11111111-1111-4111-8111-000000000001'; $b = '44444444-4444-4444-8444-444444444444';
		return array( array( $a, $a, 1800000300 ), array( 'bad', $b, 1800000300 ), array( $a, $b, '1800000300' ), array( $a, $b, 1800000000 ), array( $a, $b, 1800000301 ) );
	}
	public function test_changed_model_during_inspection_is_not_returned_as_positive_evidence() {
		$s = $this->model->snapshot(); $other = $s; $other['generation']++;
		$model = $this->createMock( Alynt_Drime_Backups_Uploader_Cadence_Authority_Store::class ); $model->method( 'snapshot' )->willReturnOnConsecutiveCalls( $s, $other );
		$model->method( 'authority_fingerprint' )->willReturn( $this->model->authority_fingerprint( $s ) );
		$producer = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Evidence( $model, $this->connection, function () { return $this->now; } );
		$this->assertSame( 'authority_evidence_observation_changed', $producer->inspect( $this->change['action_id'], $this->preview_id, $this->now + 300 )->get_error_code() );
	}
	public function test_changed_pairing_during_inspection_is_not_returned_as_positive_evidence() {
		$connection = $this->createMock( Alynt_Drime_Backups_Uploader_Dashboard_Connection::class );
		$changed = $this->pairing; $changed['action_key_id'] = 'ak_rotated';
		$connection->method( 'get' )->willReturnOnConsecutiveCalls( $this->pairing, $changed );
		$connection->method( 'site_uuid_for_remote_actions' )->willReturn( $this->site_uuid );
		$producer = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Evidence( $this->model, $connection, function () { return $this->now; } );
		$this->assertSame( 'authority_evidence_observation_changed', $producer->inspect( $this->change['action_id'], $this->preview_id, $this->now + 300 )->get_error_code() );
	}
	public function test_native_marker_on_failed_record_is_invalid_state_not_positive_evidence() {
		$s = $this->model->snapshot(); $s['claims'][ Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE ]['phase'] = 'failed_closed'; $this->replace_state( $s );
		$this->assert_inspection_error( 'unavailable' );
	}
	public function test_missing_unknown_and_invalid_marker_state_is_not_repaired() {
		$s = $this->model->snapshot(); $s['claims'][ Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE ]['authority_evidence_version'] = 2; $this->replace_state( $s ); $this->assert_inspection_error( 'unavailable' );
		$s['schema_version'] = 3; $this->replace_state( $s ); $this->assert_inspection_error( 'unavailable' );
		$this->backend->rows = array(); $this->assert_inspection_error( 'unavailable' ); $this->assertInstanceOf( WP_Error::class, $this->producer->status() );
	}
	public function test_guarded_scan_due_advancement_makes_prior_native_rollback_evidence_stale() {
		$this->now += 900; $this->assertIsArray( $this->inspection() );
		$dispatcher = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Scan_Dispatcher( $this->model, function () { return array( 'binding' => $this->producer->enrollment_binding(), 'scan_enabled' => true ); }, true, function () { return $this->now; }, function () { return $this->preview_id; } );
		$this->assertIsArray( $dispatcher->run( function () { return array( 'errors' => array(), 'candidates' => array(), 'queued' => 0 ); } ) );
		$this->assertSame( 1, $this->model->snapshot()['schedule_revision'] ); $this->assertSame( 'every_15_minutes', $this->model->snapshot()['authority']['cadence'] );
		$this->assertSame( $this->now + 900, $this->model->snapshot()['authority']['next_due_at'] ); $this->assert_inspection_error( 'source_stale' );
	}
}
