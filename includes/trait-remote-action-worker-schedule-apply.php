<?php
/**
 * Remote action worker schedule apply helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates and applies previewed Alynt scan/upload schedule cadence changes.
 *
 * @since 0.5.22
 */
trait Alynt_Drime_Backups_Uploader_Remote_Action_Worker_Schedule_Apply {
	/**
	 * Validates and applies a schedule apply request.
	 *
	 * @since 0.5.19
	 *
	 * @param array<string,mixed> $record Action record.
	 * @return array<string,mixed>|WP_Error
	 */
	private function schedule_apply_from_record( array $record ) {
		$request = isset( $record['schedule_apply'] ) && is_array( $record['schedule_apply'] ) ? $record['schedule_apply'] : array();
		if ( 'alynt_scan_upload' !== ( isset( $request['schedule_id'] ) ? sanitize_key( (string) $request['schedule_id'] ) : '' ) ) {
			return new WP_Error( 'schedule_apply_unavailable', __( 'The requested schedule is not supported.', 'alynt-drime-backups-uploader' ) );
		}

		$proposed_cadence = isset( $request['proposed_cadence'] ) ? sanitize_key( (string) $request['proposed_cadence'] ) : '';
		if ( $this->cadence_seconds( $proposed_cadence ) <= 0 ) {
			return new WP_Error( 'schedule_apply_unsupported_cadence', __( 'The requested cadence is not supported.', 'alynt-drime-backups-uploader' ) );
		}

		if ( ! $this->plugin->dashboard_connection()->is_schedule_mutation_enabled() ) {
			return new WP_Error( 'schedule_apply_unavailable', __( 'Schedule apply is not enabled on this client site.', 'alynt-drime-backups-uploader' ) );
		}

		$settings = $this->plugin->settings()->get();
		$current  = $this->current_scan_schedule_state();
		if ( ! $this->schedule_apply_locally_available( $settings, $current['cadence'] ) ) {
			return new WP_Error( 'schedule_apply_unavailable', __( 'The Alynt scan/upload schedule is not locally manageable.', 'alynt-drime-backups-uploader' ) );
		}

		$preview_action_id   = isset( $request['preview_action_id'] ) ? (string) $request['preview_action_id'] : '';
		$preview_fingerprint = isset( $request['preview_fingerprint'] ) ? (string) $request['preview_fingerprint'] : '';
		$preview_record      = $this->store->record( $preview_action_id );
		$preview             = isset( $preview_record['schedule_preview'] ) && is_array( $preview_record['schedule_preview'] ) ? $preview_record['schedule_preview'] : array();

		if ( empty( $preview_record ) || 'schedule_preview' !== ( isset( $preview_record['action_type'] ) ? sanitize_key( (string) $preview_record['action_type'] ) : '' ) || 'succeeded' !== ( isset( $preview_record['state'] ) ? sanitize_key( (string) $preview_record['state'] ) : '' ) ) {
			return new WP_Error( 'schedule_apply_preview_missing', __( 'The matching schedule preview is unavailable.', 'alynt-drime-backups-uploader' ) );
		}

		if ( empty( $preview['preview_fingerprint'] ) || ! hash_equals( (string) $preview['preview_fingerprint'], $preview_fingerprint ) ) {
			return new WP_Error( 'schedule_apply_preview_missing', __( 'The matching schedule preview fingerprint is unavailable.', 'alynt-drime-backups-uploader' ) );
		}

		if (
			'alynt_scan_upload' !== ( isset( $preview['schedule_id'] ) ? sanitize_key( (string) $preview['schedule_id'] ) : '' )
			|| ( isset( $preview['proposed_cadence'] ) ? sanitize_key( (string) $preview['proposed_cadence'] ) : '' ) !== $proposed_cadence
			|| 1 !== ( isset( $preview['capability_version'] ) ? absint( $preview['capability_version'] ) : 0 )
		) {
			return new WP_Error( 'schedule_apply_preview_missing', __( 'The schedule preview does not match this apply request.', 'alynt-drime-backups-uploader' ) );
		}

		$preview_expires_at = isset( $preview['preview_expires_at'] ) ? strtotime( (string) $preview['preview_expires_at'] ) : false;
		if ( false === $preview_expires_at || $preview_expires_at <= time() ) {
			return new WP_Error( 'schedule_apply_preview_expired', __( 'The matching schedule preview has expired.', 'alynt-drime-backups-uploader' ) );
		}

		if ( empty( $preview['current_schedule_fingerprint'] ) || ! hash_equals( (string) $preview['current_schedule_fingerprint'], (string) $current['fingerprint'] ) ) {
			return new WP_Error( 'schedule_apply_preview_stale', __( 'The local schedule changed after the preview was created.', 'alynt-drime-backups-uploader' ) );
		}

		$changed      = $current['cadence'] !== $proposed_cadence;
		$apply_result = array(
			'schedule_id'          => 'alynt_scan_upload',
			'label'                => __( 'Alynt scan/upload', 'alynt-drime-backups-uploader' ),
			'owner'                => 'alynt_uploader',
			'capability_version'   => 1,
			'preview_action_id'    => $preview_action_id,
			'preview_fingerprint'  => $preview_fingerprint,
			'previous_cadence'     => $current['cadence'],
			'applied_cadence'      => $proposed_cadence,
			'previous_next_run_at' => $current['next_run'] > 0 ? gmdate( 'c', $current['next_run'] ) : '',
			'applied_next_run_at'  => $current['next_run'] > 0 ? gmdate( 'c', $current['next_run'] ) : '',
			'changed'              => $changed,
			'rollback_available'   => false,
			'rollback_expires_at'  => '',
		);

		if ( ! $changed ) {
			$apply_result['rollback_metadata'] = $this->schedule_apply_rollback_metadata( $record, $apply_result, $current['fingerprint'], $current['fingerprint'] );
			return $apply_result;
		}

		$scheduled = $this->plugin->cron()->apply_scan_cadence( $proposed_cadence );
		if ( is_wp_error( $scheduled ) ) {
			return new WP_Error( $scheduled->get_error_code(), __( 'The requested schedule cadence could not be persisted.', 'alynt-drime-backups-uploader' ) );
		}

		$applied_next_run                    = isset( $scheduled['next_run_at'] ) ? absint( $scheduled['next_run_at'] ) : 0;
		$apply_result['applied_next_run_at'] = $applied_next_run > 0 ? gmdate( 'c', $applied_next_run ) : '';
		$apply_result['rollback_metadata']   = $this->schedule_apply_rollback_metadata( $record, $apply_result, $current['fingerprint'], $this->schedule_fingerprint( $proposed_cadence, $applied_next_run ) );

		return $apply_result;
	}

	/**
	 * Builds support-safe metadata needed for a future rollback readiness flow.
	 *
	 * This is intentionally evidence-only in this release: it captures bounded,
	 * redacted before/after schedule state but does not make rollback executable.
	 *
	 * @since 0.5.20
	 *
	 * @param array<string,mixed> $record                    Action record.
	 * @param array<string,mixed> $apply_result              Schedule apply result.
	 * @param string              $schedule_fingerprint_before Fingerprint before apply.
	 * @param string              $schedule_fingerprint_after  Fingerprint after apply.
	 * @return array<string,mixed>
	 */
	private function schedule_apply_rollback_metadata( array $record, array $apply_result, $schedule_fingerprint_before, $schedule_fingerprint_after ) {
		$captured_at = time();

		$metadata = array(
			'captured'                            => true,
			'available'                           => false,
			'reason'                              => 'schedule_rollback_runtime_not_implemented',
			'source_action_id'                    => isset( $record['action_id'] ) ? (string) $record['action_id'] : '',
			'source_preview_action_id'            => isset( $apply_result['preview_action_id'] ) ? (string) $apply_result['preview_action_id'] : '',
			'schedule_id'                         => isset( $apply_result['schedule_id'] ) ? (string) $apply_result['schedule_id'] : '',
			'owner'                               => isset( $apply_result['owner'] ) ? (string) $apply_result['owner'] : '',
			'previous_cadence'                    => isset( $apply_result['previous_cadence'] ) ? (string) $apply_result['previous_cadence'] : '',
			'applied_cadence'                     => isset( $apply_result['applied_cadence'] ) ? (string) $apply_result['applied_cadence'] : '',
			'previous_next_run_at'                => isset( $apply_result['previous_next_run_at'] ) ? (string) $apply_result['previous_next_run_at'] : '',
			'applied_next_run_at'                 => isset( $apply_result['applied_next_run_at'] ) ? (string) $apply_result['applied_next_run_at'] : '',
			'current_schedule_fingerprint_before' => (string) $schedule_fingerprint_before,
			'current_schedule_fingerprint_after'  => (string) $schedule_fingerprint_after,
			'captured_at'                         => gmdate( 'c', $captured_at ),
			'expires_at'                          => gmdate( 'c', $captured_at + 3600 ),
		);

		$metadata['rollback_metadata_fingerprint'] = $this->rollback_metadata_fingerprint( $metadata );

		return $metadata;
	}
}
