<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ConsentRequestFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ConsentRequestEnvelopeTest extends TestCase {
	use Alynt_Test_Consent_Request_Fixture;
	public function test_server_issuance_is_fixed_scope_clock_epoch_purpose_and_dormant() {
		$i = $this->issued(); $c = $i['envelope']; $this->assertSame( $this->ports->site, $c['site_uuid'] ); $this->assertSame( $this->ports->binding, $c['enrollment_binding'] );
		$this->assertSame( 1, $c['expected_generation'] ); $this->assertSame( $this->ports->now, $c['issued_at'] ); $this->assertSame( $this->ports->now + 300, $c['expires_at'] );
		$this->assertSame( 'fixture_request_v1', $c['contract'] ); $this->assertFalse( $i['dispatch_supported'] ); $this->assertFalse( $i['execution_supported'] ); $this->assertSame( 0, $this->ports->writes );
	}
	/** @dataProvider tampered_fields */
	public function test_every_echoed_canonical_field_is_nonce_bound( $field, $value ) {
		$b = $this->body(); $b[$field] = $value; $this->ports->calls = array(); $result = $this->adapter->consume( 'POST', $b );
		$this->assertInstanceOf( WP_Error::class, $result ); $this->no_sensitive_calls();
	}
	public function tampered_fields() { return array(
		array( 'contract', 'authenticated_local_admin' ), array( 'command_id', '33333333-3333-4333-8333-333333333333' ), array( 'purpose', 'rollback' ), array( 'purpose', 'revoke' ), array( 'confirm', 'true' ),
		array( 'site_uuid', '33333333-3333-4333-8333-333333333333' ), array( 'enrollment_binding', str_repeat( 'c', 64 ) ), array( 'expected_generation', '2' ), array( 'issued_at', '1800000001' ), array( 'expires_at', '1800000299' ), array( 'replacement_binding', str_repeat( 'c', 64 ) ), array( 'nonce', 'forged' )
	); }
	public function test_purpose_tamper_with_valid_shape_and_session_change_reject_before_sink() {
		$b = $this->body(); $changed = $b; $changed['purpose'] = 'revoke'; $changed['confirm'] = 'close'; $this->ports->calls = array();
		$this->error( 'denied', $this->adapter->consume( 'POST', $changed ) ); $this->no_sensitive_calls(); $this->ports->session = 'fixture-session-b';
		$this->error( 'denied', $this->adapter->consume( 'POST', $b ) ); $this->no_sensitive_calls();
	}
	/** @dataProvider bad_numbers */
	public function test_noncanonical_form_integers_reject_without_ports( $value ) {
		$b = $this->body(); $b['expected_generation'] = $value; $this->ports->calls = array(); $this->error( 'invalid', $this->adapter->consume( 'POST', $b ) ); $this->assertSame( array(), $this->ports->calls );
	}
	public function bad_numbers() { return array( array( '01' ), array( '-1' ), array( '+1' ), array( '1.0' ), array( '1e0' ), array( ' 1' ), array( '' ), array( (string) PHP_INT_MAX ), array( '999999999999999999999999999999' ), array( 1 ), array( true ), array( array( '1' ) ) ); }
	public function test_oversize_nested_extra_missing_and_invalid_utf8_inputs_do_no_work() {
		$b = $this->body(); $cases = array( array_merge( $b, array( 'actor' => '1' ) ), array_merge( $b, array( 'trusted' => 'true' ) ), array_diff_key( $b, array( 'nonce' => 1 ) ), array_replace( $b, array( 'nonce' => str_repeat( 'x', 2049 ) ) ), array_replace( $b, array( 'nonce' => "\xff" ) ), array_replace( $b, array( 'nonce' => array( 'forged' ) ) ) );
		$this->ports->calls = array(); foreach ( $cases as $bad ) $this->error( 'invalid', $this->adapter->consume( 'POST', $bad ) ); $this->assertSame( array(), $this->ports->calls );
		$raw = $this->issuance(); $raw['purpose'] = str_repeat( '\\', 2000 ); $raw['confirm'] = str_repeat( '\\', 2000 ); $this->ports->calls = array(); $this->error( 'invalid', $this->adapter->issue( 'POST', $raw ) ); $this->assertSame( array(), $this->ports->calls );
	}
	public function test_nonce_success_code_two_and_reordered_slashed_form_are_canonical() {
		$b = $this->body(); $this->ports->nonce_result = 2; $this->outcome( 'committed', $this->adapter->consume( 'POST', array_reverse( array_map( 'addslashes', $b ), true ) ) );
		$this->outcome( 'observed_current', $this->adapter->consume( 'POST', $b ) ); $this->assertSame( 1, $this->ports->writes ); $this->assertArrayNotHasKey( 'nonce', $this->ports->last_command );
	}
}
