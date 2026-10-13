<?php
/** Dormant authority and dispatch contracts; no worker or cron execution. */
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ScheduleSafetyFixture.php';

/**
 * Isolate optional WordPress fixture functions.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CadenceAuthorityStoreTest extends TestCase {
	private $backend;
	private $model;
	private $now = 1800000000;
	private $binding;
	private $owner = '44444444-4444-4444-8444-444444444444';
	private $hash;
	protected function setUp(): void {
		parent::setUp(); Monkey\setUp(); Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		$this->backend = new Alynt_Drime_Backups_Uploader_Test_Safety_Memory_CAS(); $this->binding = str_repeat( 'e', 64 ); $this->hash = str_repeat( 'f', 64 );
		$this->model = $this->new_model(); $this->model->initialize_authority( $this->binding, 'every_30_minutes', $this->now );
	}
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }
	private function new_model() { return new Alynt_Drime_Backups_Uploader_Cadence_Authority_Store( $this->backend, function () { return $this->now; } ); }
	private function claim_dispatch( $owner = null, $fingerprint = null, $revision = null ) {
		$s = $this->model->snapshot();
		return $this->model->claim_dispatch( $this->binding, null === $revision ? $s['schedule_revision'] : $revision, null === $fingerprint ? $this->model->authority_fingerprint( $s ) : $fingerprint, null === $owner ? $this->owner : $owner, $this->hash );
	}
	private function change( $number = 1, $source = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE ) {
		$s = $this->model->snapshot(); $candidate = $s; $candidate['schedule_revision']++; $candidate['authority']['cadence'] = 'every_15_minutes'; $candidate['authority']['next_due_at'] = $this->now + 900;
		$e = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $this->now, $number ); $e['before_fingerprint'] = $this->model->authority_fingerprint( $s ); $e['intended_fingerprint'] = $this->model->authority_fingerprint( $candidate );
		$this->model->reserve_change( $this->binding, $source, $e ); return $e;
	}
	private function commit( $e, $source = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE ) { return $this->model->commit_change( $this->binding, $source, $e['action_id'], $e['request_hash'], $this->now + 900 ); }

	public function test_schema_two_is_explicit_and_legacy_api_rejects_without_writing() {
		$this->assertSame( 2, $this->model->snapshot()['schema_version'] );
		$this->assertSame( 'schedule_safety_authority_api_required', $this->model->initialize( $this->binding )->get_error_code() );
		$legacy = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Store( $this->backend );
		$this->assertSame( 'schedule_safety_invalid_state', $legacy->snapshot()->get_error_code() );
		$this->assertSame( 0, $this->backend->swaps );
	}
	public function test_legacy_terminal_journal_is_retained_and_unfinished_migration_blocks() {
		$this->backend->rows = array(); $legacy = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Store( $this->backend, function () { return $this->now; } ); $legacy->initialize( $this->binding );
		$e = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $this->now ); $source = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE; $legacy->claim( $this->binding, $source, $e );
		$before = $this->backend->rows;
		$this->assertSame( 'schedule_safety_migration_recovery_pending', $this->model->initialize_authority( $this->binding, 'every_30_minutes', $this->now )->get_error_code() );
		$this->assertSame( $before, $this->backend->rows );
		$legacy->transition( $this->binding, $source, $e['action_id'], $e['request_hash'], 'claimed', 'failed_closed' ); $records = $legacy->snapshot()['claims'];
		$this->assertIsArray( $this->model->initialize_authority( $this->binding, 'every_30_minutes', $this->now ) );
		$this->assertSame( $records, $this->model->snapshot()['claims'] ); $this->assertSame( 1, $this->model->snapshot()['schedule_revision'] );
	}
	public function test_atomic_cadence_commit_marks_source_and_changes_authority_in_one_swap() {
		$e = $this->change(); $swaps = $this->backend->swaps;
		$this->assertTrue( $this->commit( $e )['changed'] );
		$s = $this->model->snapshot(); $this->assertSame( $swaps + 1, $this->backend->swaps );
		$this->assertSame( 'every_15_minutes', $s['authority']['cadence'] ); $this->assertSame( $this->now + 900, $s['authority']['next_due_at'] );
		$this->assertSame( 'succeeded', $s['claims'][ Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE ]['phase'] );
		$this->assertTrue( $this->commit( $e )['observed'] ); $this->assertSame( $swaps + 1, $this->backend->swaps );
	}
	public function test_failed_commit_preserves_both_journal_and_authority() {
		$e = $this->change(); $before = $this->model->snapshot(); $this->backend->fail_write = true;
		$this->assertSame( 'schedule_safety_write_failed', $this->commit( $e )->get_error_code() );
		$this->assertSame( $before, $this->model->snapshot() );
	}
	public function test_dispatch_winning_the_race_blocks_cadence_commit_after_reload() {
		$e = $this->change(); $other = $this->new_model(); $s = $this->model->snapshot(); $fp = $this->model->authority_fingerprint( $s );
		$this->backend->before_swap = function () use ( $other, $s, $fp ) { $this->assertIsArray( $other->claim_dispatch( $this->binding, $s['schedule_revision'], $fp, $this->owner, $this->hash ) ); };
		$this->assertSame( 'schedule_safety_authority_blocked', $this->commit( $e )->get_error_code() );
		$this->assertSame( 'every_30_minutes', $this->model->snapshot()['authority']['cadence'] );
	}
	public function test_cadence_winning_the_race_rejects_stale_dispatch_after_reload() {
		$e = $this->change(); $s = $this->model->snapshot(); $fp = $this->model->authority_fingerprint( $s );
		$this->backend->before_swap = function () use ( $e ) { $this->assertTrue( $this->commit( $e )['changed'] ); };
		$this->assertSame( 'schedule_safety_authority_stale', $this->claim_dispatch( null, $fp, $s['schedule_revision'] )->get_error_code() );
		$this->assertNull( $this->model->snapshot()['dispatch'] );
	}
	public function test_early_wakeup_is_rejected_and_due_boundary_is_inclusive() {
		$this->now--; $this->assertSame( 'schedule_safety_not_due', $this->claim_dispatch()->get_error_code() );
		$this->now++; $this->assertFalse( $this->claim_dispatch()['observed'] );
	}
	public function test_duplicate_claim_is_observation_and_start_has_one_winner() {
		$first = $this->claim_dispatch(); $this->now += 1; $this->assertTrue( $this->claim_dispatch()['observed'] );
		$this->assertSame( $first['dispatch'], $this->claim_dispatch()['dispatch'] );
		$other = $this->new_model(); $this->backend->before_swap = function () use ( $other ) { $this->assertSame( 'started', $other->start_dispatch( $this->binding, $this->owner, $this->hash )['phase'] ); };
		$this->assertSame( 'schedule_safety_dispatch_phase_invalid', $this->model->start_dispatch( $this->binding, $this->owner, $this->hash )->get_error_code() );
	}
	public function test_completion_and_due_advance_are_one_write_with_no_repeat() {
		$old = $this->model->snapshot(); $this->claim_dispatch(); $this->model->start_dispatch( $this->binding, $this->owner, $this->hash ); $this->now += 40; $swaps = $this->backend->swaps;
		$this->assertSame( 'completed', $this->model->complete_dispatch( $this->binding, $this->owner, $this->hash )['phase'] );
		$this->assertSame( $swaps + 1, $this->backend->swaps ); $this->assertSame( $this->now + 1800, $this->model->snapshot()['authority']['next_due_at'] );
		$this->assertSame( 'schedule_safety_dispatch_phase_invalid', $this->model->complete_dispatch( $this->binding, $this->owner, $this->hash )->get_error_code() );
		$this->assertSame( 'schedule_safety_authority_stale', $this->claim_dispatch( '55555555-5555-4555-8555-555555555555', $this->model->authority_fingerprint( $old ) )->get_error_code() );
	}
	public function test_expired_started_work_survives_restart_and_cannot_be_reclaimed() {
		$claim = $this->claim_dispatch(); $this->model->start_dispatch( $this->binding, $this->owner, $this->hash ); $this->now = $claim['dispatch']['lease_deadline']; $restart = $this->new_model();
		$this->assertSame( 'schedule_safety_inspection_required', $restart->complete_dispatch( $this->binding, $this->owner, $this->hash )->get_error_code() );
		$this->assertSame( 'started', $restart->snapshot()['dispatch']['phase'] );
		$this->assertSame( 'schedule_safety_authority_blocked', $this->claim_dispatch( '55555555-5555-4555-8555-555555555555' )->get_error_code() );
		$this->assertSame( 'recovery_required', $restart->require_dispatch_recovery( $this->binding, $this->owner, $this->hash )['phase'] );
		$this->assertInstanceOf( WP_Error::class, $restart->cancel_dispatch( $this->binding, $this->owner, $this->hash ) );
	}
	public function test_owner_mismatch_and_failed_start_grant_no_permission() {
		$this->claim_dispatch(); $this->assertSame( 'schedule_safety_owner_mismatch', $this->model->start_dispatch( $this->binding, $this->owner, str_repeat( 'a', 64 ) )->get_error_code() );
		$this->backend->fail_write = true; $this->assertSame( 'schedule_safety_write_failed', $this->model->start_dispatch( $this->binding, $this->owner, $this->hash )->get_error_code() );
		$this->assertSame( 'claimed', $this->model->snapshot()['dispatch']['phase'] );
	}
	public function test_invalidation_disables_authority_and_retains_uncertain_work() {
		$this->claim_dispatch(); $this->model->start_dispatch( $this->binding, $this->owner, $this->hash ); $this->model->invalidate( $this->binding, str_repeat( 'a', 64 ) );
		$this->assertFalse( $this->model->snapshot()['authority']['enabled'] ); $this->assertSame( 'recovery_required', $this->model->snapshot()['dispatch']['phase'] );
		$this->assertInstanceOf( WP_Error::class, $this->model->complete_dispatch( $this->binding, $this->owner, $this->hash ) );
	}
	public function test_expired_unstarted_claim_requires_explicit_owner_cancellation() {
		$d = $this->claim_dispatch()['dispatch']; $this->now = $d['lease_deadline'];
		$this->assertSame( 'schedule_safety_inspection_required', $this->model->start_dispatch( $this->binding, $this->owner, $this->hash )->get_error_code() );
		$this->assertSame( 'cancelled', $this->model->cancel_dispatch( $this->binding, $this->owner, $this->hash )['phase'] );
		$this->assertFalse( $this->claim_dispatch( '55555555-5555-4555-8555-555555555555' )['observed'] );
	}
	public function test_malformed_unknown_state_and_legacy_claim_api_do_not_mutate() {
		$name = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION; $s = $this->model->snapshot(); $s['authority']['enabled'] = '1'; $this->backend->rows[$name]['option_value'] = json_encode( $s ); $before = $this->backend->rows;
		$this->assertInstanceOf( WP_Error::class, $this->model->initialize_authority( $this->binding, 'every_30_minutes', $this->now ) );
		$this->assertSame( $before, $this->backend->rows );
		$this->assertInstanceOf( WP_Error::class, $this->model->claim( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, array() ) );
	}
	public function test_capacity_keeps_claims_protected_in_schema_two() {
		for ( $i = 1; $i <= 50; $i++ ) { $this->change( $i, sprintf( '22222222-2222-4222-8222-%012d', $i ) ); }
		$e = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $this->now, 51 );
		$this->assertSame( 'schedule_safety_capacity_reached', $this->model->reserve_change( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, $e )->get_error_code() ); $this->assertCount( 50, $this->model->snapshot()['claims'] );
	}
	public function test_failed_dispatch_claim_and_completion_do_not_change_authority() {
		$before = $this->model->snapshot(); $this->backend->fail_write = true;
		$this->assertSame( 'schedule_safety_write_failed', $this->claim_dispatch()->get_error_code() ); $this->assertSame( $before, $this->model->snapshot() );
		$this->backend->fail_write = false; $this->claim_dispatch(); $this->model->start_dispatch( $this->binding, $this->owner, $this->hash ); $before = $this->model->snapshot(); $this->backend->fail_write = true;
		$this->assertSame( 'schedule_safety_write_failed', $this->model->complete_dispatch( $this->binding, $this->owner, $this->hash )->get_error_code() ); $this->assertSame( $before, $this->model->snapshot() );
	}
	public function test_exact_commit_expiry_and_forged_candidate_do_not_change_authority() {
		$e = $this->change(); $s = $this->model->snapshot();
		$this->assertSame( 'schedule_safety_authority_stale', $this->model->commit_change( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, $e['action_id'], $e['request_hash'], $this->now + 899 )->get_error_code() );
		$this->assertSame( $s, $this->model->snapshot() ); $this->now = $s['claims'][ Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE ]['deadline'];
		$this->assertSame( 'schedule_safety_inspection_required', $this->commit( $e )->get_error_code() ); $this->assertSame( $s, $this->model->snapshot() );
	}
	public function test_fingerprint_is_field_order_independent_and_changes_with_due_revision_binding() {
		$s = $this->model->snapshot(); $fp = $this->model->authority_fingerprint( $s ); $copy = $s; $copy['authority'] = array_reverse( $copy['authority'], true );
		$this->assertSame( $fp, $this->model->authority_fingerprint( $copy ) ); $copy['authority']['next_due_at']++; $this->assertNotSame( $fp, $this->model->authority_fingerprint( $copy ) );
		$copy = $s; $copy['schedule_revision']++; $this->assertNotSame( $fp, $this->model->authority_fingerprint( $copy ) ); $copy = $s; $copy['enrollment_binding'] = str_repeat( 'a', 64 ); $this->assertNotSame( $fp, $this->model->authority_fingerprint( $copy ) );
	}
	public function test_disabled_model_and_unknown_schema_cannot_be_initialized_over() {
		$this->model->invalidate( $this->binding ); $before = $this->backend->rows;
		$this->assertInstanceOf( WP_Error::class, $this->model->initialize_authority( $this->binding, 'every_30_minutes', $this->now ) ); $this->assertSame( $before, $this->backend->rows );
		$name = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION; $s = $this->model->snapshot(); $s['schema_version'] = 3; $this->backend->rows[$name]['option_value'] = json_encode( $s ); $before = $this->backend->rows;
		$this->assertInstanceOf( WP_Error::class, $this->model->initialize_authority( $this->binding, 'every_30_minutes', $this->now ) ); $this->assertSame( $before, $this->backend->rows );
	}
	public function test_disabled_or_claimed_dispatch_blocks_new_change_reservations() {
		$e = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $this->now ); $source = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE;
		$this->claim_dispatch(); $this->assertSame( 'schedule_safety_authority_blocked', $this->model->reserve_change( $this->binding, $source, $e )->get_error_code() );
		$this->model->invalidate( $this->binding ); $this->assertSame( 'schedule_safety_authority_blocked', $this->model->reserve_change( $this->binding, $source, $e )->get_error_code() );
		$this->assertSame( array(), $this->model->snapshot()['claims'] );
	}
	public function test_invalidation_racing_reservation_is_rechecked_after_cas_conflict() {
		$e = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $this->now );
		$this->backend->before_swap = function () { $this->assertIsInt( $this->model->invalidate( $this->binding ) ); };
		$this->assertSame( 'schedule_safety_authority_blocked', $this->model->reserve_change( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, $e )->get_error_code() );
		$this->assertSame( array(), $this->model->snapshot()['claims'] );
	}
	public function test_retained_uncertain_journal_blocks_an_already_claimed_dispatch_start() {
		$this->change(); $this->claim_dispatch(); $s = $this->model->snapshot(); $s['claims'][ Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE ]['phase'] = 'recovery_required';
		$this->backend->rows[ Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION ]['option_value'] = json_encode( $s );
		$this->assertSame( 'schedule_safety_inspection_required', $this->model->start_dispatch( $this->binding, $this->owner, $this->hash )->get_error_code() );
		$this->assertSame( 'claimed', $this->model->snapshot()['dispatch']['phase'] );
	}
	public function test_clock_regression_does_not_grant_early_start_or_commit() {
		$e = $this->change(); $this->claim_dispatch(); $this->now--;
		$this->assertSame( 'schedule_safety_inspection_required', $this->model->start_dispatch( $this->binding, $this->owner, $this->hash )->get_error_code() );
		$this->assertSame( 'claimed', $this->model->snapshot()['dispatch']['phase'] ); $this->now++; $this->model->cancel_dispatch( $this->binding, $this->owner, $this->hash ); $this->now--;
		$this->assertSame( 'schedule_safety_inspection_required', $this->commit( $e )->get_error_code() );
	}
}
