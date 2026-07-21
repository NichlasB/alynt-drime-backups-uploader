<?php
/**
 * Failed registry context sanitization.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizes failed upload registry context.
 *
 * @since 0.5.0
 */
trait Alynt_Drime_Backups_Uploader_Backup_Registry_Failed_Context {
	/**
	 * Sanitizes failed-upload context values.
	 *
	 * @param array<string,mixed> $context Context.
	 * @return array<string,mixed>
	 */
	private function sanitize_failed_context( array $context ) {
		$sanitized = array();

		foreach ( array( 'signature', 'name', 'path', 'producer_key', 'producer_label', 'package_id', 'filename', 'backup_set_id', 'manifest_path', 'checksum_path', 'remote_index_path', 'remote_catalog_path', 'checksum_algorithm', 'checksum_value' ) as $key ) {
			if ( isset( $context[ $key ] ) && is_scalar( $context[ $key ] ) ) {
				$sanitized[ $key ] = sanitize_text_field( (string) $context[ $key ] );
			}
		}

		foreach ( array( 'size', 'attempts', 'backup_set_index', 'backup_set_total' ) as $key ) {
			if ( isset( $context[ $key ] ) ) {
				$sanitized[ $key ] = absint( $context[ $key ] );
			}
		}

		if ( isset( $context['wpvivid'] ) && is_array( $context['wpvivid'] ) ) {
			$sanitized['wpvivid'] = $this->sanitize_wpvivid_context( $context['wpvivid'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitizes WPvivid metadata stored with registry records.
	 *
	 * @param array<string,mixed> $metadata Metadata.
	 * @return array<string,mixed>
	 */
	private function sanitize_wpvivid_context( array $metadata ) {
		$sanitized = array();

		foreach ( array( 'backup_id', 'set_signature' ) as $key ) {
			if ( isset( $metadata[ $key ] ) && is_scalar( $metadata[ $key ] ) ) {
				$sanitized[ $key ] = sanitize_text_field( (string) $metadata[ $key ] );
			}
		}

		foreach ( array( 'file_count', 'part_number' ) as $key ) {
			if ( isset( $metadata[ $key ] ) ) {
				$sanitized[ $key ] = absint( $metadata[ $key ] );
			}
		}

		if ( isset( $metadata['from_list'] ) ) {
			$sanitized['from_list'] = (bool) $metadata['from_list'];
		}

		if ( isset( $metadata['set_files'] ) && is_array( $metadata['set_files'] ) ) {
			$sanitized['set_files'] = array_values(
				array_filter(
					array_map(
						function ( $file ) {
							return is_scalar( $file ) ? sanitize_text_field( basename( (string) $file ) ) : '';
						},
						$metadata['set_files']
					)
				)
			);
		}

		return $sanitized;
	}
}
