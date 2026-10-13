<?php
/**
 * Remote action persistence failure regression tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that an unsuccessful option write never grants action authority.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class RemoteActionStorePersistenceTest extends TestCase {

	private $options = array();
	private $write_mode = 'success';

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'sanitize_key' )->alias( function ( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $value ) ); } );
		Functions\when( 'sanitize_text_field' )->alias( function ( $value ) { return trim( $value ); } );
		Functions\when( 'get_option' )->alias( function ( $name, $default = array() ) { return isset( $this->options[ $name ] ) ? $this->options[ $name ] : $default; } );
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) {
				if ( 'failed' === $this->write_mode ) {
					return false;
				}
				$this->options[ $name ] = $value;
				if ( 'overwritten' === $this->write_mode ) {
					$this->options[ $name ]['running_lock']['action_id'] = '22222222-2222-4222-8222-222222222222';
				}
				return true;
			}
		);
		Functions\when( 'wp_cache_delete' )->justReturn( true );
		Functions\when( 'wp_cache_set' )->justReturn( true );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_failed_write_does_not_report_acquired_lock_or_fabricate_cached_state() {
		$this->write_mode = 'failed';
		Functions\expect( 'wp_cache_set' )->never();
		$store = new Alynt_Drime_Backups_Uploader_Remote_Action_Store();
		$this->assertFalse( $store->acquire_lock( '11111111-1111-4111-8111-111111111111' ) );
		$this->assertFalse( $store->has_active_lock() );
	}

	public function test_failed_write_does_not_return_an_accepted_record() {
		$this->write_mode = 'failed';
		$store = new Alynt_Drime_Backups_Uploader_Remote_Action_Store();
		$this->assertSame( array(), $store->upsert_action( $this->intent(), 'accepted', 'action_accepted', 'Accepted.' ) );
		$this->assertSame( array(), $store->record( $this->intent()['action_id'] ) );
	}

	public function test_successful_write_returns_record_and_round_trips_lock() {
		$store  = new Alynt_Drime_Backups_Uploader_Remote_Action_Store();
		$intent = $this->intent();
		$record = $store->upsert_action( $intent, 'accepted', 'action_accepted', 'Accepted.' );
		$this->assertSame( $record, $store->record( $intent['action_id'] ) );
		$this->assertTrue( $store->acquire_lock( $intent['action_id'] ) );
		$this->assertTrue( $store->has_active_lock() );
	}

	public function test_failed_release_does_not_fabricate_an_unlocked_state() {
		$store = new Alynt_Drime_Backups_Uploader_Remote_Action_Store();
		$store->acquire_lock( $this->intent()['action_id'] );
		$this->write_mode = 'failed';
		Functions\expect( 'wp_cache_set' )->never();
		$store->release_lock( $this->intent()['action_id'] );
		$this->assertTrue( $store->has_active_lock() );
	}

	public function test_readback_mismatch_does_not_grant_lock() {
		$this->write_mode = 'overwritten';
		$store = new Alynt_Drime_Backups_Uploader_Remote_Action_Store();
		$this->assertFalse( $store->acquire_lock( $this->intent()['action_id'] ) );
		$this->assertSame( '22222222-2222-4222-8222-222222222222', $store->get()['running_lock']['action_id'] );
	}

	private function intent() {
		return array(
			'action_id'                => '11111111-1111-4111-8111-111111111111',
			'action_type'              => 'scan_upload_now',
			'dashboard_site_public_id' => '00000000-0000-4000-8000-000000000000',
			'idempotency_key'          => 'adb-foundation-1',
			'request_hash'             => str_repeat( 'a', 64 ),
		);
	}
}
