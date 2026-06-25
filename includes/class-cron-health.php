<?php
/**
 * Cron health tracking.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks evidence about how scheduled scans are triggered.
 *
 * @since 0.3.0
 */
class Alynt_Drime_Backups_Uploader_Cron_Health {
	const OPTION_NAME               = 'alynt_drime_backups_cron_health';
	const RUNNER_WP_CLI             = 'wp_cli';
	const RUNNER_HTTP_CRON          = 'http_wp_cron';
	const RUNNER_MANUAL_ADMIN       = 'manual_admin';
	const RUNNER_UNKNOWN            = 'unknown';
	const STATUS_LIKELY_CONFIGURED  = 'likely_configured';
	const STATUS_NOT_CONFIRMED      = 'not_confirmed';
	const STATUS_ATTENTION_NEEDED   = 'attention_needed';
	const OVERDUE_WARNING_THRESHOLD = 1800;

	/**
	 * Returns default health state.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'last_runner'            => self::RUNNER_UNKNOWN,
			'last_runner_at'         => 0,
			'last_scheduled_scan_at' => 0,
			'last_manual_scan_at'    => 0,
			'last_wp_cli_scan_at'    => 0,
			'last_http_cron_scan_at' => 0,
		);
	}

	/**
	 * Returns stored health state.
	 *
	 * @return array<string,mixed>
	 */
	public function get() {
		$state = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $state ) ) {
			$state = array();
		}

		return array_merge( self::defaults(), $state );
	}

	/**
	 * Records a scheduled scan trigger.
	 *
	 * @return string Runner key.
	 */
	public function record_scheduled_scan() {
		$runner = self::current_runner();
		$state  = $this->get();
		$now    = time();

		$state['last_runner']            = $runner;
		$state['last_runner_at']         = $now;
		$state['last_scheduled_scan_at'] = $now;

		if ( self::RUNNER_WP_CLI === $runner ) {
			$state['last_wp_cli_scan_at'] = $now;
		} elseif ( self::RUNNER_HTTP_CRON === $runner ) {
			$state['last_http_cron_scan_at'] = $now;
		}

		update_option( self::OPTION_NAME, $state, false );
		$this->sync_option_cache( $state );

		return $runner;
	}

	/**
	 * Records a manual admin scan.
	 *
	 * @return void
	 */
	public function record_manual_scan() {
		$state = $this->get();
		$now   = time();

		$state['last_runner']         = self::RUNNER_MANUAL_ADMIN;
		$state['last_runner_at']      = $now;
		$state['last_manual_scan_at'] = $now;

		update_option( self::OPTION_NAME, $state, false );
		$this->sync_option_cache( $state );
	}

	/**
	 * Syncs the cron health option cache after mutation.
	 *
	 * @param array<string,mixed> $state State.
	 * @return void
	 */
	private function sync_option_cache( array $state ) {
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::OPTION_NAME, 'options' );
		}
		if ( function_exists( 'wp_cache_set' ) ) {
			wp_cache_set( self::OPTION_NAME, $state, 'options' );
		}
	}

	/**
	 * Returns the current WordPress cron runner context.
	 *
	 * @return string
	 */
	public static function current_runner() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return self::RUNNER_WP_CLI;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return self::RUNNER_HTTP_CRON;
		}

		return self::RUNNER_UNKNOWN;
	}

	/**
	 * Returns a calculated status for display.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param int|false           $next_scan Next scheduled scan timestamp.
	 * @return array<string,string>
	 */
	public function status( array $settings, $next_scan ) {
		$state     = $this->get();
		$automatic = ! empty( $settings['auto_scan_enabled'] );
		$expected  = ! empty( $settings['server_cron_expected'] );
		$wp_cli_at = isset( $state['last_wp_cli_scan_at'] ) ? absint( $state['last_wp_cli_scan_at'] ) : 0;

		if ( ! $automatic ) {
			return array(
				'status' => self::STATUS_NOT_CONFIRMED,
				'reason' => __( 'Automatic scanning is disabled.', 'alynt-drime-backups-uploader' ),
			);
		}

		if ( $expected && ! $wp_cli_at ) {
			return array(
				'status' => self::STATUS_ATTENTION_NEEDED,
				'reason' => __( 'Server cron is expected, but no WP-CLI scheduled scan has been observed yet.', 'alynt-drime-backups-uploader' ),
			);
		}

		if ( $this->is_overdue( $next_scan ) ) {
			return array(
				'status' => self::STATUS_ATTENTION_NEEDED,
				'reason' => __( 'The scheduled scan is overdue; the site may need a reliable server cron trigger.', 'alynt-drime-backups-uploader' ),
			);
		}

		if ( $wp_cli_at ) {
			return array(
				'status' => self::STATUS_LIKELY_CONFIGURED,
				'reason' => __( 'A scheduled scan has run from WP-CLI.', 'alynt-drime-backups-uploader' ),
			);
		}

		return array(
			'status' => self::STATUS_NOT_CONFIRMED,
			'reason' => __( 'Only HTTP WP-Cron or no scheduled scan evidence has been observed.', 'alynt-drime-backups-uploader' ),
		);
	}

	/**
	 * Returns whether WP-Cron appears disabled in wp-config.php.
	 *
	 * @return bool
	 */
	public function is_wp_cron_disabled() {
		return defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	}

	/**
	 * Returns whether a scheduled scan is overdue by the warning threshold.
	 *
	 * @param int|false $next_scan Next scheduled scan timestamp.
	 * @return bool
	 */
	private function is_overdue( $next_scan ) {
		return $next_scan && ( (int) $next_scan + self::OVERDUE_WARNING_THRESHOLD ) < time();
	}
}
