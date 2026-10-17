<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ClosedWriterBarrierFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedWriterBarrierBoundaryTest extends TestCase {
	use Alynt_Test_Closed_Writer_Barrier_Fixture;
	public function test_default_truthy_bad_scope_and_disabled_operations_have_zero_reads_or_guards() {
		$c = $this->begin_command(); $end = $this->terminal_command( $c ); $reads = $this->backend->reads; $swaps = $this->backend->swaps; $calls = 0; $g = function () use ( &$calls ) { $calls++; return true; };
		$models = array( $this->new_barrier( false ), $this->new_barrier( 1 ), $this->new_barrier( 'true' ), $this->new_barrier( true, 'wrong-site' ), new Alynt_Drime_Backups_Uploader_Closed_Writer_Barrier_Store( $this->backend, $this->site, function () { return $this->now; } ) );
		foreach ( $models as $m ) {
			$this->barrier_error( 'disabled', $m->snapshot() ); $this->barrier_error( 'disabled', $m->prepare_closed( $this->binding, 0, $g ) ); $this->barrier_error( 'disabled', $m->receipt( 'closed_writer_fixture_v1', $c['command_id'] ) );
			foreach ( array( 'begin', 'finish', 'abandon' ) as $method ) $this->barrier_error( 'disabled', $m->$method( 'begin' === $method ? $c : $end, $g ) );
		}
		$this->assertSame( 0, $calls ); $this->assertSame( $reads, $this->backend->reads ); $this->assertSame( $swaps, $this->backend->swaps );
	}
	public function test_grants_execution_and_inherited_mutations_are_zero_read_denied() {
		$reads = $this->backend->reads; $calls = 0; $g = function () use ( &$calls ) { $calls++; return true; };
		foreach ( array( 'grant', 'revoke', 'rebind', 'activate', 'schedule_rollback', 'dispatch' ) as $purpose ) $this->barrier_error( 'commands_disabled', $this->barrier->command( array( 'purpose' => $purpose ), $g ) );
		foreach ( array( $this->barrier->initialize( $this->binding ), $this->barrier->claim( $this->binding, $this->site, array() ), $this->barrier->transition( '', '', '', '', '', '' ), $this->barrier->invalidate( $this->binding ) ) as $error ) $this->barrier_error( 'ledger_api_required', $error );
		$this->assertSame( $reads, $this->backend->reads ); $this->assertSame( 0, $calls ); $this->assertFalse( method_exists( $this->barrier, 'activate' ) );
	}
	/** @dataProvider invalid_intents */
	public function test_invalid_inputs_reject_before_guard_or_read( $field, $value ) {
		$c = $this->begin_command(); $c[$field] = $value; $reads = $this->backend->reads;
		$this->barrier_error( 'invalid', $this->barrier->begin( $c, function () { throw new RuntimeException( 'must not run' ); } ) ); $this->assertSame( $reads, $this->backend->reads );
	}
	public function invalid_intents() { return array( array( 'extra', true ), array( 'site_uuid', '22222222-2222-4222-8222-222222222222' ), array( 'expected_generation', '1' ), array( 'expected_generation', PHP_INT_MAX ), array( 'issued_at', 0 ), array( 'expires_at', PHP_INT_MAX ), array( 'kind', array() ), array( 'target', 'cron' ), array( 'fields', array( 'api_token' ) ), array( 'fields', array( 'scan_interval', 'auto_scan_enabled' ) ), array( 'fields', array( 'auto_scan_enabled', 'auto_scan_enabled' ) ), array( 'fields', array( array() ) ), array( 'before_hash', str_repeat( 'd', 64 ) ), array( 'after_hash', true ) ); }
	public function test_guard_denial_truthy_exception_and_absence_never_read() {
		$c = $this->begin_command(); $reads = $this->backend->reads; $rows = $this->backend->rows;
		$this->barrier_error( 'invalid', $this->barrier->begin( $c, true ) );
		foreach ( array( false, 1, 'true' ) as $value ) $this->barrier_error( 'denied', $this->barrier->begin( $c, function () use ( $value ) { return $value; } ) );
		$this->barrier_error( 'unresolved', $this->barrier->begin( $c, function () { throw new RuntimeException( 'private guard' ); } ) ); $this->assertSame( $reads, $this->backend->reads ); $this->assertSame( $rows, $this->backend->rows );
	}
	/** @dataProvider unavailable_rows */
	public function test_unsupported_missing_corrupt_deep_and_autoloaded_rows_never_initialize( $case ) {
		$name = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION;
		if ( 'missing' === $case ) unset( $this->backend->rows[$name] );
		elseif ( 'autoload' === $case ) $this->backend->rows[$name]['autoload'] = 'yes';
		elseif ( 'deep' === $case ) $this->backend->rows[$name]['option_value'] = str_repeat( '{"x":', 17 ) . '0' . str_repeat( '}', 17 );
		elseif ( 'oversize' === $case ) $this->backend->rows[$name]['option_value'] = str_repeat( 'x', 131073 );
		elseif ( 'corrupt' === $case ) $this->backend->rows[$name]['option_value'] = '{';
		else $this->replace( $this->schema_three() );
		$rows = $this->backend->rows; $creates = $this->backend->creates; $swaps = $this->backend->swaps;
		$this->barrier_error( 'unavailable', $this->barrier->prepare_closed( $this->binding, $this->checkpoint['lifecycle']['permission_generation'], $this->permitted() ) );
		$this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $creates, $this->backend->creates ); $this->assertSame( $swaps, $this->backend->swaps );
	}
	public function unavailable_rows() { return array( array( 'missing' ), array( 'autoload' ), array( 'deep' ), array( 'oversize' ), array( 'corrupt' ), array( 'unsupported' ) ); }
	/** @dataProvider malformed_terminals */
	public function test_terminal_input_shape_is_validated_before_guard_or_read( $field, $value ) {
		$c = $this->terminal_command( $this->begin_command() ); $c[$field] = $value; $reads = $this->backend->reads;
		foreach ( array( 'finish', 'abandon' ) as $method ) $this->barrier_error( 'invalid', $this->barrier->$method( $c, function () { throw new RuntimeException( 'must not run' ); } ) );
		$this->assertSame( $reads, $this->backend->reads );
	}
	public function malformed_terminals() { return array( array( 'trusted', true ), array( 'operation_id', array() ), array( 'closure_generation', '2' ), array( 'observed_hash', str_repeat( 'f', 65 ) ), array( 'issued_at', 1.5 ), array( 'command_id', '55555555-5555-4555-8555-000000000001' ) ); }
}
