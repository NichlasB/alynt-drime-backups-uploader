<?php
/**
 * Drime multipart API methods.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drime multipart API methods.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Drime_Client_Multipart {
	/**
	 * Creates a multipart upload.
	 *
	 * @param string   $filename Filename.
	 * @param int      $size Size.
	 * @param string   $extension Extension.
	 * @param int|null $parent_id Concrete upload parent folder ID.
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.1.0
	 */
	public function create_multipart_upload( $filename, $size, $extension, $parent_id = null ) {
		$settings = $this->settings->get();
		$body     = array(
			'filename'    => $filename,
			'mime'        => 'application/zip',
			'size'        => absint( $size ),
			'extension'   => $extension,
			'workspaceId' => absint( $settings['workspace_id'] ),
		);
		if ( null !== $parent_id && absint( $parent_id ) > 0 ) {
			$body['parentId'] = absint( $parent_id );
		} elseif ( '' !== $settings['relative_path'] ) {
			$body['relativePath'] = $settings['relative_path'];
			if ( '' !== (string) $settings['parent_folder_id'] ) {
				$body['parentId'] = $this->parent_id_or_null( $settings );
			}
		} else {
			$body['parentId'] = $this->parent_id_or_null( $settings );
		}

		$response = $this->request( 'POST', '/s3/multipart/create', $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['key'] ) || empty( $response['uploadId'] ) ) {
			return $this->malformed_response( 'create_multipart_upload' );
		}

		return $response;
	}

	/**
	 * Signs part URLs.
	 *
	 * @param string     $key Key.
	 * @param string     $upload_id Upload ID.
	 * @param array<int> $part_numbers Part numbers.
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.1.0
	 */
	public function sign_part_urls( $key, $upload_id, array $part_numbers ) {
		$response = $this->request(
			'POST',
			'/s3/multipart/batch-sign-part-urls',
			array(
				'key'         => $key,
				'uploadId'    => $upload_id,
				'partNumbers' => array_values( array_map( 'absint', $part_numbers ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['urls'] ) || ! is_array( $response['urls'] ) ) {
			return $this->malformed_response( 'sign_part_urls' );
		}

		return $response;
	}

	/**
	 * Uploads one part to a presigned URL.
	 *
	 * @param string $url URL.
	 * @param string $data Part body.
	 * @return string|WP_Error
	 *
	 * @since 0.1.0
	 */
	public function upload_part( $url, $data ) {
		$safe_url = $this->validate_signed_upload_url( $url );
		if ( is_wp_error( $safe_url ) ) {
			return $safe_url;
		}

		$response = wp_safe_remote_request(
			$safe_url,
			array(
				'method'  => 'PUT',
				'timeout' => 180,
				'headers' => array(
					'Content-Type' => 'application/octet-stream',
				),
				'body'    => $data,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'alynt_drime_part_failed', __( 'A Drime upload part failed.', 'alynt-drime-backups-uploader' ) );
		}

		$etag = wp_remote_retrieve_header( $response, 'etag' );
		if ( '' === $etag ) {
			return new WP_Error( 'alynt_drime_missing_etag', __( 'Drime did not return an ETag for the uploaded part.', 'alynt-drime-backups-uploader' ) );
		}

		$etag = (string) $etag;
		if ( '"' !== substr( $etag, 0, 1 ) ) {
			$etag = '"' . $etag . '"';
		}

		return $etag;
	}

	/**
	 * Validates a Drime/S3 signed upload URL before sending backup bytes.
	 *
	 * @param string $url Signed URL.
	 * @return string|WP_Error
	 *
	 * @since 0.1.0
	 */
	private function validate_signed_upload_url( $url ) {
		$url      = trim( (string) $url );
		$safe_url = wp_http_validate_url( $url );

		if ( ! $safe_url || 'https' !== strtolower( (string) wp_parse_url( $safe_url, PHP_URL_SCHEME ) ) ) {
			return new WP_Error( 'alynt_drime_unsafe_signed_url', __( 'Drime returned an unsafe upload URL.', 'alynt-drime-backups-uploader' ) );
		}

		return $safe_url;
	}

	/**
	 * Completes a multipart upload.
	 *
	 * @param string           $key Key.
	 * @param string           $upload_id Upload ID.
	 * @param array<int,array> $parts Parts.
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.1.0
	 */
	public function complete_multipart_upload( $key, $upload_id, array $parts ) {
		$response = $this->request(
			'POST',
			'/s3/multipart/complete',
			array(
				'key'      => $key,
				'uploadId' => $upload_id,
				'parts'    => $parts,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['location'] ) ) {
			return $this->malformed_response( 'complete_multipart_upload' );
		}

		return $response;
	}

	/**
	 * Registers an uploaded S3 object as a Drime entry.
	 *
	 * @param string   $key Key.
	 * @param string   $client_name Display name.
	 * @param int      $size Size.
	 * @param string   $extension Extension.
	 * @param int|null $parent_id Concrete upload parent folder ID.
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.1.0
	 */
	public function create_s3_entry( $key, $client_name, $size, $extension, $parent_id = null ) {
		$settings = $this->settings->get();
		$body     = array(
			'filename'        => basename( $key ),
			'size'            => absint( $size ),
			'clientName'      => $client_name,
			'clientMime'      => 'application/zip',
			'clientExtension' => $extension,
			'workspaceId'     => absint( $settings['workspace_id'] ),
		);
		if ( null !== $parent_id && absint( $parent_id ) > 0 ) {
			$body['parentId'] = absint( $parent_id );
		} elseif ( '' !== $settings['relative_path'] ) {
			$body['relativePath'] = $settings['relative_path'];
			if ( '' !== (string) $settings['parent_folder_id'] ) {
				$body['parentId'] = $this->parent_id_or_null( $settings );
			}
		} else {
			$body['parentId'] = $this->parent_id_or_null( $settings );
		}

		$response = $this->request( 'POST', '/s3/entries', $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['fileEntry'] ) || ! is_array( $response['fileEntry'] ) ) {
			return $this->malformed_response( 'create_s3_entry' );
		}

		return $response;
	}

	/**
	 * Gets uploaded parts for resuming.
	 *
	 * @param string $key Key.
	 * @param string $upload_id Upload ID.
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.1.0
	 */
	public function get_uploaded_parts( $key, $upload_id ) {
		$response = $this->request(
			'POST',
			'/s3/multipart/get-uploaded-parts',
			array(
				'key'      => $key,
				'uploadId' => $upload_id,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['parts'] ) || ! is_array( $response['parts'] ) ) {
			return $this->malformed_response( 'get_uploaded_parts' );
		}

		return $response;
	}

	/**
	 * Aborts an in-progress multipart upload.
	 *
	 * @param string $key Key.
	 * @param string $upload_id Upload ID.
	 * @return array<string,mixed>|true|WP_Error
	 *
	 * @since 0.1.0
	 */
	public function abort_multipart_upload( $key, $upload_id ) {
		$response = $this->request(
			'POST',
			'/s3/multipart/abort',
			array(
				'key'      => $key,
				'uploadId' => $upload_id,
			)
		);

		if ( is_wp_error( $response ) || true === $response ) {
			return $response;
		}

		if ( empty( $response['status'] ) || 'success' !== (string) $response['status'] ) {
			return $this->malformed_response( 'abort_multipart_upload' );
		}

		return $response;
	}
}
