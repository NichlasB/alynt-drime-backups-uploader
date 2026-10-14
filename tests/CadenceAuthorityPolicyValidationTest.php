<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/CadenceAuthorityPolicyFixture.php';
require_once dirname( __DIR__ ) . '/includes/class-cadence-authority-entry-point-policy.php';

/** Negative admission contracts at the pure decision layer, not authenticated runtime. */
class CadenceAuthorityPolicyValidationTest extends TestCase {
	use Alynt_Drime_Backups_Uploader_Test_Cadence_Policy_Fixture;

	/** @dataProvider invalid_projections */
	public function test_malformed_projection_is_rejected_without_repair( $s ) {
		$before = $s;
		$this->assert_disposition( 'blocked', 'policy_invalid', Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( 'scheduled_scan', $s, 1800000000 ) );
		$this->assertSame( $before, $s );
	}

	public function invalid_projections() {
		$rows = array( 'null' => array( null ), 'false' => array( false ), 'scalar' => array( 1 ), 'wire_json' => array( '{}' ), 'object' => array( new stdClass() ), 'empty' => array( array() ) );
		foreach ( array_keys( $this->active_policy() ) as $field ) {
			$s = $this->active_policy(); unset( $s[$field] ); $rows['missing/' . $field] = array( $s );
		}
		$bad = array(
			'policy_version' => '1', 'mode' => array(), 'lifecycle' => array(), 'ever_activated' => 1,
			'handoff_verified' => 0, 'consent' => 'true', 'auto_scan_enabled' => '1',
			'permission_generation' => '2', 'current_permission_generation' => 2.0,
			'binding' => array(), 'current_binding' => str_repeat( 'A', 64 ), 'model_enabled' => 1,
			'pending' => array(), 'next_due_at' => -1, 'observed_at' => 1800000000.0, 'expires_at' => null,
		);
		foreach ( $bad as $field => $value ) { $s = $this->active_policy(); $s[$field] = $value; $rows['type/' . $field] = array( $s ); }
		foreach ( array( 'policy_version' => 2, 'mode' => 'other', 'lifecycle' => 'recovered', 'pending' => 'expired', 'observed_at' => 0, 'expires_at' => 1800000301, 'binding' => str_repeat( 'a', 65 ) ) as $field => $value ) {
			$s = $this->active_policy(); $s[$field] = $value; $rows['invalid/' . $field] = array( $s );
		}
		$s = $this->active_policy(); $s['mode'] = 'legacy'; $rows['contradictory_mode'] = array( $s );
		$s = $this->active_policy(); $s['lifecycle'] = 'legacy'; $rows['contradictory_lifecycle'] = array( $s );
		$s = $this->active_policy(); $s['expires_at'] = $s['observed_at']; $rows['zero_window'] = array( $s );
		$s = $this->active_policy(); $s['dispatch_supported'] = true; $rows['self_reported_permission'] = array( $s );
		$s = $this->active_policy(); unset( $s['consent'] ); $s['allowed'] = true; $rows['same_size_wrong_keys'] = array( $s );
		return $rows;
	}

	/** @dataProvider active_context_failures */
	public function test_active_context_change_never_grants_scan_or_requeue( $field, $value ) {
		$s = $this->active_policy(); $s[$field] = $value;
		foreach ( array( 'scheduled_scan', 'failed_requeue' ) as $entry ) {
			$this->assert_disposition( 'blocked', 'local_context_invalid', Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( $entry, $s, 1800000000 ) );
		}
	}
	public function active_context_failures() {
		return array( array( 'consent', false ), array( 'model_enabled', false ), array( 'auto_scan_enabled', false ), array( 'ever_activated', false ), array( 'handoff_verified', true ),
			array( 'permission_generation', 0 ), array( 'current_permission_generation', 3 ), array( 'binding', '' ), array( 'current_binding', str_repeat( 'b', 64 ) ), array( 'next_due_at', 0 ) );
	}

	/** @dataProvider unfinished_work */
	public function test_uncertainty_blocks_scan_requeue_and_legacy_even_when_observation_is_fresh( $phase ) {
		foreach ( array( 'active', 'legacy', 'handoff' ) as $lifecycle ) {
			$s = $this->policy_for( $lifecycle ); $s['pending'] = $phase;
			foreach ( array( 'scheduled_scan', 'failed_requeue' ) as $entry ) {
				$this->assert_disposition( 'blocked', 'inspection_required', Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( $entry, $s, 1800000000 ) );
			}
		}
	}
	public function unfinished_work() { return array( array( 'claimed' ), array( 'started' ), array( 'recovery_required' ) ); }

	/** @dataProvider bad_handoffs */
	public function test_missing_or_contradictory_handoff_is_not_legacy( $lifecycle, $field, $value ) {
		$s = $this->policy_for( $lifecycle ); $s[$field] = $value;
		$this->assert_disposition( 'blocked', 'legacy_handoff_required', Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( 'admin_scan', $s, 1800000000 ) );
	}
	public function bad_handoffs() {
		return array( array( 'handoff', 'handoff_verified', false ), array( 'handoff', 'permission_generation', 0 ), array( 'handoff', 'current_permission_generation', 3 ),
			array( 'legacy', 'ever_activated', true ), array( 'legacy', 'handoff_verified', true ), array( 'legacy', 'consent', true ), array( 'legacy', 'model_enabled', true ), array( 'legacy', 'next_due_at', 1 ), array( 'legacy', 'permission_generation', 1 ) );
	}

	/** @dataProvider bad_clocks */
	public function test_invalid_clock_fails_closed( $now ) {
		$this->assert_disposition( 'blocked', 'policy_invalid', Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( 'scheduled_scan', $this->active_policy(), $now ) );
	}
	public function bad_clocks() { return array( array( null ), array( true ), array( '1800000000' ), array( 1800000000.0 ), array( 0 ), array( -1 ), array( PHP_INT_MAX ), array( array() ) ); }

	public function test_freshness_boundaries_and_no_expired_legacy_fallback() {
		foreach ( array( 'legacy', 'active', 'handoff' ) as $lifecycle ) {
			$s = $this->policy_for( $lifecycle );
			foreach ( array( 1799999999, 1800000300, 1800000301 ) as $now ) {
				$this->assert_disposition( 'blocked', 'policy_stale', Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( 'scheduled_scan', $s, $now ) );
			}
		}
		$this->assert_disposition( 'guarded_dispatch_required', 'guarded_dispatch_required', Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( 'scheduled_scan', $this->active_policy(), 1800000299 ) );
	}

	public function test_unknown_routes_and_secret_bearing_inputs_are_not_echoed() {
		foreach ( array( null, true, 1, array(), new stdClass(), 'scan_upload_now', 'scheduled_scan/../private', "scheduled_scan\0", str_repeat( 'x', 10000 ) ) as $entry ) {
			$this->assert_disposition( 'blocked', 'entry_point_unknown', Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( $entry, $this->active_policy(), 1800000000 ) );
		}
		$s = $this->active_policy(); $s['secret'] = '/private/path?token=fixture';
		$this->assert_disposition( 'blocked', 'policy_invalid', Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( 'scheduled_scan', $s, 1800000000 ) );
		foreach ( array( 'upload_next', 'upload_abort' ) as $entry ) {
			$this->assert_disposition( 'outside_scope', 'existing_upload_policy_required', Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( $entry, $s ) );
		}
	}
}
