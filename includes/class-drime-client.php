<?php
/**
 * Drime API client.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Isolates Drime API calls.
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_Drime_Client {
	use Alynt_Drime_Backups_Uploader_Drime_Client_Direct_Upload;
	use Alynt_Drime_Backups_Uploader_Drime_Client_Multipart;

	const BASE_URL                  = 'https://app.drime.cloud/api/v1';
	const MIN_MULTIPART_CHUNK_SIZE  = Alynt_Drime_Backups_Uploader_Settings::MIN_MULTIPART_CHUNK_SIZE_MB * 1048576;
	const MAX_MULTIPART_CHUNK_SIZE  = Alynt_Drime_Backups_Uploader_Settings::MAX_MULTIPART_CHUNK_SIZE_MB * 1048576;
	const DEFAULT_MULTIPART_SIZE_MB = Alynt_Drime_Backups_Uploader_Settings::DEFAULT_MULTIPART_CHUNK_SIZE_MB;
	const DEFAULT_MULTIPART_SIZE    = self::DEFAULT_MULTIPART_SIZE_MB * 1048576;
	const API_REQUEST_TIMEOUT       = 180;

	/**
	 * Settings.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Settings
	 */
	private $settings;

	/**
	 * Logger.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Logger|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Settings    $settings Settings.
	 * @param Alynt_Drime_Backups_Uploader_Logger|null $logger Logger.
	 *
	 * @since 0.1.0
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Settings $settings, ?Alynt_Drime_Backups_Uploader_Logger $logger = null ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Tests the configured token.
	 *
	 * @return true|WP_Error
	 *
	 * @since 0.1.0
	 */
	public function test_connection() {
		$settings = $this->settings->get();

		return $this->request( 'GET', '/drive/file-entries?workspaceId=' . absint( $settings['workspace_id'] ) . '&perPage=1' );
	}

	/**
	 * Gets the authenticated Drime user.
	 *
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.3.0
	 */
	public function get_logged_user() {
		$response = $this->request( 'GET', '/cli/loggedUser' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$user_id = $this->extract_user_id( $response );
		if ( $user_id <= 0 ) {
			return $this->malformed_response( 'get_logged_user' );
		}

		$response['id'] = $user_id;

		return $response;
	}

	/**
	 * Lists workspaces available to the authenticated Drime user.
	 *
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.5.0
	 */
	public function list_workspaces() {
		return $this->request( 'GET', '/me/workspaces' );
	}

	/**
	 * Lists folders for the authenticated Drime user.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.3.0
	 */
	public function list_user_folders( $workspace_id = 0 ) {
		$user = $this->get_logged_user();
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		return $this->request( 'GET', '/users/' . absint( $user['id'] ) . '/folders?workspaceId=' . absint( $workspace_id ) );
	}

	/**
	 * Lists child folder entries.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $folder_hash Folder hash.
	 * @param int    $page Page number.
	 * @param string $query Search query.
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.3.0
	 */
	public function list_folder_entries( $workspace_id, $folder_hash, $page = 1, $query = '' ) {
		$args = array(
			'workspaceId' => absint( $workspace_id ),
			'type'        => 'folder',
			'folderId'    => $this->sanitize_folder_hash( $folder_hash ),
			'page'        => max( 1, absint( $page ) ),
			'perPage'     => 100,
		);

		$query = sanitize_text_field( $query );
		if ( '' !== $query ) {
			$args['search'] = $query;
		}

		return $this->request( 'GET', '/drive/file-entries?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 ) );
	}

	/**
	 * Gets a folder breadcrumb path.
	 *
	 * @param string $folder_hash Folder hash.
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.3.0
	 */
	public function get_folder_path( $folder_hash ) {
		$folder_hash = $this->sanitize_folder_hash( $folder_hash );
		if ( '' === $folder_hash ) {
			return new WP_Error( 'alynt_drime_missing_folder_hash', __( 'A Drime folder hash is required.', 'alynt-drime-backups-uploader' ) );
		}

		return $this->request( 'GET', '/folders/' . rawurlencode( $folder_hash ) . '/path' );
	}

	/**
	 * Creates a Drime folder.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $name Folder name.
	 * @param int    $parent_id Parent folder ID.
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.3.0
	 */
	public function create_folder( $workspace_id, $name, $parent_id = 0 ) {
		$body = array(
			'name' => sanitize_text_field( $name ),
		);

		if ( absint( $parent_id ) > 0 ) {
			$body['parentId'] = absint( $parent_id );
		}

		return $this->request( 'POST', '/folders?workspaceId=' . absint( $workspace_id ), $body );
	}

	/**
	 * Validates duplicates.
	 *
	 * @param array<int,array<string,mixed>> $files Files.
	 * @param int|null                       $parent_id Parent folder ID.
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.1.0
	 */
	public function validate_upload( array $files, $parent_id = null ) {
		$settings = $this->settings->get();
		$body     = array(
			'files' => $files,
		);

		if ( null !== $parent_id && absint( $parent_id ) > 0 ) {
			$body['parentId'] = absint( $parent_id );
		}

		$response = $this->request(
			'POST',
			'/uploads/validate?workspaceId=' . absint( $settings['workspace_id'] ),
			$body
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['duplicates'] ) || ! is_array( $response['duplicates'] ) ) {
			return $this->malformed_response( 'validate_upload' );
		}

		return $response;
	}

	/**
	 * Gets an available filename.
	 *
	 * @param string   $name Name.
	 * @param int|null $parent_id Parent folder ID.
	 * @return string|WP_Error
	 *
	 * @since 0.1.0
	 */
	public function get_available_name( $name, $parent_id = null ) {
		$settings = $this->settings->get();
		$body     = array(
			'name'        => $name,
			'workspaceId' => absint( $settings['workspace_id'] ),
		);

		if ( null !== $parent_id && absint( $parent_id ) > 0 ) {
			$body['parentId'] = absint( $parent_id );
		}

		if ( '' !== $settings['relative_path'] && ( null === $parent_id || absint( $parent_id ) <= 0 || '' !== (string) $settings['parent_folder_id'] ) ) {
			$body['relativePath'] = $settings['relative_path'];
		} elseif ( null === $parent_id || absint( $parent_id ) <= 0 ) {
			$body['parentId'] = $this->parent_id_or_null( $settings );
		}

		$response = $this->request( 'POST', '/entry/getAvailableName', $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! empty( $response['available'] ) && is_string( $response['available'] ) ) {
			return $response['available'];
		}

		if ( empty( $response['name'] ) || ! is_string( $response['name'] ) ) {
			return $this->malformed_response( 'get_available_name' );
		}

		return $response['name'];
	}

	/**
	 * Moves a Drime file entry to trash.
	 *
	 * This first retention implementation intentionally never sends a permanent
	 * delete request.
	 *
	 * @param int $file_entry_id Drime file entry ID.
	 * @return array<string,mixed>|true|WP_Error
	 *
	 * @since 0.1.0
	 */
	public function trash_file_entry( $file_entry_id ) {
		$file_entry_id = absint( $file_entry_id );

		if ( $file_entry_id <= 0 ) {
			return new WP_Error( 'alynt_drime_missing_file_entry_id', __( 'A Drime file entry ID is required before remote retention can run.', 'alynt-drime-backups-uploader' ) );
		}

		return $this->request(
			'POST',
			'/file-entries/delete',
			array(
				'entryIds'      => array( $file_entry_id ),
				'deleteForever' => false,
			)
		);
	}

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
