<?php
/**
 * Admin page status rendering.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page status rendering.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Admin_Page_Status {
	/**
	 * Renders manual action buttons.
	 *
	 * @return void
	 */
	private function render_manual_actions() {
		?>
		<h2><?php esc_html_e( 'Manual Actions', 'alynt-drime-backups-uploader' ); ?></h2>
		<div class="alynt-drime-backups-actions">
			<?php $this->render_action_button( 'alynt_drime_backups_test_connection', __( 'Test Drime Connection', 'alynt-drime-backups-uploader' ) ); ?>
			<?php $this->render_action_button( 'alynt_drime_backups_send_test_failure_email', __( 'Send Test Email', 'alynt-drime-backups-uploader' ) ); ?>
			<?php $this->render_action_button( 'alynt_drime_backups_scan_now', __( 'Scan Backup Folder', 'alynt-drime-backups-uploader' ) ); ?>
			<?php $this->render_action_button( 'alynt_drime_backups_upload_next', __( 'Upload Next Queued Backup', 'alynt-drime-backups-uploader' ), false, __( 'Uploading backup...', 'alynt-drime-backups-uploader' ) ); ?>
			<?php $this->render_action_button( 'alynt_drime_backups_preview_remote_retention', __( 'Preview Remote Retention', 'alynt-drime-backups-uploader' ) ); ?>
			<?php $this->render_action_button( 'alynt_drime_backups_run_remote_retention', __( 'Run Remote Retention', 'alynt-drime-backups-uploader' ), __( 'Move eligible Drime files uploaded by this plugin to trash?', 'alynt-drime-backups-uploader' ) ); ?>
		</div>
		<?php
	}

	/**
	 * Renders queue status summary.
	 *
	 * @param array<int,array<string,mixed>> $queue Queue.
	 * @param array<string,mixed>            $uploaded Uploaded records.
	 * @param array<string,mixed>            $failed Failed records.
	 * @return void
	 */
	private function render_status_summary( array $queue, array $uploaded, array $failed ) {
		?>
		<h2><?php esc_html_e( 'Status', 'alynt-drime-backups-uploader' ); ?></h2>
		<div class="alynt-drime-backups-status-grid">
			<?php $this->render_status_box( __( 'Queued', 'alynt-drime-backups-uploader' ), count( $queue ) ); ?>
			<?php $this->render_status_box( __( 'Uploaded', 'alynt-drime-backups-uploader' ), count( $uploaded ) ); ?>
			<?php $this->render_status_box( __( 'Failed', 'alynt-drime-backups-uploader' ), count( $failed ) ); ?>
		</div>
		<?php
	}

	/**
	 * Renders the internal health summary.
	 *
	 * @param array<string,mixed> $health Health summary.
	 * @return void
	 */
	private function render_health_summary( array $health ) {
		?>
		<h3><?php esc_html_e( 'Server Runner Status', 'alynt-drime-backups-uploader' ); ?></h3>
		<table class="widefat striped alynt-drime-backups-health-summary">
			<caption class="screen-reader-text"><?php esc_html_e( 'Server runner and uploader health summary', 'alynt-drime-backups-uploader' ); ?></caption>
			<tbody>
				<tr><th scope="row"><?php esc_html_e( 'Health Schema', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( number_format_i18n( isset( $health['schema_version'] ) ? absint( $health['schema_version'] ) : 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Site UUID', 'alynt-drime-backups-uploader' ); ?></th><td><code><?php echo esc_html( isset( $health['site_uuid'] ) ? (string) $health['site_uuid'] : '' ); ?></code></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Server Outbox Configured', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo ! empty( $health['server_outbox_configured'] ) ? esc_html__( 'Yes', 'alynt-drime-backups-uploader' ) : esc_html__( 'No', 'alynt-drime-backups-uploader' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Server Outbox Readable', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo ! empty( $health['server_outbox_readable'] ) ? esc_html__( 'Yes', 'alynt-drime-backups-uploader' ) : esc_html__( 'No', 'alynt-drime-backups-uploader' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Active Upload', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo ! empty( $health['active_upload'] ) ? esc_html__( 'Yes', 'alynt-drime-backups-uploader' ) : esc_html__( 'No', 'alynt-drime-backups-uploader' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Server Cron Status', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( $this->cron_health_status_label( isset( $health['cron_status'] ) ? (string) $health['cron_status'] : '' ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Server Cron Reason', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( isset( $health['cron_reason'] ) ? (string) $health['cron_reason'] : '' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Warnings', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( number_format_i18n( isset( $health['warning_count'] ) ? absint( $health['warning_count'] ) : 0 ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Last WP-CLI Scan', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( $this->format_optional_utc_time( isset( $health['last_wp_cli_scan_at'] ) ? $health['last_wp_cli_scan_at'] : 0 ) ); ?></td></tr>
			</tbody>
		</table>
		<?php if ( ! empty( $health['warnings'] ) && is_array( $health['warnings'] ) ) : ?>
			<ul class="alynt-drime-backups-health-warnings">
				<?php foreach ( $health['warnings'] as $warning ) : ?>
					<?php if ( is_array( $warning ) ) : ?>
						<li><strong><?php echo esc_html( isset( $warning['code'] ) ? (string) $warning['code'] : '' ); ?></strong>: <?php echo esc_html( isset( $warning['message'] ) ? (string) $warning['message'] : '' ); ?></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders active upload state.
	 *
	 * @param array<string,mixed> $active Active upload state.
	 * @return void
	 */
	private function render_active_upload_state( array $active ) {
		if ( empty( $active ) ) {
			return;
		}

		?>
		<h3><?php esc_html_e( 'Active Upload', 'alynt-drime-backups-uploader' ); ?></h3>
		<table class="widefat striped alynt-drime-backups-active-upload">
			<caption class="screen-reader-text"><?php esc_html_e( 'Active upload details', 'alynt-drime-backups-uploader' ); ?></caption>
			<tbody>
				<tr><th scope="row"><?php esc_html_e( 'File', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( isset( $active['remote_name'] ) ? basename( (string) $active['remote_name'] ) : '' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Completed Parts', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( isset( $active['completed_parts'] ) ? number_format_i18n( absint( $active['completed_parts'] ) ) : '0' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Total Parts', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( isset( $active['total_parts'] ) ? number_format_i18n( absint( $active['total_parts'] ) ) : '0' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Updated', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( ! empty( $active['updated_at'] ) ? wp_date( 'Y-m-d H:i:s', absint( $active['updated_at'] ) ) : '' ); ?></td></tr>
			</tbody>
		</table>
		<div class="alynt-drime-backups-active-actions">
			<?php $this->render_action_button( 'alynt_drime_backups_clear_active_upload', __( 'Clear Active Upload', 'alynt-drime-backups-uploader' ), __( 'Clear active upload state? The current multipart upload may need to restart.', 'alynt-drime-backups-uploader' ) ); ?>
		</div>
		<?php
	}

	/**
	 * Renders remote retention status.
	 *
	 * @param array<string,mixed>            $settings Settings.
	 * @param array<int,array<string,mixed>> $candidates Candidates.
	 * @return void
	 */
	private function render_remote_retention_status( array $settings, array $candidates ) {
		?>
		<h3><?php esc_html_e( 'Remote Retention', 'alynt-drime-backups-uploader' ); ?></h3>
		<table class="widefat striped alynt-drime-backups-retention">
				<caption class="screen-reader-text"><?php esc_html_e( 'Remote retention summary', 'alynt-drime-backups-uploader' ); ?></caption>
			<tbody>
				<tr><th scope="row"><?php esc_html_e( 'Enabled', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo ! empty( $settings['remote_retention_enabled'] ) ? esc_html__( 'Yes', 'alynt-drime-backups-uploader' ) : esc_html__( 'No', 'alynt-drime-backups-uploader' ); ?></td></tr>
				<?php $remote_retention_days = absint( $settings['remote_retention_days'] ); ?>
				<?php /* translators: %s: number of days. */ ?>
				<tr><th scope="row"><?php esc_html_e( 'Retention Age', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( sprintf( _n( '%s day', '%s days', $remote_retention_days, 'alynt-drime-backups-uploader' ), number_format_i18n( $remote_retention_days ) ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Eligible Files', 'alynt-drime-backups-uploader' ); ?></th><td><?php echo esc_html( number_format_i18n( count( $candidates ) ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Cleanup Mode', 'alynt-drime-backups-uploader' ); ?></th><td><?php esc_html_e( 'Move to Drime trash only', 'alynt-drime-backups-uploader' ); ?></td></tr>
			</tbody>
		</table>
		<?php if ( ! empty( $candidates ) ) : ?>
			<table class="widefat striped alynt-drime-backups-retention-candidates">
				<caption class="screen-reader-text"><?php esc_html_e( 'Remote retention candidates', 'alynt-drime-backups-uploader' ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'File', 'alynt-drime-backups-uploader' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Age', 'alynt-drime-backups-uploader' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $candidates as $candidate ) : ?>
						<tr>
							<td><?php echo esc_html( isset( $candidate['remote_name'] ) ? (string) $candidate['remote_name'] : '' ); ?></td>
							<?php $candidate_age_days = isset( $candidate['age_days'] ) ? absint( $candidate['age_days'] ) : 0; ?>
							<?php /* translators: %s: number of days. */ ?>
							<td><?php echo esc_html( sprintf( _n( '%s day', '%s days', $candidate_age_days, 'alynt-drime-backups-uploader' ), number_format_i18n( $candidate_age_days ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

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

	/**
	 * Renders an admin-post action button.
	 *
	 * @param string      $action Action.
	 * @param string      $label Label.
	 * @param bool|string $confirm Whether to show confirmation.
	 * @param string      $loading_label Optional loading label.
	 * @return void
	 */
	private function render_action_button( $action, $label, $confirm = false, $loading_label = '' ) {
		if ( '' === $loading_label ) {
			$loading_label = sprintf(
				/* translators: %s: Button label. */
				__( '%s...', 'alynt-drime-backups-uploader' ),
				$label
			);
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
			<?php wp_nonce_field( $action ); ?>
			<button
				type="submit"
				class="<?php echo esc_attr( $confirm ? 'button button-link-delete' : 'button button-secondary' ); ?>"
				data-alynt-loading-label="<?php echo esc_attr( $loading_label ); ?>"
				<?php echo $confirm ? 'data-alynt-confirm="' . esc_attr( true === $confirm ? __( 'Clear all diagnostics events? This cannot be undone.', 'alynt-drime-backups-uploader' ) : (string) $confirm ) . '"' : ''; ?>
			>
				<?php echo esc_html( $label ); ?>
			</button>
		</form>
		<?php
	}

	/**
	 * Formats a timestamp as UTC.
	 *
	 * @param int $timestamp Timestamp.
	 * @return string
	 */
	private function format_utc_time( $timestamp ) {
		return gmdate( 'Y-m-d H:i:s \U\T\C', absint( $timestamp ) );
	}

	/**
	 * Renders a status box.
	 *
	 * @param string $label Label.
	 * @param int    $count Count.
	 * @return void
	 */
	private function render_status_box( $label, $count ) {
		?>
		<div class="alynt-drime-backups-status-box">
			<strong><?php echo esc_html( number_format_i18n( $count ) ); ?></strong>
			<span><?php echo esc_html( $label ); ?></span>
		</div>
		<?php
	}
}
