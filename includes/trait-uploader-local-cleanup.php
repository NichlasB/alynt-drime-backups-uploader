<?php
/**
 * Uploader local cleanup helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.21
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deletes uploaded local packages and safe sidecar files when configured.
 *
 * @since 0.5.21
 */
trait Alynt_Drime_Backups_Uploader_Uploader_Local_Cleanup {
	/**
	 * Deletes a local backup after successful upload when enabled.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @return void
	 */
	private function maybe_delete_local_file( array $item ) {
		$settings = $this->settings->get();

		if ( empty( $settings['delete_local_after_upload'] ) ) {
			return;
		}

		if ( $this->is_wpvivid_listed_multi_file_item( $item ) ) {
			$this->maybe_delete_wpvivid_set_files( $item );
			return;
		}

		$path = isset( $item['path'] ) ? (string) $item['path'] : '';
		if ( '' === $path || ! is_file( $path ) ) {
			$this->logger->event( 'filesystem', 'warning', 'local_delete_missing_file', 'Local backup deletion was skipped because the file no longer exists.' );
			return;
		}

		if ( ! wp_delete_file( $path ) ) {
			$this->logger->event( 'filesystem', 'error', 'local_delete_failed', 'Local backup deletion failed after upload.', array( 'file' => basename( $path ) ) );
			return;
		}

		$this->delete_local_sidecars( $item, $path );
		$this->logger->event( 'filesystem', 'info', 'local_delete_succeeded', 'Local backup file deleted after upload.', array( 'file' => basename( $path ) ) );
	}

	/**
	 * Deletes sidecar files that belong to a deleted local package.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param string              $path Deleted package path.
	 * @return void
	 */
	private function delete_local_sidecars( array $item, $path ) {
		$package_dir  = dirname( $path );
		$package_name = basename( $path );

		foreach ( array( 'manifest_path', 'checksum_path', 'remote_index_path', 'remote_catalog_path' ) as $key ) {
			$sidecar = isset( $item[ $key ] ) && is_scalar( $item[ $key ] ) ? (string) $item[ $key ] : '';
			if ( '' === $sidecar || ! is_file( $sidecar ) ) {
				continue;
			}

			if ( ! $this->is_package_sidecar_path( $sidecar, $path ) ) {
				$this->logger->event( 'filesystem', 'warning', 'local_delete_sidecar_skipped', 'Local backup sidecar deletion was skipped because the path did not match the deleted package.', array( 'file' => basename( $sidecar ) ) );
				continue;
			}

			if ( ! wp_delete_file( $sidecar ) ) {
				$this->logger->event( 'filesystem', 'warning', 'local_delete_sidecar_failed', 'Local backup sidecar deletion failed after upload.', array( 'file' => basename( $sidecar ) ) );
			}
		}
	}
}
