<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ClosedWriterBarrierFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedWriterBarrierValidationTest extends TestCase {
	use Alynt_Test_Closed_Writer_Barrier_Fixture;
	/** @dataProvider malformed_states */
	public function test_malformed_or_authority_looking_state_never_repaired( $path, $value ) {
		$this->upgrade(); $c = $this->begin_command(); $s = $this->origin(); $ref = &$s; foreach ( explode( '.', $path ) as $key ) $ref = &$ref[$key]; $ref = $value; unset( $ref ); $this->replace( $s ); $rows = $this->backend->rows; $swaps = $this->backend->swaps;
		$this->barrier_error( 'unavailable', $this->barrier->snapshot() ); $this->barrier_error( 'unavailable', $this->barrier->begin( $c, $this->permitted() ) ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $swaps, $this->backend->swaps );
	}
	public function malformed_states() { return array(
		array( 'extra', false ), array( 'generation', '2' ), array( 'generation', PHP_INT_MAX ), array( 'schedule_revision', 0 ), array( 'enrollment_binding', str_repeat( 'b', 64 ) ), array( 'authority.enabled', true ), array( 'authority.next_due_at', 1 ), array( 'claims', array( 'fake' ) ), array( 'dispatch', array() ),
		array( 'lifecycle.consent', true ), array( 'lifecycle.phase', 'active' ), array( 'lifecycle.permission_generation', '2' ), array( 'lifecycle.history', 'verified_legacy' ), array( 'lifecycle.changed_at', '1800000000' ),
		array( 'barrier.source', 'authenticated_request_v1' ), array( 'barrier.site_uuid', '22222222-2222-4222-8222-222222222222' ), array( 'barrier.authenticated_grant', array() ), array( 'barrier.authenticated_receipts', array( true ) ),
		array( 'barrier.pending', true ), array( 'barrier.closure', array() ), array( 'barrier.completion', array() ), array( 'barrier.receipts', false ), array( 'barrier.upgrade.checkpoint_digest', str_repeat( 'f', 64 ) ), array( 'barrier.upgrade.result_generation', 0 ), array( 'barrier.upgrade.committed_at', -1 ),
		array( 'barrier.checkpoint.schema_version', 6 ), array( 'barrier.checkpoint.provenance.source', 'authenticated_request_v1' ), array( 'barrier.checkpoint.provenance.legacy.digest', str_repeat( 'f', 64 ) )
	); }
	public function test_receipt_tamper_missing_history_and_pending_erasure_are_rejected() {
		$this->upgrade(); $a = $this->begin_command(); $this->barrier->begin( $a, $this->permitted() ); $pending = $this->origin(); $bad = $pending; $bad['barrier']['pending'] = null; $this->replace( $bad ); $this->barrier_error( 'unavailable', $this->barrier->snapshot() );
		$this->replace( $pending ); $this->barrier->finish( $this->terminal_command( $a ), $this->permitted() ); $b = $this->begin_command( 3 ); $this->barrier->begin( $b, $this->permitted() ); $good = $this->origin(); $this->assertCount( 2, $good['barrier']['receipts'] );
		$bad = $good; $bad['barrier']['receipts'] = array(); $this->replace( $bad ); $this->barrier_error( 'unavailable', $this->barrier->snapshot() );
		$bad = $good; $bad['barrier']['closure']['command']['target'] = 'connection'; $this->replace( $bad ); $this->barrier_error( 'unavailable', $this->barrier->snapshot() );
		$bad = $good; $bad['barrier']['closure']['committed_at']++; $this->replace( $bad ); $this->barrier_error( 'unavailable', $this->barrier->snapshot() );
		$this->replace( $good ); $this->assert_closed_barrier();
	}
	public function test_native_terminal_work_and_due_survive_without_new_effects_or_other_option_writes() {
		$s = $this->pending_state( $this->schema_three() ); foreach ( $s['claims'] as &$claim ) { $claim['phase'] = 'succeeded'; $claim['completed_at'] = $this->now; $claim['authority_evidence_version'] = 1; } unset( $claim ); $s['lifecycle']['phase'] = 'disabled'; $this->replace( $s ); $this->checkpoint = $this->close_origin();
		$this->backend->rows['cron'] = array( 'option_value' => 'untouched cron', 'autoload' => 'yes' ); $this->backend->rows['alynt_drime_backups_settings'] = array( 'option_value' => 'untouched settings', 'autoload' => 'no' );
		$this->upgrade(); $c = $this->begin_command(); $this->barrier->begin( $c, $this->permitted() ); $this->barrier->finish( $this->terminal_command( $c ), $this->permitted() ); $this->assert_closed_barrier();
		$this->assertSame( 'untouched cron', $this->backend->rows['cron']['option_value'] ); $this->assertSame( 'untouched settings', $this->backend->rows['alynt_drime_backups_settings']['option_value'] );
	}
	/** @dataProvider unresolved_work */
	public function test_unfinished_source_is_preserved_not_migrated_into_a_claimed_closed_state( $dispatch ) {
		$s = $this->pending_state( $this->schema_three(), $dispatch ); $this->replace( $s ); $this->now += 90000; $rows = $this->backend->rows;
		$this->barrier_error( 'unavailable', $this->barrier->prepare_closed( $this->binding, $s['lifecycle']['permission_generation'], $this->permitted() ) ); $this->assertSame( $rows, $this->backend->rows );
	}
	public function unresolved_work() { return array( array( false ), array( true ) ); }
}
