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
	use Alynt_Drime_Backups_Uploader_Drime_Client_Transport;
	use Alynt_Drime_Backups_Uploader_Drime_Client_Response_Helpers;
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
	 * @since 0.2.0
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
}
