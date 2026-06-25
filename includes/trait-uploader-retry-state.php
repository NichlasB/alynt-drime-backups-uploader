<?php
/**
 * Uploader retry state helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uploader retry state helpers.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Uploader_Retry_State {
	/**
	 * Checks whether a queued item already exceeded retry limits.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @return bool
	 */
	private function has_exhausted_retries( array $item ) {
		$attempts = isset( $item['attempts'] ) ? absint( $item['attempts'] ) : 0;

		return $this->attempts_reached_limit( $attempts );
	}

	/**
	 * Checks whether attempts have reached the configured limit.
	 *
	 * @param int $attempts Attempts.
	 * @return bool
	 */
	private function attempts_reached_limit( $attempts ) {
		$settings    = $this->settings->get();
		$max_retries = isset( $settings['max_retries'] ) ? absint( $settings['max_retries'] ) : 3;

		return $max_retries > 0 && $attempts >= $max_retries;
	}

	/**
	 * Removes an exhausted queue item and records the failure.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @return WP_Error
	 */
	private function fail_exhausted_item( array $item ) {
		$message   = __( 'The queued backup reached the retry limit.', 'alynt-drime-backups-uploader' );
		$signature = isset( $item['signature'] ) ? (string) $item['signature'] : '';

		if ( '' !== $signature ) {
			$attempts = isset( $item['attempts'] ) ? absint( $item['attempts'] ) : 0;
			if ( ! $this->registry->mark_failed( $signature, $message, $this->registry_item_context( $item, $attempts ) ) || ! $this->queue->remove( $signature ) ) {
				return $this->state_persistence_error();
			}
		}

		$this->logger->event(
			'upload',
			'error',
			'upload_retry_limit_reached',
			'Upload retry limit reached; item removed from queue.',
			array(
				'file' => isset( $item['path'] ) ? basename( (string) $item['path'] ) : '',
			)
		);

		$this->notify_failure( $item, 'retry_limit_reached', $message, isset( $item['attempts'] ) ? absint( $item['attempts'] ) : 0 );

		return new WP_Error( 'alynt_drime_retry_limit_reached', $message );
	}
}
