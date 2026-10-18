<?php
use PHPUnit\Framework\TestCase;
use Alynt_Drime_Backups_Uploader_Closed_Writer_Session_Contract as Session;
require_once __DIR__ . '/Support/RawObservationFixture.php';

class ClosedWriterContractBoundaryTest extends TestCase {
	use Alynt_Test_Raw_Observation_Fixture;
	public function test_original_identity_and_clock_inputs_have_typed_adjacent_controls() {
		$valid = array( $this->scope['session_id'], $this->real_id( 90 ), 100, 400, 100, 1000 );
		$this->assertIsObject( Session::simulate( ...$valid ) );
		foreach ( array( 0 => array( '', true, new stdClass() ), 1 => array( '', true, $this->real_id( 0 ) . 'x' ), 2 => array( 0, '100', 401 ), 3 => array( 100, 401, '400' ), 4 => array( 99, 400, '100' ), 5 => array( -1, '1000', PHP_INT_MAX ) ) as $index => $values ) foreach ( $values as $value ) {
			$args = $valid; $args[$index] = $value; $this->assertFalse( Session::simulate( ...$args ), (string) $index );
		}
	}
	public function test_idle_session_metadata_is_exact_and_never_calls_or_coerces_inputs() {
		foreach ( array( null, false, new stdClass(), array(), $this->facts() + array( 'reader' => 'get_option' ), array( 'session_id' => $this->scope['session_id'], 'autocommit' => true ), array( 'session_id' => $this->scope['session_id'], 'autocommit' => false, 'in_transaction' => false ), $this->facts( true ) ) as $facts ) {
			$s = $this->session(); $this->assertFalse( $s->step( 'open_idle', $facts, 100, 1000 ) ); $this->assertSame( 'poisoned', $s->phase() );
		}
		foreach ( array( null, true, new stdClass(), array(), 'reconnect' ) as $event ) $this->assertFalse( $this->session()->step( $event, $this->facts(), 100, 1000 ) );
	}
	public function test_close_with_commit_in_flight_cannot_clear_uncertainty() {
		$s = $this->session(); $this->locked( $s ); $this->step( $s, 'pair_verified' ); $this->step( $s, 'commit_sent' );
		$this->assertTrue( $this->step( $s, 'close', false ) ); $this->assertTrue( $s->commit_unknown() );
		$this->assertFalse( $this->step( $s, 'commit_confirmed', false ) ); $this->assertTrue( $s->matches_command( $this->real_id( 90 ) ) );
	}
	public function test_restoration_is_refused_and_contracts_expose_no_native_fact_accessors() {
		foreach ( array( $this->observation(), $this->session() ) as $object ) {
			$class = get_class( $object ); $this->assertSame( array(), get_object_vars( $object ) );
			try { unserialize( 'O:' . strlen( $class ) . ':"' . $class . '":0:{}' ); $this->fail( 'Restoration must refuse.' ); }
			catch ( LogicException $expected ) { $this->assertSame( 'Closed contract data cannot be transferred.', $expected->getMessage() ); }
			foreach ( array( 'query', 'connect', 'reader', 'transaction', 'binding', 'commit', 'execute', 'grant' ) as $method ) $this->assertFalse( method_exists( $object, $method ) );
		}
	}
}
