<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/SerializedSettingsTargetTest.php';

class SerializedSettingsBoundsTest extends TestCase {
	private const SITE = '99999999-9999-4999-8999-999999999999';
	private function inspect( $raw ) { return Alynt_Drime_Backups_Uploader_Serialized_Settings_Target::inspect( $raw, self::SITE, 'no' ); }
	protected function setUp(): void {
		if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', __DIR__ . '/fixtures/' );
		require_once dirname( __DIR__ ) . '/includes/class-serialized-settings-parser.php'; require_once dirname( __DIR__ ) . '/includes/class-serialized-settings-target.php';
	}
	public function test_total_node_budget_includes_keys_values_and_root() {
		$s = array( 'site_uuid' => self::SITE, 'auto_scan_enabled' => true );
		for ( $i = 0; $i < 2045; ++$i ) $s[$i] = null;
		$this->assertIsArray( $this->inspect( serialize( $s ) ) ); $s[2045] = null; $this->assertFalse( $this->inspect( serialize( $s ) ) );
	}
	public function test_exact_depth_boundary_and_byte_preservation_at_one_megabyte() {
		$v = 'leaf'; for ( $i = 0; $i < 14; ++$i ) $v = array( $v );
		$s = array( 'site_uuid' => self::SITE, 'auto_scan_enabled' => true, 'extra' => $v ); $this->assertIsArray( $this->inspect( serialize( $s ) ) );
		$s['extra'] = array( $v ); $this->assertFalse( $this->inspect( serialize( $s ) ) );
		$s['extra'] = str_repeat( 'x', 1000000 ); $size = strlen( serialize( $s ) ); $s['extra'] .= str_repeat( 'x', 1048576 - $size ); $raw = serialize( $s );
		$this->assertSame( 1048576, strlen( $raw ) ); $p = $this->inspect( $raw ); $this->assertIsArray( $p ); $this->assertSame( 1048576, strlen( $p['after'] ) );
		$s['extra'] .= 'x'; $this->assertFalse( $this->inspect( serialize( $s ) ) );
	}
	public function test_bad_key_types_and_unknown_tags_cannot_invoke_autoload_or_callbacks() {
		$calls = 0; $loader = function ( $class ) use ( &$calls ) { ++$calls; }; spl_autoload_register( $loader );
		try {
			foreach ( array( 'O:12:"NoSuchClassX":0:{}', 'C:12:"NoSuchClassX":0:{}', 'a:1:{N;b:1;}', 'a:1:{b:1;b:1;}', 'a:1:{a:0:{}b:1;}', 'a:1:{s:1:"1";b:1;}', 'a:1:{i:0;b:1;}trailing' ) as $raw ) $this->assertFalse( $this->inspect( $raw ) );
		} finally { spl_autoload_unregister( $loader ); }
		$this->assertSame( 0, $calls );
	}
	public function test_root_boolean_only_even_with_nested_same_name_and_literal_tokens() {
		$s = array( 'site_uuid' => self::SITE, 'auto_scan_enabled' => true, 'nested' => array( 'auto_scan_enabled' => true, 'literal' => 'O:1:b:1;R:1;auto_scan_enabled' ) );
		$raw = serialize( $s ); $s['auto_scan_enabled'] = false; $p = $this->inspect( $raw ); $this->assertSame( serialize( $s ), $p['after'] );
		unset( $s['auto_scan_enabled'] ); $this->assertFalse( $this->inspect( serialize( $s ) ) );
	}
}
