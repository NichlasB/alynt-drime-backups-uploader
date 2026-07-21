<?php
/**
 * Admin page diagnostics status rendering.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page diagnostics status rendering.
 *
 * @since 0.4.0
 */
trait Alynt_Drime_Backups_Uploader_Admin_Page_Diagnostics_Status {
	/**
	 * Renders diagnostics panel.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param array<string,mixed> $diagnostics Diagnostics stats.
	 * @return void
	 */
	private function render_diagnostics_panel( array $settings, array $diagnostics ) {
		?>
		<h3><?php esc_html_e( 'Diagnostics', 'alynt-drime-backups-uploader' ); ?></h3>
		<div class="alynt-drime-backups-diagnostics-actions">
			<?php $this->render_action_button( 'alynt_drime_backups_export_diagnostics', __( 'Export Diagnostics', 'alynt-drime-backups-uploader' ) ); ?>
			<?php $this->render_action_button( 'alynt_drime_backups_clear_diagnostics', __( 'Clear Diagnostics', 'alynt-drime-backups-uploader' ), true ); ?>
		</div>
		<table class="widefat striped alynt-drime-backups-health">
			<caption class="screen-reader-text"><?php esc_html_e( 'Diagnostics health summary', 'alynt-drime-backups-uploader' ); ?></caption>
			<tbody>
				<tr><th scope="row"><?php esc_html_e( 'Plugin Version', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( ALYNT_DRIME_BACKUPS_UPLOADER_VERSION ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'WordPress Version', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'PHP Version', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( PHP_VERSION ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Diagnostics Enabled', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo ! empty( $settings['diagnostics_enabled'] ) ? esc_html__( 'Yes', 'alynt-drime-backups-uploader' ) : esc_html__( 'No', 'alynt-drime-backups-uploader' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Stored Events', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $diagnostics['count'] ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Last Event', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo '' === $diagnostics['last_event'] ? esc_html__( 'None', 'alynt-drime-backups-uploader' ) : esc_html( $diagnostics['last_event'] ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Scan Cron Scheduled', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo wp_next_scheduled( Alynt_Drime_Backups_Uploader_Cron::SCAN_EVENT ) ? esc_html__( 'Yes', 'alynt-drime-backups-uploader' ) : esc_html__( 'No', 'alynt-drime-backups-uploader' ); ?></td></tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders recent diagnostic events.
	 *
	 * @param array<int,array<string,mixed>> $events Events.
	 * @return void
	 */
	private function render_recent_events( array $events ) {
		?>
		<h3><?php esc_html_e( 'Recent Events', 'alynt-drime-backups-uploader' ); ?></h3>
		<?php
		$current_utc_time_reference = sprintf(
			/* translators: %s: Current UTC time. */
			__( 'Current UTC time: %s', 'alynt-drime-backups-uploader' ),
			$this->format_utc_time( time() )
		);
		?>
		<p class="alynt-drime-backups-time-reference"><?php echo esc_html( $current_utc_time_reference ); ?></p>
		<?php
		if ( empty( $events ) ) {
			$this->render_empty_events();
			return;
		}

		$this->render_events_table( $events );
	}

	/**
	 * Renders an empty events state.
	 *
	 * @return void
	 */
	private function render_empty_events() {
		?>
		<div class="alynt-drime-backups-empty">
			<h4><?php esc_html_e( 'No events yet', 'alynt-drime-backups-uploader' ); ?></h4>
			<p><?php esc_html_e( 'Scans, uploads, and connection checks will appear here.', 'alynt-drime-backups-uploader' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renders events table.
	 *
	 * @param array<int,array<string,mixed>> $events Events.
	 * @return void
	 */
	private function render_events_table( array $events ) {
		?>
		<table class="widefat striped">
			<caption class="screen-reader-text"><?php esc_html_e( 'Recent diagnostic events', 'alynt-drime-backups-uploader' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( '#', 'alynt-drime-backups-uploader' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Time', 'alynt-drime-backups-uploader' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Level', 'alynt-drime-backups-uploader' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Category', 'alynt-drime-backups-uploader' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Code', 'alynt-drime-backups-uploader' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Message', 'alynt-drime-backups-uploader' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $events as $index => $event ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( number_format_i18n( $index + 1 ) ); ?></th>
						<td><?php echo esc_html( $this->format_utc_time( isset( $event['time'] ) ? (int) $event['time'] : time() ) ); ?></td>
						<td><?php echo esc_html( isset( $event['level'] ) ? (string) $event['level'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $event['category'] ) ? (string) $event['category'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $event['code'] ) ? (string) $event['code'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $event['message'] ) ? (string) $event['message'] : '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
