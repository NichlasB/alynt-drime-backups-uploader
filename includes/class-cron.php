<?php
/**
 * Cron integration.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles WP-Cron events.
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_Cron {
	const SCAN_EVENT   = 'alynt_drime_backups_scan_event';
	const UPLOAD_EVENT = 'alynt_drime_backups_upload_event';

	/**
	 * Plugin.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Plugin $plugin Plugin.
	 *
	 * @since 0.1.0
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Adds cron hooks.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function hooks() {
		add_filter( 'cron_schedules', array( $this, 'add_schedules' ) );
		add_action( self::SCAN_EVENT, array( $this, 'scan' ) );
		add_action( self::UPLOAD_EVENT, array( $this, 'upload' ) );
		add_action( 'init', array( $this, 'maybe_schedule' ) );
	}

	/**
	 * Adds schedules.
	 *
	 * @param array<string,array<string,mixed>> $schedules Schedules.
	 * @return array<string,array<string,mixed>>
	 *
	 * @since 0.1.0
	 */
	public function add_schedules( $schedules ) {
		$schedules['fifteen_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes', 'alynt-drime-backups-uploader' ),
		);

		$schedules['thirty_minutes'] = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 30 minutes', 'alynt-drime-backups-uploader' ),
		);

		return $schedules;
	}

	/**
	 * Schedules or clears events.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function maybe_schedule() {
		$settings = $this->plugin->settings()->get();

		if ( ! empty( $settings['auto_scan_enabled'] ) ) {
			if ( ! wp_next_scheduled( self::SCAN_EVENT ) ) {
				$this->schedule_event( time() + MINUTE_IN_SECONDS, self::SCAN_EVENT );
			}

			if ( ! wp_next_scheduled( self::UPLOAD_EVENT ) ) {
				$this->schedule_event( time() + ( 2 * MINUTE_IN_SECONDS ), self::UPLOAD_EVENT );
			}
			return;
		}

		$this->clear();
	}

	/**
	 * Clears scheduled events.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function clear() {
		$this->clear_scheduled_hook( self::SCAN_EVENT );
		$this->clear_scheduled_hook( self::UPLOAD_EVENT );
	}

	/**
	 * Applies an allowlisted scan cadence through WordPress scheduling APIs.
	 *
	 * @since 0.5.19
	 *
	 * @param string $cadence Public cadence label.
	 * @return array<string,mixed>|WP_Error
	 */
	public function apply_scan_cadence( $cadence ) {
		$recurrence = $this->recurrence_for_scan_cadence( $cadence );
		$interval   = $this->interval_for_scan_cadence( $cadence );

		if ( '' === $recurrence || $interval <= 0 ) {
			return new WP_Error( 'schedule_apply_unsupported_cadence', __( 'The requested Alynt scan/upload cadence is not supported.', 'alynt-drime-backups-uploader' ) );
		}

		if ( ! function_exists( 'wp_clear_scheduled_hook' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return new WP_Error( 'schedule_apply_unavailable', __( 'WordPress scheduling APIs are unavailable.', 'alynt-drime-backups-uploader' ) );
		}

		wp_clear_scheduled_hook( self::SCAN_EVENT );

		$scheduled = wp_schedule_event( time() + $interval, $recurrence, self::SCAN_EVENT, array(), true );
		if ( is_wp_error( $scheduled ) ) {
			return $scheduled;
		}

		if ( false === $scheduled ) {
			return new WP_Error( 'schedule_apply_persist_failed', __( 'WordPress could not persist the requested Alynt scan/upload cadence.', 'alynt-drime-backups-uploader' ) );
		}

		return array(
			'cadence'     => sanitize_key( (string) $cadence ),
			'recurrence'  => $recurrence,
			'interval'    => $interval,
			'next_run_at' => function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( self::SCAN_EVENT ) : 0,
		);
	}

	/**
	 * Maps a public cadence label to a WordPress recurrence key.
	 *
	 * @since 0.5.19
	 *
	 * @param string $cadence Cadence label.
	 * @return string
	 */
	private function recurrence_for_scan_cadence( $cadence ) {
		$map = array(
			'every_15_minutes' => 'fifteen_minutes',
			'every_30_minutes' => 'thirty_minutes',
			'hourly'           => 'hourly',
		);

		$cadence = sanitize_key( (string) $cadence );

		return isset( $map[ $cadence ] ) ? $map[ $cadence ] : '';
	}

	/**
	 * Maps a public cadence label to seconds.
	 *
	 * @since 0.5.19
	 *
	 * @param string $cadence Cadence label.
	 * @return int
	 */
	private function interval_for_scan_cadence( $cadence ) {
		$map = array(
			'every_15_minutes' => 15 * MINUTE_IN_SECONDS,
			'every_30_minutes' => 30 * MINUTE_IN_SECONDS,
			'hourly'           => HOUR_IN_SECONDS,
		);

		$cadence = sanitize_key( (string) $cadence );

		return isset( $map[ $cadence ] ) ? absint( $map[ $cadence ] ) : 0;
	}

	/**
	 * Clears a hook only when an event is scheduled.
	 *
	 * @param string $hook Hook.
	 * @return void
	 *
	 * @since 0.1.0
	 */
	private function clear_scheduled_hook( $hook ) {
		if ( wp_next_scheduled( $hook ) ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Schedules one plugin cron event and records scheduling failures.
	 *
	 * @param int    $timestamp Timestamp.
	 * @param string $hook Hook.
	 * @return void
	 */
	private function schedule_event( $timestamp, $hook ) {
		$result = wp_schedule_event( $timestamp, 'fifteen_minutes', $hook, array(), true );

		if ( is_wp_error( $result ) || false === $result ) {
			$this->plugin->logger()->event(
				'cron',
				'error',
				'cron_schedule_failed',
				'A scheduled backup event could not be registered.',
				array(
					'hook'   => $hook,
					'reason' => is_wp_error( $result ) ? $result->get_error_message() : 'wp_schedule_event returned false',
				)
			);
		}
	}

	/**
	 * Runs a scan.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function scan() {
		$runner = $this->plugin->cron_health()->record_scheduled_scan();
		if ( ! $this->plugin->cron_health()->last_record_persisted() ) {
			$this->plugin->logger()->event( 'cron', 'warning', 'cron_health_save_failed', 'Scheduled scan ran, but cron health evidence could not be saved.' );
		}

		$this->plugin->logger()->event( 'cron', 'info', 'scan_started', 'Scheduled scan started.', array( 'runner' => $runner ) );
		$this->plugin->scan_and_queue();
		$this->plugin->logger()->event( 'cron', 'info', 'scan_finished', 'Scheduled scan finished.', array( 'runner' => $runner ) );
	}

	/**
	 * Uploads next queued file.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function upload() {
		$this->plugin->logger()->event( 'cron', 'info', 'upload_worker_started', 'Scheduled upload worker started.' );
		$result = $this->plugin->uploader()->upload_next();
		if ( is_wp_error( $result ) ) {
			$this->plugin->logger()->event( 'cron', 'warning', 'upload_worker_no_upload', 'Scheduled upload worker did not complete an upload.', array( 'reason' => $result->get_error_message() ) );
			return;
		}
		$this->plugin->logger()->event( 'cron', 'info', 'upload_worker_finished', 'Scheduled upload worker finished.' );
	}
}
