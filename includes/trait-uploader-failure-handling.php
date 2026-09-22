<?php
/**
 * Uploader failure handling helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.21
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles failed upload attempts, retry-limit decisions, and failure notifications.
 *
 * @since 0.5.21
 */
trait Alynt_Drime_Backups_Uploader_Uploader_Failure_Handling {
	/**
	 * Handles a failed queued upload.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param WP_Error            $result Upload error.
	 * @return WP_Error
	 *
	 * @since 0.1.0
	 */
	private function handle_failed_upload( array $item, WP_Error $result ) {
		if ( ! $this->queue->set_active( null ) ) {
			return $this->state_persistence_error();
		}

		$attempts = $this->queue->increment_attempts( (string) $item['signature'] );
		if ( 0 === $attempts ) {
			return $this->state_persistence_error();
		}

		if (
			! $this->registry->mark_failed(
				(string) $item['signature'],
				$result->get_error_message(),
				array_merge( $this->registry_item_context( $item, $attempts ), $this->upload_error_context( $result ) )
			)
		) {
			return $this->state_persistence_error();
		}

		$this->logger->event(
			'upload',
			'error',
			'upload_failed',
			'Upload failed.',
			array(
				'file'   => basename( (string) $item['path'] ),
				'reason' => $result->get_error_message(),
			)
		);

		if ( 'alynt_drime_file_changed' === $result->get_error_code() ) {
			if ( ! $this->queue->remove( (string) $item['signature'] ) ) {
				return $this->state_persistence_error();
			}

			$this->logger->event(
				'upload',
				'warning',
				'changed_file_queue_item_removed',
				'A changed backup file was removed from the queue so a fresh scan can requeue it.',
				array(
					'file' => basename( (string) $item['path'] ),
				)
			);

			return $result;
		}

		if ( $this->attempts_reached_limit( $attempts ) ) {
			if ( $this->is_transient_upload_error( $result ) ) {
				return $this->defer_transient_retry_limit( $item, $attempts, $result );
			}

			$removed = $this->remove_retry_limited_item( $item, $attempts );
			if ( is_wp_error( $removed ) ) {
				return $removed;
			}
		}

		return $result;
	}

	/**
	 * Checks whether an upload error is likely recoverable on a later worker run.
	 *
	 * @param WP_Error $result Upload error.
	 * @return bool
	 */
	private function is_transient_upload_error( WP_Error $result ) {
		$status = $this->upload_error_status( $result );
		if ( $status > 0 ) {
			return in_array( $status, array( 301, 302, 303, 307, 308, 408, 409, 425, 429 ), true ) || ( $status >= 500 && $status < 600 );
		}

		if ( 'alynt_drime_sidecar_upload_failed' === $result->get_error_code() ) {
			return true;
		}

		return in_array( $result->get_error_code(), array( 'http_request_failed', 'request_failed' ), true );
	}

	/**
	 * Returns sanitized upload-error context for failed registry records.
	 *
	 * @param WP_Error $result Upload error.
	 * @return array<string,mixed>
	 */
	private function upload_error_context( WP_Error $result ) {
		$context = array(
			'error_code' => $result->get_error_code(),
		);

		$status = $this->upload_error_status( $result );
		if ( $status > 0 ) {
			$context['error_status'] = $status;
		}

		$data = $result->get_error_data();
		if ( is_array( $data ) ) {
			foreach ( array( 'endpoint', 'sidecar_type', 'sidecar_name' ) as $key ) {
				if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
					$context[ $key ] = (string) $data[ $key ];
				}
			}
		}

		return $context;
	}

	/**
	 * Returns an HTTP status from an upload error when available.
	 *
	 * @param WP_Error $result Upload error.
	 * @return int
	 */
	private function upload_error_status( WP_Error $result ) {
		$data = $result->get_error_data();
		if ( is_array( $data ) && isset( $data['status'] ) ) {
			return absint( $data['status'] );
		}

		$data = $result->get_error_data( 'alynt_drime_api_error' );
		if ( is_array( $data ) && isset( $data['status'] ) ) {
			return absint( $data['status'] );
		}

		return 0;
	}

	/**
	 * Keeps a transiently failing item queued without sending a final-failure email.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param int                 $attempts Attempts.
	 * @param WP_Error            $result Upload error.
	 * @return WP_Error
	 */
	private function defer_transient_retry_limit( array $item, $attempts, WP_Error $result ) {
		if ( ! $this->queue->set_attempts( (string) $item['signature'], 0 ) ) {
			return $this->state_persistence_error();
		}

		$this->logger->event(
			'upload',
			'warning',
			'transient_retry_limit_deferred',
			'Transient upload retry limit reached; item kept queued for a later worker.',
			array(
				'file'     => basename( (string) $item['path'] ),
				'attempts' => $attempts,
				'reason'   => $result->get_error_message(),
			)
		);

		return $result;
	}

	/**
	 * Sends a failure notification when the notifier is available.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param string              $failure_state Failure state.
	 * @param string              $reason Failure reason.
	 * @param int                 $attempts Attempt count.
	 * @return void
	 */
	public function notify_failure( array $item, $failure_state, $reason, $attempts = 0 ) {
		if ( null === $this->notifier ) {
			return;
		}

		$this->notifier->notify_failure( $item, $failure_state, $reason, $attempts );
	}

	/**
	 * Removes a queued item that reached the retry limit.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param int                 $attempts Attempts.
	 * @return true|WP_Error
	 */
	private function remove_retry_limited_item( array $item, $attempts ) {
		if ( ! $this->queue->remove( (string) $item['signature'] ) ) {
			return $this->state_persistence_error();
		}

		$this->logger->event(
			'upload',
			'error',
			'upload_retry_limit_reached',
			'Upload retry limit reached; item removed from queue.',
			array(
				'file'     => basename( (string) $item['path'] ),
				'attempts' => $attempts,
			)
		);

		$this->notify_failure( $item, 'retry_limit_reached', __( 'The queued backup reached the retry limit.', 'alynt-drime-backups-uploader' ), $attempts );

		return true;
	}

	/**
	 * Handles a successful queued upload.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param array<string,mixed> $result Upload result.
	 * @return array<string,mixed>|WP_Error
	 */
	private function complete_successful_upload( array $item, array $result ) {
		$this->remember_remote_parent_from_result( $result, $item );

		$record = array_merge( $result, $this->registry_item_context( $item ) );

		if ( ! $this->registry->mark_uploaded( (string) $item['signature'], $record ) ) {
			return $this->state_persistence_error();
		}

		if ( ! $this->queue->remove( (string) $item['signature'] ) ) {
			return $this->state_persistence_error();
		}

		if ( ! $this->queue->set_active( null ) ) {
			return $this->state_persistence_error();
		}

		$this->logger->event( 'upload', 'info', 'upload_completed', 'Upload completed.', array( 'file' => basename( (string) $item['path'] ) ) );
		$this->maybe_delete_local_file( $item );
		$this->maybe_prune_uploaded_server_packages();

		return $result;
	}
}
