<?php
/**
 * Drime direct upload API methods.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drime direct upload API methods.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Drime_Client_Direct_Upload {
	/**
	 * Directly uploads a small file through Drime's /uploads endpoint.
	 *
	 * @param string                   $path File path.
	 * @param string                   $remote_name Remote display name.
	 * @param int|null                 $parent_id Concrete upload parent folder ID.
	 * @param array<string,mixed>|null $settings_override Effective upload settings.
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.1.0
	 */
	public function simple_upload( $path, $remote_name, $parent_id = null, ?array $settings_override = null ) {
		if ( ! function_exists( 'curl_init' ) || ! function_exists( 'curl_file_create' ) ) {
			return new WP_Error( 'alynt_drime_no_curl', __( 'The PHP cURL extension is required for direct small-file uploads.', 'alynt-drime-backups-uploader' ) );
		}

		$settings = null === $settings_override ? $this->settings->get() : array_merge( $this->settings->get(), $settings_override );
		$token    = trim( (string) $settings['api_token'] );

		if ( '' === $token ) {
			return new WP_Error( 'alynt_drime_missing_token', __( 'Add a Drime API token before uploading.', 'alynt-drime-backups-uploader' ) );
		}

		$fields   = $this->simple_upload_fields( $path, $remote_name, $settings, $parent_id );
		$response = $this->execute_simple_upload_request( $token, $fields );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$redirect_url = $this->safe_simple_upload_redirect_url( $response );
		if ( '' !== $redirect_url ) {
			$response = $this->execute_simple_upload_request( $token, $fields, $redirect_url );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
		}

		return $this->decode_simple_upload_response( $response );
	}

	/**
	 * Builds direct upload form fields.
	 *
	 * @param string              $path File path.
	 * @param string              $remote_name Remote display name.
	 * @param array<string,mixed> $settings Settings.
	 * @param int|null            $parent_id Concrete upload parent folder ID.
	 * @return array<string,mixed>
	 */
	private function simple_upload_fields( $path, $remote_name, array $settings, $parent_id = null ) {
		$fields = array(
			'file'        => curl_file_create( $path, $this->simple_upload_mime_type( $remote_name ), $remote_name ),
			'workspaceId' => (string) absint( $settings['workspace_id'] ),
		);

		if ( null !== $parent_id && absint( $parent_id ) > 0 ) {
			$fields['parentId'] = (string) absint( $parent_id );
		} elseif ( '' !== $settings['relative_path'] ) {
			$fields['relativePath'] = $settings['relative_path'];
			if ( '' !== (string) $settings['parent_folder_id'] ) {
				$fields['parentId'] = $this->parent_id_or_empty( $settings );
			}
		} else {
			$fields['parentId'] = $this->parent_id_or_empty( $settings );
		}

		return $fields;
	}

	/**
	 * Executes the direct upload request.
	 *
	 * @param string              $token  API token.
	 * @param array<string,mixed> $fields Form fields.
	 * @param string|null         $url    Upload URL.
	 * @return array{raw:string,code:int,location:string}|WP_Error
	 */
	private function execute_simple_upload_request( $token, array $fields, $url = null ) {
		$ch = curl_init( null === $url ? self::BASE_URL . '/uploads' : $url );
		if ( false === $ch ) {
			return new WP_Error( 'alynt_drime_upload_failed', __( 'The direct upload request could not be initialized.', 'alynt-drime-backups-uploader' ) );
		}

		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HEADER, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $this->simple_upload_headers( $token ) );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $fields );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 15 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 300 );

		$raw         = curl_exec( $ch );
		$code        = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		$header_size = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		$error       = curl_error( $ch );
		// phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated -- Supported PHP 7.4 runtimes still benefit from explicit cURL handle cleanup.
		curl_close( $ch );

		if ( false === $raw ) {
			$this->diagnostic(
				'error',
				'direct_upload_request_failed',
				'The direct upload request failed.',
				array(
					'status' => $code,
					'reason' => $error ? $error : 'curl_exec returned false',
				)
			);

			return new WP_Error(
				'http_request_failed',
				__( 'The direct upload request failed. Check the server network connection and try again.', 'alynt-drime-backups-uploader' ),
				array(
					'status'   => $code,
					'endpoint' => '/uploads',
				)
			);
		}

		$raw_string = (string) $raw;
		$headers    = substr( $raw_string, 0, $header_size );
		$body       = substr( $raw_string, $header_size );

		return array(
			'raw'      => (string) $body,
			'code'     => $code,
			'location' => $this->simple_upload_redirect_location( (string) $headers ),
		);
	}

	/**
	 * Returns a conservative MIME type for direct uploads.
	 *
	 * @param string $remote_name Remote display name.
	 * @return string
	 */
	private function simple_upload_mime_type( $remote_name ) {
		$remote_name = strtolower( (string) $remote_name );

		if ( preg_match( '/\.json$/', $remote_name ) ) {
			return 'text/plain';
		}

		if ( preg_match( '/\.(sha256|txt|log)$/', $remote_name ) ) {
			return 'text/plain';
		}

		if ( preg_match( '/\.(tar\.gz|tgz)$/', $remote_name ) ) {
			return 'application/gzip';
		}

		if ( preg_match( '/\.tar$/', $remote_name ) ) {
			return 'application/x-tar';
		}

		if ( preg_match( '/\.zip$/', $remote_name ) ) {
			return 'application/zip';
		}

		return 'application/octet-stream';
	}

	/**
	 * Builds direct upload request headers.
	 *
	 * @param string $token API token.
	 * @return array<int,string>
	 */
	private function simple_upload_headers( $token ) {
		return array(
			'Authorization: Bearer ' . $token,
			'Accept: application/json',
		);
	}

	/**
	 * Returns a same-host HTTPS redirect URL for a direct upload response.
	 *
	 * @param array{raw:string,code:int,location:string} $response Upload response.
	 * @return string
	 */
	private function safe_simple_upload_redirect_url( array $response ) {
		if ( ! in_array( absint( $response['code'] ), array( 301, 302, 303, 307, 308 ), true ) ) {
			return '';
		}

		$location = isset( $response['location'] ) ? trim( (string) $response['location'] ) : '';
		if ( '' === $location ) {
			return '';
		}

		$base_host = parse_url( self::BASE_URL, PHP_URL_HOST );
		$base_path = (string) parse_url( self::BASE_URL, PHP_URL_PATH );

		if ( 0 === strpos( $location, '/' ) ) {
			$location = 'https://' . $base_host . $location;
		}

		$location_host = parse_url( $location, PHP_URL_HOST );
		$location_path = (string) parse_url( $location, PHP_URL_PATH );

		if ( 'https' !== parse_url( $location, PHP_URL_SCHEME ) || $location_host !== $base_host ) {
			return '';
		}

		if ( 0 !== strpos( $location_path, rtrim( $base_path, '/' ) . '/uploads' ) ) {
			return '';
		}

		return $location;
	}

	/**
	 * Extracts a Location header from a direct upload response.
	 *
	 * @param string $headers Response headers.
	 * @return string
	 */
	private function simple_upload_redirect_location( $headers ) {
		foreach ( preg_split( "/\r\n|\n|\r/", $headers ) as $line ) {
			if ( 0 === stripos( $line, 'Location:' ) ) {
				return trim( substr( $line, 9 ) );
			}
		}

		return '';
	}

	/**
	 * Decodes a direct upload response.
	 *
	 * @param array{raw:string,code:int,location?:string} $response Upload response.
	 * @return array<string,mixed>|WP_Error
	 */
	private function decode_simple_upload_response( array $response ) {
		$decoded = json_decode( $response['raw'], true );

		if ( $response['code'] < 200 || $response['code'] >= 300 || ! is_array( $decoded ) ) {
			$message = is_array( $decoded ) && ! empty( $decoded['message'] ) ? (string) $decoded['message'] : __( 'Drime rejected the direct upload request.', 'alynt-drime-backups-uploader' );
			$data    = array(
				'status'   => absint( $response['code'] ),
				'endpoint' => '/uploads',
			);

			if ( is_array( $decoded ) && isset( $decoded['errors'] ) ) {
				$data['error_detail'] = $this->compact_simple_upload_errors( $decoded['errors'] );
			}

			return new WP_Error( 'alynt_drime_api_error', $message, $data );
		}

		if ( empty( $decoded['fileEntry'] ) || ! is_array( $decoded['fileEntry'] ) ) {
			return $this->malformed_response( 'simple_upload' );
		}

		return $decoded;
	}

	/**
	 * Compacts direct upload validation errors for non-secret diagnostics.
	 *
	 * @param mixed $errors Error payload.
	 * @return string
	 */
	private function compact_simple_upload_errors( $errors ) {
		if ( ! is_array( $errors ) ) {
			return '';
		}

		$messages = array();
		foreach ( $errors as $field => $field_errors ) {
			if ( is_array( $field_errors ) ) {
				$field_errors = implode( ' ', array_filter( array_map( 'strval', $field_errors ) ) );
			}

			if ( is_scalar( $field_errors ) && '' !== trim( (string) $field_errors ) ) {
				$messages[] = sanitize_text_field( (string) $field . ': ' . (string) $field_errors );
			}
		}

		return substr( implode( ' | ', $messages ), 0, 500 );
	}
}
