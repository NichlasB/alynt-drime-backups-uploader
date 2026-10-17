<?php
require_once __DIR__ . '/Support/ClosedWriterAdmissionFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedWriterAdmissionFailureTest extends PHPUnit\Framework\TestCase {
	use Alynt_Test_Closed_Writer_Admission_Fixture;
	public function test_three_write_attempts_keep_original_intent_and_one_reserved_completion_uuid(): void {
		$this->upgraded_admission(); $begin = $this->issue_form( 'begin_disable' ); $this->memory->conflicts = 2; $this->memory->proposals = array(); $writes = $this->memory->writes; $uuids = $this->memory->uuid_calls; $auth = $this->memory->auth_calls;
		$result = $this->admission->consume( 'POST', $begin ); $this->assertSame( 'simulated_committed', $result['secondary_outcome'] );
		$this->assertSame( $writes + 3, $this->memory->writes ); $this->assertSame( $uuids + 1, $this->memory->uuid_calls ); $this->assertGreaterThan( 6, $this->memory->auth_calls - $auth );
		$this->assertSame( $this->memory->proposals[0], $this->memory->proposals[1] ); $this->assertSame( $this->memory->proposals[0], $this->memory->proposals[2] );
	}
	public function test_exhausted_conflicts_do_not_refresh_intent_or_mutate_and_abandon_only_attempts_once(): void {
		$this->upgraded_admission(); $begin = $this->issue_form( 'begin_disable' ); $row = $this->memory->row; $writes = $this->memory->writes; $this->memory->conflicts = 3;
		$this->assertSame( 'unresolved', $this->admission->consume( 'POST', $begin )['outcome'] ); $this->assertSame( $row, $this->memory->row ); $this->assertSame( $writes + 3, $this->memory->writes );
		$this->memory->mode = 'abort'; $this->admission->consume( 'POST', $begin ); $abandon = $this->issue_form( 'abandon_closed', $begin['command_id'] ); $row = $this->memory->row; $writes = $this->memory->writes; $this->memory->conflicts = 2;
		$this->assertSame( 'unresolved', $this->admission->consume( 'POST', $abandon )['outcome'] ); $this->assertSame( $row, $this->memory->row ); $this->assertSame( $writes + 1, $this->memory->writes );
	}
	/** @dataProvider authorization_points */
	public function test_fresh_authorization_loss_at_each_boundary_never_claims_rollback( $point ): void {
		$this->upgraded_admission(); $begin = $this->issue_form( 'begin_disable' ); $calls = 0;
		$this->memory->on_auth = function ( $m ) use ( &$calls, $point ) { if ( ++$calls === $point ) $m->capable = false; };
		$this->unavailable( $this->admission->consume( 'POST', $begin ) );
		$this->assertSame( $point >= 9 ? false : true, json_decode( $this->memory->row['target'], true )['auto_scan_enabled'] );
		$s = $this->assert_admission_closed(); if ( $point >= 9 ) $this->assertNotNull( $s['writer']['completion'] );
		if ( $point <= 5 ) $this->assertNull( $s['writer']['pending'] );
	}
	public static function authorization_points(): array { return array_map( function ( $n ) { return array( $n ); }, range( 1, 10 ) ); }
	/** @dataProvider transaction_faults */
	public function test_transaction_faults_leave_closed_pending_and_retained_continuation_dead( $mode ): void {
		$this->upgraded_admission(); $this->memory->mode = $mode; $begin = $this->issue_form( 'begin_disable' ); $target = $this->memory->row['target']; $outcome = $this->admission->consume( 'POST', $begin );
		if ( 'throw_before' === $mode ) $this->unavailable( $outcome ); else $this->assertSame( 'unresolved', $outcome['secondary_outcome'] );
		$s = $this->assert_admission_closed(); $this->assertNotNull( $s['writer']['pending'] ); $this->assertNull( $s['writer']['completion'] ); $this->assertSame( $target, $this->memory->row['target'] );
		$this->assertFalse( $this->memory->retained->enter_fixture( $this->memory->identity, $s, $target, $this->memory->now ) );
	}
	public static function transaction_faults(): array { return array( array( 'none' ), array( 'abort' ), array( 'wrong_identity' ), array( 'throw_before' ) ); }
	public function test_lost_acknowledgement_is_observed_without_target_replay(): void {
		$this->upgraded_admission(); $begin = $this->issue_form( 'begin_disable' ); $this->memory->ack = 'false'; $writes = $this->memory->writes;
		$result = $this->admission->consume( 'POST', $begin ); $this->assertSame( 'observed_current', $result['outcome'] ); $this->assertSame( 'unresolved', $result['secondary_outcome'] );
		$this->assertSame( $writes + 1, $this->memory->writes ); $this->assertSame( 0, $this->memory->transactions );
		$this->memory->now += 301; $read = $this->issue_form( 'receipt_read', $begin['command_id'] ); $this->assertSame( 'unresolved', $this->admission->consume( 'POST', $read )['secondary_outcome'] );
	}
	public function test_final_false_ack_is_reconciled_and_truthy_ack_never_means_commit(): void {
		$form = $this->issue_form(); $this->memory->conflicts = 2; $this->memory->ack = 'false'; $this->assertSame( 'observed_current', $this->admission->consume( 'POST', $form )['outcome'] ); $this->assertSame( 3, $this->memory->writes );
		$begin = $this->issue_form( 'begin_disable' ); $row = $this->memory->row;
		foreach ( array( 'truthy', 'true_without_write' ) as $ack ) { $this->memory->ack = $ack; $this->unavailable( $this->admission->consume( 'POST', $begin ) ); $this->assertSame( $row, $this->memory->row ); }
	}
	public function test_throwing_ack_and_post_transaction_response_are_reconciled_without_erasing_state(): void {
		$form = $this->issue_form(); $this->memory->ack = 'throw'; $this->unavailable( $this->admission->consume( 'POST', $form ) ); $this->assert_admission_closed();
		$this->memory->ack = 'normal'; $this->assertSame( 'observed_current', $this->admission->consume( 'POST', $form )['outcome'] );
		$this->memory->mode = 'throw_after'; $begin = $this->issue_form( 'begin_disable' ); $this->unavailable( $this->admission->consume( 'POST', $begin ) ); $s = $this->assert_admission_closed();
		$this->assertNull( $s['writer']['pending'] ); $this->assertNotNull( $s['writer']['completion'] ); $writes = $this->memory->writes;
		$this->assertSame( 'simulated_committed', $this->admission->consume( 'POST', $begin )['secondary_outcome'] ); $this->assertSame( $writes, $this->memory->writes );
	}
	public function test_changed_or_expired_locked_facts_cannot_use_pre_begin_context_as_admission(): void {
		$this->upgraded_admission(); $begin = $this->issue_form( 'begin_disable' ); $this->memory->on_transaction = function ( $m ) { $m->now += 300; };
		$result = $this->admission->consume( 'POST', $begin ); $this->unavailable( $result ); $s = $this->assert_admission_closed(); $this->assertNotNull( $s['writer']['pending'] );
	}
	public function test_target_changes_before_locked_entry_reject_without_overwriting_them(): void {
		$this->upgraded_admission(); $begin = $this->issue_form( 'begin_disable' ); $this->memory->on_transaction = function ( $m ) { $m->row['target'] .= ' '; };
		$this->assertSame( 'unresolved', $this->admission->consume( 'POST', $begin )['secondary_outcome'] ); $s = $this->assert_admission_closed(); $this->assertNotNull( $s['writer']['pending'] ); $this->assertStringEndsWith( ' ', $this->memory->row['target'] );
	}
	public function test_missing_corrupt_autoloaded_and_throwing_snapshots_are_not_repaired(): void {
		$form = $this->issue_form(); $original = $this->memory->row;
		foreach ( array( array(), array_replace( $original, array( 'state' => array() ) ), array_replace( $original, array( 'autoload' => 'yes' ) ) ) as $row ) {
			$this->memory->row = $row; $this->unavailable( $this->admission->consume( 'POST', $form ) ); $this->assertSame( $row, $this->memory->row );
		}
		$this->memory->row = $original; $this->memory->throw_read = true; $this->unavailable( $this->admission->consume( 'POST', $form ) ); $this->assertSame( 0, $this->memory->writes );
	}
	public function test_regressing_clock_and_expired_nonce_window_are_zero_read_rejected(): void {
		$form = $this->issue_form(); $reads = $this->memory->reads; --$this->memory->now; $this->unavailable( $this->admission->consume( 'POST', $form ) );
		$this->memory->now += 301; $this->unavailable( $this->admission->consume( 'POST', $form ) ); $this->assertSame( $reads, $this->memory->reads );
	}
	/** @dataProvider precommit_clock_changes */
	public function test_original_deadline_and_prepared_timestamp_are_checked_before_fixture_commit( $advance ): void {
		$this->upgraded_admission(); $begin = $this->issue_form( 'begin_disable' ); $target = $this->memory->row['target'];
		$this->memory->before_commit = function ( $m ) use ( $advance ) { $m->now += $advance; };
		$result = $this->admission->consume( 'POST', $begin ); if ( 300 === $advance ) $this->unavailable( $result ); else $this->assertSame( 'unresolved', $result['secondary_outcome'] );
		$this->assertSame( $target, $this->memory->row['target'] ); $s = $this->assert_admission_closed(); $this->assertNotNull( $s['writer']['pending'] ); $this->assertNull( $s['writer']['completion'] );
		$this->assertFalse( $this->memory->retained->confirm_fixture( $this->memory->identity, $s, $target, $this->memory->now ) );
	}
	public static function precommit_clock_changes(): array { return array( array( 1 ), array( 300 ) ); }
}
