<?php
/**
 * Remote action store tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests remote action store redaction and bounds.
 */
class RemoteActionStoreTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'sanitize_key' )->alias(
			function ( $value ) {
				return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) );
			}
		);
		Functions\when( 'sanitize_text_field' )->alias(
			function ( $value ) {
				return trim( (string) $value );
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_store_bounds_records_and_redacts_unsafe_summary_words() {
		$options = array();
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) use ( &$options ) {
				return array_key_exists( $name, $options ) ? $options[ $name ] : $default;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) use ( &$options ) {
				$options[ $name ] = $value;
				return true;
			}
		);

		$store = new Alynt_Drime_Backups_Uploader_Remote_Action_Store();

		for ( $i = 0; $i < 55; $i++ ) {
			$store->upsert_action(
				array(
					'action_id'                => sprintf( '11111111-1111-4111-8111-%012d', $i ),
					'action_type'              => 'scan_upload_now',
					'dashboard_site_public_id' => '00000000-0000-4000-8000-000000000000',
					'idempotency_key'          => 'adb-act-' . $i,
					'request_hash'             => hash( 'sha256', 'request-' . $i ),
				),
				'succeeded',
				'action_scan_completed',
				'Path, token, signature, private key, package and Drime URL must not persist.',
				array(
					'found'  => 2,
					'queued' => 1,
				)
			);
		}

		$state = $options[ Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME ];

		$this->assertCount( 50, $state['records'] );
		$this->assertStringNotContainsString( 'token', strtolower( $state['latest_action']['summary'] ) );
		$this->assertStringNotContainsString( 'signature', strtolower( $state['latest_action']['summary'] ) );
		$this->assertStringNotContainsString( 'drime', strtolower( $state['latest_action']['summary'] ) );
		$this->assertSame( 2, $state['latest_action']['counts']['found'] );
		$this->assertSame( 1, $state['latest_action']['counts']['queued'] );
	}
}
