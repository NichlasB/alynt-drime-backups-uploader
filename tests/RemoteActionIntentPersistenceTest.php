<?php
/**
 * Remote action acceptance must follow successful audit persistence.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real controller/store boundary with a verified intent.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class RemoteActionIntentPersistenceTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'sanitize_key' )->alias( function ( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $value ) ); } );
		Functions\when( 'sanitize_text_field' )->alias( function ( $value ) { return trim( $value ); } );
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->justReturn( false );
		Functions\when( 'wp_cache_delete' )->justReturn( true );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_failed_audit_write_rejects_without_scheduling_worker() {
		Functions\expect( 'wp_schedule_single_event' )->never();
		$verifier = $this->createMock( Alynt_Drime_Backups_Uploader_Remote_Action_Verifier::class );
		$verifier->method( 'verify' )->willReturn(
			array(
				'action_id'                => '11111111-1111-4111-8111-111111111111',
				'action_type'              => 'schedule_apply',
				'dashboard_site_public_id' => '00000000-0000-4000-8000-000000000000',
				'idempotency_key'          => 'adb-foundation-1',
				'request_hash'             => str_repeat( 'a', 64 ),
			)
		);
		$controller = new Alynt_Drime_Backups_Uploader_Dashboard_Action_Intents_REST_Controller( $verifier, new Alynt_Drime_Backups_Uploader_Remote_Action_Store() );
		$response = $controller->handle_action_intent( new stdClass() );
		$this->assertSame( 500, $response['status'] );
		$this->assertSame( 'rejected', $response['data']['state'] );
		$this->assertSame( 'action_state_store_failed', $response['data']['code'] );
	}
}
