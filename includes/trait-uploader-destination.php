<?php
/**
 * Uploader destination helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves selected base folder plus relative path to a concrete upload folder.
 *
 * @since 0.3.0
 */
trait Alynt_Drime_Backups_Uploader_Uploader_Destination {
	/**
	 * Prepares the concrete Drime parent folder for the upload.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return int|null|WP_Error
	 */
	private function prepare_upload_parent_id( array $settings ) {
		if ( empty( $settings['relative_path'] ) ) {
			return $this->resolved_drime_parent_id( $settings );
		}

		$cached_parent_id = $this->registry->get_drime_parent_id( absint( $settings['workspace_id'] ), (string) $settings['relative_path'], absint( $settings['parent_folder_id'] ) );
		if ( $cached_parent_id > 0 ) {
			return $cached_parent_id;
		}

		if ( empty( $settings['parent_folder_id'] ) || empty( $settings['parent_folder_hash'] ) ) {
			return null;
		}

		$parent_id = absint( $settings['parent_folder_id'] );
		$hash      = (string) $settings['parent_folder_hash'];

		foreach ( $this->upload_relative_segments( (string) $settings['relative_path'] ) as $segment ) {
			$folder = $this->find_upload_child_folder( absint( $settings['workspace_id'] ), $hash, $segment );
			if ( is_wp_error( $folder ) ) {
				return $folder;
			}

			if ( empty( $folder ) ) {
				$folder = $this->create_upload_child_folder( absint( $settings['workspace_id'] ), $segment, $parent_id );
				if ( is_wp_error( $folder ) ) {
					return $folder;
				}
			}

			$parent_id = absint( $folder['id'] );
			$hash      = (string) $folder['hash'];
		}

		return $parent_id > 0 ? $parent_id : null;
	}

	/**
	 * Splits a saved relative path into safe folder names.
	 *
	 * @param string $path Relative path.
	 * @return array<int,string>
	 */
	private function upload_relative_segments( $path ) {
		$segments = array();

		foreach ( explode( '/', trim( str_replace( '\\', '/', $path ), '/' ) ) as $segment ) {
			$segment = sanitize_text_field( $segment );
			if ( '' !== $segment && false === strpos( $segment, '..' ) && strlen( $segment ) <= Alynt_Drime_Backups_Uploader_Settings::MAX_RELATIVE_PATH_SEGMENT_CHARS ) {
				$segments[] = $segment;
			}
		}

		return array_slice( $segments, 0, Alynt_Drime_Backups_Uploader_Settings::MAX_RELATIVE_PATH_SEGMENTS );
	}

	/**
	 * Finds an existing child folder under a parent hash.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $parent_hash Parent folder hash.
	 * @param string $name Folder name.
	 * @return array{id:int,hash:string}|array{}|WP_Error
	 */
	private function find_upload_child_folder( $workspace_id, $parent_hash, $name ) {
		$response = $this->client->list_folder_entries( $workspace_id, $parent_hash, 1, $name );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$folder = $this->find_upload_child_folder_in_response( $response, $name );
		if ( ! empty( $folder ) ) {
			return $folder;
		}

		$response = $this->client->list_folder_entries( $workspace_id, $parent_hash, 1, '' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->find_upload_child_folder_in_response( $response, $name );
	}

	/**
	 * Finds a named folder in a child-folder response.
	 *
	 * @param array<string,mixed> $response Response.
	 * @param string              $name Folder name.
	 * @return array{id:int,hash:string}|array{}
	 */
	private function find_upload_child_folder_in_response( array $response, $name ) {
		foreach ( $this->upload_folder_items( $response ) as $item ) {
			if ( ! is_array( $item ) || strtolower( (string) $item['name'] ) !== strtolower( $name ) ) {
				continue;
			}

			$folder = $this->upload_folder_from_item( $item );
			if ( ! empty( $folder ) ) {
				return $folder;
			}
		}

		return array();
	}

	/**
	 * Creates a missing upload child folder.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $name Folder name.
	 * @param int    $parent_id Parent folder ID.
	 * @return array{id:int,hash:string}|WP_Error
	 */
	private function create_upload_child_folder( $workspace_id, $name, $parent_id ) {
		$response = $this->client->create_folder( $workspace_id, $name, $parent_id );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$folder = $this->upload_folder_from_response( $response );
		if ( empty( $folder ) ) {
			return new WP_Error( 'alynt_drime_folder_create_failed', __( 'Drime did not return the created folder ID.', 'alynt-drime-backups-uploader' ) );
		}

		return $folder;
	}

	/**
	 * Extracts possible folder items from a response.
	 *
	 * @param array<string,mixed> $response Response.
	 * @return array<int,mixed>
	 */
	private function upload_folder_items( array $response ) {
		foreach ( array( 'folders', 'fileEntries', 'entries', 'data' ) as $key ) {
			if ( isset( $response[ $key ] ) && is_array( $response[ $key ] ) ) {
				return isset( $response[ $key ]['data'] ) && is_array( $response[ $key ]['data'] ) ? array_values( $response[ $key ]['data'] ) : array_values( $response[ $key ] );
			}
		}

		return array_values( $response );
	}

	/**
	 * Extracts a folder from common create/list response shapes.
	 *
	 * @param array<string,mixed> $response Response.
	 * @return array{id:int,hash:string}|array{}
	 */
	private function upload_folder_from_response( array $response ) {
		foreach ( array( 'folder', 'fileEntry', 'data' ) as $key ) {
			if ( isset( $response[ $key ] ) && is_array( $response[ $key ] ) ) {
				return $this->upload_folder_from_item( $response[ $key ] );
			}
		}

		return $this->upload_folder_from_item( $response );
	}

	/**
	 * Extracts a folder ID and hash from one item.
	 *
	 * @param array<string,mixed> $item Item.
	 * @return array{id:int,hash:string}|array{}
	 */
	private function upload_folder_from_item( array $item ) {
		$id   = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
		$hash = isset( $item['hash'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $item['hash'] ) : '';

		return $id > 0 && '' !== $hash
			? array(
				'id'   => $id,
				'hash' => $hash,
			)
			: array();
	}
}
