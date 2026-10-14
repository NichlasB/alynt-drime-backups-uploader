<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ConsentRequestFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ConsentRequestReplayTest extends TestCase {
	use Alynt_Test_Consent_Request_Fixture;
	public function test_duplicate_and_superseded_grants_never_write_again() {
		$b = $this->body(); $this->outcome( 'committed', $this->adapter->consume( 'POST', $b ) ); $this->outcome( 'observed_current', $this->adapter->consume( 'POST', array_reverse( $b, true ) ) );
		$close = $this->body( $this->issued( 'rebind' ) ); $this->outcome( 'committed', $this->adapter->consume( 'POST', $close ) ); $receipts = $this->ports->receipts;
		$this->outcome( 'observed_superseded', $this->adapter->consume( 'POST', $b ) ); $this->assertFalse( $this->ports->consent ); $this->assertSame( 2, $this->ports->writes ); $this->assertSame( $receipts, $this->ports->receipts );
	}
	public function test_expired_committed_request_requires_distinct_authenticated_read_not_renewal() {
		$b = $this->body(); $this->adapter->consume( 'POST', $b ); $this->ports->now += 300; $this->ports->calls = array();
		$this->error( 'denied', $this->adapter->consume( 'POST', $b ) ); $this->assertSame( 0, $this->ports->calls['consume'] ?? 0 );
		$this->ports->session = 'fixture-new-admin-session'; $r = $this->observation( $b['command_id'] ); $this->outcome( 'observed_current', $this->adapter->reconcile( 'POST', $r ) );
		$this->assertTrue( $this->ports->consent ); $this->assertSame( 1, $this->ports->writes ); $this->assertSame( 2, $this->ports->generation );
	}
	public function test_reconciliation_after_binding_change_is_observation_only_and_site_scoped() {
		$b = $this->body(); $this->adapter->consume( 'POST', $b ); $this->adapter->consume( 'POST', $this->body( $this->issued( 'rebind' ) ) );
		$state = $this->ports->receipts; $this->outcome( 'observed_superseded', $this->adapter->reconcile( 'POST', $this->observation( $b['command_id'] ) ) );
		$this->outcome( 'not_found', $this->adapter->reconcile( 'POST', $this->observation( '99999999-9999-4999-8999-999999999999' ) ) ); $this->assertSame( $state, $this->ports->receipts ); $this->assertSame( 2, $this->ports->writes );
		$r = $this->observation( $b['command_id'] ); $this->ports->calls = array(); $r['site_uuid'] = $this->ports->site; $this->error( 'invalid', $this->adapter->reconcile( 'POST', $r ) ); $this->assertSame( array(), $this->ports->calls );
	}
	public function test_command_and_read_nonces_cannot_cross_and_reads_never_accept_commit() {
		$b = $this->body(); $r = array( 'command_id' => $b['command_id'], 'nonce' => $b['nonce'] ); $this->ports->calls = array();
		$this->error( 'denied', $this->adapter->reconcile( 'POST', $r ) ); $this->no_sensitive_calls();
		$b['nonce'] = $this->observation( $b['command_id'] )['nonce']; $this->ports->calls = array(); $this->error( 'denied', $this->adapter->consume( 'POST', $b ) ); $this->no_sensitive_calls();
		$p = $this->ports->ports(); $p['observe'] = function () { return 'committed'; }; $this->error( 'unresolved', $this->adapter( true, $p )->reconcile( 'POST', $this->observation( $b['command_id'] ) ) );
	}
	/** @dataProvider ack_faults */
	public function test_ambiguous_commit_is_redacted_then_observed_without_retry_or_regrant( $fault ) {
		$b = $this->body(); $this->ports->fault = $fault; $this->error( 'unresolved', $this->adapter->consume( 'POST', $b ) ); $this->assertSame( 1, $this->ports->writes );
		$this->ports->fault = null; $this->outcome( 'observed_current', $this->adapter->reconcile( 'POST', $this->observation( $b['command_id'] ) ) ); $this->outcome( 'observed_current', $this->adapter->consume( 'POST', $b ) ); $this->assertSame( 1, $this->ports->writes );
	}
	public function ack_faults() { return array( array( 'throw' ), array( 'error' ) ); }
	public function test_authentication_required_even_for_known_duplicate_and_historical_read() {
		$b = $this->body(); $this->adapter->consume( 'POST', $b ); $r = $this->observation( $b['command_id'] ); $this->ports->logged_in = false; $this->ports->calls = array();
		$this->error( 'denied', $this->adapter->consume( 'POST', $b ) ); $this->error( 'denied', $this->adapter->reconcile( 'POST', $r ) ); $this->no_sensitive_calls( 1 ); $this->assertSame( 1, $this->ports->writes );
	}
}
