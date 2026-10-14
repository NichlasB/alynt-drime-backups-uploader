<?php
/** Explicit test-only loading; no site bootstrap, SQL, real user or nonce. */
require_once dirname( __DIR__, 2 ) . '/includes/trait-consent-request-validation.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-consent-request-adapter.php';
require_once __DIR__ . '/ConsentRequestPorts.php';

trait Alynt_Test_Consent_Request_Fixture {
	private $ports;
	private $adapter;
	protected function setUp(): void { parent::setUp(); $this->ports = new Alynt_Test_Consent_Request_Ports(); $this->adapter = $this->adapter(); }
	private function adapter( $enabled = true, $ports = null, $site = null ) { return new Alynt_Drime_Backups_Uploader_Consent_Request_Adapter( $ports ?? $this->ports->ports(), $site ?? $this->ports->site, $enabled ); }
	private function issuance( $purpose = 'grant' ) {
		return array( 'purpose' => $purpose, 'confirm' => 'grant' === $purpose ? 'prepare' : 'close', 'nonce' => $this->ports->create_nonce( 'alynt_drime_backups_consent_fixture_issue_v1:' . $purpose . ':' . $this->ports->site ) );
	}
	private function issued( $purpose = 'grant' ) { $result = $this->adapter->issue( 'POST', $this->issuance( $purpose ) ); $this->assertIsArray( $result ); return $result; }
	private function body( $issued = null ) {
		$issued = $issued ?? $this->issued(); $body = array_map( function ( $value ) { return (string) $value; }, $issued['envelope'] ); $body['nonce'] = $issued['nonce']; return $body;
	}
	private function observation( $id ) { return array( 'command_id' => $id, 'nonce' => $this->ports->create_nonce( 'alynt_drime_backups_consent_fixture_receipt_v1:' . $this->ports->site . ':' . $id ) ); }
	private function error( $suffix, $result ) { $this->assertInstanceOf( WP_Error::class, $result ); $this->assertSame( 'consent_request_' . $suffix, $result->get_error_code() ); $this->assertSame( 'Consent request fixture is unavailable.', $result->get_error_message() ); }
	private function outcome( $outcome, $result ) { $this->assertSame( array( 'outcome' => $outcome, 'source' => 'fixture_request_v1', 'dispatch_supported' => false, 'execution_supported' => false ), $result ); }
	private function no_sensitive_calls( $expected_writes = 0 ) { foreach ( array( 'context', 'consume', 'observe', 'mint', 'uuid' ) as $key ) $this->assertSame( 0, $this->ports->calls[$key] ?? 0, $key ); $this->assertSame( $expected_writes, $this->ports->writes ); }
}
