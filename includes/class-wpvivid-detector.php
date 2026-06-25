<?php
/**
 * WPvivid backup path detection.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects the WPvivid local backup directory defensively.
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_WPvivid_Detector {
	/**
	 * Returns the best known backup directory.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 *
	 * @since 0.1.0
	 */
	public function get_backup_dir( array $settings ) {
		if ( ! empty( $settings['backup_path_override'] ) ) {
			return $this->normalize_path( (string) $settings['backup_path_override'] );
		}

		$common = get_option( 'wpvivid_common_setting', array() );
		$local  = get_option( 'wpvivid_local_setting', array() );

		if ( ! is_array( $common ) ) {
			$common = array();
		}

		if ( ! is_array( $local ) ) {
			$local = array();
		}

		$folder_mode = isset( $common['local_backup_folder'] ) ? (string) $common['local_backup_folder'] : 'content_folder';

		if ( 'outside_folder' === $folder_mode && ! empty( $local['outside_path'] ) ) {
			return $this->normalize_path( (string) $local['outside_path'] );
		}

		$relative_path = isset( $local['path'] ) && '' !== (string) $local['path'] ? (string) $local['path'] : 'wpvividbackups';

		return $this->normalize_backup_path( $relative_path );
	}

	/**
	 * Normalizes a WPvivid content-relative or absolute backup path.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function normalize_backup_path( $path ) {
		$path = trim( $path );

		if ( $this->is_absolute_path( $path ) ) {
			return $this->normalize_path( $path );
		}

		return $this->normalize_path( WP_CONTENT_DIR . '/' . ltrim( $path, '/\\' ) );
	}

	/**
	 * Normalizes path separators and trailing slashes.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function normalize_path( $path ) {
		return untrailingslashit( wp_normalize_path( $path ) );
	}

	/**
	 * Determines whether a path is already absolute.
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	private function is_absolute_path( $path ) {
		return (bool) preg_match( '#^(?:[A-Za-z]:[\\\\/]|/|\\\\\\\\)#', $path );
	}
}
