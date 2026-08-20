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
		$status                 = isset( $connection['connection_status'] ) ? (string) $connection['connection_status'] : Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_DISABLED;
		$paired_enabled         = Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_PAIRED === $status && ! empty( $connection['status_endpoint_enabled'] );
		$remote_actions_enabled = $paired_enabled && ! empty( $connection['remote_actions_enabled'] ) && ! empty( $connection['action_key_id'] );
		$client_origin          = function_exists( 'home_url' ) ? home_url() : 'https://example.org';
		$status_endpoint        = ( new Alynt_Drime_Backups_Uploader_Dashboard_Connection() )->status_endpoint_for_origin( $client_origin );
		?>
		<h2><?php esc_html_e( 'Central Dashboard', 'alynt-drime-backups-uploader' ); ?></h2>
		<p><?php esc_html_e( 'Alynt Drime Backups Dashboard pairing is completed here by pasting a dashboard-generated token and explicitly opting in to read-only monitoring.', 'alynt-drime-backups-uploader' ); ?></p>
		<table class="widefat striped alynt-drime-backups-health">
			<caption class="screen-reader-text"><?php esc_html_e( 'Central dashboard connection summary', 'alynt-drime-backups-uploader' ); ?></caption>
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Connection status', 'alynt-drime-backups-uploader' ); ?></th>
					<td><?php echo esc_html( $this->dashboard_connection_status_label( $status ) ); ?></td>
				</tr>
				<?php if ( ! empty( $connection['dashboard_origin'] ) ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Dashboard origin', 'alynt-drime-backups-uploader' ); ?></th>
						<td><?php echo esc_html( (string) $connection['dashboard_origin'] ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( ! empty( $connection['expected_client_origin'] ) ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Expected client origin', 'alynt-drime-backups-uploader' ); ?></th>
						<td><?php echo esc_html( (string) $connection['expected_client_origin'] ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( ! empty( $connection['pairing_expires_at'] ) ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Pairing token expires', 'alynt-drime-backups-uploader' ); ?></th>
						<td><?php echo esc_html( (string) $connection['pairing_expires_at'] ); ?></td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status endpoint', 'alynt-drime-backups-uploader' ); ?></th>
					<td>
						<?php if ( ! empty( $connection['status_endpoint_enabled'] ) ) : ?>
							<?php
							printf(
								/* translators: %s: status endpoint URL. */
								esc_html__( 'Enabled for authenticated dashboard polling at %s', 'alynt-drime-backups-uploader' ),
								esc_url( $status_endpoint )
							);
							?>
						<?php else : ?>
							<?php esc_html_e( 'Disabled until pairing is completed with an unexpired dashboard token.', 'alynt-drime-backups-uploader' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Remote actions', 'alynt-drime-backups-uploader' ); ?></th>
					<td>
						<?php if ( $remote_actions_enabled ) : ?>
							<?php esc_html_e( 'V2 action opt-in completed. The bounded signed scan/upload-now endpoint is enabled for the paired dashboard only.', 'alynt-drime-backups-uploader' ); ?>
						<?php elseif ( $paired_enabled ) : ?>
							<?php esc_html_e( 'V1 read-only monitoring is active. V2 actions require a separate dashboard-generated adb2a token and explicit local opt-in.', 'alynt-drime-backups-uploader' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Not available until read-only dashboard pairing is completed.', 'alynt-drime-backups-uploader' ); ?>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="alynt-drime-dashboard-pairing-shell">
			<input type="hidden" name="action" value="alynt_drime_backups_save_dashboard_connection">
			<?php wp_nonce_field( 'alynt_drime_backups_save_dashboard_connection' ); ?>
			<table class="form-table" role="presentation">
				<?php if ( $paired_enabled ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Read-only dashboard monitoring', 'alynt-drime-backups-uploader' ); ?></th>
						<td>
							<p><?php esc_html_e( 'This site is already opted in to read-only dashboard monitoring.', 'alynt-drime-backups-uploader' ); ?></p>
							<p class="description"><?php esc_html_e( 'Only the authenticated, fixed, redacted status endpoint is enabled. This does not grant backup, restore, delete, cleanup, settings, credential, Drime token, or command actions.', 'alynt-drime-backups-uploader' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'V2 action opt-in token', 'alynt-drime-backups-uploader' ); ?></th>
						<td>
							<?php if ( $remote_actions_enabled ) : ?>
								<p><?php esc_html_e( 'This site has opted in to V2 scan/upload-now capability for the paired dashboard.', 'alynt-drime-backups-uploader' ); ?></p>
								<p class="description">
									<?php
									printf(
										/* translators: %s: action key identifier. */
										esc_html__( 'Current action key ID: %s. Only signed scan/upload-now intents from the paired dashboard are accepted; restore, delete, cleanup, settings, credential, and Drime token actions remain unavailable.', 'alynt-drime-backups-uploader' ),
										esc_html( (string) $connection['action_key_id'] )
									);
									?>
								</p>
							<?php else : ?>
								<label for="alynt-dashboard-action-opt-in-token" class="screen-reader-text"><?php esc_html_e( 'V2 action opt-in token', 'alynt-drime-backups-uploader' ); ?></label>
								<textarea id="alynt-dashboard-action-opt-in-token" class="large-text code" rows="3" name="alynt_drime_backups_dashboard_connection[action_opt_in_token]" aria-describedby="alynt-dashboard-action-opt-in-token-description" placeholder="<?php esc_attr_e( 'Paste the dashboard-generated adb2a token here.', 'alynt-drime-backups-uploader' ); ?>"></textarea>
								<p id="alynt-dashboard-action-opt-in-token-description" class="description"><?php esc_html_e( 'Use this only after read-only pairing is active. It stores the dashboard action public key locally and enables the bounded signed scan/upload-now endpoint for the paired dashboard only.', 'alynt-drime-backups-uploader' ); ?></p>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'V2 action opt-in confirmation', 'alynt-drime-backups-uploader' ); ?></legend>
									<label>
										<input type="checkbox" name="alynt_drime_backups_dashboard_connection[remote_action_opt_in]" value="1">
										<?php esc_html_e( 'Opt in to V2 scan/upload-now capability for the paired dashboard.', 'alynt-drime-backups-uploader' ); ?>
									</label>
								</fieldset>
							<?php endif; ?>
						</td>
					</tr>
				<?php else : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Pairing token', 'alynt-drime-backups-uploader' ); ?></th>
						<td>
							<label for="alynt-dashboard-token" class="screen-reader-text"><?php esc_html_e( 'Pairing token', 'alynt-drime-backups-uploader' ); ?></label>
							<textarea id="alynt-dashboard-token" class="large-text code" rows="3" name="alynt_drime_backups_dashboard_connection[pairing_token]" aria-describedby="alynt-dashboard-token-description" placeholder="<?php esc_attr_e( 'Paste the dashboard-generated adb1 token here.', 'alynt-drime-backups-uploader' ); ?>"></textarea>
							<p id="alynt-dashboard-token-description" class="description"><?php esc_html_e( 'The token is used once to complete pairing with the dashboard, then discarded. The raw token, one-time secret, and polling secret are not stored.', 'alynt-drime-backups-uploader' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Local opt-in intent', 'alynt-drime-backups-uploader' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="alynt_drime_backups_dashboard_connection[read_only_opt_in]" value="1">
								<?php esc_html_e( 'Opt in to read-only dashboard monitoring for this site.', 'alynt-drime-backups-uploader' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'This enables only an authenticated, fixed, redacted status endpoint after pairing succeeds. It does not grant backup, restore, delete, cleanup, settings, credential, Drime token, or command actions.', 'alynt-drime-backups-uploader' ); ?></p>
						</td>
					</tr>
				<?php endif; ?>
			</table>
			<?php if ( ! $paired_enabled && Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_TOKEN_READY === $status && ! empty( $connection['dashboard_origin'] ) ) : ?>
				<p>
					<label>
						<input type="checkbox" name="alynt_drime_backups_dashboard_connection[confirm_dashboard_origin]" value="1">
						<?php
						printf(
							/* translators: %s: dashboard origin. */
							esc_html__( 'I confirm this site should pair with %s for read-only monitoring.', 'alynt-drime-backups-uploader' ),
							esc_html( (string) $connection['dashboard_origin'] )
						);
						?>
					</label>
				</p>
			<?php endif; ?>
			<p>
				<?php if ( ! $paired_enabled ) : ?>
					<button type="submit" class="button button-secondary" name="alynt_drime_backups_dashboard_connection[connection_action]" value="prepare"><?php esc_html_e( 'Prepare Pairing Shell', 'alynt-drime-backups-uploader' ); ?></button>
					<button type="submit" class="button button-secondary" name="alynt_drime_backups_dashboard_connection[connection_action]" value="parse_token"><?php esc_html_e( 'Review Dashboard Token', 'alynt-drime-backups-uploader' ); ?></button>
					<?php if ( Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_TOKEN_READY === $status ) : ?>
						<button type="submit" class="button button-secondary" name="alynt_drime_backups_dashboard_connection[connection_action]" value="confirm_origin"><?php esc_html_e( 'Confirm Dashboard Origin', 'alynt-drime-backups-uploader' ); ?></button>
					<?php endif; ?>
					<button type="submit" class="button button-primary" name="alynt_drime_backups_dashboard_connection[connection_action]" value="complete_pairing"><?php esc_html_e( 'Complete Read-Only Pairing', 'alynt-drime-backups-uploader' ); ?></button>
				<?php endif; ?>
				<button type="submit" class="button" name="alynt_drime_backups_dashboard_connection[connection_action]" value="revoke"><?php esc_html_e( 'Revoke Dashboard Pairing', 'alynt-drime-backups-uploader' ); ?></button>
				<?php if ( $paired_enabled && ! $remote_actions_enabled ) : ?>
					<button type="submit" class="button button-secondary" name="alynt_drime_backups_dashboard_connection[connection_action]" value="complete_remote_action_opt_in"><?php esc_html_e( 'Complete V2 Action Opt-In', 'alynt-drime-backups-uploader' ); ?></button>
				<?php elseif ( $remote_actions_enabled ) : ?>
					<button type="submit" class="button" name="alynt_drime_backups_dashboard_connection[connection_action]" value="disable_remote_actions"><?php esc_html_e( 'Disable V2 Action Opt-In', 'alynt-drime-backups-uploader' ); ?></button>
				<?php endif; ?>
				<?php if ( ! $paired_enabled ) : ?>
					<button type="submit" class="button" name="alynt_drime_backups_dashboard_connection[connection_action]" value="disable"><?php esc_html_e( 'Keep Dashboard Disabled', 'alynt-drime-backups-uploader' ); ?></button>
				<?php endif; ?>
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
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_TOKEN_READY => __( 'Dashboard token reviewed', 'alynt-drime-backups-uploader' ),
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_CONFIRMED => __( 'Dashboard origin confirmed', 'alynt-drime-backups-uploader' ),
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_PAIRED   => __( 'Paired', 'alynt-drime-backups-uploader' ),
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_REVOKED  => __( 'Revoked', 'alynt-drime-backups-uploader' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $labels[ Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_DISABLED ];
	}
}
