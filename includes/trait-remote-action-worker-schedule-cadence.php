<?php
/**
 * Remote action worker schedule cadence helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.21
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps schedule cadence labels and recurrence intervals.
 *
 * @since 0.5.21
 */
trait Alynt_Drime_Backups_Uploader_Remote_Action_Worker_Schedule_Cadence {
	/**
	 * Maps a supported cadence label to seconds.
	 *
	 * @param string $cadence Cadence label.
	 * @return int
	 */
	private function cadence_seconds( $cadence ) {
		$map = array(
			'every_15_minutes' => 900,
			'every_30_minutes' => 1800,
			'hourly'           => 3600,
		);

		$cadence = sanitize_key( (string) $cadence );

		return isset( $map[ $cadence ] ) ? $map[ $cadence ] : 0;
	}

	/**
	 * Maps a recurrence name to an interval in seconds.
	 *
	 * @param string $recurrence Recurrence name.
	 * @return int
	 */
	private function recurrence_interval_seconds( $recurrence ) {
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
	 * Maps an interval in seconds to the public cadence label.
	 *
	 * @param int $seconds Interval seconds.
	 * @return string
	 */
	private function cadence_from_seconds( $seconds ) {
		$map = array(
			900  => 'every_15_minutes',
			1800 => 'every_30_minutes',
			3600 => 'hourly',
		);

		$seconds = absint( $seconds );

		return isset( $map[ $seconds ] ) ? $map[ $seconds ] : 'unknown';
	}
}
