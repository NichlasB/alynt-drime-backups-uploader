<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/CadenceLifecycleFixture.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CadenceLifecyclePreparationTest extends TestCase {
	use Alynt_Drime_Backups_Uploader_Test_Cadence_Lifecycle_Fixture;
	protected function setUp(): void { parent::setUp(); Monkey\setUp(); Functions\when( 'wp_json_encode' )->alias( 'json_encode' ); $this->prepare_fixture(); }
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

	public function test_creation_is_explicit_disabled_and_does_not_assume_history_or_consent() {
		$this->assertSame( array(), $this->backend->rows ); $this->assertInstanceOf( WP_Error::class, $this->model->snapshot() );
		$s = $this->prepare(); $this->assertSame( 3, $s['schema_version'] ); $this->assertFalse( $s['authority']['enabled'] );
		$this->assertSame( array( 'phase' => 'preparing', 'consent' => false, 'permission_generation' => 0, 'history' => 'new_model', 'changed_at' => $this->now ), $s['lifecycle'] );
		$this->assertCount( 1, $this->backend->rows ); $before = $this->backend->rows; $this->assertSame( $s, $this->prepare() ); $this->assertSame( $before, $this->backend->rows );
	}
	public function test_schema_one_migration_blocks_unfinished_and_preserves_terminal_record_verbatim() {
		list( $legacy, $e ) = $this->legacy_claim(); $before = $this->backend->rows;
		$this->assert_error( 'schedule_safety_lifecycle_recovery_pending', $this->prepare() ); $this->assertSame( $before, $this->backend->rows );
		$legacy->transition( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, $e['action_id'], $e['request_hash'], 'claimed', 'failed_closed' ); $records = $legacy->snapshot()['claims'];
		$s = $this->prepare(); $this->assertSame( $records, $s['claims'] ); $this->assertSame( 'unknown', $s['lifecycle']['history'] );
		$this->assertSame( 0, $s['lifecycle']['permission_generation'] ); $this->assertFalse( $s['lifecycle']['consent'] ); $this->assertSame( 1, $s['schedule_revision'] );
		$this->assertInstanceOf( WP_Error::class, $legacy->snapshot() );
	}
	public function test_schema_two_migration_disables_model_without_changing_cadence_due_or_dispatch_receipt() {
		$old = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Store( $this->backend, function () { return $this->now; } );
		$old->initialize_authority( $this->binding, 'every_15_minutes', 1800000900 ); $s = $old->snapshot();
		$new = $this->prepare(); $this->assertFalse( $new['authority']['enabled'] ); $this->assertSame( $s['authority']['next_due_at'], $new['authority']['next_due_at'] );
		$this->assertSame( $s['authority']['cadence'], $new['authority']['cadence'] ); $this->assertSame( $s['dispatch'], $new['dispatch'] ); $this->assertSame( $s['claims'], $new['claims'] );
		$this->assertSame( 'unknown', $new['lifecycle']['history'] ); $this->assertInstanceOf( WP_Error::class, $old->snapshot() );
	}
	/** @dataProvider unfinished_dispatches */
	public function test_uncertain_schema_two_dispatch_cannot_be_migrated_or_reset_at_expiry( $phase ) {
		$old = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Store( $this->backend, function () { return $this->now; } );
		$old->initialize_authority( $this->binding, 'every_15_minutes', 1800000900 ); $this->now = 1800000900; $s = $old->snapshot(); $owner = '44444444-4444-4444-8444-444444444444'; $hash = str_repeat( 'b', 64 );
		$old->claim_dispatch( $this->binding, $s['schedule_revision'], $old->authority_fingerprint( $s ), $owner, $hash );
		if ( 'claimed' !== $phase ) $old->start_dispatch( $this->binding, $owner, $hash );
		if ( 'recovery_required' === $phase ) $old->require_dispatch_recovery( $this->binding, $owner, $hash );
		$this->now += 600; $before = $this->backend->rows; $this->assert_error( 'schedule_safety_lifecycle_recovery_pending', $this->prepare() ); $this->assertSame( $before, $this->backend->rows );
	}
	public function unfinished_dispatches() { return array( array( 'claimed' ), array( 'started' ), array( 'recovery_required' ) ); }

	public function test_completed_dispatch_receipt_survives_migration_but_cannot_start_again() {
		$old = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Store( $this->backend, function () { return $this->now; } );
		$old->initialize_authority( $this->binding, 'every_15_minutes', $this->now ); $s = $old->snapshot(); $owner = '44444444-4444-4444-8444-444444444444'; $hash = str_repeat( 'b', 64 );
		$old->claim_dispatch( $this->binding, $s['schedule_revision'], $old->authority_fingerprint( $s ), $owner, $hash ); $old->start_dispatch( $this->binding, $owner, $hash ); $old->complete_dispatch( $this->binding, $owner, $hash ); $s = $old->snapshot();
		$new = $this->model->prepare_lifecycle( $this->binding, 'every_15_minutes', $s['authority']['next_due_at'] );
		$this->assertSame( $s['dispatch'], $new['dispatch'] ); $before = $this->backend->rows;
		$this->assertTrue( $this->model->claim_dispatch( $this->binding, $new['schedule_revision'], $this->model->authority_fingerprint( $new ), $owner, $hash )['observed'] );
		$this->assert_error( 'schedule_safety_dispatch_phase_invalid', $this->model->start_dispatch( $this->binding, $owner, $hash ) ); $this->assertSame( $before, $this->backend->rows );
	}

	public function test_failed_create_read_and_migration_leave_all_bytes_unchanged() {
		$this->backend->fail_create = true; $this->assert_error( 'fixture_create_failed', $this->prepare() ); $this->assertSame( array(), $this->backend->rows );
		$this->backend->fail_create = false; $old = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Store( $this->backend ); $old->initialize( $this->binding ); $before = $this->backend->rows;
		$this->backend->fail_read = true; $this->assert_error( 'fixture_read_failed', $this->prepare() ); $this->assertSame( $before, $this->backend->rows );
		$this->backend->fail_read = false; $this->backend->fail_write = true; $this->assert_error( 'schedule_safety_write_failed', $this->prepare() ); $this->assertSame( $before, $this->backend->rows );
	}
	public function test_prepare_reloads_competing_initializer_and_does_not_reset_permission_generation() {
		$other = $this->new_lifecycle(); $this->backend->before_create = function () use ( $other ) { $other->prepare_lifecycle( $this->binding, 'every_15_minutes', 1800000900 ); $other->record_consent( $this->binding, 0, true ); };
		$s = $this->prepare(); $this->assertSame( 1, $s['lifecycle']['permission_generation'] ); $this->assertTrue( $s['lifecycle']['consent'] ); $this->assertFalse( $s['authority']['enabled'] );
	}
	public function test_migration_rechecks_new_unfinished_work_after_conflicting_cas() {
		$legacy = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Store( $this->backend, function () { return $this->now; } ); $legacy->initialize( $this->binding );
		$this->backend->before_swap = function () use ( $legacy ) { $legacy->claim( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $this->now ) ); };
		$this->assert_error( 'schedule_safety_lifecycle_recovery_pending', $this->prepare() ); $this->assertSame( 1, $legacy->snapshot()['schema_version'] ); $this->assertCount( 1, $legacy->snapshot()['claims'] );
	}
	public function test_older_initialization_and_blind_invalidation_apis_cannot_enable_or_erase_state() {
		$this->prepare(); $before = $this->backend->rows;
		$this->assertInstanceOf( WP_Error::class, $this->model->initialize( $this->binding ) );
		$this->assert_error( 'schedule_safety_lifecycle_api_required', $this->model->initialize_authority( $this->binding, 'every_15_minutes', 1800000900 ) );
		$this->assert_error( 'schedule_safety_lifecycle_api_required', $this->model->invalidate( $this->binding ) ); $this->assertSame( $before, $this->backend->rows );
	}
}
