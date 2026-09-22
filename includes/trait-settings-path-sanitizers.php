<?php
/**
 * Settings path sanitizers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.21
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizes destination path, folder hash, and display path values.
 *
 * @since 0.5.21
 */
trait Alynt_Drime_Backups_Uploader_Settings_Path_Sanitizers {
	/**
	 * Normalizes an optional Drime relative path.
	 *
	 * @param string $path Raw path.
	 * @return string
	 */
	private function sanitize_relative_path( $path ) {
		$path = sanitize_text_field( $path );
		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '#/+#', '/', $path );
		$path = trim( (string) $path );

		if ( '' === $path ) {
			return '';
		}

		$path = '/' . trim( $path, '/' );

		if ( false !== strpos( $path, '..' ) ) {
			return '';
		}

		$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ), 'strlen' ) );
		if ( count( $segments ) > self::MAX_RELATIVE_PATH_SEGMENTS ) {
			return '';
		}

		foreach ( $segments as $segment ) {
			if ( strlen( $segment ) > self::MAX_RELATIVE_PATH_SEGMENT_CHARS ) {
				return '';
			}
		}

		return $path;
	}

	/**
	 * Sanitizes a non-secret Drime folder hash.
	 *
	 * @param string $hash Hash.
	 * @return string
	 */
	private function sanitize_folder_hash( $hash ) {
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $hash );
	}

	/**
	 * Sanitizes a non-secret Drime display path.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function sanitize_display_path( $path ) {
		$path = sanitize_text_field( str_replace( '\\', '/', $path ) );
		$path = preg_replace( '#/+#', '/', $path );

		return trim( (string) $path, '/' );
	}
}
