<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/CadenceAuthorityPolicyFixture.php';
require_once dirname( __DIR__ ) . '/includes/class-cadence-authority-entry-point-policy.php';

/** Classifies proposed entry-point policy; never executes a scanner or authenticates consent. */
class CadenceAuthorityPolicyMatrixTest extends TestCase {
	use Alynt_Drime_Backups_Uploader_Test_Cadence_Policy_Fixture;

	public function test_absent_policy_never_infers_legacy_or_execution() {
		$this->assert_disposition( 'blocked', 'policy_invalid', Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( 'scheduled_scan' ) );
	}

	/** @dataProvider matrix */
	public function test_entry_point_lifecycle_matrix( $lifecycle, $entry_point, $disposition, $code ) {
		$snapshot = $this->policy_for( $lifecycle ); $before = $snapshot;
		$result = Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( $entry_point, $snapshot, 1800000000 );
		$this->assert_disposition( $disposition, $code, $result ); $this->assertSame( $before, $snapshot );
	}

	public function matrix() {
		$rows = array();
		$scans = array( 'scheduled_scan', 'admin_scan', 'cli_scan', 'cli_run', 'remote_scan', 'direct_scan', 'queue_write', 'failed_requeue', 'schedule_apply' );
		foreach ( array( 'legacy', 'handoff', 'preparing', 'active', 'draining', 'disabled' ) as $lifecycle ) {
			foreach ( $scans as $entry ) {
				if ( 'legacy' === $lifecycle || 'handoff' === $lifecycle ) {
					$expected = array( 'legacy_policy_required', 'legacy_policy_required' );
				} elseif ( 'active' === $lifecycle ) {
					$active = array(
						'scheduled_scan' => array( 'guarded_dispatch_required', 'guarded_dispatch_required' ),
						'failed_requeue' => array( 'queue_policy_required', 'existing_queue_policy_required' ),
					);
					$expected = $active[$entry] ?? array( 'blocked', 'entry_point_unsupported' );
				} else {
					$expected = array( 'blocked', 'lifecycle_blocked' );
				}
				$rows[$lifecycle . '/' . $entry] = array_merge( array( $lifecycle, $entry ), $expected );
			}
			foreach ( array( 'upload_next', 'upload_abort' ) as $entry ) {
				$rows[$lifecycle . '/' . $entry] = array( $lifecycle, $entry, 'outside_scope', 'existing_upload_policy_required' );
			}
			$rows[$lifecycle . '/rollback'] = array( $lifecycle, 'schedule_rollback', 'blocked', 'rollback_disabled' );
		}
		return $rows;
	}

	public function test_only_scheduled_legacy_scan_depends_on_automatic_scan_setting() {
		$s = $this->policy_for( 'legacy' ); $s['auto_scan_enabled'] = false;
		$this->assert_disposition( 'blocked', 'automatic_scan_disabled', Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( 'scheduled_scan', $s, 1800000000 ) );
		foreach ( array( 'admin_scan', 'cli_scan', 'cli_run', 'remote_scan' ) as $entry ) {
			$this->assert_disposition( 'legacy_policy_required', 'legacy_policy_required', Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( $entry, $s, 1800000000 ) );
		}
	}

	public function test_due_boundary_and_queue_independence() {
		$s = $this->active_policy(); $s['next_due_at']++;
		$this->assert_disposition( 'blocked', 'not_due', Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( 'scheduled_scan', $s, 1800000000 ) );
		$this->assert_disposition( 'queue_policy_required', 'existing_queue_policy_required', Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( 'failed_requeue', $s, 1800000000 ) );
		$s['next_due_at']--;
		$this->assert_disposition( 'guarded_dispatch_required', 'guarded_dispatch_required', Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( 'scheduled_scan', $s, 1800000001 ) );
	}
}
