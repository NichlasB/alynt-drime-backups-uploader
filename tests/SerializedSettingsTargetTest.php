<?php
/** In-memory serialized-byte tests; no WordPress/bootstrap/database or real secrets. */
class SerializedSettingsTargetTest extends PHPUnit\Framework\TestCase {
	private const SITE = '99999999-9999-4999-8999-999999999999';
	protected function setUp(): void {
		if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', __DIR__ . '/' );
		foreach ( array( 'parser', 'target' ) as $part ) {
			$path = dirname( __DIR__ ) . '/includes/class-serialized-settings-' . $part . '.php';
			$this->assertFileExists( $path ); require_once $path;
		}
	}
	private function inspect( $raw, $autoload = 'no', $site = self::SITE ) { return Alynt_Drime_Backups_Uploader_Serialized_Settings_Target::inspect( $raw, $site, $autoload ); }
	public function test_only_root_boolean_bytes_change_and_other_values_remain_identical(): void {
		$t = array( 'site_uuid' => self::SITE, 'unknown' => array( '01' => "UTF8-ø\0", -2 => null, 'nested' => array( true, 10, 'b:1;' ) ), 'auto_scan_enabled' => true, 'api_token' => 'SYNTHETIC-DO-NOT-EXPORT' );
		$raw = serialize( $t ); $t['auto_scan_enabled'] = false; $r = $this->inspect( $raw );
		$this->assertSame( array( 'codec_version', 'site_uuid', 'enabled', 'before_hash', 'after_hash', 'after' ), array_keys( $r ) );
		$this->assertSame( 'php_settings_disable_v1', $r['codec_version'] ); $this->assertTrue( $r['enabled'] );
		$this->assertSame( serialize( $t ), $r['after'] ); $this->assertSame( strlen( $raw ), strlen( $r['after'] ) );
		$this->assertSame( hash( 'sha256', $raw ), $r['before_hash'] ); $this->assertSame( hash( 'sha256', $r['after'] ), $r['after_hash'] );
		foreach ( array( 'no', 'off', 'auto-off' ) as $autoload ) { $noop = $this->inspect( $r['after'], $autoload ); $this->assertFalse( $noop['enabled'] ); $this->assertSame( $r['after'], $noop['after'] ); $this->assertSame( $noop['before_hash'], $noop['after_hash'] ); }
	}
	/** @dataProvider invalid_serialization */
	public function test_malformed_or_effectful_serialization_is_a_redacted_false( $raw ): void { $this->assertFalse( $this->inspect( $raw ) ); }
	public static function invalid_serialization(): array { return array_map( function ( $raw ) { return array( $raw ); }, array(
		null, false, array(), new stdClass(), '', 'b:0;', 'N;', 'a:0:{}trailing', 'a:01:{}', 'a:+1:{}', 'a:999999999999999999999:{}',
		'a:1:{s:1:"a";d:1.0;}', 'a:1:{s:1:"a";O:8:"stdClass":0:{}}', 'a:1:{s:1:"a";C:1:"X":0:{}}', 'a:1:{s:1:"a";R:1;}',
		'a:1:{s:1:"a";r:1;}', 'a:1:{s:1:"a";E:3:"A:B";}', 'a:1:{s:1:"a";S:1:"x";}', 'a:1:{s:1:"a";s:2:"x";}',
		'a:1:{s:1:"a";i:-0;}', 'a:1:{s:1:"a";i:+1;}', 'a:1:{s:1:"a";i:01;}', 'a:1:{s:1:"a";i:999999999999999999999;}',
		'a:1:{s:1:"a";s:99999999999999999999:"x";}', 'a:1:{s:1:"a";b:2;}', 'a:2:{i:1;N;s:1:"1";N;}', 'a:1:{s:1:"1";N;}',
		'a:2:{s:1:"a";N;s:1:"a";N;}', 'a:1:{s:1:"a";s:1:"' . chr( 255 ) . '";}', str_repeat( 'x', 1048577 )
	) ); }
	public function test_missing_or_wrong_uuid_scan_field_and_autoload_are_not_repaired(): void {
		$good = array( 'site_uuid' => self::SITE, 'auto_scan_enabled' => true );
		foreach ( array( array(), array( 'site_uuid' => self::SITE ), array( 'auto_scan_enabled' => true ), array_replace( $good, array( 'site_uuid' => strtoupper( self::SITE ) . 'x' ) ), array_replace( $good, array( 'auto_scan_enabled' => 1 ) ), array_replace( $good, array( 'auto_scan_enabled' => 'true' ) ) ) as $bad ) $this->assertFalse( $this->inspect( serialize( $bad ) ) );
		foreach ( array( 'yes', 'auto', '', true, null ) as $autoload ) $this->assertFalse( $this->inspect( serialize( $good ), $autoload ) );
		$this->assertFalse( $this->inspect( serialize( $good ), 'no', 'not-a-uuid' ) );
	}
	public function test_native_integer_limits_and_literal_token_text_are_preserved(): void {
		$raw = serialize( array( 'site_uuid' => self::SITE, 'auto_scan_enabled' => true, 'min' => PHP_INT_MIN, 'max' => PHP_INT_MAX, 'text' => 'O:8:"stdClass":0:{};R:1;b:1;' ) );
		$this->assertIsArray( $this->inspect( $raw ) );
	}
}
