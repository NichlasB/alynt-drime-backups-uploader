<?php
/** Closed-only in-memory model; no authorization, SQL or secondary writer. */
require_once __DIR__ . '/ClosedConsentProvenanceFixture.php';
foreach ( array( 'records', 'validation', 'transitions', 'commands' ) as $part ) require_once dirname( __DIR__, 2 ) . '/includes/trait-closed-writer-barrier-' . $part . '.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-closed-writer-barrier-store.php';

trait Alynt_Test_Closed_Writer_Barrier_Fixture {
	use Alynt_Test_Closed_Consent_Provenance_Fixture { setUp as protected setUpClosed; }
	private $barrier;
	private $checkpoint;
	protected function setUp(): void { $this->setUpClosed(); $this->checkpoint = $this->close_origin(); $this->assertIsArray( $this->checkpoint ); $this->barrier = $this->new_barrier(); }
	private function new_barrier( $enabled = true, $site = null ) { return new Alynt_Drime_Backups_Uploader_Closed_Writer_Barrier_Store( $this->backend, $site ?? $this->site, function () { return $this->now; }, $enabled ); }
	private function upgrade( $guard = null ) { return $this->barrier->prepare_closed( $this->binding, $this->checkpoint['lifecycle']['permission_generation'], $guard ?? function () { return true; } ); }
	private function begin_command( $number = 1 ) {
		return array( 'command_id' => sprintf( '55555555-5555-4555-8555-%012d', $number ), 'site_uuid' => $this->site, 'enrollment_binding' => $this->binding, 'expected_generation' => $this->origin()['lifecycle']['permission_generation'],
			'kind' => 'settings_patch', 'target' => 'settings', 'fields' => array( 'auto_scan_enabled' ), 'before_hash' => str_repeat( 'c', 64 ), 'after_hash' => str_repeat( 'd', 64 ), 'issued_at' => $this->now, 'expires_at' => $this->now + 300 );
	}
	private function terminal_command( array $begin, $number = 2 ) {
		return array( 'command_id' => sprintf( '55555555-5555-4555-8555-%012d', $number ), 'operation_id' => $begin['command_id'], 'site_uuid' => $this->site, 'enrollment_binding' => $this->binding,
			'expected_generation' => $begin['expected_generation'] + 1, 'closure_generation' => $begin['expected_generation'] + 1, 'observed_hash' => $begin['after_hash'], 'issued_at' => $begin['issued_at'], 'expires_at' => $begin['expires_at'] );
	}
	private function barrier_error( $suffix, $value ) { $this->assertInstanceOf( WP_Error::class, $value ); $this->assertSame( 'schedule_safety_barrier_' . $suffix, $value->get_error_code() ); $this->assertSame( 'Closed writer fixture is unavailable.', $value->get_error_message() ); }
	private function barrier_result( $outcome, $value ) { $this->assertSame( array( 'outcome' => $outcome, 'source' => 'closed_writer_fixture_v1', 'dispatch_supported' => false, 'execution_supported' => false ), $value ); }
	private function permitted() { return function () { return true; }; }
	private function schema_three() {
		$s = $this->checkpoint; $a = $s['provenance']['legacy']; unset( $s['provenance'] ); foreach ( array( 'schema_version', 'generation', 'schedule_revision', 'lifecycle', 'enrollment_binding' ) as $key ) $s[$key] = $a[$key]; return $s;
	}
	private function assert_closed_barrier() {
		$s = $this->barrier->snapshot(); $this->assertIsArray( $s ); $this->assertSame( 6, $s['schema_version'] ); $this->assertFalse( $s['lifecycle']['consent'] ); $this->assertFalse( $s['authority']['enabled'] );
		$this->assertNull( $s['barrier']['authenticated_grant'] ); $this->assertSame( array(), $s['barrier']['authenticated_receipts'] ); $this->assertSame( $this->checkpoint, $s['barrier']['checkpoint'] );
		foreach ( array( 'enrollment_binding', 'authority', 'claims', 'dispatch' ) as $key ) $this->assertSame( $this->checkpoint[$key], $s[$key] ); return $s;
	}
}
