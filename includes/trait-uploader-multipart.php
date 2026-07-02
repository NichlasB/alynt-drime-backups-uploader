<?php
/**
 * Uploader multipart upload helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uploader multipart upload helpers.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Uploader_Multipart {
	/**
	 * Uploads a file through Drime multipart flow.
	 *
	 * @param string                   $path Path.
	 * @param string                   $remote_name Remote name.
	 * @param int                      $size Size.
	 * @param array<string,mixed>      $item Queue item.
	 * @param int|null                 $parent_id Concrete upload parent folder ID.
	 * @param array<string,mixed>|null $settings Effective upload settings.
	 * @return array<string,mixed>|WP_Error
	 */
	private function multipart_upload( $path, $remote_name, $size, array $item, $parent_id = null, ?array $settings = null ) {
		$extension = $this->multipart_extension( $remote_name );
		$this->log_multipart_started( $remote_name, $size );

		$memory_ok = $this->validate_multipart_chunk_memory( $this->multipart_chunk_size() );
		if ( is_wp_error( $memory_ok ) ) {
			return $memory_ok;
		}

		$session = $this->multipart_session( $path, $remote_name, $size, $extension, $item, $parent_id, $settings );
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		$parts = $this->get_completed_multipart_parts( $session['key'], $session['upload_id'] );
		if ( is_wp_error( $parts ) ) {
			return $parts;
		}

		$parts = $this->upload_multipart_parts( $path, $remote_name, $item, $session, $parts );
		if ( is_wp_error( $parts ) ) {
			return $parts;
		}

		return $this->complete_multipart_session( $path, $remote_name, $size, $extension, $session, $parts, $parent_id, $settings );
	}

	/**
	 * Returns a safe multipart extension.
	 *
	 * @param string $remote_name Remote name.
	 * @return string
	 */
	private function multipart_extension( $remote_name ) {
		$extension = pathinfo( $remote_name, PATHINFO_EXTENSION );

		return '' === $extension ? 'zip' : strtolower( $extension );
	}

	/**
	 * Logs multipart start.
	 *
	 * @param string $remote_name Remote name.
	 * @param int    $size Size.
	 * @return void
	 */
	private function log_multipart_started( $remote_name, $size ) {
		$this->logger->event(
			'upload',
			'info',
			'multipart_started',
			'Multipart upload started.',
			array(
				'file' => $remote_name,
				'size' => $size,
			)
		);
	}

	/**
	 * Creates or resumes a multipart session.
	 *
	 * @param string                   $path Path.
	 * @param string                   $remote_name Remote name.
	 * @param int                      $size Size.
	 * @param string                   $extension Extension.
	 * @param array<string,mixed>      $item Queue item.
	 * @param int|null                 $parent_id Concrete upload parent folder ID.
	 * @param array<string,mixed>|null $settings Effective upload settings.
	 * @return array{key:string,upload_id:string,total:int}|WP_Error
	 */
	private function multipart_session( $path, $remote_name, $size, $extension, array $item, $parent_id = null, ?array $settings = null ) {
		$chunk_size   = $this->multipart_chunk_size();
		$resume_state = $this->get_resume_state( $item, $path, $remote_name, $chunk_size );
		$created      = null === $resume_state ? $this->client->create_multipart_upload( $remote_name, $size, $extension, $parent_id, $settings ) : $resume_state;

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		if ( empty( $created['key'] ) || empty( $created['uploadId'] ) ) {
			return new WP_Error( 'alynt_drime_bad_multipart_create', __( 'Drime did not return a multipart upload ID.', 'alynt-drime-backups-uploader' ) );
		}

		return array(
			'key'       => (string) $created['key'],
			'upload_id' => (string) $created['uploadId'],
			'total'     => (int) ceil( $size / $chunk_size ),
		);
	}

	/**
	 * Extracts a signed URL for a part.
	 *
	 * @param array<string,mixed> $sign_response Sign response.
	 * @param int                 $part_number Part number.
	 * @return string|WP_Error
	 */
	private function extract_signed_url( array $sign_response, $part_number ) {
		if ( empty( $sign_response['urls'] ) || ! is_array( $sign_response['urls'] ) ) {
			return new WP_Error( 'alynt_drime_missing_signed_url', __( 'Drime did not return a signed upload URL.', 'alynt-drime-backups-uploader' ) );
		}

		foreach ( $sign_response['urls'] as $signed ) {
			if ( is_array( $signed ) && isset( $signed['partNumber'], $signed['url'] ) && is_scalar( $signed['url'] ) && (int) $signed['partNumber'] === (int) $part_number ) {
				return (string) $signed['url'];
			}
		}

		return new WP_Error( 'alynt_drime_missing_part_url', __( 'Drime did not return the expected part URL.', 'alynt-drime-backups-uploader' ) );
	}

	/**
	 * Returns resumable multipart state for the current item.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param string              $path Local path.
	 * @param string              $remote_name Remote filename.
	 * @param int                 $chunk_size Chunk size in bytes.
	 * @return array<string,string>|null
	 */
	private function get_resume_state( array $item, $path, $remote_name, $chunk_size ) {
		$active = $this->queue->get_active();

		if ( empty( $active ) || ! $this->active_state_matches_item( $active, $item ) ) {
			return null;
		}

		if ( empty( $active['key'] ) || empty( $active['upload_id'] ) ) {
			return null;
		}

		if ( ! empty( $active['local_file'] ) && wp_normalize_path( (string) $active['local_file'] ) !== wp_normalize_path( $path ) ) {
			return null;
		}

		if ( ! empty( $active['remote_name'] ) && (string) $active['remote_name'] !== $remote_name ) {
			return null;
		}

		if ( empty( $active['chunk_size'] ) || absint( $active['chunk_size'] ) !== absint( $chunk_size ) ) {
			return null;
		}

		return array(
			'key'      => (string) $active['key'],
			'uploadId' => (string) $active['upload_id'],
		);
	}

	/**
	 * Gets completed multipart parts from Drime for resume.
	 *
	 * @param string $key Multipart key.
	 * @param string $upload_id Multipart upload ID.
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	private function get_completed_multipart_parts( $key, $upload_id ) {
		$response = $this->client->get_uploaded_parts( $key, $upload_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$parts = array();

		foreach ( $response['parts'] as $part ) {
			if ( ! is_array( $part ) ) {
				continue;
			}

			$part_number = isset( $part['PartNumber'] ) ? absint( $part['PartNumber'] ) : ( isset( $part['partNumber'] ) ? absint( $part['partNumber'] ) : 0 );
			$etag        = isset( $part['ETag'] ) ? (string) $part['ETag'] : ( isset( $part['etag'] ) ? (string) $part['etag'] : '' );

			if ( $part_number <= 0 || '' === $etag ) {
				continue;
			}

			if ( '"' !== substr( $etag, 0, 1 ) ) {
				$etag = '"' . $etag . '"';
			}

			$parts[ $part_number ] = array(
				'PartNumber' => $part_number,
				'ETag'       => $etag,
			);
		}

		return $parts;
	}

	/**
	 * Persists multipart upload state without sensitive presigned URLs.
	 *
	 * @param string                         $path Local path.
	 * @param string                         $remote_name Remote filename.
	 * @param string                         $key Multipart key.
	 * @param string                         $upload_id Multipart upload ID.
	 * @param string                         $signature Queue signature.
	 * @param array<int,array<string,mixed>> $parts Completed parts.
	 * @param int                            $total Total parts.
	 * @return bool
	 */
	private function store_active_upload_state( $path, $remote_name, $key, $upload_id, $signature, array $parts, $total ) {
		ksort( $parts );

		return $this->queue->set_active(
			array(
				'local_file'      => $path,
				'remote_name'     => $remote_name,
				'key'             => $key,
				'upload_id'       => $upload_id,
				'signature'       => $signature,
				'completed_parts' => count( $parts ),
				'total_parts'     => $total,
				'chunk_size'      => $this->multipart_chunk_size(),
				'parts'           => array_values( $parts ),
				'updated_at'      => time(),
			)
		);
	}

	/**
	 * Checks whether active upload state belongs to the current queue item.
	 *
	 * @param array<string,mixed> $active Active upload state.
	 * @param array<string,mixed> $item Queue item.
	 * @return bool
	 */
	private function active_state_matches_item( array $active, array $item ) {
		$active_signature = isset( $active['signature'] ) ? (string) $active['signature'] : '';
		$item_signature   = isset( $item['signature'] ) ? (string) $item['signature'] : '';

		if ( '' !== $active_signature && '' !== $item_signature ) {
			return $active_signature === $item_signature;
		}

		$active_file = isset( $active['local_file'] ) ? wp_normalize_path( (string) $active['local_file'] ) : '';
		$item_file   = isset( $item['path'] ) ? wp_normalize_path( (string) $item['path'] ) : '';

		return '' !== $active_file && $active_file === $item_file;
	}

	/**
	 * Checks whether active upload state uses the current multipart chunk size.
	 *
	 * @param array<string,mixed> $active Active upload state.
	 * @return bool
	 */
	private function active_state_chunk_size_matches( array $active ) {
		return ! empty( $active['chunk_size'] ) && absint( $active['chunk_size'] ) === $this->multipart_chunk_size();
	}
}
