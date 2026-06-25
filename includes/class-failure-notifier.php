<?php
/**
 * Failed upload email notifications.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends deduplicated failed-upload notifications through wp_mail().
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_Failure_Notifier {
	use Alynt_Drime_Backups_Uploader_Option_Storage;
	use Alynt_Drime_Backups_Uploader_Failure_Notifier_Content;

	const OPTION_NAME = 'alynt_drime_backups_failure_notifications';

	/**
	 * Settings.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Settings
	 */
	private $settings;

	/**
	 * Logger.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Settings $settings Settings.
	 * @param Alynt_Drime_Backups_Uploader_Logger   $logger Logger.
	 *
	 * @since 0.1.0
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Settings $settings, Alynt_Drime_Backups_Uploader_Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Sends a failed upload notification when enabled and not already sent.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param string              $failure_state Failure state.
	 * @param string              $reason Failure reason.
	 * @param int                 $attempts Attempt count.
	 * @return bool
	 *
	 * @since 0.1.0
	 */
	public function notify_failure( array $item, $failure_state, $reason, $attempts = 0 ) {
		$settings = $this->settings->get();
		if ( empty( $settings['failure_email_enabled'] ) ) {
			$this->log_skip( 'disabled', $item, $failure_state );
			return false;
		}

		$recipients = $this->recipients_from_settings( $settings );
		if ( empty( $recipients ) ) {
			$this->log_skip( 'no_recipients', $item, $failure_state );
			return false;
		}

		$key = $this->dedupe_key( $item, $failure_state );
		if ( $this->already_sent( $key ) ) {
			$this->log_skip( 'duplicate', $item, $failure_state );
			return false;
		}

		return $this->send_failure_email( $recipients, $item, $failure_state, $reason, $attempts, $key );
	}

	/**
	 * Sends a test notification to the saved recipients.
	 *
	 * @return true|WP_Error
	 *
	 * @since 0.1.0
	 */
	public function send_test() {
		$recipients = $this->recipients();
		if ( empty( $recipients ) ) {
			return new WP_Error( 'alynt_drime_no_failure_email_recipients', __( 'No failure notification recipients are configured.', 'alynt-drime-backups-uploader' ) );
		}

		$sent = wp_mail( $recipients, $this->subject( __( 'Drime backup test email', 'alynt-drime-backups-uploader' ) ), $this->test_message(), $this->headers() );
		$this->logger->event( 'notification', $sent ? 'info' : 'error', $sent ? 'failure_email_test_sent' : 'failure_email_test_failed', $sent ? 'Failure notification test email sent.' : 'Failure notification test email failed.' );

		return $sent ? true : new WP_Error( 'alynt_drime_failure_email_test_failed', __( 'WordPress could not hand the test email to the mail stack.', 'alynt-drime-backups-uploader' ) );
	}

	/**
	 * Returns saved notification recipients as an array.
	 *
	 * @return array<int,string>
	 *
	 * @since 0.1.0
	 */
	public function recipients() {
		return $this->recipients_from_settings( $this->settings->get() );
	}

	/**
	 * Sends and records a failure email.
	 *
	 * @param array<int,string>   $recipients Recipients.
	 * @param array<string,mixed> $item Queue item.
	 * @param string              $failure_state Failure state.
	 * @param string              $reason Failure reason.
	 * @param int                 $attempts Attempt count.
	 * @param string              $key Dedupe key.
	 * @return bool
	 */
	private function send_failure_email( array $recipients, array $item, $failure_state, $reason, $attempts, $key ) {
		$sent = wp_mail( $recipients, $this->subject( __( 'Drime backup upload failed', 'alynt-drime-backups-uploader' ) ), $this->failure_message( $item, $failure_state, $reason, $attempts ), $this->headers() );
		$this->log_send_result( $sent, $item, $failure_state );

		if ( $sent ) {
			$this->mark_sent( $key, $item, $failure_state );
		}

		return (bool) $sent;
	}

	/**
	 * Returns recipients from settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return array<int,string>
	 */
	private function recipients_from_settings( array $settings ) {
		$value      = isset( $settings['failure_email_recipients'] ) ? (string) $settings['failure_email_recipients'] : '';
		$recipients = preg_split( '/[\r\n,]+/', $value );
		$valid      = array();

		foreach ( (array) $recipients as $recipient ) {
			$recipient = trim( (string) $recipient );
			if ( '' !== $recipient && $this->is_valid_email( $recipient ) ) {
				$valid[] = $recipient;
			}
		}

		return array_values( array_unique( $valid ) );
	}

	/**
	 * Returns whether a dedupe key has already sent.
	 *
	 * @param string $key Dedupe key.
	 * @return bool
	 */
	private function already_sent( $key ) {
		$sent = $this->get_array_option( self::OPTION_NAME );

		return isset( $sent[ $key ] );
	}

	/**
	 * Marks a dedupe key sent.
	 *
	 * @param string              $key Dedupe key.
	 * @param array<string,mixed> $item Queue item.
	 * @param string              $failure_state Failure state.
	 * @return bool
	 */
	private function mark_sent( $key, array $item, $failure_state ) {
		$sent         = $this->get_array_option( self::OPTION_NAME );
		$sent[ $key ] = array(
			'signature'     => $this->signature( $item ),
			'failure_state' => sanitize_key( $failure_state ),
			'sent_at'       => time(),
		);

		return $this->persist_array_option( self::OPTION_NAME, $sent );
	}

	/**
	 * Logs a skipped notification.
	 *
	 * @param string              $reason Reason.
	 * @param array<string,mixed> $item Queue item.
	 * @param string              $failure_state Failure state.
	 * @return void
	 */
	private function log_skip( $reason, array $item, $failure_state ) {
		$this->logger->event( 'notification', 'info', 'failure_email_skipped', 'Failure notification email skipped.', $this->context( $item, $failure_state, array( 'reason' => $reason ) ) );
	}

	/**
	 * Logs a send result.
	 *
	 * @param bool                $sent Sent result.
	 * @param array<string,mixed> $item Queue item.
	 * @param string              $failure_state Failure state.
	 * @return void
	 */
	private function log_send_result( $sent, array $item, $failure_state ) {
		$this->logger->event( 'notification', $sent ? 'info' : 'error', $sent ? 'failure_email_sent' : 'failure_email_failed', $sent ? 'Failure notification email sent.' : 'Failure notification email failed.', $this->context( $item, $failure_state ) );
	}

	/**
	 * Builds a safe diagnostic context.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param string              $failure_state Failure state.
	 * @param array<string,mixed> $extra Extra context.
	 * @return array<string,mixed>
	 */
	private function context( array $item, $failure_state, array $extra = array() ) {
		return array_merge(
			array(
				'file'          => $this->backup_filename( $item ),
				'signature'     => $this->signature( $item ),
				'failure_state' => sanitize_key( $failure_state ),
			),
			$extra
		);
	}

	/**
	 * Builds a dedupe key.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param string              $failure_state Failure state.
	 * @return string
	 */
	private function dedupe_key( array $item, $failure_state ) {
		return hash( 'sha256', $this->signature( $item ) . '|' . sanitize_key( $failure_state ) );
	}

	/**
	 * Returns a stable item signature.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @return string
	 */
	private function signature( array $item ) {
		if ( ! empty( $item['signature'] ) ) {
			return (string) $item['signature'];
		}

		return hash( 'sha256', $this->backup_filename( $item ) );
	}

	/**
	 * Checks whether an email address is valid.
	 *
	 * @param string $email Email.
	 * @return bool
	 */
	private function is_valid_email( $email ) {
		if ( function_exists( 'is_email' ) ) {
			return (bool) is_email( $email );
		}

		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}
