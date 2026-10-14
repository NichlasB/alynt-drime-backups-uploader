<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/CadenceContextObserverFixture.php';
/** @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CadenceContextRaceTest extends TestCase {
	use Alynt_Test_Context_Observer_Fixture;
	protected function setUp(): void { parent::setUp(); Monkey\setUp(); Functions\when( 'wp_json_encode' )->alias( 'json_encode' ); $this->prepare_observer(); }
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }
	public function test_model_epoch_change_away_and_back_is_detected_by_a_fresh_second_read() {
		$this->store->record_consent( $this->binding, 0, false ); $before = $this->store->snapshot(); $swaps = $this->backend->swaps;
		$this->settings_hook = function ( $n ) { if ( 1 === $n ) { $this->store->record_consent( $this->binding, 1, true ); $this->store->record_consent( $this->binding, 2, false ); } return $this->settings; };
		$this->assertSame( array( 'disposition' => 'blocked', 'code' => 'observation_changed', 'dispatch_supported' => false, 'execution_supported' => false ), $this->observer()->observe( 'scheduled_scan' ) );
		$end = $this->store->snapshot(); $this->assertSame( $before['lifecycle']['consent'], $end['lifecycle']['consent'] ); $this->assertSame( $before['lifecycle']['phase'], $end['lifecycle']['phase'] ); $this->assertSame( 3, $end['lifecycle']['permission_generation'] ); $this->assertSame( $swaps + 2, $this->backend->swaps );
	}
	public function test_settings_or_semantic_context_changes_during_collection_are_not_cached() {
		$this->settings_hook = function ( $n ) { if ( 2 === $n ) $this->settings['auto_scan_enabled'] = false; return $this->settings; }; $this->blocked( 'observation_changed' );
		$this->settings_hook = null; $this->settings['auto_scan_enabled'] = true; $this->calls['connection'] = 0;
		$this->connection_hook = function ( $n ) { if ( 2 === $n ) $this->connection['action_key_id'] = 'rotated'; return $this->connection; }; $this->blocked( 'observation_changed' );
	}
	public function test_ordinary_poll_timestamp_changes_do_not_change_version_one_identity() {
		$keys = array_fill_keys( array_keys( $this->connection ), true );
		$this->connection_hook = function ( $n ) use ( $keys ) { $this->connection['last_authenticated_read_at'] = $this->now + $n; $this->connection['last_error_code'] = 'changed'; $this->assertSame( $this->binding, $this->evidence->enrollment_binding() ); return array_intersect_key( $this->connection, $keys ); };
		$this->blocked( 'provenance_unavailable' );
	}
	/** @dataProvider failing_providers */
	public function test_provider_failures_are_redacted_and_never_retried_or_repaired( $provider ) {
		if ( 'settings' === $provider ) $this->settings_hook = function () { throw new RuntimeException( 'credential-cookie-private-path' ); };
		if ( 'connection' === $provider ) $this->connection_hook = function () { return new WP_Error( 'private', 'credential-cookie-private-path' ); };
		if ( 'clock' === $provider ) $this->clock_hook = function () { throw new Error( 'credential-cookie-private-path' ); };
		if ( 'backend' === $provider ) $this->backend->throw_read = true;
		if ( 'read_error' === $provider ) $this->backend->fail_read = true;
		$r = $this->blocked( 'observation_unavailable' ); $this->assertStringNotContainsString( 'credential', json_encode( $r ) );
		$this->assertLessThanOrEqual( 1, $this->backend->reads ); $this->assertLessThanOrEqual( 1, $this->calls['settings'] ); $this->assertLessThanOrEqual( 1, $this->calls['connection'] );
	}
	public function failing_providers() { return array( array( 'settings' ), array( 'connection' ), array( 'clock' ), array( 'backend' ), array( 'read_error' ) ); }
	public function test_absent_state_never_reads_connection_creates_identity_or_initializes_model() {
		$this->backend->rows = array(); $this->blocked( 'observation_unavailable' ); $this->assertSame( 1, $this->backend->reads ); $this->assertSame( 0, $this->calls['settings'] + $this->calls['connection'] );
	}
	public function test_separate_calls_do_not_reuse_old_context() {
		$r = $this->observer(); $this->blocked( 'provenance_unavailable', 'scheduled_scan', $r ); $this->settings['auto_scan_enabled'] = false; $this->blocked( 'automatic_scan_disabled', 'scheduled_scan', $r ); $this->connection['remote_actions_enabled'] = false; $this->blocked( 'observation_unavailable', 'scheduled_scan', $r );
	}
	public function test_partial_final_read_and_context_loss_do_not_return_initial_snapshot() {
		$this->settings_hook = function ( $n ) { if ( 1 === $n ) $this->backend->fail_read = true; return $this->settings; }; $this->blocked( 'observation_unavailable' );
		$this->backend->fail_read = false; $this->settings_hook = null; $this->calls['connection'] = 0; $this->connection_hook = function ( $n ) { return 2 === $n ? null : $this->connection; }; $this->blocked( 'observation_unavailable' );
	}
	public function test_final_provider_exception_is_redacted_without_retry() {
		$this->connection_hook = function ( $n ) { if ( 2 === $n ) throw new RuntimeException( 'secret-provider-stack' ); return $this->connection; }; $this->blocked( 'observation_unavailable' ); $this->assertSame( 2, $this->backend->reads ); $this->assertSame( 2, $this->calls['connection'] );
	}
}
