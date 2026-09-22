<?php
/**
 * Health summary schedule capability helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.21
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds redacted schedule-management capability summaries.
 *
 * @since 0.5.21
 */
trait Alynt_Drime_Backups_Uploader_Health_Summary_Schedule_Capability {
	/**
	 * Builds a preview-only, redacted schedule-management capability summary.
	 *
	 * This is intentionally capability reporting only. It must not change
	 * schedules, expose raw cron internals, or enable schedule mutation action
	 * types.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param bool                $remote_actions_enabled Whether V2 actions are opted in.
	 * @return array<string,mixed>
	 */
	private function schedule_management_capability( array $settings, $remote_actions_enabled ) {
		$schedule_mutation_enabled         = $this->dashboard_connection instanceof Alynt_Drime_Backups_Uploader_Dashboard_Connection && $this->dashboard_connection->is_schedule_mutation_enabled();
		$schedule_rollback_preview_enabled = $this->dashboard_connection instanceof Alynt_Drime_Backups_Uploader_Dashboard_Connection && $this->dashboard_connection->is_schedule_rollback_preview_enabled();
		$schedule                          = $this->alynt_scan_upload_schedule_capability( $settings, (bool) $remote_actions_enabled, (bool) $schedule_mutation_enabled, (bool) $schedule_rollback_preview_enabled );
		$enabled                           = ! empty( $remote_actions_enabled ) && ! empty( $schedule['manageable'] );
		$apply_supported                   = ! empty( $enabled ) && ! empty( $schedule['apply_supported'] );

		return array(
			'protocol_version'           => Alynt_Drime_Backups_Uploader_Dashboard_Connection::ACTION_PROTOCOL_VERSION,
			'capability_version'         => 1,
			'enabled'                    => (bool) $enabled,
			'preview_only'               => ! $apply_supported,
			'apply_supported'            => (bool) $apply_supported,
			'rollback_preview_supported' => ! empty( $enabled ) && ! empty( $schedule['rollback_preview_supported'] ),
			'rollback_supported'         => false,
			'schedules'                  => array( $schedule ),
		);
	}

	/**
	 * Builds the preview-only Alynt scan/upload schedule capability.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param bool                $remote_actions_enabled Whether V2 actions are opted in.
	 * @param bool                $schedule_mutation_enabled Whether schedule mutation is locally opted in.
	 * @param bool                $schedule_rollback_preview_enabled Whether rollback preview is locally opted in.
	 * @return array<string,mixed>
	 */
	private function alynt_scan_upload_schedule_capability( array $settings, $remote_actions_enabled, $schedule_mutation_enabled, $schedule_rollback_preview_enabled ) {
		$recurrence                 = function_exists( 'wp_get_schedule' ) ? wp_get_schedule( Alynt_Drime_Backups_Uploader_Cron::SCAN_EVENT ) : '';
		$interval                   = is_string( $recurrence ) ? $this->schedule_recurrence_interval_seconds( $recurrence ) : 0;
		$cadence                    = $this->schedule_interval_cadence_label( $interval );
		$next_run                   = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( Alynt_Drime_Backups_Uploader_Cron::SCAN_EVENT ) : false;
		$manageable                 = ! empty( $remote_actions_enabled ) && ! empty( $settings['auto_scan_enabled'] ) && 'unknown' !== $cadence;
		$apply_supported            = ! empty( $manageable ) && ! empty( $schedule_mutation_enabled );
		$rollback_preview_supported = ! empty( $manageable ) && ! empty( $schedule_rollback_preview_enabled );

		return array(
			'schedule_id'                    => 'alynt_scan_upload',
			'label'                          => __( 'Alynt scan/upload', 'alynt-drime-backups-uploader' ),
			'owner'                          => 'alynt_uploader',
			'manageable'                     => (bool) $manageable,
			'apply_supported'                => (bool) $apply_supported,
			'rollback_preview_supported'     => (bool) $rollback_preview_supported,
			'current_cadence'                => $cadence,
			'current_next_run_at'            => is_numeric( $next_run ) && $next_run > 0 ? gmdate( 'c', (int) $next_run ) : '',
			'supported_cadences'             => array( 'every_15_minutes', 'every_30_minutes', 'hourly' ),
			'minimum_interval_seconds'       => 900,
			'can_disable'                    => false,
			'requires_high_friction_disable' => true,
			'rollback_supported'             => false,
		);
	}

	/**
	 * Maps a WP-Cron recurrence key to seconds without exposing raw cron state.
	 *
	 * @param string $recurrence Recurrence key.
	 * @return int
	 */
	private function schedule_recurrence_interval_seconds( $recurrence ) {
		$recurrence = sanitize_key( (string) $recurrence );

		if ( 'fifteen_minutes' === $recurrence ) {
			return 900;
		}

		if ( function_exists( 'wp_get_schedules' ) ) {
			$schedules = wp_get_schedules();

			if ( is_array( $schedules ) && isset( $schedules[ $recurrence ]['interval'] ) ) {
				return max( 0, absint( $schedules[ $recurrence ]['interval'] ) );
			}
		}

		return 0;
	}

	/**
	 * Maps an interval to an allowlisted dashboard cadence label.
	 *
	 * @param int $interval Interval in seconds.
	 * @return string
	 */
	private function schedule_interval_cadence_label( $interval ) {
		$interval = max( 0, (int) $interval );

		if ( 900 === $interval ) {
			return 'every_15_minutes';
		}

		if ( 1800 === $interval ) {
			return 'every_30_minutes';
		}

		if ( 3600 === $interval ) {
			return 'hourly';
		}

		if ( 86400 === $interval ) {
			return 'daily';
		}

		if ( 604800 === $interval ) {
			return 'weekly';
		}

		return 'unknown';
	}
}
