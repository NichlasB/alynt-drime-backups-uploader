<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/CadenceContextObserverFixture.php';
/** @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CadenceContextValidationTest extends TestCase {
	use Alynt_Test_Context_Observer_Fixture;
	protected function setUp(): void { parent::setUp(); Monkey\setUp(); Functions\when( 'wp_json_encode' )->alias( 'json_encode' ); $this->prepare_observer(); }
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }
	/** @dataProvider bad_facts */
	public function test_missing_wrong_extra_and_oversize_facts_never_coerce_or_infer_trust( $target, $key, $value ) {
		if ( 'settings' === $target ) { if ( null === $value ) unset( $this->settings[$key] ); else $this->settings[$key] = $value; }
		else { if ( null === $value ) unset( $this->connection[$key] ); else $this->connection[$key] = $value; }
		$this->blocked( 'observation_unavailable' );
	}
	public function bad_facts() {
		return array( array( 'settings', 'auto_scan_enabled', null ), array( 'settings', 'auto_scan_enabled', 1 ), array( 'settings', 'auto_scan_enabled', 'false' ), array( 'settings', 'site_uuid', '' ), array( 'settings', 'site_uuid', array() ), array( 'settings', 'trusted', true ),
			array( 'connection', 'connection_status', 'revoked' ), array( 'connection', 'status_endpoint_enabled', 'true' ), array( 'connection', 'remote_actions_enabled', 1 ), array( 'connection', 'remote_actions_enabled', false ),
			array( 'connection', 'paired_at', '1799999880' ), array( 'connection', 'paired_at', 0 ), array( 'connection', 'paired_at', 1800000001 ), array( 'connection', 'remote_actions_opted_in_at', null ),
			array( 'connection', 'dashboard_origin', str_repeat( 'x', 2049 ) ), array( 'connection', 'dashboard_origin', 'http://dashboard.example.com' ), array( 'connection', 'dashboard_origin', 'https://user:password@dashboard.example.com' ), array( 'connection', 'dashboard_origin', 'https://dashboard.example.com/path' ), array( 'connection', 'dashboard_origin', 'https://dashboard.example.com:65536' ),
			array( 'connection', 'polling_key_id', array() ), array( 'connection', 'polling_credential_verifier', 'secret' ), array( 'connection', 'action_public_key', new stdClass() ), array( 'connection', 'action_key_id', str_repeat( 'a', 129 ) ), array( 'connection', 'dashboard_site_public_id', 'bad' ), array( 'connection', 'consent_verified', true ) );
	}
	/** @dataProvider identity_fields */
	public function test_semantic_identity_changes_are_not_old_context_even_when_well_shaped( $target, $key, $value ) {
		if ( 'settings' === $target ) $this->settings[$key] = $value; else $this->connection[$key] = $value; $this->blocked( 'binding_changed' );
	}
	public function identity_fields() { return array( array( 'settings', 'site_uuid', '22222222-2222-4222-8222-222222222222' ), array( 'connection', 'dashboard_origin', 'https://other.example.com' ), array( 'connection', 'expected_client_origin', 'https://other-client.example.com' ), array( 'connection', 'dashboard_site_public_id', '22222222-2222-4222-8222-222222222222' ), array( 'connection', 'polling_key_id', 'new_key' ), array( 'connection', 'polling_credential_verifier', str_repeat( 'a', 64 ) ), array( 'connection', 'action_key_id', 'new_action' ), array( 'connection', 'action_public_key', str_repeat( 'B', 43 ) ), array( 'connection', 'paired_at', 1799999800 ), array( 'connection', 'remote_actions_opted_in_at', 1799999900 ) ); }
	/** @dataProvider invalid_clocks */
	public function test_invalid_initial_clock_performs_no_model_or_fact_reads( $now ) { $this->clock_hook = function () use ( $now ) { return $now; }; $this->blocked( 'clock_invalid' ); $this->assertSame( 0, $this->backend->reads + $this->calls['settings'] + $this->calls['connection'] ); }
	public function invalid_clocks() { return array( array( null ), array( '1800000000' ), array( 0 ), array( -1 ), array( PHP_INT_MAX ) ); }
	/** @dataProvider invalid_provider_roots */
	public function test_non_array_provider_roots_never_become_defaults( $provider, $value ) {
		if ( 'settings' === $provider ) $this->settings_hook = function () use ( $value ) { return $value; }; else $this->connection_hook = function () use ( $value ) { return $value; }; $this->blocked( 'observation_unavailable' );
	}
	public function invalid_provider_roots() { $cases = array(); foreach ( array( 'settings', 'connection' ) as $p ) foreach ( array( null, false, '{"trusted":true}', new stdClass(), array() ) as $value ) $cases[] = array( $p, $value ); return $cases; }
	public function test_just_before_observation_expiry_is_bounded_but_still_non_admitting() { $this->clock_hook = function ( $n ) { return 1 === $n ? $this->now : $this->now + 299; }; $this->blocked( 'provenance_unavailable' ); }
	public function test_exact_expiry_regression_and_bad_final_clock_are_not_live_observations() {
		foreach ( array( $this->now + 300, $this->now - 1, null, PHP_INT_MAX ) as $end ) { $this->calls['clock'] = 0; $this->clock_hook = function ( $n ) use ( $end ) { return 1 === $n ? $this->now : $end; }; $this->blocked( 'observation_stale' ); }
	}
	public function test_future_lifecycle_timestamp_and_active_unknown_corrupt_schemas_are_not_repaired() {
		$baseline = $this->store->snapshot();
		foreach ( array( 'future', 'active', 'version', 'history', 'json' ) as $case ) {
			$s = $baseline; if ( 'future' === $case ) $s['lifecycle']['changed_at'] = $this->now + 1; if ( 'active' === $case ) $s['authority']['enabled'] = true; if ( 'version' === $case ) $s['schema_version'] = 4; if ( 'history' === $case ) $s['lifecycle']['history'] = 'never';
			$this->replace_observer_state( $s ); if ( 'json' === $case ) $this->backend->rows[Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION]['option_value'] = '{'; $this->blocked( 'future' === $case ? 'observation_stale' : 'observation_unavailable' );
		}
	}
}
