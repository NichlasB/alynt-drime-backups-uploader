<?php
use PHPUnit\Framework\TestCase;
use Alynt_Drime_Backups_Uploader_Closed_Writer_Json_Contract as Json;

class ClosedWriterJsonContractTest extends TestCase {
	protected function setUp(): void {
		$file = dirname( __DIR__ ) . '/includes/class-closed-writer-json-contract.php'; $this->assertFileExists( $file ); require_once $file;
	}
	public function test_plain_json_has_positive_controls_with_whitespace_and_escaped_keys() {
		$this->assertSame( array( 'a' => array( true, null, -1, 'ø' ) ), Json::decode( " \n{\"a\":[true,null,-1,\"ø\"]} \t" ) );
		$this->assertSame( array( 'a' => 1, 'b' => 2 ), Json::decode( '{"\\u0061":1,"b":2}' ) );
		$this->assertSame( array( 'x' => array( 'a' => 1 ), 'y' => array( 'a' => 2 ) ), Json::decode( '{"x":{"a":1},"y":{"a":2}}' ) );
	}
	/** @dataProvider rejected_json */
	public function test_duplicate_members_and_unsupported_values_are_rejected( $raw ) { $this->assertFalse( Json::decode( $raw ) ); }
	public static function rejected_json() { return array_map( function ( $raw ) { return array( $raw ); }, array(
		'{"a":1,"a":2}', '{"a":1,"\\u0061":2}', '{"x":{"a":1,"a":1}}', '{"x":[{"a":1,"a":2}]}', '{"0":1,"\\u0030":2}',
		'{"a":1.0}', '{"a":1e0}', '{"a":92233720368547758080}', '{"a":01}', '{"a":"\\ud800"}', '{"a":1}junk', '{"a":}', '{"a":true,}', '[]', 'null', '', null, true
	) ); }
	public function test_byte_and_depth_limits_have_adjacent_accept_controls() {
		$raw = '{"a":"' . str_repeat( 'x', 131064 ) . '"}'; $this->assertSame( 131072, strlen( $raw ) ); $this->assertIsArray( Json::decode( $raw ) );
		$this->assertFalse( Json::decode( ' ' . $raw ) );
		$raw = 'null'; for ( $i = 0; $i < 14; ++$i ) $raw = '[' . $raw . ']';
		$this->assertIsArray( Json::decode( '{"a":' . $raw . '}' ) ); $this->assertFalse( Json::decode( '{"a":[' . $raw . ']}' ) );
	}
	public function test_array_keys_and_values_share_a_finite_node_budget() {
		$raw = '{"x":[' . implode( ',', array_fill( 0, 32766, '0' ) ) . ']}'; $this->assertIsArray( Json::decode( $raw ) );
		$this->assertFalse( Json::decode( substr( $raw, 0, -2 ) . ',0]}' ) );
		$values = array( 'odd"\\' => "\0ø\\\"{}[]", '' => array(), '0' => true );
		$this->assertSame( $values, Json::decode( json_encode( $values ) ) );
	}
}
