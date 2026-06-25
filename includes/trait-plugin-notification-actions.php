<?php
/**
 * Plugin notification admin action handlers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notification admin action handlers.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Plugin_Notification_Actions {
	/**
	 * Sends a test failure notification email.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function handle_send_test_failure_email() {

		$this->verify_admin_action( 'alynt_drime_backups_send_test_failure_email' );

		$result = $this->notifier->send_test();

		$this->redirect( is_wp_error( $result ) ? 'failure_email_test_failed' : 'failure_email_test_sent' );
	}

	/**
	 * Sends a manual upload failure notification for a queue item.
	 *
	 * @param array<string,mixed>|null $item Queue item.
	 * @param WP_Error                 $result Failure result.
	 * @return void
	 */
	private function notify_manual_upload_failure( $item, WP_Error $result ) {
		if ( ! is_array( $item ) ) {
			return;
		}

		$this->uploader->notify_failure( $item, 'manual_upload_failed', $result->get_error_message(), $this->queue_attempts( $item ) );
	}

	/**
	 * Returns the latest attempt count for a queue item.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @return int
	 */
	private function queue_attempts( array $item ) {
		$signature = isset( $item['signature'] ) ? (string) $item['signature'] : '';
		$queue     = $this->queue->all();

		if ( '' !== $signature && isset( $queue[ $signature ]['attempts'] ) ) {
			return absint( $queue[ $signature ]['attempts'] );
		}

		return isset( $item['attempts'] ) ? absint( $item['attempts'] ) : 0;
	}
}
