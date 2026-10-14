<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ConsentLedgerFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ConsentLedgerValidationTest extends TestCase {
	use Alynt_Test_Consent_Ledger_Fixture;
	/** @dataProvider malformed_fields */
	public function test_malformed_commands_reject_before_read_or_write( $field, $value ) {
		$this->migrate(); $c = $this->intent(); $c[$field] = $value; $rows = $this->backend->rows; $reads = $this->backend->reads; $swaps = $this->backend->swaps;
		$this->error( 'invalid_command', $this->ledger->command( $c ) ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $reads, $this->backend->reads ); $this->assertSame( $swaps, $this->backend->swaps );
	}
	public function malformed_fields() { return array( array( 'command_id', 'bad' ), array( 'command_id', array() ), array( 'site_uuid', '33333333-3333-4333-8333-333333333333' ), array( 'enrollment_binding', strtoupper( str_repeat( 'a', 64 ) ) ), array( 'expected_generation', '1' ), array( 'expected_generation', -1 ), array( 'expected_generation', PHP_INT_MAX ), array( 'purpose', 'activate' ), array( 'purpose', 'rollback' ), array( 'issued_at', 0 ), array( 'expires_at', 1800000301 ), array( 'expires_at', '1800000300' ), array( 'replacement_binding', str_repeat( 'b', 64 ) ), array( 'trusted', true ), array( 'actor', 1 ), array( 'nonce', str_repeat( 's', 5000 ) ) ); }
	/** @dataProvider malformed_roots */
	public function test_partial_or_nonarray_command_is_not_normalized( $root ) { $this->migrate(); $rows = $this->backend->rows; $this->error( 'invalid_command', $this->ledger->command( $root ) ); $this->assertSame( $rows, $this->backend->rows ); }
	public function malformed_roots() { return array( array( null ), array( false ), array( 'grant' ), array( array() ), array( array( 'purpose' => 'grant' ) ) ); }
	/** @dataProvider corrupted_fields */
	public function test_corrupt_provenance_receipt_and_enabled_state_never_repair_or_admit( $path, $value ) {
		$this->migrate(); $this->ledger->command( $this->intent() ); $s = $this->ledger->snapshot(); $target = &$s; foreach ( $path as $key ) $target = &$target[$key]; $target = $value; unset( $target ); $this->replace( $s ); $rows = $this->backend->rows;
		$this->error( 'unavailable', $this->ledger->snapshot() ); $this->error( 'unavailable', $this->ledger->command( $this->intent( 'revoke', 2, 2 ) ) ); $this->assertSame( $rows, $this->backend->rows );
	}
	public function corrupted_fields() { return array( array( array( 'ledger', 'source' ), 'authenticated_local_admin' ), array( array( 'ledger', 'grant_command_id' ), null ), array( array( 'ledger', 'last_receipt', 'request_hash' ), str_repeat( 'f', 64 ) ), array( array( 'ledger', 'last_receipt', 'result_generation' ), 99 ), array( array( 'ledger', 'last_receipt', 'committed_at' ), 0 ), array( array( 'authority', 'enabled' ), true ), array( array( 'lifecycle', 'history' ), 'never_activated' ), array( array( 'schema_version' ), 5 ), array( array( 'ledger', 'site_uuid' ), '33333333-3333-4333-8333-333333333333' ) ); }
	/** @dataProvider invalid_clocks */
	public function test_invalid_regressing_or_overflow_clock_blocks_new_command( $now ) { $this->migrate(); $c = $this->intent(); $rows = $this->backend->rows; $this->now = $now; $this->error( 'clock', $this->ledger->command( $c ) ); $this->assertSame( $rows, $this->backend->rows ); }
	public function invalid_clocks() { return array( array( null ), array( '1800000000' ), array( 0 ), array( 1799999999 ), array( PHP_INT_MAX ) ); }
	public function test_exact_expiry_blocks_grant_but_fresh_closure_has_no_grant_deadline() {
		$this->migrate(); $c = $this->intent(); $this->now += 300; $rows = $this->backend->rows; $this->error( 'expired', $this->ledger->command( $c ) ); $this->assertSame( $rows, $this->backend->rows );
		$this->result( 'committed', $this->ledger->command( $this->intent( 'revoke', 2 ) ) ); $this->assertFalse( $this->ledger->snapshot()['lifecycle']['consent'] );
	}
	public function test_explicit_different_scope_cannot_observe_or_mutate_the_ledger() {
		$this->migrate(); $other = $this->new_ledger( true, '33333333-3333-4333-8333-333333333333' ); $rows = $this->backend->rows;
		$this->error( 'unavailable', $other->snapshot() ); $this->error( 'invalid_command', $other->command( $this->intent() ) ); $this->assertSame( $rows, $this->backend->rows );
	}
}
