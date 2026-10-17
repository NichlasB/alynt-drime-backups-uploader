<?php
use Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Model as AdmissionModel;
require_once __DIR__ . '/Support/ClosedWriterAdmissionFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedWriterAdmissionCapacityTest extends PHPUnit\Framework\TestCase {
	use Alynt_Test_Closed_Writer_Admission_Fixture;
	private function abandon_cycle(): void {
		$this->memory->mode = 'abort'; $begin = $this->issue_form( 'begin_disable' ); $this->assertIsArray( $this->admission->consume( 'POST', $begin ) );
		$end = $this->issue_form( 'abandon_closed', $begin['command_id'] ); $this->assertSame( 'abandoned', $this->admission->consume( 'POST', $end )['secondary_outcome'] );
	}
	public function test_fifty_optional_receipts_allow_terminal_but_never_prune_for_next_begin(): void {
		$this->upgraded_admission(); for ( $i = 0; $i < 26; ++$i ) $this->abandon_cycle(); $s = $this->assert_admission_closed();
		$this->assertCount( 50, $s['writer']['fixture_receipts'] ); $this->assertLessThanOrEqual( 131072, strlen( json_encode( $s ) ) );
		foreach ( array_merge( array_values( $s['writer']['fixture_receipts'] ), array( $s['writer']['upgrade'], $s['writer']['closure'], $s['writer']['completion'] ) ) as $r ) $this->assertLessThanOrEqual( 1024, strlen( json_encode( $r ) ) );
		$begin = $this->issue_form( 'begin_disable' ); $row = $this->memory->row; $writes = $this->memory->writes; $this->unavailable( $this->admission->consume( 'POST', $begin ) );
		$this->assertSame( $row, $this->memory->row ); $this->assertSame( $writes, $this->memory->writes );
	}
	public function test_legacy_history_and_new_history_share_one_budget(): void {
		$this->replace( $this->schema_three() ); $this->migrate(); for ( $i = 1; $i <= 51; ++$i ) $this->ledger->command( $this->intent( 'revoke', $i ) );
		$this->checkpoint = $this->close_origin(); $this->assertIsArray( $this->upgrade() ); $this->six = $this->origin(); $this->memory->row['state'] = $this->six;
		$this->upgraded_admission(); $this->abandon_cycle(); $s = $this->assert_admission_closed(); $this->assertCount( 50, $s['legacy_writer']['checkpoint']['provenance']['legacy']['ledger']['receipts'] );
		$begin = $this->issue_form( 'begin_disable' ); $row = $this->memory->row; $this->unavailable( $this->admission->consume( 'POST', $begin ) ); $this->assertSame( $row, $this->memory->row );
	}
	public function test_reserved_completion_collision_cannot_be_reallocated_or_posted(): void {
		$up = $this->upgraded_admission(); $begin = $this->issue_form( 'begin_disable' ); $row = $this->memory->row; $uuids = $this->memory->uuid_calls; $this->memory->next_uuid = $up['command_id'];
		$this->unavailable( $this->admission->consume( 'POST', $begin ) ); $this->assertSame( $uuids + 1, $this->memory->uuid_calls ); $this->assertSame( $row, $this->memory->row );
		$this->memory->next_uuid = null; $begin['completion_id'] = '88888888-8888-4888-8888-888888888888'; $reads = $this->memory->reads; $this->unavailable( $this->admission->consume( 'POST', $begin ) ); $this->assertSame( $reads, $this->memory->reads );
	}
	public function test_last_usable_counter_reserves_terminal_and_one_closed_increment(): void {
		$old = $this->schema_three(); foreach ( array( 'generation', 'schedule_revision' ) as $key ) $old[$key] = PHP_INT_MAX - 7; $old['lifecycle']['permission_generation'] = PHP_INT_MAX - 7;
		$this->replace( $old ); $this->checkpoint = $this->close_origin(); $this->assertIsArray( $this->upgrade() ); $this->six = $this->origin(); $this->memory->row['state'] = $this->six;
		$this->upgraded_admission(); $this->abandon_cycle(); $s = $this->assert_admission_closed(); $this->assertSame( PHP_INT_MAX - 2, $s['generation'] );
		foreach ( array( 'upgrade', 'closure', 'completion' ) as $slot ) $this->assertLessThanOrEqual( 1024, strlen( json_encode( $s['writer'][$slot] ) ) );
		$begin = $this->issue_form( 'begin_disable' ); $row = $this->memory->row; $this->unavailable( $this->admission->consume( 'POST', $begin ) ); $this->assertSame( $row, $this->memory->row );
	}
	public function test_missing_history_or_forged_completion_cannot_validate(): void {
		$this->upgraded_admission(); $this->abandon_cycle(); $this->abandon_cycle(); $good = $this->memory->row['state']; $bad = $good; $bad['writer']['fixture_receipts'] = array();
		$this->assertFalse( AdmissionModel::valid( $bad, $this->site ) ); $bad = $good; $bad['writer']['completion']['purpose'] = 'complete_fixture'; $this->assertFalse( AdmissionModel::valid( $bad, $this->site ) );
		$bad = $good; $bad['writer']['closure']['completion_id'] = $good['writer']['upgrade']['command']['command_id']; $this->assertFalse( AdmissionModel::valid( $bad, $this->site ) );
	}
	public function test_full_width_clock_and_counters_keep_each_record_and_pending_reserve_bounded(): void {
		$old = $this->schema_three(); foreach ( array( 'generation', 'schedule_revision' ) as $key ) $old[$key] = PHP_INT_MAX - 7; $old['lifecycle']['permission_generation'] = PHP_INT_MAX - 7;
		$this->replace( $old ); $this->checkpoint = $this->close_origin(); $this->assertIsArray( $this->upgrade() ); $this->six = $this->origin(); $this->memory->row['state'] = $this->six; $this->memory->now = PHP_INT_MAX - 4000;
		$this->upgraded_admission(); $this->memory->mode = 'abort'; $begin = $this->issue_form( 'begin_disable' ); $this->assertIsArray( $this->admission->consume( 'POST', $begin ) );
		$this->assertLessThanOrEqual( 131072 - 4096, strlen( json_encode( $this->memory->row['state'] ) ) );
		$end = $this->issue_form( 'abandon_closed', $begin['command_id'] ); $this->assertSame( 'abandoned', $this->admission->consume( 'POST', $end )['secondary_outcome'] );
		$s = $this->assert_admission_closed(); foreach ( array( 'upgrade', 'closure', 'completion' ) as $slot ) $this->assertLessThanOrEqual( 1024, strlen( json_encode( $s['writer'][$slot] ) ) );
	}
}
