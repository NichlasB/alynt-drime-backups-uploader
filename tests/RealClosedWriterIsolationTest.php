<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RealClosedWriterFixture.php';

class RealClosedWriterIsolationTest extends TestCase {
	use Alynt_Test_Real_Closed_Writer_Fixture;
	public function test_actual_fixture_schema_seven_and_relabelled_copy_do_not_cross_domains() {
		foreach ( array( 'records', 'validation' ) as $part ) require_once dirname( __DIR__ ) . '/includes/trait-closed-writer-admission-' . $part . '.php';
		require_once dirname( __DIR__ ) . '/includes/class-closed-writer-admission-model.php';
		$e = $this->real_command( $this->six ); $seven = Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Model::propose( $this->six, $e, array(), $this->now );
		$this->assertSame( 7, $seven['schema_version'] ); $this->assertFalse( $this->candidate( $seven, $this->real_command( $seven ) ) );
		$seven['schema_version'] = 8; $seven['writer']['source'] = 'closed_writer_admission_v1'; $this->assertFalse( $this->valid_real( $seven ) );
		$this->assertFalse( Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Model::valid( $this->real_upgraded(), $this->site ) );
	}
	public function test_receipts_and_status_never_expose_target_values_and_defaults_stay_off() {
		list( $s, $e ) = $this->real_pending(); $json = json_encode( $s );
		foreach ( array( 'private-test-token', 'unknown', 'php_settings_disable_v1', 'nonce', 'cookie' ) as $sensitive ) $this->assertStringNotContainsString( $sensitive, $json );
		$this->assertFalse( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::completion( $s, $e, $this->settings, $this->now, 1, 'no' ) );
		$this->assertFalse( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::completion( $s, $e, $this->settings, $this->now, 1, 'no', 1 ) );
		$this->assertSame( 'domain_conflict', Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::lookup( $s, $this->real_id( 3 ), $this->site ) );
		$this->assertSame( 'not_found', Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::lookup( $s, $this->real_id( 9 ), $this->site ) );
		$this->assertSame( $this->six, $this->origin() );
	}
	public function test_state_byte_limit_and_deep_objects_fail_without_repair() {
		$s = $this->real_upgraded(); $s['claims'] = array( str_repeat( 'x', 131072 ) ); $before = $s;
		$this->assertFalse( $this->valid_real( $s ) ); $this->assertSame( $before, $s );
		$s = $this->real_upgraded(); $s['writer']['pending'] = fopen( 'php://memory', 'r+' );
		try { $this->assertFalse( $this->valid_real( $s ) ); } finally { fclose( $s['writer']['pending'] ); }
	}
	public function test_receipt_layout_order_is_irrelevant_but_clock_epoch_and_digest_are_not() {
		$s = $this->real_upgraded(); $r = $s['writer']['upgrade']; $this->assertTrue( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::receipt( array_reverse( $r, true ), $this->site ) );
		foreach ( array( 'committed_at' => $r['command']['expires_at'], 'result_generation' => $r['result_generation'] + 1, 'request_digest' => str_repeat( '0', 64 ), 'completion_id' => $this->real_id( 9 ) ) as $key => $value ) {
			$bad = $r; $bad[$key] = $value; $this->assertFalse( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::receipt( $bad, $this->site ) );
		}
	}
}
