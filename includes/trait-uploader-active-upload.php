<?php
/**
 * Uploader active upload state helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uploader active upload state helpers.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Uploader_Active_Upload {
	/**
	 * Clears stale active upload state or blocks concurrent work.
	 *
	 * @param array<string,mixed> $item Current queue item.
	 * @return true|WP_Error
	 */
	private function recover_active_upload_state( array $item ) {
		$active = $this->queue->get_active();

		if ( empty( $active ) ) {
			return true;
		}

		$updated_at = isset( $active['updated_at'] ) ? absint( $active['updated_at'] ) : 0;
		$is_stale   = 0 === $updated_at || ( time() - $updated_at ) >= self::STALE_ACTIVE_UPLOAD_SECONDS;

		if ( $is_stale ) {
			$this->abort_active_upload( $active, 'stale_active_upload_abort' );
			if ( ! $this->queue->set_active( null ) ) {
				return $this->state_persistence_error();
			}

			$this->logger->event(
				'upload',
				'warning',
				'stale_active_upload_cleared',
				'Stale active upload state was cleared.',
				array(
					'file'       => isset( $active['remote_name'] ) ? basename( (string) $active['remote_name'] ) : '',
					'updated_at' => $updated_at,
				)
			);
			return true;
		}

		if ( $this->active_state_matches_item( $active, $item ) ) {
			if ( ! $this->active_state_chunk_size_matches( $active ) ) {
				$this->abort_active_upload( $active, 'chunk_size_changed_upload_abort' );
				if ( ! $this->queue->set_active( null ) ) {
					return $this->state_persistence_error();
				}

				$this->logger->event(
					'upload',
					'warning',
					'chunk_size_changed_upload_cleared',
					'Active upload state was cleared because the multipart chunk size changed.',
					array(
						'file' => isset( $active['remote_name'] ) ? basename( (string) $active['remote_name'] ) : '',
					)
				);
			}

			return true;
		}

		return new WP_Error( 'alynt_drime_upload_active', __( 'An upload is already marked active.', 'alynt-drime-backups-uploader' ) );
	}

	/**
	 * Aborts remote multipart state if an active upload has enough information.
	 *
	 * @param array<string,mixed> $active Active upload state.
	 * @param string              $event_code Event code.
	 * @return true|WP_Error
	 */
	private function abort_active_upload( array $active, $event_code ) {
		if ( empty( $active['key'] ) || empty( $active['upload_id'] ) ) {
			return true;
		}

		$result = $this->client->abort_multipart_upload( (string) $active['key'], (string) $active['upload_id'] );
		if ( is_wp_error( $result ) ) {
			$this->logger->event(
				'upload',
				'warning',
				$event_code . '_failed',
				'Could not abort the remote multipart upload before clearing local state.',
				array(
					'file'   => isset( $active['remote_name'] ) ? basename( (string) $active['remote_name'] ) : '',
					'reason' => $result->get_error_message(),
				)
			);

			return $result;
		}

		$this->logger->event(
			'upload',
			'info',
			$event_code . '_succeeded',
			'Remote multipart upload was aborted before clearing local state.',
			array(
				'file' => isset( $active['remote_name'] ) ? basename( (string) $active['remote_name'] ) : '',
			)
		);

		return true;
	}
}
