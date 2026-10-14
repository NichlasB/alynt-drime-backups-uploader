<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ClosedConsentProvenanceFixture.php';
require_once __DIR__ . '/Support/ConsentRequestFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedConsentProvenanceRequestTest extends TestCase {
	use Alynt_Test_Closed_Consent_Provenance_Fixture;
	private function request_fixture() {
		$p = new Alynt_Test_Consent_Request_Ports(); $ports = $p->ports();
		$ports['context'] = function () { $s = $this->closed->snapshot(); return is_wp_error( $s ) ? $s : array( 'site_uuid' => $this->site, 'binding' => $s['enrollment_binding'], 'generation' => $s['lifecycle']['permission_generation'], 'eligible' => false, 'replacement_binding' => null ); };
		$ports['consume'] = function ( $c, $guard ) { return $this->closed->command( $c, $guard ); };
		$ports['observe'] = function ( $site, $id ) { $result = $this->closed->receipt( 'fixture_request_v1', $id ); return is_wp_error( $result ) ? $result : $result['outcome']; };
		return array( $p, new Alynt_Drime_Backups_Uploader_Consent_Request_Adapter( $ports, $this->site, true ) );
	}
	private function issue_request( $p, $adapter, $purpose ) { return $adapter->issue( 'POST', array( 'purpose' => $purpose, 'confirm' => 'grant' === $purpose ? 'prepare' : 'close', 'nonce' => $p->create_nonce( 'alynt_drime_backups_consent_fixture_issue_v1:' . $purpose . ':' . $this->site ) ) ); }
	public function test_request_grant_never_issues_and_valid_closure_never_executes_or_migrates() {
		$this->close_origin(); list( $p, $adapter ) = $this->request_fixture(); $rows = $this->backend->rows; $swaps = $this->backend->swaps;
		$this->assertInstanceOf( WP_Error::class, $this->issue_request( $p, $adapter, 'grant' ) ); $i = $this->issue_request( $p, $adapter, 'revoke' ); $this->assertIsArray( $i );
		$body = array_map( 'strval', $i['envelope'] ); $body['nonce'] = $i['nonce']; $result = $adapter->consume( 'POST', $body ); $this->assertInstanceOf( WP_Error::class, $result ); $this->assertSame( 'consent_request_unresolved', $result->get_error_code() );
		$this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $swaps, $this->backend->swaps ); $this->assertNull( $this->closed->snapshot()['provenance']['authenticated_grant'] );
	}
	public function test_authorized_receipt_request_cannot_relabel_legacy_grant() {
		$this->migrate(); $c = $this->intent(); $this->ledger->command( $c ); $this->close_origin(); list( $p, $adapter ) = $this->request_fixture(); $rows = $this->backend->rows;
		$b = array( 'command_id' => $c['command_id'], 'nonce' => $p->create_nonce( 'alynt_drime_backups_consent_fixture_receipt_v1:' . $this->site . ':' . $c['command_id'] ) ); $r = $adapter->reconcile( 'POST', $b );
		$this->assertInstanceOf( WP_Error::class, $r ); $this->assertSame( 'consent_request_unresolved', $r->get_error_code() ); $this->assertSame( $rows, $this->backend->rows );
		$b['command_id'] = '99999999-9999-4999-8999-999999999999'; $b['nonce'] = $p->create_nonce( 'alynt_drime_backups_consent_fixture_receipt_v1:' . $this->site . ':' . $b['command_id'] );
		$this->assertSame( array( 'outcome' => 'not_found', 'source' => 'fixture_request_v1', 'dispatch_supported' => false, 'execution_supported' => false ), $adapter->reconcile( 'POST', $b ) );
	}
	public function test_denied_request_never_reads_successor_or_prepares_missing_storage() {
		list( $p, $adapter ) = $this->request_fixture(); $p->logged_in = false; $reads = $this->backend->reads; $rows = $this->backend->rows;
		$this->assertInstanceOf( WP_Error::class, $this->issue_request( $p, $adapter, 'revoke' ) ); $this->assertSame( $reads, $this->backend->reads ); $this->assertSame( $rows, $this->backend->rows );
		$p->logged_in = true; $this->assertInstanceOf( WP_Error::class, $this->issue_request( $p, $adapter, 'revoke' ) ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( 3, $this->origin()['schema_version'] );
	}
}
