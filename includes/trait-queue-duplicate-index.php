<?php
/**
 * Upload queue duplicate lookup helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Upload queue duplicate lookup helpers.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Queue_Duplicate_Index {
	/**
	 * Builds duplicate lookup maps for queued paths and producer-specific files.
	 *
	 * @param array<string,array<string,mixed>> $queue Queue.
	 * @return array<string,array<string,bool>>
	 */
	private function duplicate_index( array $queue ) {
		$index = array(
			'paths'   => array(),
			'wpvivid' => array(),
		);

		foreach ( $queue as $existing ) {
			if ( ! is_array( $existing ) ) {
				continue;
			}

			$this->index_duplicate_item( $index, $existing );
		}

		return $index;
	}

	/**
	 * Adds one queue item to duplicate lookup maps.
	 *
	 * @param array<string,array<string,bool>> $index Duplicate index.
	 * @param array<string,mixed>              $item Item.
	 * @return void
	 */
	private function index_duplicate_item( array &$index, array $item ) {
		$path = $this->local_path_key( $item );
		if ( '' !== $path ) {
			$index['paths'][ $path ] = true;
		}

		$wpvivid = $this->wpvivid_file_key( $item );
		if ( '' !== $wpvivid ) {
			$index['wpvivid'][ $wpvivid ] = true;
		}
	}

	/**
	 * Checks for duplicate queue entries beyond the signature key.
	 *
	 * @param array<string,array<string,bool>> $index Duplicate index.
	 * @param array<string,mixed>              $item Item.
	 * @return bool
	 */
	private function has_indexed_duplicate_item( array $index, array $item ) {
		$path    = $this->local_path_key( $item );
		$wpvivid = $this->wpvivid_file_key( $item );

		return ( '' !== $path && isset( $index['paths'][ $path ] ) ) || ( '' !== $wpvivid && isset( $index['wpvivid'][ $wpvivid ] ) );
	}

	/**
	 * Returns a normalized local path lookup key.
	 *
	 * @param array<string,mixed> $item Item.
	 * @return string
	 */
	private function local_path_key( array $item ) {
		return isset( $item['path'] ) ? wp_normalize_path( (string) $item['path'] ) : '';
	}

	/**
	 * Returns a WPvivid backup file lookup key.
	 *
	 * @param array<string,mixed> $item Item.
	 * @return string
	 */
	private function wpvivid_file_key( array $item ) {
		$id   = $this->wpvivid_backup_id( $item );
		$name = isset( $item['name'] ) ? (string) $item['name'] : '';

		return '' !== $id && '' !== $name ? $id . '|' . $name : '';
	}

	/**
	 * Returns a queue item's WPvivid backup id.
	 *
	 * @param array<string,mixed> $item Item.
	 * @return string
	 */
	private function wpvivid_backup_id( array $item ) {
		if ( empty( $item['wpvivid'] ) || ! is_array( $item['wpvivid'] ) || empty( $item['wpvivid']['backup_id'] ) ) {
			return '';
		}

		return (string) $item['wpvivid']['backup_id'];
	}
}
