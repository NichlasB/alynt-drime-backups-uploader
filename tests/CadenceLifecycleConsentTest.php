<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/CadenceLifecycleFixture.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CadenceLifecycleConsentTest extends TestCase {
	use Alynt_Drime_Backups_Uploader_Test_Cadence_Lifecycle_Fixture;
	protected function setUp(): void { parent::setUp(); Monkey\setUp(); Functions\when( 'wp_json_encode' )->alias( 'json_encode' ); $this->prepare_fixture(); $this->prepare(); }
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

	public function test_consent_and_revocation_update_generation_revision_and_phase_in_one_cas() {
		$s = $this->model->snapshot(); $swaps = $this->backend->swaps;
		$this->assertSame( 1, $this->model->record_consent( $this->binding, 0, true ) ); $new = $this->model->snapshot();
		$this->assertSame( $swaps + 1, $this->backend->swaps ); $this->assertSame( $s['generation'] + 1, $new['generation'] ); $this->assertSame( $s['schedule_revision'] + 1, $new['schedule_revision'] );
		$this->assertTrue( $new['lifecycle']['consent'] ); $this->assertSame( 'preparing', $new['lifecycle']['phase'] ); $this->assertFalse( $new['authority']['enabled'] );
		$this->assertSame( 2, $this->model->record_consent( $this->binding, 1, false ) ); $end = $this->model->snapshot();
		$this->assertSame( 'disabled', $end['lifecycle']['phase'] ); $this->assertSame( $s['authority'], $end['authority'] ); $this->assertSame( $s['claims'], $end['claims'] );
		$this->assertNotSame( $this->model->authority_fingerprint( $s ), $this->model->authority_fingerprint( $end ) );
	}
	public function test_recorded_model_consent_cannot_dispatch_or_reserve_schedule_work() {
		$this->model->record_consent( $this->binding, 0, true ); $s = $this->model->snapshot(); $before = $this->backend->rows;
		$this->assert_error( 'schedule_safety_authority_blocked', $this->model->claim_dispatch( $this->binding, $s['schedule_revision'], $this->model->authority_fingerprint( $s ), '44444444-4444-4444-8444-444444444444', str_repeat( 'b', 64 ) ) );
		$this->assert_error( 'schedule_safety_authority_blocked', $this->model->reserve_change( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $this->now ) ) );
		$this->assertSame( $before, $this->backend->rows );
	}
	public function test_stale_generation_after_change_away_and_back_is_not_reused() {
		$this->model->record_consent( $this->binding, 0, true ); $this->model->record_consent( $this->binding, 1, false ); $this->model->record_consent( $this->binding, 2, true ); $before = $this->backend->rows;
		$this->assert_error( 'schedule_safety_lifecycle_stale', $this->model->record_consent( $this->binding, 0, true ) ); $this->assertSame( $before, $this->backend->rows );
	}
	public function test_revocation_winning_a_cas_race_blocks_stale_grant_after_reload() {
		$other = $this->new_lifecycle(); $this->backend->before_swap = function () use ( $other ) { $this->assertSame( 1, $other->record_consent( $this->binding, 0, false ) ); };
		$this->assert_error( 'schedule_safety_lifecycle_stale', $this->model->record_consent( $this->binding, 0, true ) ); $s = $this->model->snapshot();
		$this->assertFalse( $s['lifecycle']['consent'] ); $this->assertSame( 'disabled', $s['lifecycle']['phase'] ); $this->assertSame( 1, $s['lifecycle']['permission_generation'] );
	}
	public function test_rebinding_winning_a_cas_race_blocks_old_context_and_does_not_transfer_consent() {
		$other = $this->new_lifecycle(); $replacement = str_repeat( 'b', 64 );
		$this->backend->before_swap = function () use ( $other, $replacement ) { $other->record_consent( $this->binding, 0, false, $replacement ); };
		$this->assert_error( 'schedule_safety_binding_changed', $this->model->record_consent( $this->binding, 0, true ) ); $s = $this->model->snapshot();
		$this->assertSame( $replacement, $s['enrollment_binding'] ); $this->assertFalse( $s['lifecycle']['consent'] ); $this->assertFalse( $s['authority']['enabled'] );
	}
	public function test_failed_write_and_exhausted_contention_do_not_partially_change_any_state() {
		$before = $this->backend->rows; $this->backend->fail_write = true;
		$this->assert_error( 'schedule_safety_write_failed', $this->model->record_consent( $this->binding, 0, true ) ); $this->assertSame( $before, $this->backend->rows );
		$this->backend->fail_write = false; $this->backend->conflicts = 3; $swaps = $this->backend->swaps;
		$this->assert_error( 'schedule_safety_contention', $this->model->record_consent( $this->binding, 0, true ) ); $this->assertSame( $swaps + 3, $this->backend->swaps ); $this->assertSame( $before, $this->backend->rows );
	}
	public function test_failed_revocation_never_reports_disabled_or_changes_binding() {
		$this->model->record_consent( $this->binding, 0, true ); $before = $this->backend->rows; $this->backend->fail_write = true;
		$this->assert_error( 'schedule_safety_write_failed', $this->model->record_consent( $this->binding, 1, false, str_repeat( 'b', 64 ) ) ); $this->assertSame( $before, $this->backend->rows );
	}
	public function test_unresolved_journal_survives_expiry_restart_rebinding_and_repeated_revocation() {
		$prepared = $this->model->snapshot(); $this->backend->rows = array(); list( $legacy, $e ) = $this->legacy_claim();
		$record = $legacy->snapshot()['claims'][Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE]; $record['phase'] = 'recovery_required';
		$prepared['claims'][Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE] = $record; $prepared['lifecycle']['phase'] = 'draining'; $this->replace_state( $prepared );
		$this->now += 90000; $restart = $this->new_lifecycle(); $before = $restart->snapshot();
		$this->assert_error( 'schedule_safety_lifecycle_recovery_pending', $restart->record_consent( $this->binding, 0, true ) );
		$this->assertSame( 1, $restart->record_consent( $this->binding, 0, false, str_repeat( 'b', 64 ) ) ); $end = $restart->snapshot();
		$this->assertSame( $before['claims'], $end['claims'] ); $this->assertSame( $before['authority'], $end['authority'] ); $this->assertSame( 'draining', $end['lifecycle']['phase'] );
		$this->assertSame( 2, $restart->record_consent( str_repeat( 'b', 64 ), 1, false ) ); $this->assertSame( $before['claims'], $restart->snapshot()['claims'] );
	}
	/** @dataProvider invalid_changes */
	public function test_invalid_mutation_inputs_are_not_coerced_or_persisted( $generation, $consent, $replacement ) {
		$before = $this->backend->rows; $this->assert_error( 'schedule_safety_lifecycle_invalid', $this->model->record_consent( $this->binding, $generation, $consent, $replacement ) ); $this->assertSame( $before, $this->backend->rows );
	}
	public function invalid_changes() { return array( array( '0', true, null ), array( -1, true, null ), array( PHP_INT_MAX, true, null ), array( 0, 1, null ), array( 0, 'true', null ), array( 0, true, str_repeat( 'b', 64 ) ), array( 0, false, 'bad' ) ); }
	/** @dataProvider invalid_times */
	public function test_clock_regression_invalid_type_or_overflow_cannot_mutate( $now ) {
		$this->now = $now; $before = $this->backend->rows;
		$this->assert_error( 'schedule_safety_lifecycle_clock_invalid', $this->model->record_consent( $this->binding, 0, true ) ); $this->assertSame( $before, $this->backend->rows );
	}
	public function invalid_times() { return array( array( 1799999999 ), array( '1800000000' ), array( null ), array( 0 ), array( PHP_INT_MAX ) ); }
	/** @dataProvider overflowing_counters */
	public function test_monotonic_counter_exhaustion_never_wraps_or_reinitializes( $field ) {
		$s = $this->model->snapshot(); if ( 'permission_generation' === $field ) $s['lifecycle'][$field] = PHP_INT_MAX - 1; else $s[$field] = PHP_INT_MAX - 1;
		$this->replace_state( $s ); $before = $this->backend->rows; $generation = $s['lifecycle']['permission_generation'];
		$this->assert_error( 'schedule_safety_invalid_state', $this->model->record_consent( $this->binding, $generation, true ) ); $this->assertSame( $before, $this->backend->rows );
	}
	public function overflowing_counters() { return array( array( 'permission_generation' ), array( 'generation' ), array( 'schedule_revision' ) ); }
}
