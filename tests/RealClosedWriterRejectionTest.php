<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RealClosedWriterFixture.php';

class RealClosedWriterRejectionTest extends TestCase {
	use Alynt_Test_Real_Closed_Writer_Fixture;
	/** @dataProvider invalid_state */
	public function test_strict_schema_and_provenance_rejections( $path, $value ) {
		list( $s ) = $this->real_pending(); $v =& $s; foreach ( $path as $key ) $v =& $v[$key]; $v = $value;
		$this->assertFalse( $this->valid_real( $s ) );
	}
	public static function invalid_state() {
		return array(
			array( array( 'schema_version' ), 7 ), array( array( 'schema_version' ), '8' ), array( array( 'extra' ), false ),
			array( array( 'authority', 'enabled' ), true ), array( array( 'lifecycle', 'consent' ), true ), array( array( 'lifecycle', 'phase' ), 'active' ),
			array( array( 'writer', 'source' ), 'closed_writer_admission_fixture_v1' ), array( array( 'writer', 'authenticated_grant' ), array() ),
			array( array( 'writer', 'fixture_receipts' ), array( 'fake' => true ) ), array( array( 'writer', 'authenticated_receipts' ), array( 'fake' => true ) ),
			array( array( 'writer', 'internal_receipts' ), array( 'fake' => true ) ), array( array( 'writer', 'upgrade', 'domain' ), 'internal_closed_writer_v1' ),
			array( array( 'writer', 'closure', 'domain' ), 'closed_writer_admission_fixture_v1' ), array( array( 'writer', 'closure', 'domain' ), 'internal_closed_writer_v1' ),
			array( array( 'writer', 'closure', 'request_digest' ), str_repeat( 'f', 64 ) ), array( array( 'writer', 'closure', 'completion_id' ), null ),
			array( array( 'writer', 'closure', 'purpose' ), 'grant' ), array( array( 'writer', 'closure', 'command', 'source' ), 'authenticated_closed_writer_v1' ),
			array( array( 'writer', 'origin', 'source_digest' ), str_repeat( 'f', 64 ) ), array( array( 'writer', 'origin', 'changed_at' ), 1 ),
			array( array( 'writer', 'pending', 'operation_id' ), '88888888-8888-4888-8888-000000000099' ), array( array( 'writer', 'pending', 'extra' ), false ),
			array( array( 'writer', 'pending', 'closure_generation' ), 0 ), array( array( 'writer', 'pending' ), null ), array( array( 'writer', 'completion' ), array() ),
			array( array( 'generation' ), PHP_INT_MAX ), array( array( 'schedule_revision' ), 0 ), array( array( 'lifecycle', 'permission_generation' ), '4' ),
			array( array( 'lifecycle', 'changed_at' ), 1 ), array( array( 'legacy_writer', 'pending' ), array() ), array( array( 'claims' ), array( 'fake' => true ) ),
		);
	}
	public function test_schema_seven_even_if_relabelled_cannot_be_upgraded_or_used_as_real() {
		$s = $this->real_upgraded(); $s['schema_version'] = 7;
		$this->assertFalse( $this->candidate( $s, $this->real_command( $s ) ) );
		$s['schema_version'] = 8; $s['writer']['source'] = 'closed_writer_admission_fixture_v1';
		$this->assertFalse( $this->candidate( $s, $this->real_command( $s ) ) );
		$s['writer']['source'] = 'closed_writer_admission_v1'; $s['writer']['upgrade']['domain'] = 'closed_writer_admission_fixture_v1';
		$this->assertFalse( $this->valid_real( $s ) );
	}
	/** @dataProvider malformed_command */
	public function test_typed_command_is_not_coerced_and_inputs_stay_unchanged( $key, $value ) {
		$e = $this->real_command( $this->six ); $e[$key] = $value; $s = $this->six;
		$this->assertFalse( $this->candidate( $s, $e ) ); $this->assertSame( $this->six, $s );
	}
	public static function malformed_command() {
		return array( array( 'expected_generation', '1' ), array( 'expected_generation', -1 ), array( 'issued_at', 0 ), array( 'expires_at', PHP_INT_MAX ), array( 'purpose', 'grant' ), array( 'confirm', 'enable' ), array( 'site_uuid', 'OTHER' ), array( 'enrollment_binding', str_repeat( '8', 64 ) ), array( 'origin_digest', str_repeat( '0', 64 ) ), array( 'command_id', array() ), array( 'nonce', 'synthetic' ), array( 'source', 'authenticated_closed_writer_v1' ) );
	}
	public function test_json_serializable_input_is_rejected_before_any_callback() {
		$o = new class implements JsonSerializable { public $calls = 0; public function jsonSerialize(): array { ++$this->calls; return array(); } };
		$s = $this->real_upgraded(); $r = $s['writer']['upgrade']; $r['request_digest'] = $o; $s['writer']['upgrade'] = $r;
		$this->assertFalse( $this->valid_real( $s ) ); $this->assertFalse( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::receipt( $r, $this->site ) );
		$this->assertFalse( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::hash( array( $o ) ) );
		$e = $this->real_command( $this->six ); $e['extra'] = $o; $this->assertFalse( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::record( 'upgrade_closed', $e, $this->now ) ); $this->assertSame( 0, $o->calls );
	}
}
