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

	public function test_schedule_apply_rollback_metadata_is_sanitized_and_never_available() {
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
		$store->upsert_action(
			array(
				'action_id'                => '22222222-2222-4222-8222-222222222222',
				'action_type'              => 'schedule_apply',
				'dashboard_site_public_id' => '00000000-0000-4000-8000-000000000000',
				'idempotency_key'          => 'adb-apply-1',
				'request_hash'             => str_repeat( 'a', 64 ),
			),
			'succeeded',
			'schedule_apply_succeeded',
			'Schedule apply completed for Alynt scan/upload.',
			array(),
			0,
			array(),
			array(
				'schedule_id'          => 'alynt_scan_upload',
				'owner'                => 'alynt_uploader',
				'previous_cadence'     => 'every_15_minutes',
				'applied_cadence'      => 'every_30_minutes',
				'previous_next_run_at' => '2026-06-25T16:45:00+00:00',
				'applied_next_run_at'  => '2026-06-25T17:00:00+00:00',
				'changed'              => true,
				'rollback_available'   => true,
				'rollback_metadata'    => array(
					'captured'                            => true,
					'available'                           => true,
					'reason'                              => 'schedule_rollback_runtime_not_implemented',
					'source_action_id'                    => '22222222-2222-4222-8222-222222222222',
					'source_preview_action_id'            => '11111111-1111-4111-8111-111111111111',
					'schedule_id'                         => 'alynt_scan_upload',
					'owner'                               => 'alynt_uploader',
					'previous_cadence'                    => 'every_15_minutes',
					'applied_cadence'                     => 'every_30_minutes',
					'previous_next_run_at'                => '2026-06-25T16:45:00+00:00',
					'applied_next_run_at'                 => '2026-06-25T17:00:00+00:00',
					'current_schedule_fingerprint_before' => str_repeat( 'b', 64 ),
					'current_schedule_fingerprint_after'  => str_repeat( 'c', 64 ),
					'rollback_metadata_fingerprint'       => str_repeat( 'd', 64 ),
					'captured_at'                         => '2026-06-25T16:45:01+00:00',
					'expires_at'                          => '2026-06-25T17:45:01+00:00',
				),
			)
		);

		$metadata = $options[ Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME ]['latest_action']['schedule_apply']['rollback_metadata'];

		$this->assertFalse( $options[ Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME ]['latest_action']['schedule_apply']['rollback_available'] );
		$this->assertTrue( $metadata['captured'] );
		$this->assertFalse( $metadata['available'] );
		$this->assertSame( 'schedule_rollback_runtime_not_implemented', $metadata['reason'] );
		$this->assertSame( str_repeat( 'b', 64 ), $metadata['current_schedule_fingerprint_before'] );
		$this->assertSame( str_repeat( 'c', 64 ), $metadata['current_schedule_fingerprint_after'] );
		$this->assertSame( str_repeat( 'd', 64 ), $metadata['rollback_metadata_fingerprint'] );
	}

	public function test_schedule_rollback_preview_is_sanitized_and_never_apply_supported() {
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
		$store->upsert_action(
			array(
				'action_id'                => '33333333-3333-4333-8333-333333333333',
				'action_type'              => 'schedule_rollback_preview',
				'dashboard_site_public_id' => '00000000-0000-4000-8000-000000000000',
				'idempotency_key'          => 'adb-rollback-preview-1',
				'request_hash'             => str_repeat( 'a', 64 ),
			),
			'succeeded',
			'schedule_rollback_preview_ready',
			'Schedule rollback preview is ready. No schedule was changed.',
			array(),
			0,
			array(),
			array(),
			array(
				'preview_action_id'                  => '33333333-3333-4333-8333-333333333333',
				'preview_fingerprint'               => str_repeat( 'e', 64 ),
				'source_apply_action_id'             => '22222222-2222-4222-8222-222222222222',
				'rollback_metadata_fingerprint'      => str_repeat( 'd', 64 ),
				'schedule_id'                        => 'alynt_scan_upload',
				'label'                              => 'Alynt scan/upload',
				'owner'                              => 'alynt_uploader',
				'capability_version'                 => 1,
				'current_cadence'                    => 'every_30_minutes',
				'applied_cadence'                    => 'every_30_minutes',
				'rollback_cadence'                   => 'every_15_minutes',
				'current_next_run_at'                => '2026-06-25T17:00:00+00:00',
				'rollback_next_run_estimate_at'      => '2026-06-25T17:15:00+00:00',
				'current_schedule_fingerprint'       => str_repeat( 'c', 64 ),
				'expected_current_schedule_fingerprint' => str_repeat( 'c', 64 ),
				'previous_schedule_fingerprint'      => str_repeat( 'b', 64 ),
				'preview_created_at'                 => '2026-06-25T17:00:01+00:00',
				'preview_expires_at'                 => '2026-06-25T17:15:01+00:00',
				'would_change'                       => true,
				'rollback_apply_supported'           => true,
				'rollback_supported'                 => true,
				'warnings'                           => array( 'auto_scan_disabled', 'manual_review' ),
			)
		);

		$preview = $options[ Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME ]['latest_action']['schedule_rollback_preview'];

		$this->assertSame( 'schedule_rollback_preview', $options[ Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME ]['latest_action']['action_type'] );
		$this->assertSame( 'every_15_minutes', $preview['rollback_cadence'] );
		$this->assertFalse( $preview['rollback_apply_supported'] );
		$this->assertFalse( $preview['rollback_supported'] );
		$this->assertSame( array( 'auto_scan_disabled', 'manual_review' ), $preview['warnings'] );
	}
}
