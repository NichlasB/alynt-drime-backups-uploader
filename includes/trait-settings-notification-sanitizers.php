<?php
/**
 * Settings notification sanitizers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizes notification recipients and diagnostics severity levels.
 *
 * @since 0.5.22
 */
trait Alynt_Drime_Backups_Uploader_Settings_Notification_Sanitizers {
	/**
	 * Returns the default failure notification recipients.
	 *
	 * @return string
	 */
	private static function default_failure_email_recipients() {
		$admin_email = function_exists( 'get_option' ) ? get_option( 'admin_email', '' ) : '';

		return is_string( $admin_email ) ? self::sanitize_single_email( $admin_email ) : '';
	}

	/**
	 * Sanitizes comma- or newline-separated email recipients.
	 *
	 * @param string $recipients Raw recipients.
	 * @return string
	 */
	private function sanitize_email_recipients( $recipients ) {
		$recipients = preg_split( '/[\r\n,]+/', $recipients );
		$valid      = array();

		foreach ( (array) $recipients as $recipient ) {
			$recipient = self::sanitize_single_email( trim( (string) $recipient ) );
			if ( '' !== $recipient && self::is_valid_email( $recipient ) ) {
				$valid[] = $recipient;
			}
		}

		return implode( "\n", array_values( array_unique( $valid ) ) );
	}

	/**
	 * Sanitizes a single email value with a test-safe fallback.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private static function sanitize_single_email( $email ) {
		return trim( preg_replace( '/[\r\n]+/', '', (string) $email ) );
	}

	/**
	 * Validates a single email value with a conservative fallback.
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	private static function is_valid_email( $email ) {
		if ( function_exists( 'is_email' ) ) {
			return (bool) is_email( $email );
		}

		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
	}

	/**
	 * Sanitizes a severity level.
	 *
	 * @param string $level Level.
	 * @return string
	 */
	private function sanitize_level( $level ) {
		$level = sanitize_key( $level );

		return array_key_exists( $level, self::severity_levels() ) ? $level : 'warning';
	}
	/**
	 * Returns severity levels in ascending order.
	 *
	 * @return array<string,int>
	 *
	 * @since 0.1.0
	 */
	public static function severity_levels() {
		return array(
			'debug'    => 100,
			'info'     => 200,
			'warning'  => 300,
			'error'    => 400,
			'critical' => 500,
		);
	}
}
