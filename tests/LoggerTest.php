<?php
/**
 * Logger tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_logger_redacts_sensitive_substrings_in_scalar_values() {
		$options = array(
			Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME => array(
				'diagnostics_enabled'   => true,
				'diagnostics_min_level' => 'debug',
				'diagnostics_retention' => 25,
			),
		);

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

		Functions\when( 'sanitize_key' )->alias(
			function ( $value ) {
				return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) );
			}
		);

		Functions\when( 'sanitize_text_field' )->alias(
			function ( $value ) {
				return trim( wp_strip_all_tags( (string) $value ) );
			}
		);

		Functions\when( 'wp_strip_all_tags' )->alias(
			function ( $value ) {
				return strip_tags( (string) $value );
			}
		);

		$logger = new Alynt_Drime_Backups_Uploader_Logger( new Alynt_Drime_Backups_Uploader_Settings() );
		$logger->event(
			'external_api',
			'error',
			'request_failed',
			'Request failed.',
			array(
				'reason' => 'Bearer abc123 failed for https://uploads.example.test/signed?token=secret',
			)
		);

		$events = $options[ Alynt_Drime_Backups_Uploader_Logger::OPTION_NAME ];
		$this->assertSame( 'Bearer [redacted] failed for [redacted-url]', $events[0]['context']['reason'] );
	}
}
