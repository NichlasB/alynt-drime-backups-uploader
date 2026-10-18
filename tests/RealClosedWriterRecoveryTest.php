<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RealClosedWriterFixture.php';

class RealClosedWriterRecoveryTest extends TestCase {
	use Alynt_Test_Real_Closed_Writer_Fixture;
	public function test_context_fences_site_blog_autoload_exact_bytes_epoch_and_source() {
		$s = $this->real_upgraded(); $e = $this->real_command( $s, 'begin_disable', 2 );
		$this->assertFalse( $this->candidate( $s, $e, null, $this->real_id( 3 ), true, 2 ) );
		$this->assertFalse( $this->candidate( $s, $e, null, $this->real_id( 3 ), true, 1, 'off' ) );
		$this->assertFalse( $this->candidate( $s, $e, null, $this->real_id( 3 ), true, 1, 'yes' ) );
		$this->assertFalse( $this->candidate( $s, $e, str_replace( 'private-test-token', 'private-test-other', $this->settings ), $this->real_id( 3 ) ) );
		list( $p, $begin ) = $this->real_pending( $s );
		$this->assertFalse( $this->real_complete( $p, $begin, null, 2 ) );
		$this->assertFalse( $this->real_complete( $p, $begin, $this->settings . 'x' ) );
		$this->assertFalse( $this->candidate( $p, $e, null, $this->real_id( 4 ) ) );
		$this->assertFalse( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::context( $s, $this->settings, $this->site, '1', 'no' ) );
	}
	public function test_pending_is_not_a_noop_even_if_target_is_already_disabled() {
		$s = $this->real_upgraded(); $raw = str_replace( 's:17:"auto_scan_enabled";b:1;', 's:17:"auto_scan_enabled";b:0;', $this->settings );
		$e = $this->real_command( $s, 'begin_disable', 2, $raw ); $p = $this->candidate( $s, $e, $raw );
		$this->assertSame( $s, $p['state'] ); $this->assertNull( $p['target'] );
		list( $pending, $begin ) = $this->real_pending( $s ); $e = $this->real_command( $pending, 'begin_disable', 4, $raw );
		$this->assertFalse( $this->candidate( $pending, $e, $raw ) ); $this->assertFalse( $this->real_complete( $pending, $begin, $raw ) );
	}
	public function test_abandon_uses_fresh_observation_without_target_candidate_and_reserves_unused_id() {
		list( $s, $begin ) = $this->real_pending(); $e = $this->real_command( $s, 'abandon_closed', 4 ); $p = $this->candidate( $s, $e );
		$this->assertIsArray( $p ); $this->assertNull( $p['target'] ); $this->assertNull( $p['state']['writer']['pending'] );
		$this->assertSame( 'authenticated_closed_writer_v1', $p['state']['writer']['completion']['domain'] ); $this->assertTrue( $this->valid_real( $p['state'] ) );
		$this->assertFalse( $this->real_complete( $p['state'], $begin ) );
		$next = $this->real_command( $p['state'], 'begin_disable', 5 ); $this->assertFalse( $this->candidate( $p['state'], $next, null, $this->real_id( 3 ) ) );
		$next['command_id'] = $this->real_id( 3 ); $this->assertFalse( $this->candidate( $p['state'], $next, null, $this->real_id( 6 ) ) );
		$this->assertSame( 'domain_conflict', Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::lookup( $p['state'], $this->real_id( 3 ), $this->site ) );
	}
	public function test_two_cycles_move_history_to_correct_maps_and_stale_completion_cannot_use_aba_bytes() {
		list( $s, $first ) = $this->real_pending(); $s = $this->real_complete( $s, $first )['state'];
		list( $s, $second ) = $this->real_pending( $s, 4 );
		$this->assertCount( 1, $s['writer']['authenticated_receipts'] ); $this->assertCount( 1, $s['writer']['internal_receipts'] );
		$this->assertFalse( $this->real_complete( $s, $first ) ); $p = $this->real_complete( $s, $second ); $this->assertIsArray( $p );
		$this->assertSame( 'observed_superseded', Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::lookup( $p['state'], $first['command_id'], $this->site ) );
		$wrong = $s; $r = reset( $wrong['writer']['internal_receipts'] ); $wrong['writer']['authenticated_receipts'][$r['command']['command_id']] = $r; $wrong['writer']['internal_receipts'] = array(); $this->assertFalse( $this->valid_real( $wrong ) );
		$wrong = $s; $wrong['writer']['internal_receipts'][$s['writer']['closure']['command']['command_id']] = $s['writer']['closure']; $this->assertFalse( $this->valid_real( $wrong ) );
	}
	public function test_expiry_and_clock_regression_never_clear_or_renew_pending() {
		list( $s, $e ) = $this->real_pending(); $before = $s; $this->now = $e['expires_at'];
		$this->assertFalse( $this->real_complete( $s, $e ) ); $this->assertSame( $before, $s );
		$fresh = $this->real_command( $s, 'abandon_closed', 4 ); $this->assertIsArray( $this->candidate( $s, $fresh ) );
		$this->now = $e['issued_at'] - 1; $this->assertFalse( $this->real_complete( $s, $e ) );
		$this->now = $e['expires_at'] - 1; $this->assertIsArray( $this->real_complete( $s, $e ) );
	}
	public function test_ids_and_command_roles_fail_closed() {
		$s = $this->real_upgraded(); $e = $this->real_command( $s, 'begin_disable', 2 );
		foreach ( array( null, $e['command_id'], $this->real_id( 1 ), 'bad', array() ) as $id ) $this->assertFalse( $this->candidate( $s, $e, null, $id ) );
		$e = array( 'contract' => 'closed_writer_request_v1', 'purpose' => 'receipt_read', 'command_id' => $this->real_id( 7 ), 'site_uuid' => $this->site, 'observed_command_id' => $this->real_id( 1 ), 'issued_at' => $this->now, 'expires_at' => $this->now + 300, 'confirm' => 'observe' );
		$this->assertFalse( $this->candidate( $s, $e ) ); $this->assertSame( 'unavailable', Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::lookup( array(), $this->real_id( 1 ), $this->site ) );
	}
}
