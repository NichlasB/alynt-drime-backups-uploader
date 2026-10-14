<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/CadenceContextObserverFixture.php';

/** @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CadenceContextObserverTest extends TestCase {
	use Alynt_Test_Context_Observer_Fixture;
	protected function setUp(): void { parent::setUp(); Monkey\setUp(); Functions\when( 'wp_json_encode' )->alias( 'json_encode' ); $this->prepare_observer(); }
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }
	public function test_default_dormancy_and_construction_have_zero_reads_or_writes() {
		$r = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Context_Observer( $this->store, function () { throw new RuntimeException(); }, function () { throw new RuntimeException(); }, function () { throw new RuntimeException(); } );
		$this->blocked( 'observer_disabled', 'scheduled_scan', $r ); $this->assertSame( 0, $this->backend->reads ); $this->assertSame( array( 'settings' => 0, 'connection' => 0, 'clock' => 0 ), $this->calls );
		$root = file_get_contents( dirname( __DIR__ ) . '/alynt-drime-backups-uploader.php' ); $this->assertStringNotContainsString( 'class-cadence-authority-context-observer.php', $root ); $this->assertStringNotContainsString( 'trait-cadence-authority-context-facts.php', $root );
	}
	/** @dataProvider non_boolean_switches */
	public function test_truthy_enable_switches_never_activate_readers( $value ) { $this->blocked( 'observer_disabled', 'scheduled_scan', $this->observer( $value ) ); $this->assertSame( 0, array_sum( $this->calls ) + $this->backend->reads ); }
	public function non_boolean_switches() { return array( array( false ), array( 1 ), array( 'true' ), array( array() ), array( null ) ); }
	public function test_valid_facts_match_actual_version_one_binding_and_do_not_admit_schema_three() {
		Functions\expect( 'get_option' )->never(); Functions\expect( 'update_option' )->never(); Functions\expect( 'add_option' )->never();
		$r = $this->observer(); $this->blocked( 'provenance_unavailable', 'scheduled_scan', $r ); $this->blocked( 'provenance_unavailable', 'scheduled_scan', $r );
		$this->assertSame( 4, $this->backend->reads ); $this->assertSame( array( 'settings' => 4, 'connection' => 4, 'clock' => 4 ), $this->calls );
	}
	/** @dataProvider scan_entries */
	public function test_synthetic_consent_is_never_active_or_verified_legacy_permission( $entry ) {
		$this->store->record_consent( $this->binding, 0, true ); $s = $this->store->snapshot(); $this->assertFalse( $s['authority']['enabled'] );
		$this->blocked( 'provenance_unavailable', $entry ); $s['lifecycle']['history'] = 'unknown'; $this->replace_observer_state( $s ); $this->blocked( 'provenance_unavailable', $entry );
		$this->store->record_consent( $this->binding, 1, false ); $this->blocked( 'provenance_unavailable', $entry );
	}
	public function scan_entries() { return array_map( function ( $e ) { return array( $e ); }, array( 'scheduled_scan', 'admin_scan', 'cli_scan', 'cli_run', 'remote_scan', 'direct_scan', 'queue_write', 'failed_requeue', 'schedule_apply' ) ); }
	public function test_upload_abort_and_reserved_rollback_do_not_read_context_or_depend_on_schema() {
		$r = $this->observer(); $this->backend->throw_read = true;
		foreach ( array( 'upload_next', 'upload_abort', 'schedule_rollback', 'unknown', array() ) as $e ) $this->assertSame( Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( $e ), $r->observe( $e ) );
		$this->assertSame( 0, array_sum( $this->calls ) + $this->backend->reads );
	}
	public function test_disabled_automatic_scan_is_explicit_without_enabling_legacy() { $this->settings['auto_scan_enabled'] = false; $this->blocked( 'automatic_scan_disabled' ); }
	public function test_pending_uncertainty_survives_time_elapsed_and_observer_reads() {
		$prepared = $this->store->snapshot(); $this->backend->rows = array(); $old = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Store( $this->backend, function () { return $this->now; } );
		$old->initialize( $this->binding ); $e = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $this->now ); $source = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE; $old->claim( $this->binding, $source, $e );
		$prepared['claims'] = $old->snapshot()['claims']; $prepared['lifecycle']['phase'] = 'draining'; $this->replace_observer_state( $prepared ); $this->now += 90000;
		$this->blocked( 'inspection_required' ); $this->assertSame( 'claimed', $this->store->snapshot()['claims'][$source]['phase'] );
	}
	public function test_expired_dispatch_recovery_remains_pending_without_cancellation() {
		$s = $this->store->snapshot(); $s['lifecycle']['phase'] = 'draining';
		$s['dispatch'] = array( 'owner' => '77777777-7777-4777-8777-777777777777', 'request_hash' => str_repeat( 'f', 64 ), 'enrollment_binding' => $this->binding, 'schedule_revision' => 0, 'due_at' => $this->now - 600, 'claimed_at' => $this->now - 300, 'lease_deadline' => $this->now, 'phase' => 'recovery_required', 'completed_at' => 0 );
		$this->replace_observer_state( $s ); $this->blocked( 'inspection_required' );
		$this->assertSame( $s, $this->store->snapshot() );
	}
}
