<?php
/**
 * Remote action worker schedule fingerprint helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.21
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds redacted schedule preview, apply, and rollback fingerprints.
 *
 * @since 0.5.21
 */
trait Alynt_Drime_Backups_Uploader_Remote_Action_Worker_Schedule_Fingerprints {
	/**
	 * Builds a redacted fingerprint for one scan schedule state.
	 *
	 * @since 0.5.20
	 *
	 * @param string $cadence  Public cadence label.
	 * @param int    $next_run Next run timestamp.
	 * @return string
	 */
	private function schedule_fingerprint( $cadence, $next_run ) {
		return hash( 'sha256', 'alynt_scan_upload|' . sanitize_key( (string) $cadence ) . '|' . (string) max( 0, absint( $next_run ) ) );
	}

	/**
	 * Builds a redacted preview fingerprint.
	 *
	 * @since 0.5.19
	 *
	 * @param array<string,mixed> $preview Preview details.
	 * @return string
	 */
	private function preview_fingerprint( array $preview ) {
		return hash(
			'sha256',
			implode(
				'|',
				array(
					isset( $preview['preview_action_id'] ) ? (string) $preview['preview_action_id'] : '',
					isset( $preview['schedule_id'] ) ? (string) $preview['schedule_id'] : '',
					isset( $preview['capability_version'] ) ? (string) absint( $preview['capability_version'] ) : '0',
					isset( $preview['current_cadence'] ) ? (string) $preview['current_cadence'] : '',
					isset( $preview['proposed_cadence'] ) ? (string) $preview['proposed_cadence'] : '',
					isset( $preview['current_schedule_fingerprint'] ) ? (string) $preview['current_schedule_fingerprint'] : '',
					isset( $preview['preview_created_at'] ) ? (string) $preview['preview_created_at'] : '',
					isset( $preview['preview_expires_at'] ) ? (string) $preview['preview_expires_at'] : '',
				)
			)
		);
	}

	/**
	 * Builds a redacted rollback metadata fingerprint.
	 *
	 * @since 0.5.21
	 *
	 * @param array<string,mixed> $metadata Rollback metadata.
	 * @return string
	 */
	private function rollback_metadata_fingerprint( array $metadata ) {
		return hash(
			'sha256',
			implode(
				'|',
				array(
					isset( $metadata['source_action_id'] ) ? (string) $metadata['source_action_id'] : '',
					isset( $metadata['source_preview_action_id'] ) ? (string) $metadata['source_preview_action_id'] : '',
					isset( $metadata['schedule_id'] ) ? (string) $metadata['schedule_id'] : '',
					isset( $metadata['owner'] ) ? (string) $metadata['owner'] : '',
					isset( $metadata['previous_cadence'] ) ? (string) $metadata['previous_cadence'] : '',
					isset( $metadata['applied_cadence'] ) ? (string) $metadata['applied_cadence'] : '',
					isset( $metadata['current_schedule_fingerprint_before'] ) ? (string) $metadata['current_schedule_fingerprint_before'] : '',
					isset( $metadata['current_schedule_fingerprint_after'] ) ? (string) $metadata['current_schedule_fingerprint_after'] : '',
					isset( $metadata['captured_at'] ) ? (string) $metadata['captured_at'] : '',
					isset( $metadata['expires_at'] ) ? (string) $metadata['expires_at'] : '',
				)
			)
		);
	}

	/**
	 * Builds a redacted rollback preview fingerprint.
	 *
	 * @since 0.5.21
	 *
	 * @param array<string,mixed> $preview Rollback preview details.
	 * @return string
	 */
	private function rollback_preview_fingerprint( array $preview ) {
		return hash(
			'sha256',
			implode(
				'|',
				array(
					isset( $preview['preview_action_id'] ) ? (string) $preview['preview_action_id'] : '',
					isset( $preview['source_apply_action_id'] ) ? (string) $preview['source_apply_action_id'] : '',
					isset( $preview['rollback_metadata_fingerprint'] ) ? (string) $preview['rollback_metadata_fingerprint'] : '',
					isset( $preview['schedule_id'] ) ? (string) $preview['schedule_id'] : '',
					isset( $preview['capability_version'] ) ? (string) absint( $preview['capability_version'] ) : '0',
					isset( $preview['current_cadence'] ) ? (string) $preview['current_cadence'] : '',
					isset( $preview['rollback_cadence'] ) ? (string) $preview['rollback_cadence'] : '',
					isset( $preview['current_schedule_fingerprint'] ) ? (string) $preview['current_schedule_fingerprint'] : '',
					isset( $preview['preview_created_at'] ) ? (string) $preview['preview_created_at'] : '',
					isset( $preview['preview_expires_at'] ) ? (string) $preview['preview_expires_at'] : '',
				)
			)
		);
	}
}
