<?php
/**
 * Remote action store cleanup preview sanitization helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.23
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizes bounded, redacted cleanup preview state for remote-action records.
 *
 * @since 0.5.23
 */
trait Alynt_Drime_Backups_Uploader_Remote_Action_Store_Cleanup_Sanitizers {
	/**
	 * Sanitizes cleanup preview details for status-safe action history.
	 *
	 * @since 0.5.23
	 *
	 * @param array<string,mixed> $preview Cleanup preview details.
	 * @return array<string,mixed>
	 */
	private function safe_cleanup_preview( array $preview ) {
		if ( empty( $preview ) ) {
			return array();
		}

		$clean = array(
			'preview_action_id'    => isset( $preview['preview_action_id'] ) ? $this->sanitize_uuid( (string) $preview['preview_action_id'] ) : '',
			'preview_fingerprint'  => isset( $preview['preview_fingerprint'] ) ? $this->sanitize_hash( (string) $preview['preview_fingerprint'] ) : '',
			'capability_version'   => isset( $preview['capability_version'] ) ? absint( $preview['capability_version'] ) : 0,
			'scope'                => isset( $preview['scope'] ) ? sanitize_key( (string) $preview['scope'] ) : '',
			'preview_created_at'   => isset( $preview['preview_created_at'] ) ? sanitize_text_field( (string) $preview['preview_created_at'] ) : '',
			'expires_at'           => isset( $preview['expires_at'] ) ? sanitize_text_field( (string) $preview['expires_at'] ) : '',
			'categories'           => array(),
			'total_eligible_count' => isset( $preview['total_eligible_count'] ) ? max( 0, absint( $preview['total_eligible_count'] ) ) : 0,
			'total_approx_bytes'   => isset( $preview['total_approx_bytes'] ) ? max( 0, absint( $preview['total_approx_bytes'] ) ) : 0,
			'apply_supported'      => false,
		);

		if ( isset( $preview['categories'] ) && is_array( $preview['categories'] ) ) {
			foreach ( $preview['categories'] as $category ) {
				if ( is_string( $category ) ) {
					$category = array(
						'category'       => $category,
						'eligible_count' => 0,
						'approx_bytes'   => 0,
						'age_band'       => 'none',
						'reason_code'    => '',
					);
				}

				if ( ! is_array( $category ) ) {
					continue;
				}

				$category_key = isset( $category['category'] ) ? sanitize_key( (string) $category['category'] ) : '';
				if ( Alynt_Drime_Backups_Uploader_Dashboard_Connection::CLEANUP_CATEGORY_UPLOADER_TEMP !== $category_key ) {
					continue;
				}

				$clean['categories'][] = array(
					'category'       => $category_key,
					'eligible_count' => isset( $category['eligible_count'] ) ? max( 0, absint( $category['eligible_count'] ) ) : 0,
					'approx_bytes'   => isset( $category['approx_bytes'] ) ? max( 0, absint( $category['approx_bytes'] ) ) : 0,
					'age_band'       => isset( $category['age_band'] ) ? sanitize_key( (string) $category['age_band'] ) : 'none',
					'reason_code'    => isset( $category['reason_code'] ) ? sanitize_key( (string) $category['reason_code'] ) : '',
				);
			}
		}

		return $clean;
	}
}
