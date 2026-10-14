<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ConsentRequestFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ConsentRequestBoundaryTest extends TestCase {
	use Alynt_Test_Consent_Request_Fixture;
	/** @dataProvider disabled_modes */
	public function test_disabled_construction_and_every_operation_invoke_no_ports( $enabled ) {
		$a = $this->adapter( $enabled ); $this->assertSame( array(), $this->ports->calls );
		foreach ( array( 'issue', 'consume', 'reconcile' ) as $method ) $this->error( 'denied', $a->$method( 'POST', array() ) );
		$this->assertSame( array(), $this->ports->calls );
	}
	public function disabled_modes() { return array( array( false ), array( 1 ), array( 'true' ), array( null ) ); }
	public function test_default_and_invalid_port_or_scope_are_dormant() {
		$p = $this->ports->ports(); $default = new Alynt_Drime_Backups_Uploader_Consent_Request_Adapter( $p, $this->ports->site ); $this->error( 'denied', $default->issue( 'POST', array() ) );
		foreach ( array( array(), array_merge( $p, array( 'trusted' => true ) ), array_replace( $p, array( 'authorize' => true ) ) ) as $bad ) $this->error( 'denied', $this->adapter( true, $bad )->consume( 'POST', array() ) );
		$this->error( 'denied', $this->adapter( true, $p, 'invalid' )->reconcile( 'POST', array() ) ); $this->assertSame( array(), $this->ports->calls );
	}
	/** @dataProvider bad_methods */
	public function test_non_post_never_reads_or_mints( $method ) {
		foreach ( array( 'issue', 'consume', 'reconcile' ) as $api ) $this->error( 'denied', $this->adapter->$api( $method, array() ) ); $this->assertSame( array(), $this->ports->calls );
	}
	public function bad_methods() { return array( array( 'GET' ), array( 'post' ), array( 'PUT' ), array( '' ), array( null ) ); }
	/** @dataProvider denied_auth */
	public function test_current_auth_required_before_sensitive_work_in_all_operations( $field, $value ) {
		$b = $this->body(); $i = $this->issuance(); $r = $this->observation( $b['command_id'] ); $this->ports->$field = $value; $this->ports->calls = array();
		$this->error( 'denied', $this->adapter->issue( 'POST', $i ) ); $this->error( 'denied', $this->adapter->consume( 'POST', $b ) ); $this->error( 'denied', $this->adapter->reconcile( 'POST', $r ) ); $this->no_sensitive_calls();
	}
	public function denied_auth() { return array( array( 'logged_in', false ), array( 'capable', false ), array( 'single_site', false ), array( 'authorize_result', 1 ), array( 'authorize_result', 'yes' ) ); }
	public function test_issuance_nonce_required_and_purpose_domains_do_not_cross() {
		$i = $this->issuance(); $i['purpose'] = 'revoke'; $i['confirm'] = 'close'; $this->ports->calls = array();
		$this->error( 'denied', $this->adapter->issue( 'POST', $i ) ); $this->no_sensitive_calls();
	}
	public function test_authorization_lost_during_issuance_never_mints_command_nonce() {
		$i = $this->issuance(); $this->ports->calls = array(); $this->ports->on_context = function ( $p ) { $p->capable = false; };
		$this->error( 'denied', $this->adapter->issue( 'POST', $i ) ); $this->assertSame( 0, $this->ports->calls['mint'] ?? 0 ); $this->assertSame( 0, $this->ports->writes );
	}
	/** @dataProvider invalid_nonce_results */
	public function test_truthy_nonce_results_never_authorize( $value ) {
		$b = $this->body(); $this->ports->nonce_result = $value; $this->ports->calls = array(); $this->error( 'denied', $this->adapter->consume( 'POST', $b ) ); $this->no_sensitive_calls();
	}
	public function invalid_nonce_results() { return array( array( true ), array( '1' ), array( 3 ), array( 0 ) ); }
}
