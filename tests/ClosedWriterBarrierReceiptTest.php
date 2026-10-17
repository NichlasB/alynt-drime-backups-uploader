<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ClosedWriterBarrierFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedWriterBarrierReceiptTest extends TestCase {
	use Alynt_Test_Closed_Writer_Barrier_Fixture;
	public function test_duplicate_and_superseded_observations_never_reopen_or_extend_an_operation() {
		$this->upgrade(); $c = $this->begin_command(); $this->barrier->begin( $c, $this->permitted() ); $rows = $this->backend->rows; $swaps = $this->backend->swaps;
		$this->barrier_result( 'observed_current', $this->barrier->begin( array_reverse( $c, true ), $this->permitted() ) ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $swaps, $this->backend->swaps );
		$end = $this->terminal_command( $c ); $this->barrier->finish( $end, $this->permitted() ); $rows = $this->backend->rows; $swaps = $this->backend->swaps;
		$this->barrier_result( 'observed_superseded', $this->barrier->begin( $c, $this->permitted() ) ); $this->barrier_result( 'observed_current', $this->barrier->finish( $end, $this->permitted() ) ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $swaps, $this->backend->swaps );
		$changed = $c; $changed['after_hash'] = str_repeat( 'e', 64 ); $this->barrier_error( 'conflict', $this->barrier->begin( $changed, $this->permitted() ) ); $this->barrier_error( 'conflict', $this->barrier->abandon( $end, $this->permitted() ) );
	}
	public function test_expired_consumption_rejects_but_read_observation_does_not_renew() {
		$this->upgrade(); $c = $this->begin_command(); $this->barrier->begin( $c, $this->permitted() ); $this->now += 300; $rows = $this->backend->rows;
		$this->barrier_error( 'expired', $this->barrier->begin( $c, $this->permitted() ) ); $this->barrier_result( 'observed_current', $this->barrier->receipt( 'closed_writer_fixture_v1', $c['command_id'] ) ); $this->assertSame( $rows, $this->backend->rows );
	}
	public function test_fixture_archive_retains_synthetic_grant_without_namespace_promotion() {
		$this->replace( $this->schema_three() ); $this->migrate(); $legacy = $this->intent(); $this->ledger->command( $legacy ); $this->checkpoint = $this->close_origin(); $this->upgrade();
		$this->barrier_result_for_archive( $legacy['command_id'] ); $this->barrier_error( 'domain_conflict', $this->barrier->receipt( 'closed_writer_fixture_v1', $legacy['command_id'] ) );
		$c = $this->begin_command(); $c['command_id'] = $legacy['command_id']; $this->barrier_error( 'domain_conflict', $this->barrier->begin( $c, $this->permitted() ) );
		$c = $this->begin_command(); $this->barrier->begin( $c, $this->permitted() ); $this->barrier_error( 'domain_conflict', $this->barrier->receipt( 'fixture_intent_v1', $c['command_id'] ) ); $s = $this->assert_closed_barrier(); $this->assertTrue( $s['barrier']['checkpoint']['provenance']['legacy']['lifecycle']['consent'] );
	}
	private function barrier_result_for_archive( $id ) { $this->assertSame( array( 'outcome' => 'observed_superseded', 'source' => 'fixture_intent_v1', 'dispatch_supported' => false, 'execution_supported' => false ), $this->barrier->receipt( 'fixture_intent_v1', $id ) ); }
	public function test_unsupported_authenticated_or_request_domain_is_zero_read_rejected() {
		$this->upgrade(); $c = $this->begin_command(); $reads = $this->backend->reads;
		foreach ( array( 'authenticated_request_v1', 'fixture_request_v1', true, array() ) as $domain ) $this->barrier_error( 'invalid', $this->barrier->receipt( $domain, $c['command_id'] ) );
		$this->assertSame( $reads, $this->backend->reads ); $this->barrier_result( 'not_found', $this->barrier->receipt( 'closed_writer_fixture_v1', $c['command_id'] ) );
	}
	public function test_matching_upgrade_at_current_epoch_observes_pending_never_resets_it() {
		$this->upgrade(); $c = $this->begin_command(); $this->barrier->begin( $c, $this->permitted() ); $s = $this->origin(); $rows = $this->backend->rows; $swaps = $this->backend->swaps;
		$this->assertSame( $s, $this->barrier->prepare_closed( $this->binding, $s['lifecycle']['permission_generation'], $this->permitted() ) ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $swaps, $this->backend->swaps );
		$this->barrier_error( 'stale', $this->upgrade() ); $this->assertSame( $rows, $this->backend->rows );
	}
}
