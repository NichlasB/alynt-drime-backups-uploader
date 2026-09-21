<?php
/**
 * Generic outbox archive helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes archive names, paths, signatures, and supported archive checks.
 *
 * @since 0.5.22
 */
trait Alynt_Drime_Backups_Uploader_Generic_Outbox_Producer_Archive_Helpers {
	/**
	 * Returns an archive path without its known archive extension.
	 *
	 * @param string $file Archive path.
	 * @return string
	 */
	private function archive_stem( $file ) {
		foreach ( $this->archive_extensions() as $extension ) {
			if ( $this->ends_with( strtolower( $file ), $extension ) ) {
				return substr( $file, 0, -strlen( $extension ) );
			}
		}

		return $file;
	}

	/**
	 * Returns whether a file is a supported package archive.
	 *
	 * @param string $name File name.
	 * @return bool
	 */
	private function is_supported_archive( $name ) {
		$lower = strtolower( $name );

		foreach ( $this->archive_extensions() as $extension ) {
			if ( $this->ends_with( $lower, $extension ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns supported archive extensions.
	 *
	 * @return array<int,string>
	 */
	private function archive_extensions() {
		return array( '.tar.zst', '.tar.gz', '.tgz', '.zip', '.tar' );
	}

	/**
	 * Determines whether a filename looks incomplete.
	 *
	 * @param string $name File name.
	 * @return bool
	 */
	private function looks_temporary( $name ) {
		$lower = strtolower( basename( $name ) );

		return (bool) preg_match( '/(?:\.tmp|\.part|\.partial|temp|partial|incomplete)(?:$|\.)/', $lower );
	}

	/**
	 * Builds a stable snapshot key for file stability tracking.
	 *
	 * @param string $file File path.
	 * @return string
	 */
	private function snapshot_key( $file ) {
		return hash( 'sha256', wp_normalize_path( $file ) );
	}

	/**
	 * Builds a package signature that changes when a path is replaced.
	 *
	 * @param string $file File path.
	 * @param int    $size File size.
	 * @param int    $mtime Modified timestamp.
	 * @return string
	 */
	private function package_signature( $file, $size, $mtime ) {
		return hash( 'sha256', wp_normalize_path( $file ) . '|' . (int) $size . '|' . (int) $mtime );
	}

	/**
	 * Normalizes a filesystem path.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function normalize_path( $path ) {
		return untrailingslashit( wp_normalize_path( trim( $path ) ) );
	}

	/**
	 * Checks whether a string ends with a suffix.
	 *
	 * @param string $value Value.
	 * @param string $suffix Suffix.
	 * @return bool
	 */
	private function ends_with( $value, $suffix ) {
		return substr( $value, -strlen( $suffix ) ) === $suffix;
	}
}
