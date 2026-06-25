<?php
/**
 * Drime folder browser destination preview helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Destination preview helpers for the Drime folder browser.
 *
 * @since 0.3.0
 */
trait Alynt_Drime_Backups_Uploader_Folder_Browser_Preview {
	/**
	 * Resolves a folder by numeric ID from the user's folder tree.
	 *
	 * @param int $folder_id Folder ID.
	 * @return array<string,mixed>|WP_Error
	 */
	private function resolve_folder_by_id( $folder_id ) {
		$list = $this->list_folders();
		if ( is_wp_error( $list ) ) {
			return $list;
		}

		foreach ( $list['folders'] as $folder ) {
			if ( absint( $folder['id'] ) === $folder_id && '' !== $folder['hash'] ) {
				return $folder;
			}
		}

		return new WP_Error( 'alynt_drime_parent_folder_not_found', __( 'The selected Drime base folder could not be found.', 'alynt-drime-backups-uploader' ) );
	}

	/**
	 * Gets a display path for a folder hash.
	 *
	 * @param string $folder_hash Folder hash.
	 * @return string|WP_Error
	 */
	private function base_path_for_hash( $folder_hash ) {
		if ( '' === $folder_hash ) {
			return __( 'Drime root', 'alynt-drime-backups-uploader' );
		}

		$response = $this->client->get_folder_path( $folder_hash );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->normalize_path_response( $response );
	}

	/**
	 * Walks existing relative path segments below a base folder hash.
	 *
	 * @param string            $parent_folder_hash Parent folder hash.
	 * @param array<int,string> $segments Relative path segments.
	 * @return array{existing_segments:array<int,string>,missing_segments:array<int,string>}|WP_Error
	 */
	private function walk_existing_segments( $parent_folder_hash, array $segments ) {
		$existing     = array();
		$missing      = array();
		$current_hash = $parent_folder_hash;

		foreach ( $segments as $index => $segment ) {
			if ( '' === $current_hash ) {
				$missing = array_slice( $segments, $index );
				break;
			}

			$children = $this->list_folders( $current_hash );
			if ( is_wp_error( $children ) ) {
				return $children;
			}

			$match = $this->find_child_folder( $children['folders'], $segment );
			if ( null === $match ) {
				$missing = array_slice( $segments, $index );
				break;
			}

			$existing[]   = $segment;
			$current_hash = (string) $match['hash'];
		}

		return array(
			'existing_segments' => $existing,
			'missing_segments'  => $missing,
		);
	}

	/**
	 * Finds a child folder by name.
	 *
	 * @param array<int,array<string,mixed>> $folders Folders.
	 * @param string                         $name Folder name.
	 * @return array<string,mixed>|null
	 */
	private function find_child_folder( array $folders, $name ) {
		foreach ( $folders as $folder ) {
			if ( strtolower( (string) $folder['name'] ) === strtolower( $name ) ) {
				return $folder;
			}
		}

		return null;
	}
}
