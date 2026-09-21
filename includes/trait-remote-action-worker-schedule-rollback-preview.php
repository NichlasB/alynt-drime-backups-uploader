<?php
/**
 * Remote action worker schedule rollback preview helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates previous apply metadata and builds non-mutating rollback previews.
 *
 * @since 0.5.22
 */
trait Alynt_Drime_Backups_Uploader_Remote_Action_Worker_Schedule_Rollback_Preview {
	/**
	 * Validates previous apply metadata and builds a non-mutating rollback preview.
	 *
	 * @since 0.5.21
	 *
	 * @param array<string,mixed> $record Action record.
	 * @return array<string,mixed>|WP_Error
	 */
	private function schedule_rollback_preview_from_record( array $record ) {
		if ( ! $this->plugin->dashboard_connection()->is_schedule_rollback_preview_enabled() ) {
			return new WP_Error( 'schedule_rollback_preview_unavailable', __( 'Schedule rollback preview is not enabled on this client site.', 'alynt-drime-backups-uploader' ) );
		}

		$request = isset( $record['schedule_rollback_preview'] ) && is_array( $record['schedule_rollback_preview'] ) ? $record['schedule_rollback_preview'] : array();
		if ( 'alynt_scan_upload' !== ( isset( $request['schedule_id'] ) ? sanitize_key( (string) $request['schedule_id'] ) : '' ) ) {
			return new WP_Error( 'schedule_rollback_preview_schedule_invalid', __( 'The requested schedule is not supported.', 'alynt-drime-backups-uploader' ) );
		}

		$source_apply_action_id        = isset( $request['source_apply_action_id'] ) ? (string) $request['source_apply_action_id'] : '';
		$rollback_metadata_fingerprint = isset( $request['rollback_metadata_fingerprint'] ) ? (string) $request['rollback_metadata_fingerprint'] : '';
		$source_record                 = $this->store->record( $source_apply_action_id );
		$apply                         = isset( $source_record['schedule_apply'] ) && is_array( $source_record['schedule_apply'] ) ? $source_record['schedule_apply'] : array();
		$metadata                      = isset( $apply['rollback_metadata'] ) && is_array( $apply['rollback_metadata'] ) ? $apply['rollback_metadata'] : array();

		if ( empty( $source_record ) || 'schedule_apply' !== ( isset( $source_record['action_type'] ) ? sanitize_key( (string) $source_record['action_type'] ) : '' ) || 'succeeded' !== ( isset( $source_record['state'] ) ? sanitize_key( (string) $source_record['state'] ) : '' ) ) {
			return new WP_Error( 'schedule_rollback_preview_source_missing', __( 'The source schedule apply action is unavailable.', 'alynt-drime-backups-uploader' ) );
		}

		if ( empty( $metadata['captured'] ) || empty( $metadata['source_action_id'] ) || ! hash_equals( (string) $metadata['source_action_id'], $source_apply_action_id ) ) {
			return new WP_Error( 'schedule_rollback_preview_metadata_missing', __( 'The source schedule apply action does not include rollback metadata.', 'alynt-drime-backups-uploader' ) );
		}

		$computed_metadata_fingerprint = $this->rollback_metadata_fingerprint( $metadata );
		$stored_metadata_fingerprint   = isset( $metadata['rollback_metadata_fingerprint'] ) ? (string) $metadata['rollback_metadata_fingerprint'] : $computed_metadata_fingerprint;
		if (
			'' === $rollback_metadata_fingerprint
			|| ! hash_equals( $computed_metadata_fingerprint, $rollback_metadata_fingerprint )
			|| ! hash_equals( $stored_metadata_fingerprint, $rollback_metadata_fingerprint )
		) {
			return new WP_Error( 'schedule_rollback_preview_metadata_mismatch', __( 'The rollback metadata fingerprint does not match the source apply action.', 'alynt-drime-backups-uploader' ) );
		}

		$metadata_expires_at = isset( $metadata['expires_at'] ) ? strtotime( (string) $metadata['expires_at'] ) : false;
		if ( false === $metadata_expires_at || $metadata_expires_at <= time() ) {
			return new WP_Error( 'schedule_rollback_preview_metadata_expired', __( 'The rollback metadata has expired.', 'alynt-drime-backups-uploader' ) );
		}

		if ( 'alynt_scan_upload' !== ( isset( $metadata['schedule_id'] ) ? sanitize_key( (string) $metadata['schedule_id'] ) : '' ) ) {
			return new WP_Error( 'schedule_rollback_preview_schedule_invalid', __( 'The requested schedule is not supported.', 'alynt-drime-backups-uploader' ) );
		}

		$previous_cadence = isset( $metadata['previous_cadence'] ) ? sanitize_key( (string) $metadata['previous_cadence'] ) : '';
		$applied_cadence  = isset( $metadata['applied_cadence'] ) ? sanitize_key( (string) $metadata['applied_cadence'] ) : '';
		if ( $this->cadence_seconds( $previous_cadence ) <= 0 || $this->cadence_seconds( $applied_cadence ) <= 0 ) {
			return new WP_Error( 'schedule_rollback_preview_cadence_invalid', __( 'The rollback cadence evidence is not supported.', 'alynt-drime-backups-uploader' ) );
		}

		$current                      = $this->current_scan_schedule_state();
		$expected_current_fingerprint = isset( $metadata['current_schedule_fingerprint_after'] ) ? (string) $metadata['current_schedule_fingerprint_after'] : '';
		if ( '' === $expected_current_fingerprint || ! hash_equals( $expected_current_fingerprint, (string) $current['fingerprint'] ) ) {
			return new WP_Error( 'schedule_rollback_preview_stale', __( 'The local schedule changed after the source apply action.', 'alynt-drime-backups-uploader' ) );
		}

		$settings = $this->plugin->settings()->get();
		$warnings = array();
		if ( empty( $settings['auto_scan_enabled'] ) ) {
			$warnings[] = 'auto_scan_disabled';
		}
		if ( $current['cadence'] !== $applied_cadence ) {
			$warnings[] = 'current_cadence_differs_from_applied';
		}

		$created_at                     = time();
		$preview                        = array(
			'preview_action_id'                     => isset( $record['action_id'] ) ? (string) $record['action_id'] : '',
			'source_apply_action_id'                => $source_apply_action_id,
			'rollback_metadata_fingerprint'         => $rollback_metadata_fingerprint,
			'schedule_id'                           => 'alynt_scan_upload',
			'label'                                 => __( 'Alynt scan/upload', 'alynt-drime-backups-uploader' ),
			'owner'                                 => 'alynt_uploader',
			'capability_version'                    => 1,
			'current_cadence'                       => $current['cadence'],
			'applied_cadence'                       => $applied_cadence,
			'rollback_cadence'                      => $previous_cadence,
			'current_next_run_at'                   => $current['next_run'] > 0 ? gmdate( 'c', $current['next_run'] ) : '',
			'rollback_next_run_estimate_at'         => gmdate( 'c', $created_at + $this->cadence_seconds( $previous_cadence ) ),
			'current_schedule_fingerprint'          => $current['fingerprint'],
			'expected_current_schedule_fingerprint' => $expected_current_fingerprint,
			'previous_schedule_fingerprint'         => isset( $metadata['current_schedule_fingerprint_before'] ) ? (string) $metadata['current_schedule_fingerprint_before'] : '',
			'preview_created_at'                    => gmdate( 'c', $created_at ),
			'preview_expires_at'                    => gmdate( 'c', $created_at + 900 ),
			'would_change'                          => $current['cadence'] !== $previous_cadence,
			'rollback_apply_supported'              => false,
			'rollback_supported'                    => false,
			'warnings'                              => $warnings,
		);
		$preview['preview_fingerprint'] = $this->rollback_preview_fingerprint( $preview );

		return $preview;
	}
}
