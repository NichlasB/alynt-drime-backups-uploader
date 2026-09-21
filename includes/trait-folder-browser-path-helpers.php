<?php
/**
 * Drime folder browser path helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Path helpers for the Drime folder browser.
 *
 * @since 0.3.0
 */
trait Alynt_Drime_Backups_Uploader_Folder_Browser_Path_Helpers {
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
