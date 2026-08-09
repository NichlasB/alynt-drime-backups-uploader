<?php
/**
 * Admin page dashboard settings rendering.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page dashboard settings rendering.
 *
 * @since 0.5.3
 */
trait Alynt_Drime_Backups_Uploader_Admin_Page_Dashboard_Settings {
	/**
	 * Renders the central dashboard pairing shell.
	 *
	 * @param array<string,mixed> $connection Dashboard connection state.
	 * @return void
	 */
	private function render_dashboard_connection_shell( array $connection ) {
		$status = isset( $connection['connection_status'] ) ? (string) $connection['connection_status'] : Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_DISABLED;
		?>
		<h2><?php esc_html_e( 'Central Dashboard', 'alynt-drime-backups-uploader' ); ?></h2>
		<p><?php esc_html_e( 'Alynt Drime Backups Dashboard pairing is prepared here, but the external status endpoint remains disabled until authenticated pairing is implemented and completed.', 'alynt-drime-backups-uploader' ); ?></p>
		<table class="widefat striped alynt-drime-backups-health">
			<caption class="screen-reader-text"><?php esc_html_e( 'Central dashboard connection summary', 'alynt-drime-backups-uploader' ); ?></caption>
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Connection status', 'alynt-drime-backups-uploader' ); ?></th>
					<td><?php echo esc_html( $this->dashboard_connection_status_label( $status ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status endpoint', 'alynt-drime-backups-uploader' ); ?></th>
					<td><?php esc_html_e( 'Disabled. No public dashboard status route is registered in this slice.', 'alynt-drime-backups-uploader' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Remote actions', 'alynt-drime-backups-uploader' ); ?></th>
					<td><?php esc_html_e( 'Not available in v1. The dashboard contract is read-only monitoring only.', 'alynt-drime-backups-uploader' ); ?></td>
				</tr>
			</tbody>
		</table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="alynt-drime-dashboard-pairing-shell">
			<input type="hidden" name="action" value="alynt_drime_backups_save_dashboard_connection">
			<?php wp_nonce_field( 'alynt_drime_backups_save_dashboard_connection' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Pairing token', 'alynt-drime-backups-uploader' ); ?></th>
					<td>
						<textarea class="large-text code" rows="3" disabled aria-describedby="alynt-dashboard-token-description" placeholder="<?php esc_attr_e( 'Token entry arrives in the next implementation slice.', 'alynt-drime-backups-uploader' ); ?>"></textarea>
						<p id="alynt-dashboard-token-description" class="description"><?php esc_html_e( 'Do not store or paste dashboard pairing tokens yet. Token parsing, dashboard-origin confirmation, and credential verifier storage are separate approval-gated work.', 'alynt-drime-backups-uploader' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Local opt-in intent', 'alynt-drime-backups-uploader' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="alynt_drime_backups_dashboard_connection[read_only_opt_in]" value="1">
							<?php esc_html_e( 'Prepare this site for future read-only dashboard pairing.', 'alynt-drime-backups-uploader' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'This records local intent only. It does not enable an endpoint, create credentials, contact a dashboard, or expose backup data.', 'alynt-drime-backups-uploader' ); ?></p>
					</td>
				</tr>
			</table>
			<p>
				<button type="submit" class="button button-secondary" name="alynt_drime_backups_dashboard_connection[connection_action]" value="prepare"><?php esc_html_e( 'Prepare Pairing Shell', 'alynt-drime-backups-uploader' ); ?></button>
				<button type="submit" class="button" name="alynt_drime_backups_dashboard_connection[connection_action]" value="disable"><?php esc_html_e( 'Keep Dashboard Disabled', 'alynt-drime-backups-uploader' ); ?></button>
			</p>
		</form>
		<?php
	}

	/**
	 * Returns a human-readable dashboard connection status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	private function dashboard_connection_status_label( $status ) {
		$labels = array(
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_DISABLED => __( 'Disabled', 'alynt-drime-backups-uploader' ),
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_READY    => __( 'Ready for pairing shell', 'alynt-drime-backups-uploader' ),
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_PAIRED   => __( 'Paired', 'alynt-drime-backups-uploader' ),
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_REVOKED  => __( 'Revoked', 'alynt-drime-backups-uploader' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $labels[ Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_DISABLED ];
	}
}
