<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ClosedConsentProvenanceFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedConsentProvenanceBoundaryTest extends TestCase {
	use Alynt_Test_Closed_Consent_Provenance_Fixture;
	public function test_legacy_receipts_are_superseded_and_cross_domain_identity_reuse_rejects() {
		$this->migrate(); $c = $this->intent(); $this->ledger->command( $c ); $this->ledger->command( $this->intent( 'revoke', 2 ) ); $this->close_origin(); $rows = $this->backend->rows;
		foreach ( array( $c['command_id'], $this->intent( 'revoke', 2, 1 )['command_id'] ) as $id ) {
			$this->assertSame( array( 'outcome' => 'observed_superseded', 'source' => 'fixture_intent_v1', 'dispatch_supported' => false, 'execution_supported' => false ), $this->closed->receipt( 'fixture_intent_v1', $id ) );
			$this->provenance_error( 'domain_conflict', $this->closed->receipt( 'fixture_request_v1', $id ) );
		}
		$this->assertSame( $rows, $this->backend->rows ); $this->assertSame( 'not_found', $this->closed->receipt( 'fixture_request_v1', '99999999-9999-4999-8999-999999999999' )['outcome'] );
	}
	public function test_all_commands_and_legacy_mutations_stay_zero_read_disabled() {
		$this->close_origin(); $reads = $this->backend->reads; $rows = $this->backend->rows; $calls = 0; $guard = function () use ( &$calls ) { $calls++; return true; };
		foreach ( array( 'grant', 'revoke', 'rebind', 'activate', 'rollback', 'authenticated_grant' ) as $purpose ) $this->provenance_error( 'commands_disabled', $this->closed->command( array( 'purpose' => $purpose ), $guard ) );
		$this->provenance_error( 'ledger_api_required', $this->closed->initialize( $this->binding ) ); $this->provenance_error( 'ledger_api_required', $this->closed->claim( $this->binding, 'x', array() ) ); $this->provenance_error( 'ledger_api_required', $this->closed->invalidate( $this->binding ) ); $this->provenance_error( 'ledger_api_required', $this->closed->transition( $this->binding, 'a', 'b', 'c', 'd', 'e' ) );
		foreach ( array( 'authenticated_local_admin', 'trusted', 'closed_migration_fixture_v1' ) as $domain ) $this->provenance_error( 'invalid', $this->closed->receipt( $domain, '22222222-2222-4222-8222-000000000001' ) );
		$this->assertSame( $reads, $this->backend->reads ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( 0, $calls );
	}
	public function test_closed_schema_cannot_be_blessed_with_grants_receipts_or_source_labels() {
		$s = $this->close_origin(); $cases = array();
		foreach ( array( 'authenticated_grant' => array( 'source' => 'authenticated_local_admin' ), 'authenticated_receipts' => array( 'fake' => true ), 'source' => 'authenticated_local_admin', 'site_uuid' => '99999999-9999-4999-8999-999999999999' ) as $key => $value ) { $bad = $s; $bad['provenance'][$key] = $value; $cases[] = $bad; }
		$bad = $s; $bad['lifecycle']['consent'] = true; $cases[] = $bad; $bad = $s; $bad['authority']['enabled'] = true; $cases[] = $bad; $bad = $s; $bad['lifecycle']['phase'] = 'active'; $cases[] = $bad;
		foreach ( $cases as $bad ) { $this->replace( $bad ); $rows = $this->backend->rows; $this->provenance_error( 'unavailable', $this->closed->snapshot() ); $this->provenance_error( 'unavailable', $this->close_origin() ); $this->assertSame( $rows, $this->backend->rows ); }
	}
	public function test_archival_tampering_and_schema_three_extra_ledger_fail_closed() {
		$s = $this->close_origin(); $cases = array(); $bad = $s; $bad['provenance']['legacy']['ledger'] = array(); $cases[] = $bad;
		$bad = $s; $bad['authority']['next_due_at']++; $cases[] = $bad; $bad = $s; $bad['provenance']['legacy']['digest'] = str_repeat( '0', 64 ); $cases[] = $bad;
		$bad = $s; $bad['provenance']['migration']['result_generation']++; $cases[] = $bad; $bad = $s; $bad['generation']++; $cases[] = $bad; $bad = $s; $bad['provenance']['legacy']['trusted'] = true; $cases[] = $bad;
		foreach ( $cases as $bad ) { $this->replace( $bad ); $this->provenance_error( 'unavailable', $this->closed->snapshot() ); }
	}
}
