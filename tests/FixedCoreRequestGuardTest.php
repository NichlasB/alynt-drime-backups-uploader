<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/FixedCoreRequestFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class FixedCoreRequestGuardTest extends TestCase {
	use Alynt_Test_Fixed_Core_Request_Fixture;
	/** @dataProvider denied */
	public function test_unsupported_authentication_scope_and_nonce_are_closed( $key, $value, $verifies ) {
		$GLOBALS['alynt_core_request_test'][$key] = $value; $roles = $GLOBALS['wp_roles']; $this->unavailable( $this->issue() );
		$this->assertCount( $verifies, $this->calls( 'verify_nonce' ) ); $this->assertCount( 0, $this->calls( 'query' ) ); $this->assertSame( $roles, $GLOBALS['wp_roles'] );
	}
	public static function denied() { return array(
		array( 'multisite', true, 0 ), array( 'external_cache', true, 0 ), array( 'blog', '1', 0 ), array( 'blog', 0, 0 ), array( 'actor', 0, 0 ), array( 'actor', '7', 0 ), array( 'cookie_actor', 8, 0 ),
		array( 'logged_in', false, 0 ), array( 'session_now', false, 0 ), array( 'user_now', false, 0 ), array( 'role_now', false, 0 ), array( 'filter_allows', false, 0 ), array( 'username', 'other-user', 0 ),
		array( 'expiration', '1', 0 ), array( 'expiration', '01', 0 ), array( 'expiration', '9223372036854775808', 0 ), array( 'expiration', array(), 0 ),
		array( 'nonce_result', true, 1 ), array( 'nonce_result', '1', 1 ), array( 'nonce_result', false, 1 )
	); }
	public function test_scope_and_custom_roles_cannot_be_selected_or_adopted() {
		foreach ( array( 'switched' => true, '_wp_switched_stack' => array( 1 ), 'wp_user_roles' => array( 'administrator' => true ) ) as $key => $value ) {
			$GLOBALS[$key] = $value; $this->unavailable( $this->issue() ); unset( $GLOBALS[$key] );
		}
		$roles = $GLOBALS['wp_roles']; $GLOBALS['wp_roles'] = new stdClass(); $this->unavailable( $this->issue() ); $GLOBALS['wp_roles'] = $roles;
		$GLOBALS['current_user'] = new stdClass(); $this->unavailable( $this->issue() ); unset( $GLOBALS['current_user'] );
		$wpdb = $GLOBALS['wpdb']; foreach ( array( 'options', 'users', 'usermeta', 'base_prefix' ) as $key ) { $old = $wpdb->$key; $wpdb->$key = 'other'; $this->unavailable( $this->issue() ); $wpdb->$key = $old; }
		$this->assertCount( 0, $this->calls( 'verify_nonce' ) );
		$this->unavailable( $this->issue() ); $this->assertCount( 1, $this->calls( 'verify_nonce' ) );
		$GLOBALS['wpdb'] = new wpdb(); $this->unavailable( $this->issue() ); $this->assertCount( 1, $this->calls( 'verify_nonce' ) );
		$GLOBALS['wpdb'] = $wpdb; $GLOBALS['alynt_core_request_test']['blog'] = 2; $this->unavailable( $this->issue() ); $this->assertCount( 1, $this->calls( 'verify_nonce' ) );
	}
	public function test_nonce_rechecks_fresh_session_user_and_role_definitions_including_stale_caches() {
		foreach ( array( 'session', 'user', 'role' ) as $kind ) {
			$s =& $GLOBALS['alynt_core_request_test']; foreach ( array( 'session', 'user', 'role' ) as $reset ) { $s[$reset . '_now'] = true; $s[$reset . '_cache'] = true; }
			$s['on']['verify_nonce'] = function () use ( $kind ) { $GLOBALS['alynt_core_request_test'][$kind . '_now'] = false; $GLOBALS['alynt_core_request_test'][$kind . '_cache'] = true; };
			$this->unavailable( $this->issue() ); $this->assertFalse( $s[$kind . '_now'] );
		}
		$this->assertCount( 3, $this->calls( 'verify_nonce' ) ); $this->assertCount( 0, $this->calls( 'query' ) );
		$deletes = array_column( $this->calls( 'cache_delete' ), 1 ); foreach ( array( array( 7, 'user_meta' ), array( 'wp_user_roles', 'options' ), array( 'alloptions', 'options' ), array( 'notoptions', 'options' ) ) as $expected ) $this->assertContains( $expected, $deletes );
	}
	public function test_mid_request_actor_cookie_and_table_changes_are_not_adopted() {
		foreach ( array( 'actor', 'cookie', 'table', 'method' ) as $kind ) {
			$GLOBALS['alynt_core_request_test']['actor'] = 7; $_COOKIE[LOGGED_IN_COOKIE] = 'unit-cookie'; $GLOBALS['wpdb']->options = 'wp_options'; $_SERVER['REQUEST_METHOD'] = 'POST';
			$GLOBALS['alynt_core_request_test']['on']['verify_nonce'] = function () use ( $kind ) {
				if ( 'actor' === $kind ) $GLOBALS['alynt_core_request_test']['actor'] = 8;
				if ( 'cookie' === $kind ) $_COOKIE[LOGGED_IN_COOKIE] = 'new-session';
				if ( 'table' === $kind ) $GLOBALS['wpdb']->options = 'other_options';
				if ( 'method' === $kind ) $_SERVER['REQUEST_METHOD'] = 'GET';
			};
			$before = count( $this->calls( 'validate_cookie' ) ); $this->unavailable( $this->issue() ); $this->assertCount( $before + 1, $this->calls( 'validate_cookie' ) );
		}
	}
	public function test_original_window_and_separate_read_nonce_never_replay_a_command() {
		foreach ( array( array( time() - 301, time() - 1 ), array( time() + 100, time() + 300 ) ) as $window ) {
			$e = $this->command_form(); $e['issued_at'] = (string) $window[0]; $e['expires_at'] = (string) $window[1]; $this->unavailable( $this->owner->consume( 'POST', $e ) );
		}
		$this->assertCount( 0, $this->calls( 'verify_nonce' ) ); $e = $this->command_form( 'receipt_read' ); $typed = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::parse( $e )['envelope'];
		$GLOBALS['alynt_core_request_test']['expected_action'] = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::action( $typed ); $GLOBALS['alynt_core_request_test']['nonce_result'] = 2;
		$this->unavailable( $this->owner->consume( 'POST', $e ) ); $this->assertCount( 2, $this->calls( 'validate_cookie' ) ); $this->assertCount( 1, $this->calls( 'verify_nonce' ) );
		$GLOBALS['alynt_core_request_test']['expected_action'] = 'alynt_drime_backups_closed_writer_fixture_receipt_v1:fake'; $this->unavailable( $this->owner->consume( 'POST', $e ) ); $this->assertCount( 3, $this->calls( 'validate_cookie' ) );
	}
	public function test_missing_array_or_oversized_cookie_and_backward_clock_are_closed() {
		foreach ( array( null, '', array(), str_repeat( 'x', 4097 ) ) as $cookie ) { $_COOKIE[LOGGED_IN_COOKIE] = $cookie; $this->unavailable( $this->issue() ); }
		$_COOKIE[LOGGED_IN_COOKIE] = 'unit-cookie'; $r = new ReflectionObject( $this->owner ); $p = $r->getProperty( 'last_clock' ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $p->setValue( $this->owner, time() + 10 );
		$this->unavailable( $this->issue() ); $this->assertCount( 0, $this->calls( 'verify_nonce' ) ); $this->assertCount( 0, $this->calls( 'validate_cookie' ) );
	}
	/** @dataProvider unsupported_context */
	public function test_shortinit_and_cli_are_not_promoted_into_browser_request_context( $constant ) {
		define( $constant, true ); $this->unavailable( $this->issue() ); $this->assertCount( 0, $this->calls( 'verify_nonce' ) ); $this->assertCount( 0, $this->calls( 'validate_cookie' ) );
	}
	public static function unsupported_context() { return array( array( 'SHORTINIT' ), array( 'WP_CLI' ) ); }
}
