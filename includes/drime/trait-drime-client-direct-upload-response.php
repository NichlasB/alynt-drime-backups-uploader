<?php
/**
 * Drime direct upload response helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drime direct upload response helpers.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Drime_Client_Direct_Upload_Response {
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
