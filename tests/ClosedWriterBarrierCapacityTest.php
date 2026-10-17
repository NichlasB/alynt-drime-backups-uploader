<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ClosedWriterBarrierFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedWriterBarrierCapacityTest extends TestCase {
	use Alynt_Test_Closed_Writer_Barrier_Fixture;
	public function test_full_new_history_blocks_another_begin_but_reserves_current_completion() {
		$this->upgrade();
		for ( $i = 0; $i < 26; $i++ ) {
			$c = $this->begin_command( $i * 2 + 1 ); $this->barrier_result( 'committed', $this->barrier->begin( $c, $this->permitted() ) );
			if ( 25 === $i ) $this->assertCount( 50, $this->origin()['barrier']['receipts'] );
			$this->barrier_result( 'committed', $this->barrier->finish( $this->terminal_command( $c, $i * 2 + 2 ), $this->permitted() ) );
		}
		$s = $this->assert_closed_barrier(); $this->assertCount( 50, $s['barrier']['receipts'] ); $rows = $this->backend->rows;
		$this->barrier_error( 'capacity', $this->barrier->begin( $this->begin_command( 60 ), $this->permitted() ) ); $this->assertSame( $rows, $this->backend->rows );
		$this->barrier_result( 'observed_superseded', $this->barrier->receipt( 'closed_writer_fixture_v1', '55555555-5555-4555-8555-000000000001' ) );
	}
	public function test_frozen_fixture_history_shares_budget_and_never_prunes_for_a_new_begin() {
		$this->replace( $this->schema_three() ); $this->migrate(); for ( $i = 1; $i <= 51; $i++ ) $this->ledger->command( $this->intent( 'revoke', $i ) ); $this->checkpoint = $this->close_origin(); $this->assertIsArray( $this->checkpoint ); $this->upgrade();
		$c = $this->begin_command(); $this->barrier_result( 'committed', $this->barrier->begin( $c, $this->permitted() ) ); $this->barrier_result( 'committed', $this->barrier->abandon( $this->terminal_command( $c ), $this->permitted() ) );
		$rows = $this->backend->rows; $this->barrier_error( 'capacity', $this->barrier->begin( $this->begin_command( 3 ), $this->permitted() ) ); $this->assertSame( $rows, $this->backend->rows ); $this->assert_closed_barrier();
	}
	public function test_last_usable_begin_reserves_terminal_and_one_further_closure_increment() {
		$old = $this->schema_three(); foreach ( array( 'generation', 'schedule_revision' ) as $key ) $old[$key] = PHP_INT_MAX - 6; $old['lifecycle']['permission_generation'] = PHP_INT_MAX - 6; $this->replace( $old ); $this->checkpoint = $this->close_origin();
		$this->assertSame( PHP_INT_MAX - 5, $this->checkpoint['lifecycle']['permission_generation'] ); $this->assertIsArray( $this->upgrade() ); $c = $this->begin_command();
		$this->barrier_result( 'committed', $this->barrier->begin( $c, $this->permitted() ) ); $this->barrier_result( 'committed', $this->barrier->finish( $this->terminal_command( $c ), $this->permitted() ) ); $s = $this->assert_closed_barrier(); $this->assertSame( PHP_INT_MAX - 2, $s['lifecycle']['permission_generation'] );
		$rows = $this->backend->rows; $this->barrier_error( 'capacity', $this->barrier->begin( $this->begin_command( 3 ), $this->permitted() ) ); $this->assertSame( $rows, $this->backend->rows );
	}
	public function test_upgrade_refuses_insufficient_counter_or_encoded_byte_reserve_without_overwrite() {
		$old = $this->schema_three(); foreach ( array( 'generation', 'schedule_revision' ) as $key ) $old[$key] = PHP_INT_MAX - 5; $old['lifecycle']['permission_generation'] = PHP_INT_MAX - 5; $this->replace( $old ); $this->checkpoint = $this->close_origin(); $rows = $this->backend->rows;
		$this->barrier_error( 'capacity', $this->upgrade() ); $this->assertSame( $rows, $this->backend->rows );
	}
	public function test_encoder_failure_or_reserve_loss_at_upgrade_and_begin_preserves_bytes() {
		$rows = $this->backend->rows;
		\Brain\Monkey\Functions\when( 'wp_json_encode' )->alias( function ( $value ) { return isset( $value['schema_version'] ) && 6 === $value['schema_version'] ? false : json_encode( $value ); } );
		$this->barrier_error( 'capacity', $this->upgrade() ); $this->assertSame( $rows, $this->backend->rows );
		\Brain\Monkey\Functions\when( 'wp_json_encode' )->alias( 'json_encode' ); $this->upgrade(); $c = $this->begin_command(); $rows = $this->backend->rows;
		\Brain\Monkey\Functions\when( 'wp_json_encode' )->alias( function ( $value ) { return isset( $value['schema_version'], $value['barrier']['pending'] ) && 6 === $value['schema_version'] && null !== $value['barrier']['pending'] ? str_repeat( 'x', 131072 - 4095 ) : json_encode( $value ); } );
		$this->barrier_error( 'capacity', $this->barrier->begin( $c, $this->permitted() ) ); $this->assertSame( $rows, $this->backend->rows );
	}
	public function test_oversized_frozen_checkpoint_duplication_rejects_without_discarding_work() {
		$this->replace( $this->schema_three() ); $this->migrate(); for ( $i = 1; $i <= 51; $i++ ) $this->ledger->command( $this->intent( 'revoke', $i ) ); $s = $this->pending_state( $this->origin() ); $record = reset( $s['claims'] ); $record['phase'] = 'succeeded'; $record['completed_at'] = $this->now; $s['claims'] = array();
		for ( $i = 1; $i <= 50; $i++ ) { $r = $record; $r['action_id'] = sprintf( '11111111-1111-4111-8111-%012d', $i ); $r['lease_owner'] = $r['action_id']; $s['claims'][sprintf( '66666666-6666-4666-8666-%012d', $i )] = $r; }
		$s['lifecycle']['phase'] = 'disabled'; $this->replace( $s ); $this->assertIsArray( $this->ledger->snapshot() ); $this->checkpoint = $this->close_origin(); $this->assertIsArray( $this->checkpoint ); $rows = $this->backend->rows;
		$this->barrier_error( 'capacity', $this->upgrade() ); $this->assertSame( $rows, $this->backend->rows ); $this->assertCount( 50, $this->checkpoint['claims'] );
	}
}
