<?php
/**
 * Package sidecar upload helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles package sidecar uploads.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Uploader_Sidecars {
	/**
	 * Uploads generic package sidecars to the same Drime parent as the archive.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param string              $package_path Package path.
	 * @param array<string,mixed> $settings Settings.
	 * @param int|null            $parent_id Concrete upload parent folder ID.
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	private function upload_package_sidecars( array $item, $package_path, array $settings, $parent_id = null ) {
		$sidecars = array();

		foreach ( $this->package_sidecar_paths( $item, $package_path ) as $kind => $path ) {
			$size = filesize( $path );
			if ( false === $size || $size <= 0 ) {
				return new WP_Error( 'alynt_drime_sidecar_empty', __( 'A package sidecar is empty and could not be uploaded.', 'alynt-drime-backups-uploader' ) );
			}

			$remote_name = basename( $path );
			$remote_name = $this->preflight_remote_name( $remote_name, (int) $size, $settings, $parent_id );
			if ( is_wp_error( $remote_name ) ) {
				return $remote_name;
			}

			if ( false === $remote_name ) {
				$sidecars[] = array(
					'type'             => $kind,
					'path'             => $path,
					'remote_name'      => basename( $path ),
					'size'             => (int) $size,
					'skipped_existing' => true,
				);
				continue;
			}

			$result = $this->simple_upload_item( $path, $remote_name, (int) $size, $parent_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$result['type'] = $kind;
			$sidecars[]     = $result;

			$this->logger->event( 'upload', 'info', 'sidecar_upload_completed', 'Package sidecar uploaded.', array( 'file' => basename( $path ) ) );
		}

		return $sidecars;
	}

	/**
	 * Returns readable package sidecars that are safe to upload with the archive.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param string              $package_path Package path.
	 * @return array<string,string>
	 */
	private function package_sidecar_paths( array $item, $package_path ) {
		if ( ! isset( $item['producer_key'] ) || 'generic_outbox' !== (string) $item['producer_key'] ) {
			return array();
		}

		$paths = array();
		foreach (
			array(
				'manifest'     => 'manifest_path',
				'checksum'     => 'checksum_path',
				'remote_index' => 'remote_index_path',
			) as $kind => $key
		) {
			$path = isset( $item[ $key ] ) && is_scalar( $item[ $key ] ) ? (string) $item[ $key ] : '';
			if ( '' === $path ) {
				continue;
			}

			if ( ! $this->is_package_sidecar_path( $path, $package_path ) || ! is_file( $path ) || ! is_readable( $path ) ) {
				return array();
			}

			$paths[ $kind ] = $path;
		}

		return $paths;
	}

	/**
	 * Checks whether a sidecar path belongs to a package path.
	 *
	 * @param string $sidecar_path Sidecar path.
	 * @param string $package_path Package path.
	 * @return bool
	 */
	private function is_package_sidecar_path( $sidecar_path, $package_path ) {
		$package_dir  = dirname( $package_path );
		$package_name = basename( $package_path );
		$stem_name    = basename( $this->package_archive_stem( $package_path ) );

		return dirname( $sidecar_path ) === $package_dir
			&& (
				0 === strpos( basename( $sidecar_path ), $package_name . '.' )
				|| ( '' !== $stem_name && 0 === strpos( basename( $sidecar_path ), $stem_name . '.' ) )
			);
	}

	/**
	 * Returns an archive path without a known archive extension.
	 *
	 * @param string $package_path Package path.
	 * @return string
	 */
	private function package_archive_stem( $package_path ) {
		$lower = strtolower( $package_path );

		foreach ( array( '.tar.zst', '.tar.gz', '.tgz', '.zip', '.tar' ) as $extension ) {
			if ( substr( $lower, -strlen( $extension ) ) === $extension ) {
				return substr( $package_path, 0, -strlen( $extension ) );
			}
		}

		return '';
	}
}
