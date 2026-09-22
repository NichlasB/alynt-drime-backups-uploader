<?php
/**
 * Health summary WPvivid schedule policy helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.21
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Derives redacted WPvivid schedule-aware freshness policy details.
 *
 * @since 0.5.21
 */
trait Alynt_Drime_Backups_Uploader_Health_Summary_WPvivid_Schedule_Policy {
	/**
	 * Builds a redacted WPvivid schedule-derived freshness policy summary.
	 *
	 * @return array<string,mixed>
	 */
	private function wpvivid_schedule_policy() {
		$candidates       = array();
		$option_has_state = false;

		foreach ( array( 'wpvivid_schedule_setting', 'wpvivid_schedule_addon_setting', 'wpvivid_incremental_schedules' ) as $option_name ) {
			$option = function_exists( 'get_option' ) ? get_option( $option_name, array() ) : array();

			if ( ! is_array( $option ) ) {
				continue;
			}

			$option_has_state = $option_has_state || ( ! empty( $option ) && $this->wpvivid_schedule_option_has_state( $option ) );
			$this->collect_wpvivid_schedule_candidates( $option, $option_name, $candidates );
		}

		if ( empty( $candidates ) && ! $option_has_state && function_exists( 'wp_get_schedule' ) ) {
			$event      = defined( 'WPVIVID_MAIN_SCHEDULE_EVENT' ) ? WPVIVID_MAIN_SCHEDULE_EVENT : 'wpvivid_main_schedule_event';
			$recurrence = wp_get_schedule( $event );
			$interval   = is_string( $recurrence ) ? $this->wpvivid_recurrence_interval_seconds( $recurrence ) : 0;

			if ( $interval > 0 ) {
				$candidates[] = array(
					'basis'              => 'wp_cron_event',
					'recurrence'         => sanitize_key( (string) $recurrence ),
					'interval_seconds'   => $interval,
					'local_backup_saved' => true,
				);
			}
		}

		if ( empty( $candidates ) ) {
			return array(
				'detected'              => false,
				'basis'                 => 'not_detected',
				'recurrence'            => '',
				'schedule_count'        => 0,
				'interval_seconds'      => 0,
				'grace_seconds'         => 0,
				'policy_window_seconds' => 0,
			);
		}

		$selected = array(
			'basis'            => 'not_detected',
			'recurrence'       => '',
			'interval_seconds' => 0,
		);

		foreach ( $candidates as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}

			$interval = isset( $candidate['interval_seconds'] ) ? max( 0, (int) $candidate['interval_seconds'] ) : 0;

			if ( $interval >= $selected['interval_seconds'] ) {
				$selected = array(
					'basis'            => isset( $candidate['basis'] ) ? sanitize_key( (string) $candidate['basis'] ) : 'not_detected',
					'recurrence'       => isset( $candidate['recurrence'] ) ? sanitize_key( (string) $candidate['recurrence'] ) : '',
					'interval_seconds' => $interval,
				);
			}
		}

		$grace  = $this->wpvivid_schedule_grace_seconds( $selected['interval_seconds'] );
		$window = min( self::WPVIVID_SCHEDULE_POLICY_MAX, $selected['interval_seconds'] + $grace );

		return array(
			'detected'              => $selected['interval_seconds'] > 0,
			'basis'                 => $selected['basis'],
			'recurrence'            => $selected['recurrence'],
			'schedule_count'        => count( $candidates ),
			'interval_seconds'      => $selected['interval_seconds'],
			'grace_seconds'         => $grace,
			'policy_window_seconds' => $window,
		);
	}

	/**
	 * Recursively collects redacted local WPvivid schedule intervals from known option shapes.
	 *
	 * @param array<string,mixed>            $value Schedule option fragment.
	 * @param string                         $basis Redacted basis label.
	 * @param array<int,array<string,mixed>> $candidates Candidate schedule summaries.
	 * @param int                            $depth Recursion depth.
	 * @return void
	 */
	private function collect_wpvivid_schedule_candidates( array $value, $basis, array &$candidates, $depth = 0 ) {
		if ( $depth > 4 ) {
			return;
		}

		$enabled = ! array_key_exists( 'enable', $value ) || ! empty( $value['enable'] );

		if ( isset( $value['status'] ) && 'active' !== sanitize_key( (string) $value['status'] ) ) {
			$enabled = false;
		}

		$local = $this->wpvivid_schedule_allows_local_backup( $value );

		foreach ( array( 'type', 'recurrence', 'incremental_recurrence', 'files_recurrence', 'db_recurrence' ) as $key ) {
			if ( empty( $value[ $key ] ) || ! is_string( $value[ $key ] ) ) {
				continue;
			}

			$recurrence = sanitize_key( (string) $value[ $key ] );
			$interval   = $this->wpvivid_recurrence_interval_seconds( $recurrence );

			if ( $enabled && $local && $interval > 0 ) {
				$candidates[] = array(
					'basis'            => sanitize_key( (string) $basis ),
					'recurrence'       => $recurrence,
					'interval_seconds' => $interval,
				);
			}
		}

		foreach ( $value as $child ) {
			if ( is_array( $child ) ) {
				$this->collect_wpvivid_schedule_candidates( $child, $basis, $candidates, $depth + 1 );
			}
		}
	}

	/**
	 * Determines whether a WPvivid option fragment contains schedule-shaped state.
	 *
	 * @param array<string,mixed> $value Schedule option fragment.
	 * @param int                 $depth Recursion depth.
	 * @return bool
	 */
	private function wpvivid_schedule_option_has_state( array $value, $depth = 0 ) {
		if ( $depth > 4 ) {
			return false;
		}

		foreach ( array( 'enable', 'status', 'type', 'recurrence', 'incremental_recurrence', 'files_recurrence', 'db_recurrence', 'backup', 'save_local_remote' ) as $key ) {
			if ( array_key_exists( $key, $value ) ) {
				return true;
			}
		}

		foreach ( $value as $child ) {
			if ( is_array( $child ) && $this->wpvivid_schedule_option_has_state( $child, $depth + 1 ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determines whether a WPvivid schedule option indicates local backup files are saved.
	 *
	 * @param array<string,mixed> $value Schedule option fragment.
	 * @return bool
	 */
	private function wpvivid_schedule_allows_local_backup( array $value ) {
		$backup = isset( $value['backup'] ) && is_array( $value['backup'] ) ? $value['backup'] : array();

		if ( array_key_exists( 'local', $backup ) ) {
			return ! empty( $backup['local'] );
		}

		if ( isset( $value['save_local_remote'] ) && 'remote' === sanitize_key( (string) $value['save_local_remote'] ) ) {
			return false;
		}

		if ( ! empty( $backup['remote'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Maps a WPvivid/WP-Cron recurrence key to an interval in seconds.
	 *
	 * @param string $recurrence Recurrence key.
	 * @return int
	 */
	private function wpvivid_recurrence_interval_seconds( $recurrence ) {
		$recurrence = sanitize_key( (string) $recurrence );
		$known      = array(
			'wpvivid_12hours'     => 43200,
			'twicedaily'          => 43200,
			'wpvivid_daily'       => 86400,
			'daily'               => 86400,
			'onceday'             => 86400,
			'wpvivid_weekly'      => 604800,
			'weekly'              => 604800,
			'wpvivid_fortnightly' => 1209600,
			'fortnightly'         => 1209600,
			'wpvivid_monthly'     => 2592000,
			'monthly'             => 2592000,
			'montly'              => 2592000,
		);

		if ( isset( $known[ $recurrence ] ) ) {
			return $known[ $recurrence ];
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
	 * Computes a bounded grace window for schedule-aware WPvivid freshness.
	 *
	 * @param int $interval Schedule interval in seconds.
	 * @return int
	 */
	private function wpvivid_schedule_grace_seconds( $interval ) {
		$interval = max( 0, (int) $interval );
		$day      = 86400;
		$grace    = (int) ceil( ( $interval * 0.2 ) / $day ) * $day;

		return max( self::WPVIVID_SCHEDULE_GRACE_MIN, min( self::WPVIVID_SCHEDULE_GRACE_MAX, $grace ) );
	}
}
