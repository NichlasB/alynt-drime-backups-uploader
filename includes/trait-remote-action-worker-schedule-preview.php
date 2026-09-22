<?php
/**
 * Remote action worker schedule preview helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.21
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds non-mutating Alynt scan/upload schedule previews.
 *
 * @since 0.5.21
 */
trait Alynt_Drime_Backups_Uploader_Remote_Action_Worker_Schedule_Preview {
	/**
	 * Returns a bounded non-mutating preview for the Alynt scan/upload schedule.
	 *
	 * @param array<string,mixed> $record Action record.
	 * @return array<string,mixed>|WP_Error
	 */
	private function schedule_preview_from_record( array $record ) {
		$request = isset( $record['schedule_preview'] ) && is_array( $record['schedule_preview'] ) ? $record['schedule_preview'] : array();
		if ( 'alynt_scan_upload' !== ( isset( $request['schedule_id'] ) ? sanitize_key( (string) $request['schedule_id'] ) : '' ) ) {
			return new WP_Error( 'schedule_preview_schedule_invalid', __( 'The requested schedule is not supported.', 'alynt-drime-backups-uploader' ) );
		}

		$proposed_cadence = isset( $request['proposed_cadence'] ) ? sanitize_key( (string) $request['proposed_cadence'] ) : '';
		$proposed_seconds = $this->cadence_seconds( $proposed_cadence );
		if ( $proposed_seconds <= 0 ) {
			return new WP_Error( 'schedule_preview_cadence_invalid', __( 'The requested cadence is not supported.', 'alynt-drime-backups-uploader' ) );
		}

		$current          = $this->current_scan_schedule_state();
		$current_cadence  = $current['cadence'];
		$current_next_run = $current['next_run'];
		$settings         = $this->plugin->settings()->get();
		$warnings         = array();

		if ( empty( $settings['auto_scan_enabled'] ) ) {
			$warnings[] = 'auto_scan_disabled';
		}
		if ( 'unknown' === $current_cadence ) {
			$warnings[] = 'current_cadence_unknown';
		}
		if ( ! is_numeric( $current_next_run ) || $current_next_run <= 0 ) {
			$warnings[] = 'current_next_run_unavailable';
		}

		$created_at                     = time();
		$preview                        = array(
			'preview_action_id'             => isset( $record['action_id'] ) ? (string) $record['action_id'] : '',
			'schedule_id'                   => 'alynt_scan_upload',
			'label'                         => __( 'Alynt scan/upload', 'alynt-drime-backups-uploader' ),
			'owner'                         => 'alynt_uploader',
			'capability_version'            => 1,
			'current_cadence'               => $current_cadence,
			'proposed_cadence'              => $proposed_cadence,
			'current_next_run_at'           => is_numeric( $current_next_run ) && $current_next_run > 0 ? gmdate( 'c', (int) $current_next_run ) : '',
			'proposed_next_run_estimate_at' => gmdate( 'c', time() + $proposed_seconds ),
			'current_schedule_fingerprint'  => $current['fingerprint'],
			'preview_created_at'            => gmdate( 'c', $created_at ),
			'preview_expires_at'            => gmdate( 'c', $created_at + 900 ),
			'would_change'                  => $current_cadence !== $proposed_cadence,
			'apply_supported'               => $this->schedule_apply_locally_available( $settings, $current_cadence ),
			'rollback_supported'            => false,
			'warnings'                      => $warnings,
		);
		$preview['preview_fingerprint'] = $this->preview_fingerprint( $preview );

		return $preview;
	}

	/**
	 * Returns whether schedule apply is locally available for the current state.
	 *
	 * @since 0.5.19
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param string              $current_cadence Current cadence label.
	 * @return bool
	 */
	private function schedule_apply_locally_available( array $settings, $current_cadence ) {
		return ! empty( $settings['auto_scan_enabled'] )
			&& 'unknown' !== sanitize_key( (string) $current_cadence )
			&& $this->plugin->dashboard_connection()->is_schedule_mutation_enabled();
	}

	/**
	 * Returns the current redacted scan schedule state.
	 *
	 * @since 0.5.19
	 *
	 * @return array{cadence:string,next_run:int,fingerprint:string}
	 */
	private function current_scan_schedule_state() {
		$current_recurrence = function_exists( 'wp_get_schedule' ) ? wp_get_schedule( Alynt_Drime_Backups_Uploader_Cron::SCAN_EVENT ) : '';
		$current_seconds    = is_string( $current_recurrence ) ? $this->recurrence_interval_seconds( $current_recurrence ) : 0;
		$current_cadence    = $this->cadence_from_seconds( $current_seconds );
		$current_next_run   = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( Alynt_Drime_Backups_Uploader_Cron::SCAN_EVENT ) : false;
		$current_next_run   = is_numeric( $current_next_run ) ? max( 0, absint( $current_next_run ) ) : 0;

		return array(
			'cadence'     => $current_cadence,
			'next_run'    => $current_next_run,
			'fingerprint' => $this->schedule_fingerprint( $current_cadence, $current_next_run ),
		);
	}
}
