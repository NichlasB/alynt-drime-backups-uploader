<?php
/** Unit-only core-shaped probes. Not a genuine authentication or SQL proof. */
require_once __DIR__ . '/FixedCoreRequestShims.php';
trait Alynt_Test_Fixed_Core_Request_Fixture {
	private $owner;
	protected function setUp(): void {
		alynt_test_fixed_core_request_shims();
		if ( ! defined( 'LOGGED_IN_COOKIE' ) ) define( 'LOGGED_IN_COOKIE', 'alynt_unit_logged_in' );
		$GLOBALS['alynt_core_request_test'] = array( 'calls' => array(), 'on' => array(), 'throw' => '', 'multisite' => false, 'external_cache' => false, 'blog' => 1, 'actor' => 7, 'cookie_actor' => 7, 'logged_in' => true, 'session_now' => true, 'session_cache' => true, 'user_now' => true, 'user_cache' => true, 'role_now' => true, 'role_cache' => true, 'filter_allows' => true, 'nonce_result' => 1 );
		$GLOBALS['wpdb'] = new wpdb(); $GLOBALS['wp_roles'] = new WP_Roles( 1 ); $_COOKIE[LOGGED_IN_COOKIE] = 'unit-cookie'; $_SERVER['REQUEST_METHOD'] = 'POST';
		$f = dirname( __DIR__, 2 ) . '/includes/class-real-closed-writer-request-owner.php'; $this->assertFileExists( $f ); require_once $f;
		$GLOBALS['alynt_core_request_test']['calls'] = array(); $this->owner = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Owner::create(); $this->assertIsObject( $this->owner );
	}
	protected function tearDown(): void {
		// PHPUnit serializes failed child-test results. Do not let the deliberately
		// non-serializable owner mask the original assertion failure in that result.
		$this->owner = null;
		$GLOBALS['alynt_core_request_test']['on'] = array();
		parent::tearDown();
	}
	private function issuance_form() { return array( 'purpose' => 'upgrade_closed', 'confirm' => 'close', 'nonce' => 'unit-nonce' ); }
	private function command_form( $purpose = 'upgrade_closed' ) {
		$now = time(); $e = array( 'contract' => 'closed_writer_request_v1', 'purpose' => $purpose, 'command_id' => '88888888-8888-4888-8888-000000000001', 'site_uuid' => '11111111-1111-4111-8111-111111111111', 'enrollment_binding' => str_repeat( 'a', 64 ), 'expected_generation' => '2', 'issued_at' => (string) $now, 'expires_at' => (string) ( $now + 300 ), 'confirm' => 'close', 'origin_digest' => str_repeat( 'b', 64 ), 'nonce' => 'unit-nonce' );
		if ( 'receipt_read' === $purpose ) { unset( $e['enrollment_binding'], $e['expected_generation'], $e['origin_digest'] ); $e['confirm'] = 'observe'; $e['observed_command_id'] = '88888888-8888-4888-8888-000000000002'; }
		return $e;
	}
	private function calls( $name ) { return array_values( array_filter( $GLOBALS['alynt_core_request_test']['calls'], function ( $c ) use ( $name ) { return $c[0] === $name; } ) ); }
	private function unavailable( $r ) { $this->assertSame( array( 'outcome' => 'unavailable', 'source' => 'closed_writer_construction_v1', 'storage_supported' => false, 'dispatch_supported' => false, 'execution_supported' => false ), $r ); }
	private function issue() { return $this->owner->issue( 'POST', $this->issuance_form() ); }
}
