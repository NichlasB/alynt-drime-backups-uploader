<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/FixedCoreRequestFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class FixedCoreRequestLifetimeTest extends TestCase {
	use Alynt_Test_Fixed_Core_Request_Fixture;
	/** @dataProvider failure_stages */
	public function test_every_throw_redacts_and_clears_private_lifetime_and_role_view( $stage ) {
		$roles = $GLOBALS['wp_roles']; $GLOBALS['alynt_core_request_test']['throw'] = $stage; $this->unavailable( $this->issue() ); $this->assertSame( $roles, $GLOBALS['wp_roles'] );
		$GLOBALS['alynt_core_request_test']['throw'] = ''; $GLOBALS['alynt_core_request_test']['calls'] = array(); $this->unavailable( $this->issue() ); $this->assertCount( 2, $this->calls( 'validate_cookie' ) );
	}
	public static function failure_stages() { return array_map( function ( $v ) { return array( $v ); }, array( 'blog', 'parse_cookie', 'clean_user', 'cache_delete', 'validate_cookie', 'roles', 'user', 'capability', 'logged_in', 'verify_nonce' ) ); }
	public function test_reentrant_core_hook_cannot_reenter_and_lifetime_is_not_retained_after_return() {
		$nested = null; $GLOBALS['alynt_core_request_test']['on']['verify_nonce'] = function () use ( &$nested ) { $nested = $this->owner->consume( 'POST', $this->command_form() ); };
		$this->unavailable( $this->issue() ); $this->unavailable( $nested ); $this->assertCount( 1, $this->calls( 'verify_nonce' ) );
		$this->unavailable( $this->issue() ); $this->assertCount( 2, $this->calls( 'verify_nonce' ) );
		$r = new ReflectionObject( $this->owner ); $p = $r->getProperty( 'active' ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $this->assertFalse( $p->getValue( $this->owner ) );
		$this->assertFalse( $r->hasProperty( 'nonce' ) ); $this->assertFalse( $r->hasProperty( 'cookie' ) ); $this->assertFalse( $r->hasMethod( 'within_fixture' ) );
	}
	public function test_owner_cannot_be_cloned_serialized_or_constructed_from_restoration_data() {
		foreach ( array( 'clone', 'serialize', 'restore', 'wakeup' ) as $operation ) {
			$threw = false;
			try {
				if ( 'clone' === $operation ) { $copy = clone $this->owner; }
				if ( 'serialize' === $operation ) serialize( $this->owner );
				if ( 'restore' === $operation ) { $class = get_class( $this->owner ); unserialize( 'O:' . strlen( $class ) . ':"' . $class . '":0:{}' ); }
				if ( 'wakeup' === $operation ) $this->owner->__wakeup();
			} catch ( Throwable $error ) { $threw = true; }
			$this->assertTrue( $threw );
		}
		$this->assertSame( array(), $GLOBALS['alynt_core_request_test']['calls'] );
	}
	public function test_invalid_purposes_methods_provider_flags_and_objects_do_not_reach_core() {
		foreach ( array( 'grant', 'enable', 'activate', 'rollback', 'dispatch', 'rebind', 'finish', 'complete_disable' ) as $purpose ) { $f = $this->issuance_form(); $f['purpose'] = $purpose; $this->unavailable( $this->owner->issue( 'POST', $f ) ); $this->unavailable( $this->owner->consume( 'POST', $f ) ); }
		$this->unavailable( $this->owner->issue( 'GET', $this->issuance_form() ) ); $this->unavailable( $this->owner->issue( 'POST', $this->issuance_form(), true ) );
		$_SERVER['REQUEST_METHOD'] = 'GET'; $this->unavailable( $this->issue() ); $_SERVER['REQUEST_METHOD'] = 'POST';
		$o = new class implements JsonSerializable { public $calls = 0; public function jsonSerialize(): array { ++$this->calls; return array(); } }; $this->unavailable( $this->owner->issue( 'POST', $o ) ); $this->assertSame( 0, $o->calls );
		$this->assertSame( array(), $GLOBALS['alynt_core_request_test']['calls'] );
	}
	public function test_callback_identity_change_before_nonce_is_detected_and_prior_global_view_restored() {
		$roles = $GLOBALS['wp_roles']; $GLOBALS['alynt_core_request_test']['on']['logged_in'] = function () { $GLOBALS['alynt_core_request_test']['actor'] = 8; };
		$this->unavailable( $this->issue() ); $this->assertCount( 0, $this->calls( 'verify_nonce' ) ); $this->assertSame( $roles, $GLOBALS['wp_roles'] );
	}
	public function test_distinct_current_administrators_can_be_checked_in_separate_lifetimes_but_neither_gets_permission() {
		$this->unavailable( $this->issue() ); $GLOBALS['alynt_core_request_test']['actor'] = 8; $GLOBALS['alynt_core_request_test']['cookie_actor'] = 8; $_COOKIE[LOGGED_IN_COOKIE] = 'unit-cookie-admin-two';
		$this->unavailable( $this->issue() ); $this->assertSame( array( 8, 'manage_options' ), $this->calls( 'capability' )[3][1] ); $this->assertCount( 0, $this->calls( 'query' ) );
	}
}
