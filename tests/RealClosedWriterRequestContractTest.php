<?php
use PHPUnit\Framework\TestCase;
use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract as RealRequest;

class RealClosedWriterRequestContractTest extends TestCase {
	protected function setUp(): void { $f = dirname( __DIR__ ) . '/includes/class-real-closed-writer-request-contract.php'; $this->assertFileExists( $f ); require_once $f; }
	private function envelope( $purpose ) {
		$e = array( 'contract' => 'closed_writer_request_v1', 'purpose' => $purpose, 'command_id' => '88888888-8888-4888-8888-000000000001', 'site_uuid' => '11111111-1111-4111-8111-111111111111', 'enrollment_binding' => str_repeat( 'a', 64 ), 'expected_generation' => 2, 'issued_at' => 1800000000, 'expires_at' => 1800000300, 'confirm' => array( 'upgrade_closed' => 'close', 'begin_disable' => 'disable', 'abandon_closed' => 'abandon', 'receipt_read' => 'observe' )[$purpose] );
		if ( 'upgrade_closed' === $purpose ) $e['origin_digest'] = str_repeat( 'b', 64 );
		if ( 'begin_disable' === $purpose ) foreach ( array( 'context_digest', 'before_hash', 'after_hash' ) as $key ) $e[$key] = str_repeat( 'b', 64 );
		if ( 'abandon_closed' === $purpose ) $e += array( 'operation_id' => '88888888-8888-4888-8888-000000000002', 'closure_generation' => 3, 'context_digest' => str_repeat( 'b', 64 ), 'observed_hash' => str_repeat( 'c', 64 ) );
		if ( 'receipt_read' === $purpose ) { unset( $e['enrollment_binding'], $e['expected_generation'] ); $e['observed_command_id'] = '88888888-8888-4888-8888-000000000003'; }
		return $e;
	}
	public function test_purpose_specific_typed_order_and_real_actions_are_separate_from_fixture_actions() {
		foreach ( array( 'upgrade_closed', 'begin_disable', 'abandon_closed', 'receipt_read' ) as $purpose ) {
			$e = $this->envelope( $purpose ); $body = array_map( 'strval', array_reverse( $e, true ) ); $body['nonce'] = 'synthetic-nonce'; $r = RealRequest::parse( $body ); $this->assertIsArray( $r );
			$this->assertSame( $e, array_replace( $e, $r['envelope'] ) ); $this->assertSame( RealRequest::fields( $purpose ), array_keys( $r['envelope'] ) );
			$ordered = array(); foreach ( RealRequest::fields( $purpose ) as $key ) $ordered[$key] = $e[$key];
			$prefix = 'receipt_read' === $purpose ? 'alynt_drime_backups_closed_writer_receipt_v1:' : 'alynt_drime_backups_closed_writer_command_v1:';
			$this->assertSame( $prefix . hash( 'sha256', json_encode( $ordered ) ), RealRequest::action( $e ) );
			$this->assertSame( RealRequest::action( $e ), RealRequest::action( array_reverse( $e, true ) ) );
			$this->assertSame( 'alynt_drime_backups_closed_writer_issue_v1:1:' . $purpose, RealRequest::issue_action( 1, $purpose ) );
			$f = array( 'purpose' => $purpose, 'confirm' => $e['confirm'], 'nonce' => 'synthetic-nonce' ); if ( 'abandon_closed' === $purpose ) $f['operation_id'] = $e['operation_id']; if ( 'receipt_read' === $purpose ) $f['observed_command_id'] = $e['observed_command_id'];
			$this->assertSame( $f, RealRequest::issuance( $f ) );
		}
	}
	/** @dataProvider bad_scalar */
	public function test_malformed_strings_are_denied_before_coercion( $key, $value ) {
		$e = array_map( 'strval', $this->envelope( 'upgrade_closed' ) ); $e['nonce'] = 'n'; $e[$key] = $value; $this->assertFalse( RealRequest::parse( $e ) );
	}
	public static function bad_scalar() { return array( array( 'expected_generation', '02' ), array( 'expected_generation', '-1' ), array( 'expected_generation', '+1' ), array( 'expected_generation', '9223372036854775808' ), array( 'expected_generation', 2 ), array( 'issued_at', '0' ), array( 'expires_at', '1800000301' ), array( 'expires_at', '1800000000' ), array( 'expires_at', (string) PHP_INT_MAX ), array( 'nonce', '' ), array( 'nonce', array() ), array( 'nonce', str_repeat( 'x', 2049 ) ), array( 'nonce', chr( 255 ) ), array( 'source', 'authenticated_closed_writer_v1' ), array( 'target', 'settings' ), array( 'grant', 'true' ), array( 'site_uuid', 'NOT-A-UUID' ), array( 'confirm', 'grant' ), array( 'purpose', 'complete_disable' ), array( 'purpose', 'rollback' ), array( 'purpose', 'activate' ) ); }
	public function test_every_original_field_changes_its_action_and_extra_fields_cannot_select_scope() {
		foreach ( array( 'upgrade_closed', 'begin_disable', 'abandon_closed', 'receipt_read' ) as $purpose ) {
			$e = $this->envelope( $purpose );
			foreach ( $e as $key => $value ) {
				$bad = $e; $bad[$key] = is_int( $value ) ? $value + 1 : ( 'command_id' === $key || 'site_uuid' === $key || 'operation_id' === $key || 'observed_command_id' === $key ? '99999999-9999-4999-8999-999999999999' : str_repeat( 'f', 64 ) );
				$this->assertNotSame( RealRequest::action( $e ), RealRequest::action( $bad ) );
			}
		}
		foreach ( array( 'source', 'blog_id', 'site_uuid', 'provider', 'enabled', 'clock', 'nonce_action', 'target' ) as $key ) {
			$f = array( 'purpose' => 'begin_disable', 'confirm' => 'disable', 'nonce' => 'n', $key => 'posted' ); $this->assertFalse( RealRequest::issuance( $f ) );
		}
		foreach ( array( 0, -1, '1', true, PHP_INT_MAX ) as $blog ) $this->assertFalse( RealRequest::issue_action( $blog, 'upgrade_closed' ) );
	}
	public function test_once_unslashed_bounds_canonical_decimal_edges_and_callback_rejection() {
		$body = array_map( 'strval', $this->envelope( 'upgrade_closed' ) ); $body['nonce'] = "n\\\\once"; $this->assertSame( "n\\once", RealRequest::parse( $body )['nonce'] );
		$body['nonce'] = str_repeat( "\x01", 2048 ); $this->assertFalse( RealRequest::parse( $body ) ); $body['nonce'] = 'n'; $body[str_repeat( 'x', 100000 )] = 'n'; $this->assertFalse( RealRequest::parse( $body ) );
		$e = $this->envelope( 'upgrade_closed' ); $e['expected_generation'] = PHP_INT_MAX - 1; $e['issued_at'] = PHP_INT_MAX - 3900; $e['expires_at'] = PHP_INT_MAX - 3600; $this->assertTrue( RealRequest::valid( $e ) );
		$e['issued_at'] = true; $this->assertFalse( RealRequest::valid( $e ) );
		$o = new class implements JsonSerializable { public $calls = 0; public function jsonSerialize(): array { ++$this->calls; return array(); } }; $this->assertFalse( RealRequest::parse( array( 'purpose' => $o ) ) ); $this->assertSame( 0, $o->calls );
	}
}
