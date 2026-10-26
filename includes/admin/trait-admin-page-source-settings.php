<?php
/**
 * Admin page backup source settings rendering.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page backup source settings rendering.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Admin_Page_Source_Settings {
	/**
	 * Renders source settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param string              $detected_path Detected path.
	 * @return void
	 */
	private function render_source_settings( array $settings, $detected_path ) {
		?>
		<h2><?php esc_html_e( 'Backup Sources', 'alynt-drime-backups-uploader' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="alynt-server-outbox-path"><?php esc_html_e( 'Server Outbox Path', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<input id="alynt-server-outbox-path" name="alynt_drime_backups_settings[server_outbox_path]" type="text" class="large-text code" value="<?php echo esc_attr( (string) $settings['server_outbox_path'] ); ?>" aria-describedby="alynt-server-outbox-path-description">
					<p id="alynt-server-outbox-path-description" class="description"><?php esc_html_e( 'Directory where the server backup runner writes completed backup packages.', 'alynt-drime-backups-uploader' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="alynt-server-relative-path"><?php esc_html_e( 'Server Drime Relative Path', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<input id="alynt-server-relative-path" name="alynt_drime_backups_settings[server_relative_path]" type="text" class="regular-text" value="<?php echo esc_attr( (string) $settings['server_relative_path'] ); ?>" placeholder="<?php echo esc_attr__( '/example.com/server', 'alynt-drime-backups-uploader' ); ?>" aria-describedby="alynt-server-relative-path-description">
					<p id="alynt-server-relative-path-description" class="description"><?php esc_html_e( 'Optional destination subpath for server-runner/generic-outbox packages. Leave empty to use the shared Drime relative path.', 'alynt-drime-backups-uploader' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="alynt-server-runner-install-command"><?php esc_html_e( '1. Install / Update Server Runner', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<textarea id="alynt-server-runner-install-command" class="large-text code alynt-drime-command-snippet alynt-drime-command-snippet--compact" readonly rows="4" aria-describedby="alynt-server-runner-install-command-description"><?php echo esc_textarea( $this->server_runner_install_commands( $settings ) ); ?></textarea>
					<p id="alynt-server-runner-install-command-description" class="description"><?php esc_html_e( 'Run this as the site user. It creates private directories, writes config.json, installs the runner, sets permissions, and runs health.', 'alynt-drime-backups-uploader' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="alynt-server-runner-test-command"><?php esc_html_e( '2. Create First Test Backup', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<textarea id="alynt-server-runner-test-command" class="large-text code alynt-drime-command-snippet alynt-drime-command-snippet--compact" readonly rows="3" aria-describedby="alynt-server-runner-test-command-description"><?php echo esc_textarea( $this->server_runner_test_command( $settings ) ); ?></textarea>
					<p id="alynt-server-runner-test-command-description" class="description"><?php esc_html_e( 'Run this as the site user to create one package and verify the package path printed by the runner.', 'alynt-drime-backups-uploader' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="alynt-wp-cli-upload-command"><?php esc_html_e( '3. Scan And Upload Completed Packages', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<textarea id="alynt-wp-cli-upload-command" class="large-text code alynt-drime-command-snippet alynt-drime-command-snippet--compact" readonly rows="3" aria-describedby="alynt-wp-cli-upload-command-description"><?php echo esc_textarea( $this->wp_cli_scan_upload_command() ); ?></textarea>
					<p id="alynt-wp-cli-upload-command-description" class="description"><?php esc_html_e( 'Run this as the site user after the package is old enough to pass the minimum file age and stability checks.', 'alynt-drime-backups-uploader' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="alynt-server-cron-review-commands"><?php esc_html_e( '4. Review And Install Cron', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<textarea id="alynt-server-cron-review-commands" class="large-text code alynt-drime-command-snippet" readonly rows="8" aria-describedby="alynt-server-cron-review-commands-description"><?php echo esc_textarea( $this->server_cron_review_commands( $settings ) ); ?></textarea>
					<p id="alynt-server-cron-review-commands-description" class="description"><?php esc_html_e( 'Run these as the site user to build and review a proposed crontab file. Each shell command is single-line. The final install command stays commented until you approve it.', 'alynt-drime-backups-uploader' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'WPvivid Detected Path', 'alynt-drime-backups-uploader' ); ?></th>
				<td><code><?php echo esc_html( $detected_path ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><label for="alynt-backup-path-override"><?php esc_html_e( 'WPvivid Path Override', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<input id="alynt-backup-path-override" name="alynt_drime_backups_settings[backup_path_override]" type="text" class="large-text code" value="<?php echo esc_attr( (string) $settings['backup_path_override'] ); ?>" aria-describedby="alynt-backup-path-override-description">
					<p id="alynt-backup-path-override-description" class="description"><?php esc_html_e( 'Optional. Use only if WPvivid stores local backups outside the detected path.', 'alynt-drime-backups-uploader' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="alynt-wpvivid-relative-path"><?php esc_html_e( 'WPvivid Drime Relative Path', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<input id="alynt-wpvivid-relative-path" name="alynt_drime_backups_settings[wpvivid_relative_path]" type="text" class="regular-text" value="<?php echo esc_attr( (string) $settings['wpvivid_relative_path'] ); ?>" placeholder="<?php echo esc_attr__( '/example.com/wpvivid', 'alynt-drime-backups-uploader' ); ?>" aria-describedby="alynt-wpvivid-relative-path-description">
					<p id="alynt-wpvivid-relative-path-description" class="description"><?php esc_html_e( 'Optional destination subpath for WPvivid packages. Leave empty to use the shared Drime relative path.', 'alynt-drime-backups-uploader' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}
}
