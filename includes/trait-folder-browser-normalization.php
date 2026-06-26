<?php
/**
 * Drime folder browser normalization helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalization helpers for the Drime folder browser.
 *
 * @since 0.3.0
 */
trait Alynt_Drime_Backups_Uploader_Folder_Browser_Normalization {
	/**
	 * Filters folders by name or path.
	 *
	 * @param array<int,array<string,mixed>> $folders Folders.
	 * @param string                         $query Search query.
	 * @return array<int,array<string,mixed>>
	 */
	private function filter_folders( array $folders, $query ) {
		$query = strtolower( trim( (string) $query ) );
		if ( '' === $query ) {
			return $folders;
		}

		return array_values(
			array_filter(
				$folders,
				function ( $folder ) use ( $query ) {
					return false !== strpos( strtolower( (string) $folder['name'] ), $query )
						|| false !== strpos( strtolower( (string) $folder['path'] ), $query );
				}
			)
		);
	}

	/**
	 * Normalizes a collection of folders from common Drime response shapes.
	 *
	 * @param array<string,mixed> $response Response.
	 * @return array<int,array<string,mixed>>
	 */
	private function normalize_folders( array $response ) {
		$items         = $this->extract_items( $response );
		$folders       = array();
		$names_by_id   = array();
		$parents_by_id = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['id'], $item['name'] ) ) {
				continue;
			}

			$id = absint( $item['id'] );
			if ( 0 === $id ) {
				continue;
			}

			$names_by_id[ $id ]   = sanitize_text_field( (string) $item['name'] );
			$parents_by_id[ $id ] = isset( $item['parent_id'] ) ? absint( $item['parent_id'] ) : 0;
		}

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! $this->is_folder_item( $item ) ) {
				continue;
			}

			$name = isset( $item['name'] ) ? sanitize_text_field( (string) $item['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}

			$folders[] = array(
				'id'           => isset( $item['id'] ) ? absint( $item['id'] ) : 0,
				'hash'         => $this->sanitize_hash( isset( $item['hash'] ) ? (string) $item['hash'] : ( isset( $item['folder_hash'] ) ? (string) $item['folder_hash'] : '' ) ),
				'name'         => $name,
				'parent_id'    => isset( $item['parent_id'] ) ? absint( $item['parent_id'] ) : 0,
				'path'         => $this->display_path_for_item( $item, $name, $names_by_id, $parents_by_id ),
				'workspace_id' => isset( $item['workspace_id'] ) ? absint( $item['workspace_id'] ) : 0,
			);
		}

		return $folders;
	}

	/**
	 * Extracts list items from common response containers.
	 *
	 * @param array<string,mixed> $response Response.
	 * @return array<int,mixed>
	 */
	private function extract_items( array $response ) {
		foreach ( array( 'folders', 'fileEntries', 'entries', 'data' ) as $key ) {
			if ( isset( $response[ $key ] ) && is_array( $response[ $key ] ) ) {
				if ( isset( $response[ $key ]['data'] ) && is_array( $response[ $key ]['data'] ) ) {
					return array_values( $response[ $key ]['data'] );
				}

				return array_values( $response[ $key ] );
			}
		}

		return array_values( $response );
	}

	/**
	 * Returns whether an item is a folder.
	 *
	 * @param array<string,mixed> $item Item.
	 * @return bool
	 */
	private function is_folder_item( array $item ) {
		if ( isset( $item['type'] ) ) {
			return 'folder' === strtolower( (string) $item['type'] );
		}

		if ( isset( $item['is_folder'] ) ) {
			return (bool) $item['is_folder'];
		}

		return true;
	}

	/**
	 * Normalizes a folder path response.
	 *
	 * @param array<string,mixed>|string $response Response.
	 * @return string
	 */
	private function normalize_path_response( $response ) {
		if ( is_string( $response ) ) {
			return $this->sanitize_display_path( $response );
		}

		foreach ( array( 'path', 'fullPath', 'breadcrumb' ) as $key ) {
			if ( ! empty( $response[ $key ] ) ) {
				if ( is_array( $response[ $key ] ) ) {
					return $this->display_path_from_breadcrumb( $response[ $key ] );
				}

				return $this->sanitize_display_path( (string) $response[ $key ] );
			}
		}

		if ( ! empty( $response['data'] ) && is_array( $response['data'] ) ) {
			return $this->normalize_path_response( $response['data'] );
		}

		return __( 'Selected Drime folder', 'alynt-drime-backups-uploader' );
	}

	/**
	 * Sanitizes a folder hash.
	 *
	 * @param string $hash Hash.
	 * @return string
	 */
	private function sanitize_hash( $hash ) {
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $hash );
	}

	/**
	 * Builds a human-readable display path for a folder item.
	 *
	 * @param array<string,mixed> $item Item.
	 * @param string              $name Folder name.
	 * @param array<int,string>   $names_by_id Folder names by ID.
	 * @param array<int,int>      $parents_by_id Parent IDs by folder ID.
	 * @return string
	 */
	private function display_path_for_item( array $item, $name, array $names_by_id, array $parents_by_id ) {
		$raw_path = $this->sanitize_display_path( isset( $item['path'] ) ? (string) $item['path'] : ( isset( $item['fullPath'] ) ? (string) $item['fullPath'] : '' ) );

		if ( '' !== $raw_path && ! preg_match( '/^\d+(\/\d+)*$/', $raw_path ) ) {
			return $raw_path;
		}

		if ( '' !== $raw_path ) {
			$names = array();
			foreach ( explode( '/', $raw_path ) as $id ) {
				if ( empty( $names_by_id[ absint( $id ) ] ) ) {
					return $raw_path;
				}

				$names[] = $names_by_id[ absint( $id ) ];
			}

			return $this->sanitize_display_path( implode( '/', $names ) );
		}

		$id    = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
		$names = array( $name );
		$seen  = array();

		while ( ! empty( $parents_by_id[ $id ] ) && empty( $seen[ $parents_by_id[ $id ] ] ) ) {
			$id          = $parents_by_id[ $id ];
			$seen[ $id ] = true;
			array_unshift( $names, isset( $names_by_id[ $id ] ) ? $names_by_id[ $id ] : (string) $id );
		}

		return $this->sanitize_display_path( implode( '/', $names ) );
	}

	/**
	 * Builds a display path from a Drime breadcrumb array.
	 *
	 * @param array<int,mixed> $items Breadcrumb items.
	 * @return string
	 */
	private function display_path_from_breadcrumb( array $items ) {
		$names = array();

		foreach ( $items as $item ) {
			if ( is_array( $item ) && ! empty( $item['name'] ) ) {
				$names[] = sanitize_text_field( (string) $item['name'] );
			}
		}

		return empty( $names ) ? __( 'Selected Drime folder', 'alynt-drime-backups-uploader' ) : $this->sanitize_display_path( implode( '/', $names ) );
	}

	/**
	 * Sanitizes a relative path.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function sanitize_relative_path( $path ) {
		$path = sanitize_text_field( $path );
		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '#/+#', '/', $path );
		$path = trim( (string) $path );

		if ( '' === $path || false !== strpos( $path, '..' ) ) {
			return '';
		}

		$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ), 'strlen' ) );
		if ( count( $segments ) > 20 ) {
			return '';
		}

		foreach ( $segments as $segment ) {
			if ( strlen( $segment ) > 120 ) {
				return '';
			}
		}

		return '/' . implode( '/', $segments );
	}

	/**
	 * Splits a relative path into folder names.
	 *
	 * @param string $relative_path Relative path.
	 * @return array<int,string>
	 */
	private function relative_path_segments( $relative_path ) {
		if ( '' === $relative_path ) {
			return array();
		}

		return array_values( array_filter( explode( '/', trim( $relative_path, '/' ) ), 'strlen' ) );
	}

	/**
	 * Sanitizes a display path.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function sanitize_display_path( $path ) {
		$path = sanitize_text_field( str_replace( '\\', '/', $path ) );
		$path = preg_replace( '#/+#', '/', $path );

		return trim( (string) $path, '/' );
	}

	/**
	 * Joins a base and relative path for display.
	 *
	 * @param string $base Base path.
	 * @param string $relative Relative path.
	 * @return string
	 */
	private function join_paths( $base, $relative ) {
		$base     = trim( (string) $base, '/' );
		$relative = trim( (string) $relative, '/' );

		if ( '' === $relative ) {
			return '' === $base ? __( 'Drime root', 'alynt-drime-backups-uploader' ) : $base;
		}

		return ( '' === $base || __( 'Drime root', 'alynt-drime-backups-uploader' ) === $base ) ? $relative : $base . '/' . $relative;
	}
}
