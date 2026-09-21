<?php
/**
 * Drime client response and normalization helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drime client response and normalization helpers.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Drime_Client_Response_Helpers {
	/**
	 * Converts a failed WordPress HTTP request into a diagnostic error.
	 *
	 * @param string   $path Endpoint path.
	 * @param WP_Error $response Error response.
	 * @return WP_Error
	 */
	private function failed_request_error( $path, WP_Error $response ) {
		$this->diagnostic(
			'error',
			'request_failed',
			'Drime request failed.',
			array(
				'endpoint' => $path,
				'reason'   => $response->get_error_message(),
			)
		);

		return $response;
	}

	/**
	 * Converts a Drime API error response into a WP_Error.
	 *
	 * @param int                         $code HTTP status.
	 * @param string                      $path Endpoint path.
	 * @param array<string,mixed>|mixed[] $decoded Decoded response.
	 * @return WP_Error
	 */
	private function api_error_response( $code, $path, $decoded ) {
		$message = is_array( $decoded ) && ! empty( $decoded['message'] ) ? (string) $decoded['message'] : __( 'Drime returned an error response.', 'alynt-drime-backups-uploader' );

		$this->diagnostic(
			'error',
			'api_error',
			'Drime returned an error response.',
			array(
				'status'   => $code,
				'endpoint' => $path,
				'message'  => $message,
			)
		);

		return new WP_Error( 'alynt_drime_api_error', $message, array( 'status' => $code ) );
	}

	/**
	 * Returns a standard malformed response error.
	 *
	 * @param string $context Response context.
	 * @return WP_Error
	 */
	private function malformed_response( $context ) {
		$this->diagnostic(
			'error',
			'malformed_response',
			'Drime returned an unexpected response shape.',
			array(
				'context' => $context,
			)
		);

		return new WP_Error( 'alynt_drime_malformed_response', __( 'Drime returned an unexpected response shape.', 'alynt-drime-backups-uploader' ) );
	}

	/**
	 * Writes a Drime client diagnostic event.
	 *
	 * @param string              $level Level.
	 * @param string              $code Event code.
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @return void
	 */
	private function diagnostic( $level, $code, $message, array $context = array() ) {
		if ( $this->logger instanceof Alynt_Drime_Backups_Uploader_Logger ) {
			$this->logger->event( 'external_api', $level, $code, $message, $context );
		}
	}

	/**
	 * Returns parent ID or null.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return int|null
	 */
	private function parent_id_or_null( array $settings ) {
		return '' === (string) $settings['parent_folder_id'] ? null : absint( $settings['parent_folder_id'] );
	}

	/**
	 * Returns parent ID or an empty string for multipart form upload.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private function parent_id_or_empty( array $settings ) {
		return '' === (string) $settings['parent_folder_id'] ? '' : (string) absint( $settings['parent_folder_id'] );
	}

	/**
	 * Extracts a user ID from common Drime response shapes.
	 *
	 * @param array<string,mixed> $response Response.
	 * @return int
	 */
	private function extract_user_id( array $response ) {
		if ( ! empty( $response['id'] ) ) {
			return absint( $response['id'] );
		}

		if ( ! empty( $response['user'] ) && is_array( $response['user'] ) && ! empty( $response['user']['id'] ) ) {
			return absint( $response['user']['id'] );
		}

		if ( ! empty( $response['data'] ) && is_array( $response['data'] ) && ! empty( $response['data']['id'] ) ) {
			return absint( $response['data']['id'] );
		}

		return 0;
	}

	/**
	 * Sanitizes a Drime folder hash for endpoint paths and query strings.
	 *
	 * @param string $folder_hash Folder hash.
	 * @return string
	 */
	private function sanitize_folder_hash( $folder_hash ) {
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $folder_hash );
	}
}
