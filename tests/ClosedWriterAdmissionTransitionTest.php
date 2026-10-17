<?php
use Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Model as AdmissionModel;
require_once __DIR__ . '/Support/ClosedWriterAdmissionFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedWriterAdmissionTransitionTest extends PHPUnit\Framework\TestCase {
	use Alynt_Test_Closed_Writer_Admission_Fixture;
	public function test_begin_then_scoped_simulated_completion_advances_twice_and_uses_reserved_identity(): void {
		$this->upgraded_admission(); $old = $this->memory->row; $begin = $this->issue_form( 'begin_disable' ); $result = $this->admission->consume( 'POST', $begin );
		$this->assertIsArray( $result ); $this->assertSame( 'simulated_committed', $result['secondary_outcome'] ); $this->assertFalse( $result['physical_target_effects_proven'] );
		$s = $this->assert_admission_closed(); $this->assertSame( $old['state']['generation'] + 2, $s['generation'] ); $this->assertNull( $s['writer']['pending'] );
		$this->assertSame( $s['writer']['closure']['completion_id'], $s['writer']['completion']['command']['command_id'] );
		$this->assertFalse( json_decode( $this->memory->row['target'], true )['auto_scan_enabled'] ); $this->assertSame( 0, $this->memory->inside_auth_delta ); $this->assertFalse( $this->memory->reused );
		$this->assertFalse( $this->memory->retained->enter_fixture( $this->memory->identity, $s, $this->memory->row['target'], $this->memory->now ) );
		$this->assertFalse( $this->memory->reconfirmed ); $this->assertFalse( $this->memory->retained->confirm_fixture( $this->memory->identity, $s, $this->memory->row['target'], $this->memory->now ) );
		$this->assertStringNotContainsString( 'DO-NOT-EXPORT', json_encode( $s ) . json_encode( $result ) );
	}
	public function test_duplicate_and_upgrade_replay_observe_before_context_comparison_without_reexecution(): void {
		$upgrade = $this->upgraded_admission(); $begin = $this->issue_form( 'begin_disable' ); $this->admission->consume( 'POST', $begin ); $row = $this->memory->row; $writes = $this->memory->writes;
		$this->assertSame( 'observed_superseded', $this->admission->consume( 'POST', $begin )['outcome'] );
		$this->assertSame( 'observed_superseded', $this->admission->consume( 'POST', $upgrade )['outcome'] );
		$this->assertSame( $row, $this->memory->row ); $this->assertSame( $writes, $this->memory->writes ); $this->assertSame( 1, $this->memory->transactions );
		$changed = $begin; $changed['after_hash'] = str_repeat( 'b', 64 ); $changed = $this->resign( $changed );
		$this->assertSame( 'domain_conflict', $this->admission->consume( 'POST', $changed )['outcome'] );
	}
	public function test_fresh_noop_does_not_renew_epoch_and_stale_context_is_not_noop(): void {
		$this->upgraded_admission(); $stale = $this->issue_form( 'begin_disable' ); $t = json_decode( $this->memory->row['target'], true ); $t['auto_scan_enabled'] = false; $this->memory->row['target'] = json_encode( $t );
		$this->unavailable( $this->admission->consume( 'POST', $stale ) ); $fresh = $this->issue_form( 'begin_disable' ); $row = $this->memory->row; $writes = $this->memory->writes;
		$this->assertSame( 'observed_noop', $this->admission->consume( 'POST', $fresh )['outcome'] ); $this->assertSame( $row, $this->memory->row ); $this->assertSame( $writes, $this->memory->writes );
	}
	public function test_fresh_abandon_closes_original_pending_once_without_target_replay(): void {
		$this->upgraded_admission(); $this->memory->mode = 'abort'; $begin = $this->issue_form( 'begin_disable' ); $this->admission->consume( 'POST', $begin ); $target = $this->memory->row['target'];
		$this->memory->now += 301; $abandon = $this->issue_form( 'abandon_closed', $begin['command_id'] ); $result = $this->admission->consume( 'POST', $abandon );
		$this->assertSame( 'abandoned', $result['secondary_outcome'] ); $this->assertSame( $target, $this->memory->row['target'] ); $s = $this->assert_admission_closed(); $this->assertNull( $s['writer']['pending'] );
		$this->assertFalse( $this->memory->retained->enter_fixture( $this->memory->identity, $s, $target, $this->memory->now ) );
		$this->assertSame( 'observed_current', $this->admission->consume( 'POST', $abandon )['outcome'] );
	}
	public function test_pending_cannot_be_silently_expired_erased_or_replaced_by_noop(): void {
		$this->upgraded_admission(); $this->memory->mode = 'none'; $begin = $this->issue_form( 'begin_disable' ); $this->admission->consume( 'POST', $begin ); $good = $this->memory->row['state'];
		$bad = $good; $bad['writer']['pending'] = null; $this->assertFalse( AdmissionModel::valid( $bad, $this->site ) );
		$bad = $good; $bad['writer']['pending']['completion_id'] = $begin['command_id']; $this->assertFalse( AdmissionModel::valid( $bad, $this->site ) );
		$this->memory->now += 1000; $this->unavailable( $this->admission->consume( 'POST', $begin ) ); $this->assertSame( $good, $this->memory->row['state'] );
		$f = array( 'purpose' => 'begin_disable', 'confirm' => 'disable', 'nonce' => $this->memory->nonce( $this->admission->issue_action( 'begin_disable' ) ) );
		$this->unavailable( $this->admission->issue( 'POST', $f ) );
	}
	public function test_receipt_read_has_own_deadline_and_nonce_and_rechecks_after_read(): void {
		$upgrade = $this->upgraded_admission(); $this->memory->now += 301; $this->unavailable( $this->admission->consume( 'POST', $upgrade ) );
		$read = $this->issue_form( 'receipt_read', $upgrade['command_id'] ); $row = $this->memory->row; $this->assertSame( 'observed_current', $this->admission->consume( 'POST', $read )['outcome'] );
		$changed = $read; $changed['observed_command_id'] = '88888888-8888-4888-8888-888888888888'; $this->unavailable( $this->admission->consume( 'POST', $changed ) );
		$this->memory->on_read = function ( $m ) { $m->capable = false; }; $this->unavailable( $this->admission->consume( 'POST', $read ) ); $this->assertSame( $row, $this->memory->row );
	}
}
