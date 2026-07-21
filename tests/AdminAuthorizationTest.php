<?php
/**
 * Admin action authorization tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class Alynt_Drime_Backups_Uploader_Test_Admin_Action_Harness {
	use Alynt_Drime_Backups_Uploader_Plugin_Admin_Actions;

	public function verify_admin( $action ) {
		$this->verify_admin_action( $action );
	}

	public function verify_ajax() {
		$this->verify_ajax_action();
	}
}

class AdminAuthorizationTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'esc_html__' )->alias(
			function ( $message ) {
				return $message;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_admin_action_denies_missing_capability_before_nonce_check() {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( false );
		Functions\expect( 'check_admin_referer' )->never();
		Functions\expect( 'wp_die' )->once()->andReturnUsing(
			function () {
				throw new RuntimeException( 'admin-action-denied' );
			}
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'admin-action-denied' );

		( new Alynt_Drime_Backups_Uploader_Test_Admin_Action_Harness() )->verify_admin( 'alynt_test_action' );
	}

	public function test_admin_action_requires_its_nonce_for_authorized_user() {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( true );
		Functions\expect( 'wp_die' )->never();
		Functions\expect( 'check_admin_referer' )->once()->with( 'alynt_test_action' )->andReturn( 1 );

		( new Alynt_Drime_Backups_Uploader_Test_Admin_Action_Harness() )->verify_admin( 'alynt_test_action' );

		$this->addToAssertionCount( 1 );
	}

	public function test_ajax_action_denies_missing_capability_before_nonce_check() {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( false );
		Functions\expect( 'check_ajax_referer' )->never();
		$this->expect_json_error( 'ajax-capability-denied' );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'ajax-capability-denied' );

		( new Alynt_Drime_Backups_Uploader_Test_Admin_Action_Harness() )->verify_ajax();
	}

	public function test_ajax_action_rejects_invalid_nonce() {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( true );
		Functions\expect( 'check_ajax_referer' )
			->once()
			->with( 'alynt_drime_backups_folder_browser', 'nonce', false )
			->andReturn( false );
		$this->expect_json_error( 'ajax-nonce-denied' );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'ajax-nonce-denied' );

		( new Alynt_Drime_Backups_Uploader_Test_Admin_Action_Harness() )->verify_ajax();
	}

	public function test_ajax_action_accepts_authorized_valid_request() {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( true );
		Functions\expect( 'check_ajax_referer' )
			->once()
			->with( 'alynt_drime_backups_folder_browser', 'nonce', false )
			->andReturn( 1 );
		Functions\expect( 'wp_send_json_error' )->never();

		( new Alynt_Drime_Backups_Uploader_Test_Admin_Action_Harness() )->verify_ajax();

		$this->addToAssertionCount( 1 );
	}

	private function expect_json_error( $exception_message ) {
		Functions\expect( 'wp_send_json_error' )
			->once()
			->with( Mockery::type( 'array' ), 403 )
			->andReturnUsing(
				function () use ( $exception_message ) {
					throw new RuntimeException( $exception_message );
				}
			);
	}
}
