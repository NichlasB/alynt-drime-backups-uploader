<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/FixedCoreRequestFixture.php';
/**
 * Internal decision tests, not WordPress authentication or admission proof.
 * Reflection observes existing private booleans; no production port is added.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class FixedCoreRequestDecisionTest extends TestCase {
	use Alynt_Test_Fixed_Core_Request_Fixture;
	private function inspect_private( $method, array $args = array() ) {
		$m = new ReflectionMethod( $this->owner, $method );
		if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true );
		return $m->invokeArgs( $this->owner, $args );
	}
	private function decision( $frame, $e = null, $action = 'unit-action' ) {
		return $this->inspect_private( 'guard', array( $frame, 'unit-nonce', $action, $e ) );
	}
	/** @dataProvider initial_denials */
	public function test_each_initial_guard_has_an_independent_negative_decision( $key, $value ) {
		$frame = $this->inspect_private( 'frame' );
		$this->assertTrue( $this->decision( $frame ), 'Positive control must actually reach guard truth, without granting anything.' );
		$GLOBALS['alynt_core_request_test'][$key] = $value;
		$this->assertFalse( $this->decision( $frame ), $key . ' must deny internally even though the owner always returns unavailable.' );
		$this->unavailable( $this->issue() );
	}
	public static function initial_denials() { return array(
		array( 'session_now', false ), array( 'user_now', false ), array( 'role_now', false ), array( 'filter_allows', false ),
		array( 'logged_in', false ), array( 'cookie_actor', 8 ), array( 'username', 'other' ), array( 'expiration', '1' ),
		array( 'nonce_result', false ), array( 'nonce_result', true ), array( 'nonce_result', '1' ), array( 'nonce_result', 0 )
	); }
	/** @dataProvider after_nonce_denials */
	public function test_fresh_post_nonce_guard_truth_is_not_inferred_from_unavailable( $change ) {
		$frame = $this->inspect_private( 'frame' );
		$this->assertTrue( $this->decision( $frame ) );
		$GLOBALS['alynt_core_request_test']['on']['verify_nonce'] = function () use ( $change ) {
			$s =& $GLOBALS['alynt_core_request_test'];
			if ( in_array( $change, array( 'session', 'user', 'role' ), true ) ) { $s[$change . '_now'] = false; $s[$change . '_cache'] = true; }
			if ( 'filter' === $change ) $s['filter_allows'] = false;
			if ( 'actor' === $change ) $s['actor'] = 8;
			if ( 'cookie' === $change ) $_COOKIE[LOGGED_IN_COOKIE] = 'changed';
			if ( 'blog' === $change ) $s['blog'] = 2;
			if ( 'table' === $change ) $GLOBALS['wpdb']->options = 'other_options';
			if ( 'method' === $change ) $_SERVER['REQUEST_METHOD'] = 'GET';
			if ( 'clock' === $change ) {
				$p = new ReflectionProperty( $this->owner, 'last_clock' ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $p->setValue( $this->owner, time() + 100 );
			}
		};
		$roles = $GLOBALS['wp_roles'];
		$this->assertFalse( $this->decision( $frame ), $change . ' after nonce must deny the private decision.' );
		$this->assertSame( $roles, $GLOBALS['wp_roles'] );
		$this->assertCount( 0, $this->calls( 'protected_io' ) );
	}
	public static function after_nonce_denials() { return array_map( function ( $v ) { return array( $v ); }, array( 'session', 'user', 'role', 'filter', 'actor', 'cookie', 'blog', 'table', 'method', 'clock' ) ); }
	public function test_nonce_domains_have_distinct_accept_and_reject_controls() {
		$frame = $this->inspect_private( 'frame' );
		$e = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::parse( $this->command_form() )['envelope'];
		$command = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::action( $e );
		$read = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::parse( $this->command_form( 'receipt_read' ) )['envelope'];
		$receipt = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::action( $read );
		$issue = 'alynt_drime_backups_closed_writer_issue_v1:1:upgrade_closed';
		foreach ( array( $issue, $command, $receipt ) as $expected ) {
			$GLOBALS['alynt_core_request_test']['expected_action'] = $expected;
			foreach ( array( $issue, $command, $receipt, 'alynt_drime_backups_closed_writer_fixture_command_v1:fake' ) as $actual ) {
				$this->assertSame( $expected === $actual, $this->decision( $frame, null, $actual ) );
			}
		}
		$GLOBALS['alynt_core_request_test']['expected_action'] = $command;
		$GLOBALS['alynt_core_request_test']['nonce_result'] = 2;
		$this->assertTrue( $this->decision( $frame, $e, $command ) );
		$e['issued_at'] -= 400; $e['expires_at'] -= 400;
		$this->assertFalse( $this->decision( $frame, $e, $command ), 'A still-valid nonce cannot extend original intent.' );
	}
	public function test_authentication_order_and_absent_protected_io_are_observable() {
		$this->unavailable( $this->issue() );
		$auth = array( 'parse_cookie', 'clean_user', 'cache_delete', 'validate_cookie', 'cache_delete', 'cache_delete', 'cache_delete', 'roles', 'user', 'capability', 'logged_in' );
		$interesting = array_merge( $auth, array( 'verify_nonce', 'query', 'protected_io' ) );
		$trace = array_values( array_filter( array_column( $GLOBALS['alynt_core_request_test']['calls'], 0 ), function ( $name ) use ( $interesting ) { return in_array( $name, $interesting, true ); } ) );
		$this->assertSame( array_merge( $auth, array( 'verify_nonce' ), $auth ), $trace );
		$GLOBALS['alynt_core_request_test']['calls'] = array();
		$f = $this->command_form(); $f['source'] = 'posted';
		$this->unavailable( $this->owner->consume( 'POST', $f ) );
		$this->assertSame( array(), $GLOBALS['alynt_core_request_test']['calls'], 'Malformed intent must not reach even scope/authentication reads.' );
	}
	public function test_public_consume_routes_each_original_purpose_to_its_exact_nonce_domain() {
		foreach ( array( 'upgrade_closed', 'begin_disable', 'abandon_closed', 'receipt_read' ) as $purpose ) {
			$f = $this->command_form( 'receipt_read' === $purpose ? $purpose : 'upgrade_closed' );
			if ( in_array( $purpose, array( 'begin_disable', 'abandon_closed' ), true ) ) {
				unset( $f['origin_digest'] ); $f['purpose'] = $purpose; $f['context_digest'] = str_repeat( 'b', 64 );
				if ( 'begin_disable' === $purpose ) { $f['confirm'] = 'disable'; $f['before_hash'] = str_repeat( 'c', 64 ); $f['after_hash'] = str_repeat( 'd', 64 ); }
				else { $f['confirm'] = 'abandon'; $f['operation_id'] = '88888888-8888-4888-8888-000000000002'; $f['closure_generation'] = '3'; $f['observed_hash'] = str_repeat( 'c', 64 ); }
			}
			$e = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::parse( $f )['envelope'];
			$action = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::action( $e );
			$GLOBALS['alynt_core_request_test']['calls'] = array(); $GLOBALS['alynt_core_request_test']['expected_action'] = $action;
			$this->unavailable( $this->owner->consume( 'POST', $f ) );
			$this->assertCount( 1, $this->calls( 'verify_nonce' ), 'Missing nonce routing must fail before inspecting the recorded call.' );
			$this->assertSame( array( 'unit-nonce', $action ), $this->calls( 'verify_nonce' )[0][1] );
			$this->assertCount( 2, $this->calls( 'validate_cookie' ), 'Both sides must run for the exact real command/read action.' );
			$this->assertCount( 0, $this->calls( 'protected_io' ) ); $this->assertCount( 0, $this->calls( 'query' ) );
		}
	}
}
