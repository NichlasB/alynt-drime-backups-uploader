<?php
/** Guarded callback state/side-effect boundaries; all callbacks are harmless fixtures. */
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/GuardedScanCallbackFixture.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class GuardedScanCallbackTest extends TestCase {
	use Alynt_Drime_Backups_Uploader_Test_Guarded_Scan_Fixture;
	protected function setUp(): void { parent::setUp(); Monkey\setUp(); Functions\when( 'wp_json_encode' )->alias( 'json_encode' ); $this->prepare_guarded_scan(); }
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }
	public function test_disabled_default_never_reads_policy_or_store_or_runs_callback() {
		$store = $this->createMock( Alynt_Drime_Backups_Uploader_Cadence_Authority_Store::class ); $store->expects( $this->never() )->method( 'snapshot' );
		$dispatcher = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Scan_Dispatcher( $store, function () { $this->fail( 'Disabled policy read.' ); } );
		$this->assertSame( 'authority_scan_disabled', $dispatcher->run( $this->successful_callback() )->get_error_code() );
		$this->assertSame( 'authority_scan_disabled', $this->new_dispatcher( '1' )->run( $this->successful_callback() )->get_error_code() ); $this->assertSame( 0, $this->callback_count );
	}
	public function test_success_runs_once_and_completes_and_advances_due_atomically() {
		$this->assertSame( array( 'outcome' => 'completed', 'callback_invoked' => true ), $this->dispatcher->run( $this->successful_callback() ) );
		$this->assertSame( 1, $this->callback_count ); $this->assertSame( 'completed', $this->model->snapshot()['dispatch']['phase'] );
		$this->assertSame( $this->now + 1800, $this->model->snapshot()['authority']['next_due_at'] ); $this->assertSame( 3, $this->backend->swaps );
		$before = $this->backend->rows; $this->assertInstanceOf( WP_Error::class, $this->new_dispatcher()->run( $this->successful_callback() ) ); $this->assertSame( $before, $this->backend->rows ); $this->assertSame( 1, $this->callback_count );
	}
	public function test_early_wakeup_is_no_write_and_missed_windows_do_not_catch_up() {
		$this->now--; $before = $this->backend->rows; $this->assertInstanceOf( WP_Error::class, $this->dispatcher->run( $this->successful_callback() ) ); $this->assertSame( $before, $this->backend->rows ); $this->assertSame( 0, $this->callback_count );
		$this->now += 10801; $this->assertIsArray( $this->dispatcher->run( $this->successful_callback() ) ); $this->assertSame( $this->now + 1800, $this->model->snapshot()['authority']['next_due_at'] ); $this->assertSame( 1, $this->callback_count );
	}
	/** @dataProvider failed_start_phases */
	public function test_claim_or_start_write_failure_never_runs_callback( $phase, $expected ) {
		$this->backend->fail_phases = array( $phase ); $this->assertInstanceOf( WP_Error::class, $this->dispatcher->run( $this->successful_callback() ) );
		$this->assertSame( $expected, $this->model->snapshot()['dispatch']['phase'] ?? null ); $this->assertSame( 0, $this->callback_count );
		$this->assertSame( 1800000000, $this->model->snapshot()['authority']['next_due_at'] );
	}
	public function failed_start_phases() { return array( array( 'claimed', null ), array( 'started', 'claimed' ) ); }
	public function test_policy_change_after_claim_retains_unstarted_ownership_without_resuming() {
		$this->after_policy = function ( $count ) { if ( 2 === $count ) $this->scan_enabled = false; };
		$this->assertSame( 'authority_scan_context_changed', $this->dispatcher->run( $this->successful_callback() )->get_error_code() );
		$this->scan_enabled = true; $this->after_policy = null; $this->assert_protected( 'claimed', 0 );
	}
	public function test_binding_change_at_last_pre_callback_check_blocks_the_callback() {
		$this->after_policy = function ( $count ) { if ( 3 === $count ) $this->binding = str_repeat( 'd', 64 ); };
		$this->assertSame( 'authority_scan_recovery_required', $this->dispatcher->run( $this->successful_callback() )->get_error_code() ); $this->assert_protected( 'recovery_required', 0 );
	}
	public function test_invalidation_after_start_retains_uncertainty_and_no_callback() {
		$this->backend->after_phase = function ( $phase ) { if ( 'started' === $phase ) $this->model->invalidate( $this->binding ); };
		$this->assertInstanceOf( WP_Error::class, $this->dispatcher->run( $this->successful_callback() ) ); $this->assert_protected( 'recovery_required', 0 );
		$this->assertFalse( $this->model->snapshot()['authority']['enabled'] );
	}
	public function test_exact_lease_expiry_between_start_and_callback_never_grants_work() {
		$this->backend->after_phase = function ( $phase ) { if ( 'started' === $phase ) $this->now += 300; };
		$this->assertSame( 'authority_scan_recovery_required', $this->dispatcher->run( $this->successful_callback() )->get_error_code() ); $this->assert_protected( 'recovery_required', 0 );
	}
	public function test_reentrant_wakeup_cannot_run_a_second_callback() {
		$this->backend->after_phase = function ( $phase ) { if ( 'started' === $phase ) $this->assertInstanceOf( WP_Error::class, $this->new_dispatcher()->run( $this->successful_callback() ) ); };
		$this->assertIsArray( $this->dispatcher->run( $this->successful_callback() ) ); $this->assertSame( 1, $this->callback_count );
	}
	/** @dataProvider bad_callback_results */
	public function test_bad_callback_result_never_advances_due_or_replays( $result ) {
		$this->assertSame( 'authority_scan_recovery_required', $this->dispatcher->run( function () use ( $result ) { $this->callback_count++; return $result; } )->get_error_code() );
		$this->assert_protected( 'recovery_required', 1 );
	}
	public function bad_callback_results() {
		return array( array( false ), array( null ), array( new WP_Error( 'private', '/private/fixture/path' ) ), array( array() ),
			array( array( 'errors' => array( '/private/fixture/path' ), 'candidates' => array(), 'queued' => 0 ) ),
			array( array( 'errors' => array(), 'candidates' => array(), 'queued' => '0' ) ), array( array( 'errors' => array(), 'candidates' => array(), 'queued' => 1 ) ),
			array( array( 'errors' => array(), 'candidates' => array(), 'queued' => -1 ) ) );
	}
	public function test_callback_exception_is_redacted_and_never_replayed() {
		$result = $this->dispatcher->run( function () { $this->callback_count++; throw new RuntimeException( '/private/path?secret=fixture' ); } );
		$this->assertSame( 'authority_scan_recovery_required', $result->get_error_code() ); $this->assertStringNotContainsString( 'secret', $result->get_error_message() ); $this->assert_protected( 'recovery_required', 1 );
	}
	/** @dataProvider post_callback_changes */
	public function test_post_callback_permission_invalidation_or_expiry_never_acknowledges_success( $change ) {
		$result = $this->dispatcher->run( function () use ( $change ) {
			$this->callback_count++; if ( 'expiry' === $change ) $this->now += 300; elseif ( 'invalidation' === $change ) $this->model->invalidate( $this->binding ); else $this->scan_enabled = false;
			return array( 'errors' => array(), 'candidates' => array(), 'queued' => 0 );
		} );
		$this->assertInstanceOf( WP_Error::class, $result ); $this->assert_protected( 'recovery_required', 1 );
	}
	public function post_callback_changes() { return array( array( 'expiry' ), array( 'invalidation' ), array( 'permission' ) ); }
	public function test_failed_completion_and_failed_recovery_marker_preserve_started_work() {
		$this->backend->fail_phases = array( 'completed', 'recovery_required' );
		$this->assertSame( 'authority_scan_recovery_unconfirmed', $this->dispatcher->run( $this->successful_callback() )->get_error_code() ); $this->assert_protected( 'started', 1 );
	}
	public function test_failed_completion_marks_recovery_without_advancing_due() {
		$this->backend->fail_phases = array( 'completed' ); $this->assertSame( 'authority_scan_recovery_required', $this->dispatcher->run( $this->successful_callback() )->get_error_code() ); $this->assert_protected( 'recovery_required', 1 );
	}
	public function test_completion_exception_marks_recovery_without_exporting_exception() {
		$this->backend->throw_phases = array( 'completed' ); $this->assertSame( 'authority_scan_recovery_required', $this->dispatcher->run( $this->successful_callback() )->get_error_code() ); $this->assert_protected( 'recovery_required', 1 );
	}
	public function test_invalidation_during_claim_cas_is_rechecked_without_callback() {
		$this->backend->before_swap = function () { $this->model->invalidate( $this->binding ); };
		$this->assertSame( 'authority_scan_claim_blocked', $this->dispatcher->run( $this->successful_callback() )->get_error_code() ); $this->assertSame( 0, $this->callback_count );
	}
	public function test_unknown_model_state_is_not_created_or_repaired() {
		$name = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION; $this->backend->rows[$name]['option_value'] = '{"schema_version":3}'; $before = $this->backend->rows;
		$this->assertSame( 'authority_scan_unavailable', $this->dispatcher->run( $this->successful_callback() )->get_error_code() ); $this->assertSame( $before, $this->backend->rows );
		$this->backend->rows = array(); $this->assertInstanceOf( WP_Error::class, $this->dispatcher->run( $this->successful_callback() ) ); $this->assertSame( array(), $this->backend->rows );
	}
	public function test_malformed_policy_is_never_a_permission_or_state_initializer() {
		foreach ( array( array(), array( 'binding' => $this->binding, 'scan_enabled' => 1 ), array( 'binding' => 'bad', 'scan_enabled' => true ), array( 'binding' => $this->binding, 'scan_enabled' => true, 'execution_supported' => true ), new WP_Error( 'fixture', 'unavailable' ) ) as $policy ) {
			$before = $this->backend->rows;
			$dispatcher = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Scan_Dispatcher( $this->model, function () use ( $policy ) { return $policy; }, true );
			$this->assertSame( 'authority_scan_unavailable', $dispatcher->run( $this->successful_callback() )->get_error_code() ); $this->assertSame( $before, $this->backend->rows );
		}
		$this->assertSame( 0, $this->callback_count );
	}
	public function test_invalid_internal_owner_issuer_does_not_start_work() {
		$before = $this->backend->rows;
		$dispatcher = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Scan_Dispatcher( $this->model, function () { return array( 'binding' => $this->binding, 'scan_enabled' => true ); }, true, function () { return $this->now; }, function () { return 'not-a-uuid'; } );
		$this->assertSame( 'authority_scan_claim_blocked', $dispatcher->run( $this->successful_callback() )->get_error_code() ); $this->assertSame( $before, $this->backend->rows ); $this->assertSame( 0, $this->callback_count );
	}
}
