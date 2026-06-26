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
	 * @param string   $path File path.
	 * @param string   $remote_name Remote display name.
	 * @param int|null $parent_id Concrete upload parent folder ID.
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.1.0
	 */
	public function simple_upload( $path, $remote_name, $parent_id = null ) {
		if ( ! function_exists( 'curl_init' ) || ! function_exists( 'curl_file_create' ) ) {
			return new WP_Error( 'alynt_drime_no_curl', __( 'The PHP cURL extension is required for direct small-file uploads.', 'alynt-drime-backups-uploader' ) );
		}

		$settings = $this->settings->get();
		$token    = trim( (string) $settings['api_token'] );

		if ( '' === $token ) {
			return new WP_Error( 'alynt_drime_missing_token', __( 'Add a Drime API token before uploading.', 'alynt-drime-backups-uploader' ) );
		}

		$response = $this->execute_simple_upload_request( $token, $this->simple_upload_fields( $path, $remote_name, $settings, $parent_id ) );

		if ( is_wp_error( $response ) ) {
			return $response;
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
			'file'        => curl_file_create( $path, 'application/zip', $remote_name ),
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
	 * @param string              $token API token.
	 * @param array<string,mixed> $fields Form fields.
	 * @return array{raw:string,code:int}|WP_Error
	 */
	private function execute_simple_upload_request( $token, array $fields ) {
		$ch = curl_init( self::BASE_URL . '/uploads' );
		if ( false === $ch ) {
			return new WP_Error( 'alynt_drime_upload_failed', __( 'The direct upload request could not be initialized.', 'alynt-drime-backups-uploader' ) );
		}

		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Authorization: Bearer ' . $token ) );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $fields );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 15 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 300 );

		$raw   = curl_exec( $ch );
		$code  = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		$error = curl_error( $ch );
		unset( $ch );

		if ( false === $raw ) {
			$this->diagnostic(
				'error',
				'direct_upload_request_failed',
				'The direct upload request failed.',
				array(
					'reason' => $error ? $error : 'curl_exec returned false',
				)
			);

			return new WP_Error( 'alynt_drime_upload_failed', __( 'The direct upload request failed. Check the server network connection and try again.', 'alynt-drime-backups-uploader' ) );
		}

		return array(
			'raw'  => (string) $raw,
			'code' => $code,
		);
	}

	/**
	 * Decodes a direct upload response.
	 *
	 * @param array{raw:string,code:int} $response Upload response.
	 * @return array<string,mixed>|WP_Error
	 */
	private function decode_simple_upload_response( array $response ) {
		$decoded = json_decode( $response['raw'], true );

		if ( $response['code'] < 200 || $response['code'] >= 300 || ! is_array( $decoded ) ) {
			return new WP_Error( 'alynt_drime_upload_failed', __( 'Drime rejected the direct upload request.', 'alynt-drime-backups-uploader' ) );
		}

		if ( empty( $decoded['fileEntry'] ) || ! is_array( $decoded['fileEntry'] ) ) {
			return $this->malformed_response( 'simple_upload' );
		}

		return $decoded;
	}
}
