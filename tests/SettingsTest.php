<?php
/**
 * Settings tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_email' )->alias(
			function ( $value ) {
				return trim( (string) $value );
			}
		);
		Functions\when( 'is_email' )->alias(
			function ( $value ) {
				return false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
			}
		);
		Functions\when( 'sanitize_key' )->alias(
			function ( $value ) {
				return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) );
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_update_can_confirm_persisted_settings() {
		$options  = array();
		$settings = $this->settings_with_options( $options );

		$saved = $settings->update(
			array(
				'api_token' => 'new-token',
			)
		);

		$this->assertTrue( $settings->is_persisted( $saved ) );
	}

	public function test_update_can_detect_failed_persistence() {
		$options  = array();
		$settings = $this->settings_with_options( $options, false );

		$saved = $settings->update(
			array(
				'api_token' => 'new-token',
			)
		);

		$this->assertFalse( $settings->is_persisted( $saved ) );
	}

	public function test_multipart_chunk_size_is_clamped_to_supported_range() {
		$options  = array();
		$settings = $this->settings_with_options( $options );

		$low = $settings->update(
			array(
				'multipart_chunk_size_mb' => 1,
			)
		);

		$high = $settings->update(
			array(
				'multipart_chunk_size_mb' => 999,
			)
		);

		$this->assertSame( 5, $low['multipart_chunk_size_mb'] );
		$this->assertSame( 256, $high['multipart_chunk_size_mb'] );
	}

	public function test_multipart_chunk_size_accepts_supported_validation_targets() {
		$options  = array();
		$settings = $this->settings_with_options( $options );

		foreach ( array( 64, 128, 192, 256 ) as $chunk_size ) {
			$saved = $settings->update(
				array(
					'multipart_chunk_size_mb' => $chunk_size,
				)
			);

			$this->assertSame( $chunk_size, $saved['multipart_chunk_size_mb'] );
		}

		$this->assertSame( 256 * 1048576, Alynt_Drime_Backups_Uploader_Drime_Client::MAX_MULTIPART_CHUNK_SIZE );
	}

	public function test_remote_retention_days_are_clamped_to_supported_range() {
		$options  = array();
		$settings = $this->settings_with_options( $options );

		$low = $settings->update(
			array(
				'remote_retention_enabled' => '1',
				'remote_retention_days'    => -5,
			)
		);

		$high = $settings->update(
			array(
				'remote_retention_days' => 999,
			)
		);

		$this->assertTrue( $low['remote_retention_enabled'] );
		$this->assertSame( 1, $low['remote_retention_days'] );
		$this->assertSame( 365, $high['remote_retention_days'] );
	}

	public function test_failure_email_recipients_are_normalized() {
		$options  = array();
		$settings = $this->settings_with_options( $options );

		$saved = $settings->update(
			array(
				'failure_email_enabled'    => '1',
				'failure_email_recipients' => "admin@example.test, bad-address\nops@example.test\nadmin@example.test",
			)
		);

		$this->assertTrue( $saved['failure_email_enabled'] );
		$this->assertSame( "admin@example.test\nops@example.test", $saved['failure_email_recipients'] );
	}

	public function test_server_cron_expected_is_sanitized_as_boolean() {
		$options  = array();
		$settings = $this->settings_with_options( $options );

		$enabled = $settings->update(
			array(
				'server_cron_expected' => '1',
			)
		);

		$disabled = $settings->update( array() );

		$this->assertTrue( $enabled['server_cron_expected'] );
		$this->assertFalse( $disabled['server_cron_expected'] );
	}

	public function test_server_outbox_path_is_sanitized() {
		$options  = array();
		$settings = $this->settings_with_options( $options );

		$saved = $settings->update(
			array(
				'server_outbox_path' => ' /var/www/example.com/private/backups ',
			)
		);

		$this->assertSame( ' /var/www/example.com/private/backups ', $saved['server_outbox_path'] );
	}

	public function test_site_uuid_is_generated_and_persisted() {
		$options  = array();
		$settings = $this->settings_with_options( $options );

		$uuid = $settings->site_uuid();

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $uuid );
		$this->assertSame( $uuid, $options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['site_uuid'] );
		$this->assertSame( $uuid, $settings->site_uuid() );
	}

	public function test_update_preserves_existing_site_uuid() {
		$uuid    = '12345678-1234-4234-9234-123456789abc';
		$options = array(
			Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME => array(
				'site_uuid' => $uuid,
			),
		);
		$settings = $this->settings_with_options( $options );

		$saved = $settings->update(
			array(
				'api_token' => 'new-token',
			)
		);

		$this->assertSame( $uuid, $saved['site_uuid'] );
	}

	public function test_folder_browser_metadata_is_sanitized_and_cleared_with_empty_parent() {
		$options  = array();
		$settings = $this->settings_with_options( $options );

		$saved = $settings->update(
			array(
				'parent_folder_id'           => '123',
				'parent_folder_hash'         => 'hash-123/../bad',
				'parent_folder_display_path' => 'General//Files//Backups',
			)
		);

		$cleared = $settings->update(
			array(
				'parent_folder_id'           => '',
				'parent_folder_hash'         => 'hash-123',
				'parent_folder_display_path' => 'General/Files/Backups',
			)
		);

		$this->assertSame( '123', $saved['parent_folder_id'] );
		$this->assertSame( 'hash-123bad', $saved['parent_folder_hash'] );
		$this->assertSame( 'General/Files/Backups', $saved['parent_folder_display_path'] );
		$this->assertSame( '', $cleared['parent_folder_hash'] );
		$this->assertSame( '', $cleared['parent_folder_display_path'] );
	}

	public function test_workspace_change_clears_saved_parent_folder_selection() {
		$options  = array(
			Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME => array(
				'workspace_id'                => 42,
				'parent_folder_id'            => '123',
				'parent_folder_hash'          => 'hash-123',
				'parent_folder_display_path'  => 'General/Files/Backups',
			),
		);
		$settings = $this->settings_with_options( $options );

		$saved = $settings->update(
			array(
				'workspace_id'                => 1873,
				'parent_folder_id'            => '123',
				'parent_folder_hash'          => 'hash-123',
				'parent_folder_display_path'  => 'General/Files/Backups',
			)
		);

		$this->assertSame( 1873, $saved['workspace_id'] );
		$this->assertSame( '', $saved['parent_folder_id'] );
		$this->assertSame( '', $saved['parent_folder_hash'] );
		$this->assertSame( '', $saved['parent_folder_display_path'] );
	}

	public function test_relative_path_rejects_excessive_depth_or_segment_length() {
		$options  = array();
		$settings = $this->settings_with_options( $options );

		$deep = $settings->update(
			array(
				'relative_path' => '/' . implode( '/', array_fill( 0, Alynt_Drime_Backups_Uploader_Settings::MAX_RELATIVE_PATH_SEGMENTS + 1, 'folder' ) ),
			)
		);

		$long = $settings->update(
			array(
				'relative_path' => '/' . str_repeat( 'a', Alynt_Drime_Backups_Uploader_Settings::MAX_RELATIVE_PATH_SEGMENT_CHARS + 1 ),
			)
		);

		$this->assertSame( '', $deep['relative_path'] );
		$this->assertSame( '', $long['relative_path'] );
	}

	/**
	 * Creates settings with mocked option storage.
	 *
	 * @param array<string,mixed> $options Option storage.
	 * @param bool                $persist Whether update_option should persist.
	 * @return Alynt_Drime_Backups_Uploader_Settings
	 */
	private function settings_with_options( array &$options, $persist = true ) {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) use ( &$options ) {
				return array_key_exists( $name, $options ) ? $options[ $name ] : $default;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) use ( &$options, $persist ) {
				if ( $persist ) {
					$options[ $name ] = $value;
				}

				return $persist;
			}
		);

		return new Alynt_Drime_Backups_Uploader_Settings();
	}
}
