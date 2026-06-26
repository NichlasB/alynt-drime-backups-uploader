<?php
/**
 * Failed upload admin actions.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles failed upload retry actions.
 *
 * @since 0.5.1
 */
trait Alynt_Drime_Backups_Uploader_Plugin_Failed_Upload_Actions {
	/**
	 * Requeues one failed upload when its local file is still readable.
	 *
	 * @return void
	 */
	public function handle_requeue_failed_upload() {

		$this->verify_admin_action( 'alynt_drime_backups_requeue_failed_upload' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified above.
		$signature = isset( $_POST['signature'] ) ? sanitize_text_field( wp_unslash( $_POST['signature'] ) ) : '';
		$item      = $this->failed_record_to_queue_item( $signature );

		if ( is_wp_error( $item ) ) {
			$this->logger->event( 'upload', 'error', 'failed_upload_requeue_failed', 'Failed upload could not be requeued.', array( 'reason' => $item->get_error_message() ) );
			$notice = 'alynt_drime_failed_upload_missing' === $item->get_error_code() ? 'failed_upload_missing' : 'failed_upload_requeue_failed';
			$this->redirect( $notice );
		}

		if ( ! $this->queue->prepend( $item, $this->registry->get_uploaded() ) ) {
			$this->logger->event( 'upload', 'error', 'failed_upload_requeue_failed', 'Failed upload could not be requeued.', array( 'signature' => $signature ) );
			$this->redirect( 'failed_upload_requeue_failed' );
		}

		$this->registry->clear_failed( $signature );
		$this->logger->event( 'upload', 'info', 'failed_upload_requeued', 'Failed upload requeued.', array( 'file' => basename( (string) $item['path'] ) ) );

		$this->redirect( 'failed_upload_requeued' );
	}

	/**
	 * Converts a failed registry record back into a queue item.
	 *
	 * @param string $signature Signature.
	 * @return array<string,mixed>|WP_Error
	 */
	private function failed_record_to_queue_item( $signature ) {
		if ( '' === $signature ) {
			return new WP_Error( 'alynt_drime_failed_upload_missing', __( 'The failed upload record could not be found.', 'alynt-drime-backups-uploader' ) );
		}

		$record = $this->registry->get_failed_record( $signature );
		$path   = isset( $record['path'] ) ? (string) $record['path'] : '';

		if ( empty( $record ) || '' === $path || ! is_readable( $path ) ) {
			return new WP_Error( 'alynt_drime_failed_upload_missing', __( 'The failed upload file is no longer readable.', 'alynt-drime-backups-uploader' ) );
		}

		$item = array(
			'signature' => $signature,
			'path'      => $path,
			'name'      => isset( $record['name'] ) && '' !== (string) $record['name'] ? (string) $record['name'] : basename( $path ),
			'attempts'  => 0,
		);

		if ( isset( $record['wpvivid'] ) && is_array( $record['wpvivid'] ) ) {
			$item['wpvivid'] = $record['wpvivid'];
		}

		foreach ( array( 'producer_key', 'producer_label', 'package_id', 'filename', 'backup_set_id', 'manifest_path', 'checksum_path', 'checksum_algorithm', 'checksum_value' ) as $key ) {
			if ( isset( $record[ $key ] ) && is_scalar( $record[ $key ] ) ) {
				$item[ $key ] = (string) $record[ $key ];
			}
		}

		foreach ( array( 'backup_set_index', 'backup_set_total' ) as $key ) {
			if ( isset( $record[ $key ] ) ) {
				$item[ $key ] = absint( $record[ $key ] );
			}
		}

		return $item;
	}
}
