<?php
/** Strict real model logic over the existing in-memory fixture CAS only. */
require_once __DIR__ . '/ConsentLedgerFixture.php';
require_once dirname( __DIR__, 2 ) . '/includes/trait-closed-consent-provenance-validation.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-closed-consent-provenance-store.php';

trait Alynt_Test_Closed_Consent_Provenance_Fixture {
	use Alynt_Test_Consent_Ledger_Fixture { setUp as protected setUpLedger; }
	private $closed;
	protected function setUp(): void { $this->setUpLedger(); $this->closed = $this->new_closed(); }
	private function new_closed( $enabled = true, $site = null ) { return new Alynt_Drime_Backups_Uploader_Closed_Consent_Provenance_Store( $this->backend, $site ?? $this->site, function () { return $this->now; }, $enabled ); }
	private function origin() { return json_decode( $this->backend->rows[Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION]['option_value'], true ); }
	private function close_origin( $guard = null ) { return $this->closed->prepare_closed( $this->binding, $this->origin()['lifecycle']['permission_generation'], $guard ?? function () { return true; } ); }
	private function provenance_error( $code, $result ) { $this->assertInstanceOf( WP_Error::class, $result ); $this->assertSame( 'schedule_safety_provenance_' . $code, $result->get_error_code() ); $this->assertSame( 'Schedule safety operation is unavailable.', $result->get_error_message() ); }
	private function closed_result( $state, $old ) {
		$this->assertIsArray( $state ); $this->assertSame( 5, $state['schema_version'] ); $this->assertFalse( $state['lifecycle']['consent'] ); $this->assertSame( 'disabled', $state['lifecycle']['phase'] ); $this->assertFalse( $state['authority']['enabled'] );
		$this->assertNull( $state['provenance']['authenticated_grant'] ); $this->assertSame( array(), $state['provenance']['authenticated_receipts'] ); $this->assertSame( 'closed_migration_fixture_v1', $state['provenance']['source'] );
		foreach ( array( 'generation', 'schedule_revision' ) as $key ) $this->assertSame( $old[$key] + 1, $state[$key] ); $this->assertSame( $old['lifecycle']['permission_generation'] + 1, $state['lifecycle']['permission_generation'] );
		foreach ( array( 'authority', 'claims', 'dispatch', 'enrollment_binding' ) as $key ) $this->assertSame( $old[$key], $state[$key] ); $this->assertSame( $old['lifecycle'], $state['provenance']['legacy']['lifecycle'] ); $this->assertSame( $old['ledger'] ?? null, $state['provenance']['legacy']['ledger'] );
	}
}
