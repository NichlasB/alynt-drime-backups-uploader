<?php
use Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Model as AdmissionModel;
require_once __DIR__ . '/Support/ClosedWriterAdmissionFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedWriterAdmissionBoundaryTest extends PHPUnit\Framework\TestCase {
	use Alynt_Test_Closed_Writer_Admission_Fixture;
	public function test_literal_enablement_and_literal_authorization_and_nonce_results_are_required(): void {
		$form = $this->issue_form(); $reads = $this->memory->reads;
		foreach ( array( 1, 'true', array() ) as $enabled ) $this->unavailable( $this->new_admission( $enabled )->consume( 'POST', $form ) );
		$ports = $this->memory->ports(); $ports['authorize'] = function () { return 'true'; }; $this->unavailable( $this->new_admission( true, null, $ports )->consume( 'POST', $form ) );
		$ports = $this->memory->ports(); $ports['verify'] = function () { return true; }; $this->unavailable( $this->new_admission( true, null, $ports )->consume( 'POST', $form ) );
		$this->assertSame( $reads, $this->memory->reads ); $this->assertSame( 0, $this->memory->writes );
	}
	public function test_issuance_rechecks_session_after_private_read_and_denies_echoed_context(): void {
		$nonce = $this->memory->nonce( $this->admission->issue_action( 'upgrade_closed' ) ); $form = array( 'purpose' => 'upgrade_closed', 'confirm' => 'close', 'nonce' => $nonce );
		$this->memory->on_read = function ( $m ) { $m->session = 'changed'; }; $this->unavailable( $this->admission->issue( 'POST', $form ) ); $this->assertSame( 0, $this->memory->writes );
		$this->memory->on_read = null; $this->memory->session = 'fixture-session'; $reads = $this->memory->reads; $form['expected_generation'] = '1';
		$this->unavailable( $this->admission->issue( 'POST', $form ) ); $this->assertSame( $reads, $this->memory->reads );
	}
	public function test_unsupported_scope_target_and_finish_fields_never_reach_a_private_sink(): void {
		$this->upgraded_admission(); $form = $this->issue_form( 'begin_disable' ); $reads = $this->memory->reads; $writes = $this->memory->writes;
		foreach ( array( 'target' => 'connection', 'desired' => 'true', 'fields' => 'credentials', 'callback' => 'anything', 'observed_hash' => str_repeat( 'c', 64 ), 'permission' => 'true' ) as $key => $value ) {
			$f = $form; $f[$key] = $value; $this->unavailable( $this->admission->consume( 'POST', $f ) );
		}
		$this->assertSame( $reads, $this->memory->reads ); $this->assertSame( $writes, $this->memory->writes );
	}
	public function test_competing_begin_winner_fences_stale_command_and_cannot_be_reset(): void {
		$this->upgraded_admission(); $loser = $this->issue_form( 'begin_disable' ); $winner = $this->issue_form( 'begin_disable' ); $other = $this->new_admission(); $this->memory->mode = 'none';
		$this->memory->on_compare = function ( $m ) use ( $other, $winner ) { $m->on_compare = null; $this->assertIsArray( $other->consume( 'POST', $winner ) ); };
		$this->unavailable( $this->admission->consume( 'POST', $loser ) ); $s = $this->assert_admission_closed(); $this->assertSame( $winner['command_id'], $s['writer']['pending']['operation_id'] );
		$this->assertSame( 1, $this->memory->transactions );
	}
	public function test_another_fresh_administrator_can_abandon_but_unexpected_target_stays_unresolved(): void {
		$this->upgraded_admission(); $this->memory->mode = 'none'; $begin = $this->issue_form( 'begin_disable' ); $this->admission->consume( 'POST', $begin );
		$this->memory->session = 'fresh-second-admin'; $this->memory->row['target'] .= ' '; $abandon = $this->issue_form( 'abandon_closed', $begin['command_id'] ); $row = $this->memory->row;
		$this->unavailable( $this->admission->consume( 'POST', $abandon ) ); $this->assertSame( $row, $this->memory->row );
		$this->memory->row['target'] = rtrim( $this->memory->row['target'] ); $abandon = $this->issue_form( 'abandon_closed', $begin['command_id'] ); $this->assertSame( 'abandoned', $this->admission->consume( 'POST', $abandon )['secondary_outcome'] );
	}
	public function test_superseded_state_at_transaction_entry_cannot_use_a_retained_original_owner(): void {
		$this->upgraded_admission(); $begin = $this->issue_form( 'begin_disable' ); $other = $this->new_admission();
		$this->memory->on_transaction = function ( $m ) use ( $other, $begin ) {
			$m->on_transaction = null; $form = $this->issue_form( 'abandon_closed', $begin['command_id'] ); $this->assertSame( 'abandoned', $other->consume( 'POST', $form )['secondary_outcome'] );
		};
		$result = $this->admission->consume( 'POST', $begin ); $this->assertSame( 'unresolved', $result['secondary_outcome'] ); $s = $this->assert_admission_closed(); $this->assertNull( $s['writer']['pending'] );
		$this->assertSame( 'abandon_closed', $s['writer']['completion']['purpose'] ); $this->assertTrue( json_decode( $this->memory->row['target'], true )['auto_scan_enabled'] );
	}
	public function test_real_source_and_legacy_ids_never_become_new_fixture_or_real_receipts(): void {
		$a = $this->begin_command(); $this->barrier->begin( $a, $this->permitted() ); $this->barrier->finish( $this->terminal_command( $a ), $this->permitted() ); $this->six = $this->origin(); $this->memory->row['state'] = $this->six;
		$this->memory->next_uuid = $a['command_id']; $form = $this->issue_form(); $row = $this->memory->row; $this->assertSame( 'domain_conflict', $this->admission->consume( 'POST', $form )['outcome'] ); $this->assertSame( $row, $this->memory->row );
		$this->memory->next_uuid = null; $this->upgraded_admission(); $bad = $this->memory->row['state']; $bad['writer']['source'] = 'closed_writer_admission_v1'; $this->assertFalse( AdmissionModel::valid( $bad, $this->site ) );
	}
	public function test_new_definitions_remain_unwired_and_no_authentication_material_is_persisted(): void {
		$this->upgraded_admission(); $this->abandon_for_privacy(); $json = json_encode( $this->memory->row['state'] );
		foreach ( array( 'DO-NOT-EXPORT', 'fixture-session', 'nonce', 'password', 'cookie' ) as $secret ) $this->assertStringNotContainsString( $secret, $json );
		$bootstrap = file_get_contents( dirname( __DIR__ ) . '/alynt-drime-backups-uploader.php' ); $this->assertStringNotContainsString( 'closed-writer-admission', $bootstrap );
		$this->assertStringNotContainsString( 'Closed_Writer_Admission', $bootstrap );
	}
	private function abandon_for_privacy(): void { $this->memory->mode = 'none'; $begin = $this->issue_form( 'begin_disable' ); $this->admission->consume( 'POST', $begin ); $this->admission->consume( 'POST', $this->issue_form( 'abandon_closed', $begin['command_id'] ) ); }
	public function test_locked_target_change_before_commit_is_not_overwritten_by_prepared_pair(): void {
		$this->upgraded_admission(); $begin = $this->issue_form( 'begin_disable' ); $this->memory->before_commit = function ( $m ) { $m->row['target'] .= ' '; };
		$this->assertSame( 'unresolved', $this->admission->consume( 'POST', $begin )['secondary_outcome'] ); $this->assertStringEndsWith( ' ', $this->memory->row['target'] );
		$s = $this->assert_admission_closed(); $this->assertNotNull( $s['writer']['pending'] ); $this->assertNull( $s['writer']['completion'] );
	}
}
