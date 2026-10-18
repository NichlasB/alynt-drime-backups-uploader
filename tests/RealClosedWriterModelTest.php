<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RealClosedWriterFixture.php';

class RealClosedWriterModelTest extends TestCase {
	use Alynt_Test_Real_Closed_Writer_Fixture;
	public function test_upgrade_is_a_non_admitting_candidate_preserving_complete_origin() {
		$e = $this->real_command( $this->six );
		$this->assertFalse( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::plan( $this->six, $e, $this->settings, $this->now, 1, 'no' ) );
		$p = $this->candidate( $this->six, $e ); $this->assertIsArray( $p );
		$this->assertTrue( $p['candidate_only'] ); foreach ( array( 'admission_proven', 'dispatch_supported', 'execution_supported' ) as $k ) $this->assertFalse( $p[$k] );
		$s = $p['state']; $this->assertSame( 8, $s['schema_version'] ); $this->assertTrue( $this->valid_real( $s ) );
		$this->assertSame( $this->six, Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::original( $s ) );
		$this->assertSame( 'authenticated_closed_writer_v1', $s['writer']['upgrade']['domain'] );
		$this->assertNull( $s['writer']['authenticated_grant'] ); $this->assertFalse( $s['authority']['enabled'] ); $this->assertFalse( $s['lifecycle']['consent'] );
		$this->assertSame( array(), $s['writer']['fixture_receipts'] ); $this->assertNull( $p['target'] );
	}
	public function test_begin_and_completion_derive_exact_bytes_and_preserve_original_window() {
		list( $s, $e ) = $this->real_pending(); $before = $s;
		$this->assertTrue( $this->valid_real( $s ) ); $this->assertSame( $this->real_id( 3 ), $s['writer']['pending']['completion_id'] );
		$p = $this->real_complete( $s, $e ); $this->assertIsArray( $p ); $t = unserialize( $this->settings ); $t['auto_scan_enabled'] = false;
		$this->assertSame( serialize( $t ), $p['target'] ); $this->assertSame( $before, $s ); $this->assertTrue( $this->valid_real( $p['state'] ) );
		$r = $p['state']['writer']['completion']; $this->assertSame( 'internal_closed_writer_v1', $r['domain'] ); $this->assertSame( 'complete_disable', $r['purpose'] );
		$this->assertSame( $e['issued_at'], $r['command']['issued_at'] ); $this->assertSame( $e['expires_at'], $r['command']['expires_at'] );
		$this->assertSame( $s['generation'] + 1, $p['state']['generation'] ); $this->assertNull( $p['state']['writer']['pending'] );
		$this->assertSame( 'observed_current', Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::lookup( $p['state'], $this->real_id( 3 ), $this->site ) );
	}
}
