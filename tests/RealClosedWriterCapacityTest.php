<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RealClosedWriterFixture.php';

class RealClosedWriterCapacityTest extends TestCase {
	use Alynt_Test_Real_Closed_Writer_Fixture;
	private function cycle( $s, $n, $complete ) {
		list( $s, $e ) = $this->real_pending( $s, $n );
		if ( $complete ) return $this->real_complete( $s, $e )['state'];
		$p = $this->candidate( $s, $this->real_command( $s, 'abandon_closed', $n + 2 ) ); $this->assertIsArray( $p ); return $p['state'];
	}
	public function test_fifty_combined_new_history_records_keep_terminal_room_without_pruning() {
		$s = $this->real_upgraded(); for ( $i = 0; $i < 26; ++$i ) $s = $this->cycle( $s, 2 + $i * 3, 0 === $i % 2 );
		$this->assertTrue( $this->valid_real( $s ) ); $this->assertSame( 50, count( $s['writer']['authenticated_receipts'] ) + count( $s['writer']['internal_receipts'] ) );
		$this->assertNotEmpty( $s['writer']['internal_receipts'] ); $this->assertLessThanOrEqual( 131072, strlen( json_encode( $s ) ) );
		foreach ( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::records( $s ) as $r ) $this->assertLessThanOrEqual( 1024, strlen( json_encode( $r ) ) );
		$before = $s; $this->assertFalse( $this->candidate( $s, $this->real_command( $s, 'begin_disable', 100 ), null, $this->real_id( 101 ) ) ); $this->assertSame( $before, $s );
		$bad = $s; $bad['writer']['authenticated_receipts'] = array(); $this->assertFalse( $this->valid_real( $bad ) );
	}
	public function test_legacy_and_new_maps_share_one_budget_and_legacy_ids_cannot_be_reused() {
		$this->replace( $this->schema_three() ); $this->migrate(); for ( $i = 1; $i <= 51; ++$i ) $this->ledger->command( $this->intent( 'revoke', $i ) );
		$this->checkpoint = $this->close_origin(); $this->assertIsArray( $this->upgrade() ); $this->six = $this->origin(); $s = $this->real_upgraded();
		$this->assertSame( 50, Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::legacy( $s )['count'] );
		$ids = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::legacy( $s )['ids']; $id = key( $ids ); $e = $this->real_command( $s, 'begin_disable', 2 );
		$this->assertFalse( $this->candidate( $s, $e, null, $id ) ); $e['command_id'] = $id; $this->assertFalse( $this->candidate( $s, $e, null, $this->real_id( 3 ) ) );
		$s = $this->cycle( $s, 2, false ); $this->assertTrue( $this->valid_real( $s ) ); $this->assertFalse( $this->candidate( $s, $this->real_command( $s, 'begin_disable', 5 ), null, $this->real_id( 6 ) ) );
	}
	public function test_full_width_counters_and_clocks_keep_receipts_bounded_and_terminal_reserve() {
		$old = $this->schema_three(); foreach ( array( 'generation', 'schedule_revision' ) as $k ) $old[$k] = PHP_INT_MAX - 7; $old['lifecycle']['permission_generation'] = PHP_INT_MAX - 7;
		$this->replace( $old ); $this->checkpoint = $this->close_origin(); $this->assertIsArray( $this->upgrade() ); $this->six = $this->origin(); $this->now = PHP_INT_MAX - 4000;
		list( $s, $e ) = $this->real_pending(); $this->assertLessThanOrEqual( 131072 - 4096, strlen( json_encode( $s ) ) );
		$p = $this->real_complete( $s, $e ); $this->assertIsArray( $p ); $s = $p['state']; $this->assertSame( PHP_INT_MAX - 2, $s['generation'] );
		foreach ( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::records( $s ) as $r ) $this->assertLessThanOrEqual( 1024, strlen( json_encode( $r ) ) );
		$this->assertFalse( $this->candidate( $s, $this->real_command( $s, 'begin_disable', 4 ), null, $this->real_id( 5 ) ) );
		$this->six['generation'] = PHP_INT_MAX; $this->assertFalse( $this->candidate( $this->six, $this->real_command( $this->six ) ) );
	}
	public function test_terminal_forgery_identity_reuse_gaps_and_cross_domain_relocation_are_rejected() {
		list( $pending, $e ) = $this->real_pending(); $good = $this->real_complete( $pending, $e )['state'];
		foreach ( array( 'command_id', 'operation_id', 'closure_generation', 'observed_hash', 'issued_at', 'expires_at' ) as $field ) {
			$bad = $good; $r =& $bad['writer']['completion']; $c = $r['command']; $c[$field] = is_int( $c[$field] ) ? $c[$field] + 1 : ( 'observed_hash' === $field ? str_repeat( '0', 64 ) : $this->real_id( 90 ) );
			$r = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::record( 'complete_disable', $c, $this->now ); $this->assertFalse( $this->valid_real( $bad ) );
		}
		$bad = $good; $r =& $bad['writer']['completion']; $r['domain'] = 'authenticated_closed_writer_v1'; $this->assertFalse( $this->valid_real( $bad ) );
		$bad = $good; $bad['writer']['authenticated_receipts'][$bad['writer']['upgrade']['command']['command_id']] = $bad['writer']['upgrade']; $this->assertFalse( $this->valid_real( $bad ) );
	}
}
