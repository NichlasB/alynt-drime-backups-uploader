<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ConsentRequestFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ConsentRequestFailureTest extends TestCase {
	use Alynt_Test_Consent_Request_Fixture;
	/** @dataProvider invalid_clocks */
	public function test_bad_clock_issuance_or_consumption_never_writes( $now ) {
		$b = $this->body(); $i = $this->issuance(); $this->ports->now = $now;
		$this->error( 'context', $this->adapter->issue( 'POST', $i ) ); $this->error( 'denied', $this->adapter->consume( 'POST', $b ) ); $this->assertSame( 0, $this->ports->writes );
	}
	public function invalid_clocks() { return array( array( 0 ), array( -1 ), array( '1800000000' ), array( 1800000000.0 ), array( PHP_INT_MAX ), array( null ) ); }
	/** @dataProvider denied_times */
	public function test_future_and_exact_expiry_reject_before_sink_even_with_valid_nonce( $delta ) {
		$b = $this->body(); $this->ports->now += $delta; $this->ports->calls = array(); $this->error( 'denied', $this->adapter->consume( 'POST', $b ) ); $this->no_sensitive_calls();
	}
	public function denied_times() { return array( array( -1 ), array( 300 ), array( 301 ) ); }
	public function test_zero_generation_and_php_integer_boundary_parse_without_wrap() {
		$this->ports->generation = 0; $b = $this->body(); $this->outcome( 'committed', $this->adapter->consume( 'POST', $b ) ); $this->assertSame( 1, $this->ports->generation );
		$this->ports->generation = PHP_INT_MAX - 3600; $b = $this->body(); $this->outcome( 'committed', $this->adapter->consume( 'POST', $b ) );
	}
	/** @dataProvider malformed_contexts */
	public function test_invalid_context_is_not_normalized_or_minted_as_intent( $key, $value ) {
		$p = $this->ports->ports(); $p['context'] = function () use ( $key, $value ) { $ctx = $this->ports->context(); $ctx[$key] = $value; return $ctx; }; $i = $this->issuance(); $this->ports->calls = array();
		$this->error( 'context', $this->adapter( true, $p )->issue( 'POST', $i ) ); $this->assertSame( 0, $this->ports->calls['mint'] ?? 0 ); $this->assertSame( 0, $this->ports->writes );
	}
	public function malformed_contexts() { return array( array( 'site_uuid', 'other-site' ), array( 'generation', '1' ), array( 'generation', -1 ), array( 'eligible', 1 ), array( 'binding', 'bad' ), array( 'replacement_binding', str_repeat( 'a', 64 ) ), array( 'trusted', true ) ); }
	/** @dataProvider throwing_ports */
	public function test_port_exceptions_are_redacted_without_implicit_retry( $port, $operation ) {
		$b = 'issue' === $operation ? $this->issuance() : ( 'consume' === $operation ? $this->body() : $this->observation( '99999999-9999-4999-8999-999999999999' ) );
		$p = $this->ports->ports(); $p[$port] = function () { throw new RuntimeException( 'secret cookie, database, token, path' ); }; $a = $this->adapter( true, $p );
		$this->error( 'unresolved', $a->$operation( 'POST', $b ) ); $this->assertSame( 0, $this->ports->writes );
	}
	public function throwing_ports() { return array( array( 'authorize', 'issue' ), array( 'verify_nonce', 'consume' ), array( 'create_nonce', 'issue' ), array( 'context', 'issue' ), array( 'clock', 'consume' ), array( 'uuid', 'issue' ), array( 'consume', 'consume' ), array( 'observe', 'reconcile' ) ); }
	public function test_private_port_results_are_not_forwarded_or_claimed_as_permissions() {
		$b = $this->body(); $p = $this->ports->ports();
		foreach ( array( true, array( 'outcome' => 'committed', 'secret' => 'token' ), new WP_Error( 'private', 'secret' ), 'active', 'authenticated' ) as $bad ) {
			$p['consume'] = function () use ( $bad ) { return $bad; }; $this->error( 'unresolved', $this->adapter( true, $p )->consume( 'POST', $b ) );
		}
		$this->assertSame( 0, $this->ports->writes );
	}
}
