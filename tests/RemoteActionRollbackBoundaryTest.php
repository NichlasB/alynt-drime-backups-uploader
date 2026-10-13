<?php
/**
 * Reserved rollback execution boundary regression tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Keeps rollback unavailable at both parsing and stored worker boundaries.
 */
class RemoteActionRollbackBoundaryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'sanitize_key' )->alias( function ( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $value ) ); } );
		Functions\when( 'sanitize_text_field' )->alias( function ( $value ) { return trim( $value ); } );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_reserved_action_name_is_rejected_by_body_parser() {
		$verifier = new Alynt_Drime_Backups_Uploader_Remote_Action_Verifier( $this->createMock( Alynt_Drime_Backups_Uploader_Dashboard_Connection::class ) );
		$parser = new ReflectionMethod( $verifier, 'parse_body' );
		if ( PHP_VERSION_ID < 80100 ) {
			$parser->setAccessible( true );
		}
		$result = $parser->invoke(
			$verifier,
			json_encode(
				array(
					'protocol_version'         => 2,
					'action_id'                => '11111111-1111-4111-8111-111111111111',
					'dashboard_site_public_id' => '22222222-2222-4222-8222-222222222222',
					'site_uuid'                => '33333333-3333-4333-8333-333333333333',
					'action_type'              => 'schedule_rollback',
					'idempotency_key'          => 'adb-foundation-rollback',
					'requested_at'             => gmdate( 'c' ),
					'expires_at'               => gmdate( 'c', time() + 300 ),
				)
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'action_intent_invalid', $result->get_error_code() );
	}

	public function test_injected_stored_rollback_does_not_fall_through_to_scan_upload() {
		$record = array( 'action_id' => '11111111-1111-4111-8111-111111111111', 'action_type' => 'schedule_rollback' );
		$store = $this->createMock( Alynt_Drime_Backups_Uploader_Remote_Action_Store::class );
		$store->method( 'record' )->willReturn( $record );
		$store->expects( $this->never() )->method( 'acquire_lock' );
		$store->expects( $this->once() )->method( 'upsert_action' )->with( $record, 'unsupported', 'action_unsupported', $this->isType( 'string' ) );
		$plugin = $this->createMock( Alynt_Drime_Backups_Uploader_Plugin::class );
		$plugin->expects( $this->never() )->method( 'scan_and_queue' );
		$plugin->expects( $this->never() )->method( 'cron' );
		$plugin->expects( $this->never() )->method( 'cron_health' );
		$worker = new Alynt_Drime_Backups_Uploader_Remote_Action_Worker( $plugin, $store );
		$worker->handle( $record['action_id'] );
	}
}
