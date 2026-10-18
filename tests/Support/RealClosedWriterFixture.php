<?php
/** Pure data fixtures: no real identity, request, admission handle or storage. */
require_once __DIR__ . '/ClosedWriterBarrierFixture.php';
foreach ( array( 'contract', 'snapshot', 'projection' ) as $part ) require_once dirname( __DIR__, 2 ) . '/includes/class-closed-writer-admission-' . $part . '.php';

trait Alynt_Test_Real_Closed_Writer_Fixture {
	use Alynt_Test_Closed_Writer_Barrier_Fixture { setUp as protected setUpBarrier; }
	private $six;
	private $settings;
	protected function setUp(): void {
		$this->setUpBarrier(); $this->assertIsArray( $this->upgrade() ); $this->six = $this->origin();
		foreach ( array( 'serialized-settings-parser', 'serialized-settings-target', 'real-closed-writer-data', 'real-closed-writer-model' ) as $part ) {
			$file = dirname( __DIR__, 2 ) . '/includes/class-' . $part . '.php'; $this->assertFileExists( $file ); require_once $file;
		}
		$this->settings = serialize( array( 'site_uuid' => $this->site, 'auto_scan_enabled' => true, 'unknown' => array( "ø\0b:1;", 'private-test-token' ) ) );
	}
	private function real_command( $s, $purpose = 'upgrade_closed', $n = 1, $raw = null, $blog = 1 ) {
		$e = array( 'contract' => 'closed_writer_request_v1', 'purpose' => $purpose, 'command_id' => $this->real_id( $n ), 'site_uuid' => $this->site, 'enrollment_binding' => $s['enrollment_binding'],
			'expected_generation' => $s['lifecycle']['permission_generation'], 'issued_at' => $this->now, 'expires_at' => $this->now + 300, 'confirm' => array( 'upgrade_closed' => 'close', 'begin_disable' => 'disable', 'abandon_closed' => 'abandon' )[$purpose] );
		if ( 'upgrade_closed' === $purpose ) $e['origin_digest'] = Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::hash( $s );
		else {
			$c = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::context( $s, $raw ?? $this->settings, $this->site, $blog, 'no' ); $this->assertIsArray( $c ); $e['context_digest'] = $c['context_digest'];
			if ( 'begin_disable' === $purpose ) { $e['before_hash'] = $c['before_hash']; $e['after_hash'] = $c['after_hash']; }
			else { $e['operation_id'] = $s['writer']['pending']['operation_id']; $e['closure_generation'] = $s['writer']['pending']['closure_generation']; $e['observed_hash'] = $c['before_hash']; }
		}
		return $e;
	}
	private function real_id( $n ) { return sprintf( '88888888-8888-4888-8888-%012d', $n ); }
	private function candidate( $s, $e, $raw = null, $reserved = null, $enabled = true, $blog = 1, $autoload = 'no' ) {
		return Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::plan( $s, $e, $raw ?? $this->settings, $this->now, $blog, $autoload, $reserved, $enabled );
	}
	private function real_upgraded() { $p = $this->candidate( $this->six, $this->real_command( $this->six ) ); $this->assertIsArray( $p ); return $p['state']; }
	private function real_pending( $s = null, $n = 2 ) { $s = $s ?? $this->real_upgraded(); $e = $this->real_command( $s, 'begin_disable', $n ); $p = $this->candidate( $s, $e, null, $this->real_id( $n + 1 ) ); $this->assertIsArray( $p ); return array( $p['state'], $e ); }
	private function real_complete( $s, $e, $raw = null, $blog = 1 ) { return Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::completion( $s, $e, $raw ?? $this->settings, $this->now, $blog, 'no', true ); }
	private function valid_real( $s ) { return Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::valid( $s, $this->site ); }
}
