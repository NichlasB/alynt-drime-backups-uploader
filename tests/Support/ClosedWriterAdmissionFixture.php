<?php
/** Loads dormant candidate definitions only in tests; all effects stay in memory. */
require_once __DIR__ . '/ClosedWriterBarrierFixture.php';
foreach ( array( 'records', 'validation', 'request' ) as $part ) require_once dirname( __DIR__, 2 ) . '/includes/trait-closed-writer-admission-' . $part . '.php';
foreach ( array( 'contract', 'snapshot', 'projection', 'model', 'continuation', 'boundary' ) as $part ) require_once dirname( __DIR__, 2 ) . '/includes/class-closed-writer-admission-' . $part . '.php';
require_once __DIR__ . '/ClosedWriterAdmissionMemory.php';

trait Alynt_Test_Closed_Writer_Admission_Fixture {
	use Alynt_Test_Closed_Writer_Barrier_Fixture { setUp as protected setUpBarrier; }
	private $memory;
	private $admission;
	private $six;
	protected function setUp(): void {
		$this->setUpBarrier(); $this->assertIsArray( $this->upgrade() ); $this->six = $this->origin();
		$this->memory = new Alynt_Test_Closed_Writer_Admission_Memory( $this->six, $this->site );
		$this->admission = $this->new_admission();
	}
	private function new_admission( $enabled = true, $site = null, $ports = null ) {
		return new Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Boundary( $ports ?? $this->memory->ports(), $site ?? $this->site, $this->memory->identity, $enabled );
	}
	private function issue_form( $purpose = 'upgrade_closed', $id = null ) {
		$confirm = array( 'upgrade_closed' => 'close', 'begin_disable' => 'disable', 'abandon_closed' => 'abandon', 'receipt_read' => 'observe' );
		$f = array( 'purpose' => $purpose, 'confirm' => $confirm[$purpose], 'nonce' => $this->memory->nonce( $this->admission->issue_action( $purpose ) ) );
		if ( 'abandon_closed' === $purpose ) $f['operation_id'] = $id;
		if ( 'receipt_read' === $purpose ) $f['observed_command_id'] = $id;
		$body = $this->admission->issue( 'POST', $f ); $this->assertIsArray( $body ); return $body;
	}
	private function upgraded_admission() {
		$form = $this->issue_form(); $this->assertIsArray( $this->admission->consume( 'POST', $form ) );
		$this->assertTrue( Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Model::valid( $this->memory->row['state'], $this->site ) ); return $form;
	}
	private function typed_form( array $form ) { return Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::parse( $form )['envelope']; }
	private function resign( array $form ) {
		$e = $form; unset( $e['nonce'] ); foreach ( array( 'expected_generation', 'closure_generation', 'issued_at', 'expires_at' ) as $key ) if ( isset( $e[$key] ) ) $e[$key] = (int) $e[$key];
		$form['nonce'] = $this->memory->nonce( Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::action( $e ) ); return $form;
	}
	private function unavailable( $value ) {
		$this->assertInstanceOf( WP_Error::class, $value ); $this->assertSame( 'closed_writer_admission_unavailable', $value->get_error_code() );
		$this->assertSame( 'Closed admission fixture is unavailable.', $value->get_error_message() );
	}
	private function assert_admission_closed() {
		$s = $this->memory->row['state']; $this->assertTrue( Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Model::valid( $s, $this->site ) );
		$this->assertFalse( $s['lifecycle']['consent'] ); $this->assertFalse( $s['authority']['enabled'] );
		$this->assertSame( $this->six['barrier'], $s['legacy_writer'] ); $this->assertNull( $s['writer']['authenticated_grant'] );
		$this->assertSame( array(), $s['writer']['authenticated_receipts'] ); $this->assertSame( array(), $s['writer']['internal_receipts'] ); return $s;
	}
}
