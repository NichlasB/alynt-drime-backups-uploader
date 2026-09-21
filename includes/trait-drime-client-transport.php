<?php
/**
 * Drime client HTTP transport helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drime client HTTP transport helpers.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Drime_Client_Transport {
	/**
	 * Sends a JSON request to Drime.
	 *
	 * @param string                   $method Method.
	 * @param string                   $path Path.
	 * @param array<string,mixed>|null $body Body.
	 * @return array<string,mixed>|true|WP_Error
	 */
	private function request( $method, $path, $body = null ) {
		$args = $this->request_args( $method, $body );
		if ( is_wp_error( $args ) ) {
			return $args;
		}

		$response = wp_remote_request( self::BASE_URL . $path, $args );
		if ( is_wp_error( $response ) ) {
			return $this->failed_request_error( $path, $response );
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$raw     = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			return $this->api_error_response( $code, $path, $decoded );
		}

		if ( '' === trim( $raw ) ) {
			return true;
		}

		return is_array( $decoded ) ? $decoded : $this->malformed_response( $path );
	}

	/**
	 * Builds request arguments.
	 *
	 * @param string                   $method Method.
	 * @param array<string,mixed>|null $body Body.
	 * @return array<string,mixed>|WP_Error
	 */
	private function request_args( $method, $body = null ) {
		$settings = $this->settings->get();
		$token    = trim( (string) $settings['api_token'] );

		if ( '' === $token ) {
			return new WP_Error( 'alynt_drime_missing_token', __( 'Add a Drime API token before connecting.', 'alynt-drime-backups-uploader' ) );
		}

		$args = array(
			'method'  => $method,
			'timeout' => self::API_REQUEST_TIMEOUT,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		return $args;
	}
}
