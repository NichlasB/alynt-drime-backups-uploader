<?php
/**
 * Admin page cron health rendering.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page cron health rendering.
 *
 * @since 0.3.0
 */
trait Alynt_Drime_Backups_Uploader_Admin_Page_Cron_Health {
	/**
	 * Renders scan timing state.
	 *
	 * @param array<string,mixed>                      $settings Settings.
	 * @param array<int,array<string,mixed>>           $events Events.
	 * @param Alynt_Drime_Backups_Uploader_Cron_Health $cron_health Cron health.
	 * @return void
	 */
	private function render_scan_state( array $settings, array $events, Alynt_Drime_Backups_Uploader_Cron_Health $cron_health ) {
		$next_scan      = wp_next_scheduled( Alynt_Drime_Backups_Uploader_Cron::SCAN_EVENT );
		$last_scan_time = $this->last_scan_finished_time( $events );
		$health_state   = $cron_health->get();
		$health_status  = $cron_health->status( $settings, $next_scan );

		?>
		<h3><?php esc_html_e( 'Scan State', 'alynt-drime-backups-uploader' ); ?></h3>
		<table class="widefat striped alynt-drime-wpvivid-scan-state">
			<caption class="screen-reader-text"><?php esc_html_e( 'Automated scan timing state', 'alynt-drime-backups-uploader' ); ?></caption>
			<tbody>
				<tr><th scope="row"><?php esc_html_e( 'Current UTC Time', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( $this->format_utc_time( time() ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Automatic Scanning', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo ! empty( $settings['auto_scan_enabled'] ) ? esc_html__( 'Enabled', 'alynt-drime-backups-uploader' ) : esc_html__( 'Disabled', 'alynt-drime-backups-uploader' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Next Automated Scan', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( $this->format_scan_schedule( $next_scan, ! empty( $settings['auto_scan_enabled'] ) ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Last Scan Finished', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo $last_scan_time ? esc_html( $this->format_utc_time( $last_scan_time ) ) : esc_html__( 'None recorded', 'alynt-drime-backups-uploader' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Last Scheduled Scan', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( $this->format_optional_utc_time( $health_state['last_scheduled_scan_at'] ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Last Scan Runner', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( $this->cron_runner_label( $health_state['last_runner'] ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Last WP-CLI Scan', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( $this->format_optional_utc_time( $health_state['last_wp_cli_scan_at'] ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Server Cron Expected', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo ! empty( $settings['server_cron_expected'] ) ? esc_html__( 'Yes', 'alynt-drime-backups-uploader' ) : esc_html__( 'No', 'alynt-drime-backups-uploader' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'WP-Cron Disabled', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo $cron_health->is_wp_cron_disabled() ? esc_html__( 'Yes', 'alynt-drime-backups-uploader' ) : esc_html__( 'No', 'alynt-drime-backups-uploader' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Server Cron Health', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( $this->cron_health_status_label( $health_status['status'] ) ); ?> - <?php echo esc_html( $health_status['reason'] ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Minimum File Age', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( number_format_i18n( absint( $settings['min_file_age_seconds'] ) ) ); ?> <?php esc_html_e( 'seconds', 'alynt-drime-backups-uploader' ); ?></td></tr>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'WP-Cron runs due scans when WordPress receives a cron trigger; a due or overdue time can wait until the next cron run.', 'alynt-drime-backups-uploader' ); ?></p>
		<?php
	}

	/**
	 * Renders a persistent cron-health notice when attention is needed.
	 *
	 * @param array<string,mixed>                      $settings Settings.
	 * @param Alynt_Drime_Backups_Uploader_Cron_Health $cron_health Cron health.
	 * @return void
	 */
	private function render_cron_health_notice( array $settings, Alynt_Drime_Backups_Uploader_Cron_Health $cron_health ) {
		$status = $cron_health->status( $settings, wp_next_scheduled( Alynt_Drime_Backups_Uploader_Cron::SCAN_EVENT ) );

		if ( Alynt_Drime_Backups_Uploader_Cron_Health::STATUS_ATTENTION_NEEDED !== $status['status'] ) {
			return;
		}

		printf(
			'<div class="notice notice-warning" role="alert"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: cron health reason. */
					__( 'Drime Backups server cron attention needed: %s', 'alynt-drime-backups-uploader' ),
					$status['reason']
				)
			)
		);
	}

	/**
	 * Formats an optional timestamp as UTC.
	 *
	 * @param mixed $timestamp Timestamp.
	 * @return string
	 */
	private function format_optional_utc_time( $timestamp ) {
		$timestamp = absint( $timestamp );

		return $timestamp ? $this->format_utc_time( $timestamp ) : __( 'None recorded', 'alynt-drime-backups-uploader' );
	}

	/**
	 * Returns a display label for a cron runner.
	 *
	 * @param mixed $runner Runner key.
	 * @return string
	 */
	private function cron_runner_label( $runner ) {
		$labels = array(
			Alynt_Drime_Backups_Uploader_Cron_Health::RUNNER_WP_CLI       => __( 'WP-CLI', 'alynt-drime-backups-uploader' ),
			Alynt_Drime_Backups_Uploader_Cron_Health::RUNNER_HTTP_CRON    => __( 'HTTP WP-Cron', 'alynt-drime-backups-uploader' ),
			Alynt_Drime_Backups_Uploader_Cron_Health::RUNNER_MANUAL_ADMIN => __( 'Manual Admin Action', 'alynt-drime-backups-uploader' ),
			Alynt_Drime_Backups_Uploader_Cron_Health::RUNNER_UNKNOWN      => __( 'Unknown', 'alynt-drime-backups-uploader' ),
		);

		return isset( $labels[ $runner ] ) ? $labels[ $runner ] : $labels[ Alynt_Drime_Backups_Uploader_Cron_Health::RUNNER_UNKNOWN ];
	}

	/**
	 * Returns a display label for cron health status.
	 *
	 * @param mixed $status Status key.
	 * @return string
	 */
	private function cron_health_status_label( $status ) {
		$labels = array(
			Alynt_Drime_Backups_Uploader_Cron_Health::STATUS_LIKELY_CONFIGURED => __( 'Likely configured', 'alynt-drime-backups-uploader' ),
			Alynt_Drime_Backups_Uploader_Cron_Health::STATUS_NOT_CONFIRMED     => __( 'Not confirmed', 'alynt-drime-backups-uploader' ),
			Alynt_Drime_Backups_Uploader_Cron_Health::STATUS_ATTENTION_NEEDED  => __( 'Attention needed', 'alynt-drime-backups-uploader' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $labels[ Alynt_Drime_Backups_Uploader_Cron_Health::STATUS_NOT_CONFIRMED ];
	}

	/**
	 * Formats the next scan schedule.
	 *
	 * @param int|false $next_scan Next scan timestamp.
	 * @param bool      $enabled Whether automatic scanning is enabled.
	 * @return string
	 */
	private function format_scan_schedule( $next_scan, $enabled ) {
		if ( ! $enabled ) {
			return __( 'Automatic scanning is disabled.', 'alynt-drime-backups-uploader' );
		}

		if ( ! $next_scan ) {
			return __( 'Not scheduled.', 'alynt-drime-backups-uploader' );
		}

		$now       = time();
		$formatted = $this->format_utc_time( (int) $next_scan );

		if ( (int) $next_scan <= $now ) {
			return sprintf(
				/* translators: %s: UTC scan time. */
				__( '%s (due now or waiting for WP-Cron)', 'alynt-drime-backups-uploader' ),
				$formatted
			);
		}

		return sprintf(
			/* translators: 1: UTC scan time, 2: relative time until scan. */
			__( '%1$s (in %2$s)', 'alynt-drime-backups-uploader' ),
			$formatted,
			human_time_diff( $now, (int) $next_scan )
		);
	}

	/**
	 * Finds the newest scan-finished event timestamp.
	 *
	 * @param array<int,array<string,mixed>> $events Events.
	 * @return int
	 */
	private function last_scan_finished_time( array $events ) {
		foreach ( $events as $event ) {
			if ( 'scan_finished' === ( isset( $event['code'] ) ? (string) $event['code'] : '' ) ) {
				return isset( $event['time'] ) ? absint( $event['time'] ) : 0;
			}
		}

		return 0;
	}
}
