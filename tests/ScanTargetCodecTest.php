<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/class-scan-target-codec.php';

class ScanTargetCodecTest extends TestCase {
	private function item( $char, $path = null ) { return array( 'signature' => str_repeat( $char, 64 ), 'path' => $path ?? '/private/' . $char . '.zip', 'name' => $char . '.zip', 'attempts' => 0, 'unknown' => array( 'keep' => 'exact' ) ); }
	private function deps() { return array( 'active' => 'a:0:{}', 'uploaded' => 'a:0:{}', 'failed' => 'a:0:{}' ); }
	private function queue( $raw, $verb, $payload, $deps = null ) { return Alynt_Drime_Backups_Uploader_Scan_Target_Codec::queue( $raw, $verb, $payload, $deps ?? $this->deps() ); }
	private function bytes( $items ) { $out = array(); foreach ( $items as $item ) $out[$item['signature']] = $item; return serialize( $out ); }
	private function candidate( $r, $expected ) { $this->assertIsArray( $r ); $this->assertSame( $expected, $r['after'] ); foreach ( array( 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $key ) $this->assertFalse( $r[$key] ); }
	public function test_both_producer_snapshot_candidates_preserve_untouched_entry_spans() {
		$key = str_repeat( 'a', 64 ); $before = serialize( array( $key => array( 'mtime' => 4, 'size' => 8 ) ) ); $proposal = serialize( array( $key => array( 'size' => 8, 'mtime' => 4 ), str_repeat( 'b', 64 ) => array( 'size' => 9, 'mtime' => 5 ) ) );
		foreach ( array( 'wpvivid_snapshot', 'outbox_snapshot' ) as $role ) { $r = Alynt_Drime_Backups_Uploader_Scan_Target_Codec::snapshot( $role, $before, $proposal ); $this->candidate( $r, serialize( array( $key => array( 'mtime' => 4, 'size' => 8 ), str_repeat( 'b', 64 ) => array( 'size' => 9, 'mtime' => 5 ) ) ) ); }
		$this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Target_Codec::snapshot( 'settings', $before, $proposal ) );
	}
	public function test_append_prepend_remove_and_attempt_delta_preserve_exact_unknown_bytes() {
		$a = $this->item( 'a' ); $b = $this->item( 'b' ); $before = $this->bytes( array( $a ) );
		$this->candidate( $this->queue( $before, 'append_new', $this->bytes( array( $b ) ) ), $this->bytes( array( $a, $b ) ) );
		$deps = $this->deps(); $deps['failed'] = $this->bytes( array( $b ) );
		$this->candidate( $this->queue( $before, 'prepend_requeue', $this->bytes( array( $b ) ), $deps ), $this->bytes( array( $b, $a ) ) );
		$this->candidate( $this->queue( $before, 'remove_existing', array( 'signature' => $a['signature'] ) ), 'a:0:{}' );
		$a['attempts'] = 7; $this->candidate( $this->queue( $before, 'set_attempts_existing', array( 'signature' => $a['signature'], 'attempts' => 7 ) ), $this->bytes( array( $a ) ) );
	}
	public function test_duplicate_signature_normalized_path_and_wpvivid_file_cannot_bypass_sink() {
		$a = $this->item( 'a', 'c:\\private\\a.zip' ); $b = $this->item( 'b', 'C:/private//a.zip' );
		$this->assertFalse( $this->queue( $this->bytes( array( $a ) ), 'append_new', $this->bytes( array( $b ) ) ) );
		$this->assertFalse( $this->queue( $this->bytes( array( $a ) ), 'append_new', $this->bytes( array( $a ) ) ) );
		$a = $this->item( 'a' ); $b = $this->item( 'b' ); $a['wpvivid'] = array( 'backup_id' => '12' ); $b['wpvivid'] = array( 'backup_id' => 12 ); $b['name'] = $a['name'];
		$this->assertFalse( $this->queue( $this->bytes( array( $a ) ), 'append_new', $this->bytes( array( $b ) ) ) );
		$this->assertFalse( $this->queue( 'a:0:{}', 'append_new', $this->bytes( array( $a, $b ) ) ) );
	}
	public function test_uploaded_failed_and_active_dependencies_have_independent_negative_controls() {
		$a = $this->item( 'a' );
		foreach ( array( 'uploaded', 'failed' ) as $role ) { $deps = $this->deps(); $deps[$role] = $this->bytes( array( $a ) ); $this->assertFalse( $this->queue( 'a:0:{}', 'append_new', $this->bytes( array( $a ) ), $deps ) ); }
		$deps = $this->deps(); $deps['active'] = serialize( array( 'signature' => str_repeat( 'b', 64 ), 'local_file' => $a['path'] ) ); $this->assertFalse( $this->queue( 'a:0:{}', 'append_new', $this->bytes( array( $a ) ), $deps ) );
		$deps = $this->deps(); $deps['failed'] = $this->bytes( array( $a ) ); $this->candidate( $this->queue( 'a:0:{}', 'prepend_requeue', $this->bytes( array( $a ) ), $deps ), $this->bytes( array( $a ) ) );
	}
	public function test_requeue_requires_the_exact_failed_record_instead_of_new_discovery() {
		$a = $this->item( 'a' );
		$this->assertFalse( $this->queue( 'a:0:{}', 'prepend_requeue', $this->bytes( array( $a ) ) ) );
		$deps = $this->deps(); $failed = $a; $failed['path'] = '/different.zip'; $deps['failed'] = $this->bytes( array( $failed ) );
		$this->assertFalse( $this->queue( 'a:0:{}', 'prepend_requeue', $this->bytes( array( $a ) ), $deps ) );
	}
	public function test_malformed_unsupported_serialization_never_instantiates_objects() {
		$bad = array( 'a:1:{s:1:"x";O:8:"stdClass":0:{}}', 'a:1:{s:1:"x";R:1;}', 'a:2:{s:1:"x";i:1;s:1:"x";i:2;}', 'a:1:{s:1:"1";i:2;}', 'a:1:{s:1:"x";s:2:"x";}', 'a:1:{s:1:"x";d:1.1;}', 'a:0:{}x', 'a:1:{s:1:"x";s:1:"' . "\xff" . '";}' );
		foreach ( $bad as $raw ) { $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Target_Bytes::parse( $raw ) ); $this->assertFalse( $this->queue( $raw, 'append_new', 'a:0:{}' ) ); }
	}
	public function test_strict_typed_delta_fields_and_missing_records_refuse_without_repair() {
		$a = $this->item( 'a' ); $raw = $this->bytes( array( $a ) );
		foreach ( array( -1, '1', 1.0, PHP_INT_MAX, new stdClass() ) as $attempts ) $this->assertFalse( $this->queue( $raw, 'set_attempts_existing', array( 'signature' => $a['signature'], 'attempts' => $attempts ) ) );
		foreach ( array( 'delete_option', 'write', 'flush' ) as $verb ) $this->assertFalse( $this->queue( $raw, $verb, array() ) );
		$this->assertFalse( $this->queue( 'a:0:{}', 'remove_existing', array( 'signature' => $a['signature'] ) ) );
		$deps = $this->deps(); $deps['failed'] = serialize( array( 'arbitrary' => true ) ); $this->assertFalse( $this->queue( $raw, 'remove_existing', array( 'signature' => $a['signature'] ), $deps ) );
		$deps = $this->deps(); $deps['provider'] = function() { throw new LogicException(); }; $this->assertFalse( $this->queue( $raw, 'remove_existing', array( 'signature' => $a['signature'] ), $deps ) );
	}
	public function test_snapshot_shape_and_byte_node_depth_volume_bounds() {
		foreach ( array( array( 'arbitrary' => array( 'size' => 1, 'mtime' => 1 ) ), array( str_repeat( 'a', 64 ) => array( 'size' => '1', 'mtime' => 1 ) ), array( str_repeat( 'a', 64 ) => array( 'size' => -1, 'mtime' => 1 ) ) ) as $s ) $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Target_Codec::snapshot( 'wpvivid_snapshot', 'a:0:{}', serialize( $s ) ) );
		$this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Target_Codec::snapshot( 'wpvivid_snapshot', 'a:0:{}', str_repeat( 'x', 524289 ) ) );
		$deep = 1; for ( $i = 0; $i < 17; ++$i ) $deep = array( $deep ); $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Target_Bytes::parse( serialize( $deep ) ) );
		$this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Target_Bytes::parse( serialize( array_fill( 0, 2048, 1 ) ) ) );
		$items = array(); for ( $i = 0; $i < 65; ++$i ) { $item = $this->item( 'a', '/p/' . $i ); $item['signature'] = hash( 'sha256', (string) $i ); $items[] = $item; }
		$this->assertIsArray( $this->queue( 'a:0:{}', 'append_new', $this->bytes( array_slice( $items, 0, 64 ) ) ) ); $this->assertFalse( $this->queue( 'a:0:{}', 'append_new', $this->bytes( $items ) ) );
	}
	public function test_exact_snapshot_and_queue_record_capacity_have_positive_and_negative_controls() {
		$snapshots = array();
		for ( $i = 0; $i < 513; ++$i ) $snapshots[hash( 'sha256', (string) $i )] = array( 'size' => 1, 'mtime' => 2 );
		$this->assertIsArray( Alynt_Drime_Backups_Uploader_Scan_Target_Codec::snapshot( 'wpvivid_snapshot', 'a:0:{}', serialize( array_slice( $snapshots, 0, 512, true ) ) ) );
		$this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Target_Codec::snapshot( 'wpvivid_snapshot', 'a:0:{}', serialize( $snapshots ) ) );
		$items = array(); for ( $i = 0; $i < 128; ++$i ) { $item = $this->item( 'a', '/p/' . $i ); $item['signature'] = hash( 'sha256', (string) $i ); $items[] = $item; }
		$this->candidate( $this->queue( $this->bytes( $items ), 'append_new', 'a:0:{}' ), $this->bytes( $items ) );
		$this->assertFalse( $this->queue( $this->bytes( $items ), 'append_new', $this->bytes( array( $this->item( 'f' ) ) ) ) );
	}
	public function test_proposal_byte_limit_and_untouched_private_string_tokens_are_preserved() {
		$item = $this->item( 'a' ); $item['unknown'] = '';
		for ( $i = 0; $i < 3; ++$i ) { $difference = 262144 - strlen( $this->bytes( array( $item ) ) ); $item['unknown'] = str_repeat( 'x', strlen( $item['unknown'] ) + $difference ); }
		$exact = $this->bytes( array( $item ) ); $this->assertSame( 262144, strlen( $exact ) );
		$this->candidate( $this->queue( 'a:0:{}', 'append_new', $exact ), $exact ); $item['unknown'] .= 'x'; $this->assertFalse( $this->queue( 'a:0:{}', 'append_new', $this->bytes( array( $item ) ) ) );
		$item = $this->item( 'b' ); $item['unknown'] = "O:8:\"stdClass\":0:{}\0\n秘密"; $before = $this->bytes( array( $item ) ); $item['attempts'] = 1;
		$this->candidate( $this->queue( $before, 'set_attempts_existing', array( 'signature' => $item['signature'], 'attempts' => 1 ) ), $this->bytes( array( $item ) ) );
	}
}
