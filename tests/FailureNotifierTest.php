<?php
/**
 * Failure notifier tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class FailureNotifierTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'sanitize_key' )->alias(
			function ( $key ) {
				return strtolower( preg_replace( '/[^a-zA-Z0-9_\\-]/', '', (string) $key ) );
			}
		);
		Functions\when( 'sanitize_text_field' )->alias(
			function ( $value ) {
				return is_scalar( $value ) ? trim( (string) $value ) : '';
			}
		);
		Functions\when( 'wp_strip_all_tags' )->alias(
			function ( $value ) {
				return strip_tags( (string) $value );
			}
		);
		Functions\when( 'is_email' )->alias(
			function ( $value ) {
				return false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
			}
		);
		Functions\when( 'get_bloginfo' )->alias(
			function ( $show ) {
				return 'name' === $show ? 'Example Site' : '6.5';
			}
		);
		Functions\when( 'home_url' )->alias(
			function ( $path = '' ) {
				return 'https://example.test' . $path;
			}
		);
		Functions\when( 'admin_url' )->alias(
			function ( $path = '' ) {
				return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			function ( array $args, $url ) {
				return $url . '?' . http_build_query( $args );
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_failure_notification_sends_plain_text_email() {
		$options  = $this->base_options( true );
		$mail     = array();
		$notifier = $this->notifier_with_options( $options, $mail, true );

		$sent = $notifier->notify_failure( $this->queue_item(), 'manual_upload_failed', 'Failed at C:\\private\\backup.zip and https://signed.example.test/upload', 2 );

		$this->assertTrue( $sent );
		$this->assertCount( 1, $mail );
		$this->assertSame( array( 'admin@example.test', 'ops@example.test' ), $mail[0]['to'] );
		$this->assertSame( '[Example Site] Drime backup upload failed', $mail[0]['subject'] );
		$this->assertStringContainsString( 'Backup file: backup.zip', $mail[0]['message'] );
		$this->assertStringContainsString( 'Failure status: Manual Upload Failed', $mail[0]['message'] );
		$this->assertStringContainsString( 'Attempts: 2', $mail[0]['message'] );
		$this->assertStringNotContainsString( 'C:\\private', $mail[0]['message'] );
		$this->assertStringNotContainsString( 'https://signed.example.test', $mail[0]['message'] );
	}

	public function test_failure_notification_is_deduplicated_by_signature_and_state() {
		$options  = $this->base_options( true );
		$mail     = array();
		$notifier = $this->notifier_with_options( $options, $mail, true );

		$notifier->notify_failure( $this->queue_item(), 'retry_limit_reached', 'Retry limit reached.', 3 );
		$notifier->notify_failure( $this->queue_item(), 'retry_limit_reached', 'Retry limit reached.', 3 );

		$this->assertCount( 1, $mail );
		$this->assertCount( 1, $options[ Alynt_Drime_Backups_Uploader_Failure_Notifier::OPTION_NAME ] );
	}

	public function test_disabled_failure_notification_does_not_send() {
		$options  = $this->base_options( false );
		$mail     = array();
		$notifier = $this->notifier_with_options( $options, $mail, true );

		$sent = $notifier->notify_failure( $this->queue_item(), 'manual_upload_failed', 'Failed.', 1 );

		$this->assertFalse( $sent );
		$this->assertSame( array(), $mail );
	}

	public function test_failed_mail_result_is_not_marked_as_sent() {
		$options  = $this->base_options( true );
		$mail     = array();
		$notifier = $this->notifier_with_options( $options, $mail, false );

		$sent = $notifier->notify_failure( $this->queue_item(), 'retry_limit_reached', 'Retry limit reached.', 3 );

		$this->assertFalse( $sent );
		$this->assertCount( 1, $mail );
		$this->assertSame( array(), $options[ Alynt_Drime_Backups_Uploader_Failure_Notifier::OPTION_NAME ] );
	}

	public function test_test_email_uses_saved_recipients_even_when_notifications_are_disabled() {
		$options  = $this->base_options( false );
		$mail     = array();
		$notifier = $this->notifier_with_options( $options, $mail, true );

		$result = $notifier->send_test();

		$this->assertTrue( $result );
		$this->assertCount( 1, $mail );
		$this->assertSame( '[Example Site] Drime backup test email', $mail[0]['subject'] );
	}

	/**
	 * Creates the notifier with mocked option and mail storage.
	 *
	 * @param array<string,mixed> $options Options.
	 * @param array<int,array<string,mixed>> $mail Captured mail.
	 * @param bool                $mail_result Mail result.
	 * @return Alynt_Drime_Backups_Uploader_Failure_Notifier
	 */
	private function notifier_with_options( array &$options, array &$mail, $mail_result ) {
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
		Functions\when( 'wp_mail' )->alias(
			function ( $to, $subject, $message, $headers ) use ( &$mail, $mail_result ) {
				$mail[] = compact( 'to', 'subject', 'message', 'headers' );
				return $mail_result;
			}
		);

		$settings = new Alynt_Drime_Backups_Uploader_Settings();

		return new Alynt_Drime_Backups_Uploader_Failure_Notifier( $settings, new Alynt_Drime_Backups_Uploader_Logger( $settings ) );
	}

	/**
	 * Builds base options.
	 *
	 * @param bool $enabled Whether failure emails are enabled.
	 * @return array<string,mixed>
	 */
	private function base_options( $enabled ) {
		return array(
			Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME => array(
				'failure_email_enabled'    => $enabled,
				'failure_email_recipients' => "admin@example.test\nops@example.test",
				'diagnostics_enabled'      => false,
			),
			Alynt_Drime_Backups_Uploader_Failure_Notifier::OPTION_NAME => array(),
		);
	}

	/**
	 * Builds a queue item.
	 *
	 * @return array<string,mixed>
	 */
	private function queue_item() {
		return array(
			'signature' => 'sig-one',
			'path'      => 'C:\\backups\\backup.zip',
			'name'      => 'backup.zip',
		);
	}
}
