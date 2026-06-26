<?php
/**
 * Admin page settings form rendering.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page settings form rendering.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Admin_Page_Settings {
	/**
	 * Renders the settings form.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param string              $detected_path Detected path.
	 * @return void
	 */
	private function render_settings_form( array $settings, $detected_path ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="alynt_drime_backups_save_settings">
			<?php wp_nonce_field( 'alynt_drime_backups_save_settings' ); ?>
			<?php $this->render_drime_settings( $settings ); ?>
			<?php $this->render_source_settings( $settings, $detected_path ); ?>
			<?php $this->render_behavior_settings( $settings ); ?>
			<?php submit_button( __( 'Save Settings', 'alynt-drime-backups-uploader' ), 'primary', 'submit', true, array( 'data-alynt-loading-label' => __( 'Saving...', 'alynt-drime-backups-uploader' ) ) ); ?>
		</form>
		<?php
	}

	/**
	 * Renders Drime settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private function render_drime_settings( array $settings ) {
		?>
		<h2><?php esc_html_e( 'Drime', 'alynt-drime-backups-uploader' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="alynt-api-token"><?php esc_html_e( 'API Token', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<input id="alynt-api-token" name="alynt_drime_backups_settings[api_token]" type="password" class="regular-text" value="<?php echo esc_attr( '' === $settings['api_token'] ? '' : '************' ); ?>" autocomplete="off" aria-describedby="alynt-api-token-description">
					<p id="alynt-api-token-description" class="description"><?php esc_html_e( 'Enter a Drime bearer token. Leave the masked value unchanged to keep the saved token.', 'alynt-drime-backups-uploader' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="alynt-workspace-id"><?php esc_html_e( 'Workspace ID', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<input id="alynt-workspace-id" name="alynt_drime_backups_settings[workspace_id]" type="number" min="0" value="<?php echo esc_attr( (string) $settings['workspace_id'] ); ?>" aria-describedby="alynt-workspace-id-description">
					<p id="alynt-workspace-id-description" class="description"><?php esc_html_e( 'Use 0 for your personal/default Drime workspace.', 'alynt-drime-backups-uploader' ); ?></p>
					<div class="alynt-drime-workspace-tools" data-alynt-workspace-browser>
						<button type="button" class="button" data-alynt-workspaces-load><?php esc_html_e( 'Load Drime Workspaces', 'alynt-drime-backups-uploader' ); ?></button>
						<span class="spinner" data-alynt-workspace-spinner></span>
						<label class="screen-reader-text" for="alynt-workspace-select"><?php esc_html_e( 'Choose Drime workspace', 'alynt-drime-backups-uploader' ); ?></label>
						<select id="alynt-workspace-select" data-alynt-workspace-select hidden>
							<?php /* translators: %d: saved Drime workspace ID. */ ?>
							<option value="<?php echo esc_attr( (string) absint( $settings['workspace_id'] ) ); ?>"><?php echo esc_html( 0 === absint( $settings['workspace_id'] ) ? __( 'Personal/default workspace', 'alynt-drime-backups-uploader' ) : sprintf( __( 'Workspace ID %d', 'alynt-drime-backups-uploader' ), absint( $settings['workspace_id'] ) ) ); ?></option>
						</select>
						<div class="alynt-drime-workspace-status" aria-live="polite" data-alynt-workspace-status></div>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="alynt-parent-folder-id"><?php esc_html_e( 'Parent Folder ID', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<input id="alynt-parent-folder-id" name="alynt_drime_backups_settings[parent_folder_id]" type="number" min="0" value="<?php echo esc_attr( (string) $settings['parent_folder_id'] ); ?>" aria-describedby="alynt-parent-folder-id-description">
					<input id="alynt-parent-folder-hash" name="alynt_drime_backups_settings[parent_folder_hash]" type="hidden" value="<?php echo esc_attr( (string) $settings['parent_folder_hash'] ); ?>">
					<input id="alynt-parent-folder-display-path" name="alynt_drime_backups_settings[parent_folder_display_path]" type="hidden" value="<?php echo esc_attr( (string) $settings['parent_folder_display_path'] ); ?>">
					<p id="alynt-parent-folder-id-description" class="description"><?php esc_html_e( 'Leave empty for the Drime root folder, or browse and select an existing Drime base folder.', 'alynt-drime-backups-uploader' ); ?></p>
					<p class="description" data-alynt-selected-folder>
						<?php
						if ( '' !== (string) $settings['parent_folder_display_path'] ) {
							printf(
								/* translators: %s: selected Drime folder path. */
								esc_html__( 'Selected base folder: %s', 'alynt-drime-backups-uploader' ),
								esc_html( (string) $settings['parent_folder_display_path'] )
							);
						} else {
							esc_html_e( 'Selected base folder: Drime root or manually entered folder ID.', 'alynt-drime-backups-uploader' );
						}
						?>
					</p>
					<div class="alynt-drime-folder-tools" data-alynt-folder-browser>
						<button type="button" class="button" data-alynt-folder-browser-open><?php esc_html_e( 'Browse Drime Folders', 'alynt-drime-backups-uploader' ); ?></button>
						<button type="button" class="button" data-alynt-destination-preview><?php esc_html_e( 'Preview Destination', 'alynt-drime-backups-uploader' ); ?></button>
						<span class="spinner" data-alynt-folder-spinner></span>
						<div class="alynt-drime-folder-status" aria-live="polite" data-alynt-folder-status></div>
						<div class="alynt-drime-folder-panel" hidden data-alynt-folder-panel>
							<div class="alynt-drime-folder-search">
								<label class="screen-reader-text" for="alynt-folder-search"><?php esc_html_e( 'Search Drime folders', 'alynt-drime-backups-uploader' ); ?></label>
								<input id="alynt-folder-search" type="search" class="regular-text" data-alynt-folder-search>
								<button type="button" class="button" data-alynt-folder-search-button><?php esc_html_e( 'Search', 'alynt-drime-backups-uploader' ); ?></button>
							</div>
							<table class="widefat striped alynt-drime-folder-table">
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Name', 'alynt-drime-backups-uploader' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Path', 'alynt-drime-backups-uploader' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Actions', 'alynt-drime-backups-uploader' ); ?></th>
									</tr>
								</thead>
								<tbody data-alynt-folder-rows></tbody>
							</table>
						</div>
						<div class="alynt-drime-destination-preview" aria-live="polite" data-alynt-destination-status></div>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="alynt-relative-path"><?php esc_html_e( 'Relative Path', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<input id="alynt-relative-path" name="alynt_drime_backups_settings[relative_path]" type="text" class="regular-text" value="<?php echo esc_attr( (string) $settings['relative_path'] ); ?>" placeholder="<?php echo esc_attr__( '/WPvivid Backups', 'alynt-drime-backups-uploader' ); ?>" aria-describedby="alynt-relative-path-description">
					<p id="alynt-relative-path-description" class="description"><?php esc_html_e( 'Optional subpath under the selected base folder. Missing folders are created only when an upload needs them.', 'alynt-drime-backups-uploader' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

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
				<th scope="row"><label for="alynt-server-runner-config"><?php esc_html_e( 'Server Runner Config', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<textarea id="alynt-server-runner-config" class="large-text code alynt-drime-command-snippet" readonly rows="18" aria-describedby="alynt-server-runner-config-description"><?php echo esc_textarea( $this->server_runner_config_json( $settings ) ); ?></textarea>
					<p id="alynt-server-runner-config-description" class="description"><?php esc_html_e( 'Save this as config.json beside the server runner script for this site. It does not include Drime credentials.', 'alynt-drime-backups-uploader' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="alynt-server-runner-install-commands"><?php esc_html_e( 'Server Runner Install Commands', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<textarea id="alynt-server-runner-install-commands" class="large-text code alynt-drime-command-snippet" readonly rows="8" aria-describedby="alynt-server-runner-install-commands-description"><?php echo esc_textarea( $this->server_runner_install_commands( $settings ) ); ?></textarea>
					<p id="alynt-server-runner-install-commands-description" class="description"><?php esc_html_e( 'Run these as the site user after reviewing the generated config. They install the runner script and directories only; they do not add cron or run a backup.', 'alynt-drime-backups-uploader' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="alynt-server-runner-health-command"><?php esc_html_e( 'Server Runner Health Command', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<input id="alynt-server-runner-health-command" type="text" class="large-text code" readonly value="<?php echo esc_attr( $this->server_runner_command( 'health', $settings ) ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="alynt-wp-cli-run-command"><?php esc_html_e( 'WP-CLI Runner Command', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<input id="alynt-wp-cli-run-command" type="text" class="large-text code" readonly value="<?php echo esc_attr( $this->wp_cli_command( 'run --max-uploads=1' ) ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="alynt-wp-cli-status-command"><?php esc_html_e( 'WP-CLI Status Command', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<input id="alynt-wp-cli-status-command" type="text" class="large-text code" readonly value="<?php echo esc_attr( $this->wp_cli_command( 'status --format=json' ) ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="alynt-gridpane-cron-snippet"><?php esc_html_e( 'GridPane Cron Snippet', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<textarea id="alynt-gridpane-cron-snippet" class="large-text code alynt-drime-cron-snippet" readonly rows="6" aria-describedby="alynt-gridpane-cron-snippet-description"><?php echo esc_textarea( $this->gridpane_cron_snippet( $settings ) ); ?></textarea>
					<p id="alynt-gridpane-cron-snippet-description" class="description"><?php esc_html_e( 'Copy this into the site user crontab after the server runner is installed and health checks pass.', 'alynt-drime-backups-uploader' ); ?></p>
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
		</table>
		<?php
	}

	/**
	 * Builds a WP-CLI command for the current site path.
	 *
	 * @param string $subcommand Plugin subcommand.
	 * @return string
	 */
	private function wp_cli_command( $subcommand ) {
		return 'wp --path=' . escapeshellarg( untrailingslashit( ABSPATH ) ) . ' alynt-drime-backups ' . $subcommand;
	}

	/**
	 * Builds a GridPane-oriented server cron snippet.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private function gridpane_cron_snippet( array $settings ) {
		$runner_command = $this->server_runner_command( 'run', $settings );
		$upload_command = $this->wp_cli_command_posix( 'run --max-uploads=1' );
		$status_command = $this->wp_cli_command_posix( 'status --format=json' );

		return implode(
			"\n",
			array(
				'# Alynt Drime Backups: create one server-side package daily.',
				'17 2 * * * ' . $runner_command,
				'# Alynt Drime Backups: scan/upload completed packages every 15 minutes.',
				'*/15 * * * * ' . $upload_command,
				'# Alynt Drime Backups: optional status log check.',
				'7 3 * * * ' . $status_command,
			)
		);
	}

	/**
	 * Builds server runner config JSON for the current site.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private function server_runner_config_json( array $settings ) {
		$runner_base    = $this->runner_base_path( $settings );
		$wordpress_path = untrailingslashit( ABSPATH );
		$site_url       = untrailingslashit( $this->site_url_for_runner() );
		$site_host      = $this->site_host_for_runner( $site_url );
		$config         = array(
			'site_id'                  => $site_host,
			'site_url'                 => $site_url,
			'wordpress_path'           => $wordpress_path,
			'outbox_path'              => $runner_base . '/outbox',
			'work_path'                => $runner_base . '/work',
			'restore_path'             => dirname( $wordpress_path ) . '/restores/alynt-drime-backups',
			'archive_format'           => 'tar.gz',
			'minimum_free_space_bytes' => 1073741824,
			'package_prefix'           => $this->package_prefix_for_runner( $site_host ),
			'wp_cli_path'              => 'wp',
			'database'                 => array(
				'enabled' => true,
			),
			'exclude_paths'            => array(
				'.git',
				'wp-content/cache',
				'wp-content/debug.log',
				'wp-content/uploads/wpvividbackups',
				'wp-content/updraft',
				'wp-content/ai1wm-backups',
				'wp-content/backup-*',
			),
		);

		if ( function_exists( 'wp_json_encode' ) ) {
			return wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		}

		return json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Builds conservative server runner install commands.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private function server_runner_install_commands( array $settings ) {
		$runner_base    = $this->runner_base_path( $settings );
		$runner_dir     = $runner_base . '/runner';
		$outbox_path    = $runner_base . '/outbox';
		$work_path      = $runner_base . '/work';
		$restore_path   = dirname( untrailingslashit( ABSPATH ) ) . '/restores/alynt-drime-backups';
		$source_runner  = untrailingslashit( ALYNT_DRIME_BACKUPS_UPLOADER_PATH ) . '/server-runner/alynt-backup-runner.php';
		$target_runner  = $runner_dir . '/alynt-backup-runner.php';
		$target_config  = $runner_dir . '/config.json';
		$health_command = $this->server_runner_command( 'health', $settings );

		return implode(
			"\n",
			array(
				'mkdir -p ' . $this->posix_shell_arg( $runner_dir ) . ' ' . $this->posix_shell_arg( $outbox_path ) . ' ' . $this->posix_shell_arg( $work_path ) . ' ' . $this->posix_shell_arg( $restore_path ),
				'cp ' . $this->posix_shell_arg( $source_runner ) . ' ' . $this->posix_shell_arg( $target_runner ),
				'chmod 750 ' . $this->posix_shell_arg( $target_runner ),
				'chmod 640 ' . $this->posix_shell_arg( $target_config ) . ' # after saving the generated config.json',
				$health_command,
			)
		);
	}

	/**
	 * Builds a server runner command.
	 *
	 * @param string              $command Runner command.
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private function server_runner_command( $command, array $settings ) {
		$runner_base = $this->runner_base_path( $settings );
		$runner_path = $runner_base . '/runner/alynt-backup-runner.php';
		$config_path = $runner_base . '/runner/config.json';
		$command     = preg_replace( '/[^a-z0-9_-]+/i', '', (string) $command );
		$command     = '' !== $command ? $command : 'health';

		return 'php ' . $this->posix_shell_arg( $runner_path ) . ' ' . $command . ' --config=' . $this->posix_shell_arg( $config_path );
	}

	/**
	 * Returns the expected server runner base path.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private function runner_base_path( array $settings ) {
		$outbox = isset( $settings['server_outbox_path'] ) ? trim( (string) $settings['server_outbox_path'] ) : '';
		if ( '' !== $outbox ) {
			return untrailingslashit( dirname( $outbox ) );
		}

		return untrailingslashit( dirname( untrailingslashit( ABSPATH ) ) . '/private/alynt-drime-backups' );
	}

	/**
	 * Returns the site URL used for generated runner config.
	 *
	 * @return string
	 */
	private function site_url_for_runner() {
		if ( function_exists( 'home_url' ) ) {
			return (string) home_url( '/' );
		}

		if ( function_exists( 'site_url' ) ) {
			return (string) site_url( '/' );
		}

		return 'https://example.test';
	}

	/**
	 * Returns the host part for generated runner config.
	 *
	 * @param string $site_url Site URL.
	 * @return string
	 */
	private function site_host_for_runner( $site_url ) {
		$host = parse_url( $site_url, PHP_URL_HOST );
		$host = is_string( $host ) ? strtolower( $host ) : '';

		return '' !== $host ? $host : 'wordpress-site';
	}

	/**
	 * Returns a filesystem-safe package prefix for generated runner config.
	 *
	 * @param string $site_host Site host.
	 * @return string
	 */
	private function package_prefix_for_runner( $site_host ) {
		$prefix = preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $site_host ) );
		$prefix = trim( (string) $prefix, '-' );

		return '' !== $prefix ? $prefix : 'wordpress-site';
	}

	/**
	 * Builds a WP-CLI command with POSIX quoting for GridPane crontabs.
	 *
	 * @param string $subcommand Plugin subcommand.
	 * @return string
	 */
	private function wp_cli_command_posix( $subcommand ) {
		return 'wp --path=' . $this->posix_shell_arg( untrailingslashit( ABSPATH ) ) . ' alynt-drime-backups ' . $subcommand;
	}

	/**
	 * Quotes a string for a POSIX shell command.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function posix_shell_arg( $value ) {
		return "'" . str_replace( "'", "'\\''", (string) $value ) . "'";
	}

	/**
	 * Renders behavior settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private function render_behavior_settings( array $settings ) {
		?>
		<h2><?php esc_html_e( 'Behavior', 'alynt-drime-backups-uploader' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php $this->render_upload_behavior_settings( $settings ); ?>
			<?php $this->render_failure_email_settings( $settings ); ?>
			<?php $this->render_diagnostics_settings( $settings ); ?>
		</table>
		<?php
	}

	/**
	 * Renders upload behavior settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private function render_upload_behavior_settings( array $settings ) {
		$min_chunk_size_mb     = Alynt_Drime_Backups_Uploader_Settings::MIN_MULTIPART_CHUNK_SIZE_MB;
		$max_chunk_size_mb     = Alynt_Drime_Backups_Uploader_Settings::MAX_MULTIPART_CHUNK_SIZE_MB;
		$default_chunk_size_mb = Alynt_Drime_Backups_Uploader_Settings::DEFAULT_MULTIPART_CHUNK_SIZE_MB;

		?>
		<tr>
			<th scope="row"><label for="alynt-duplicate-mode"><?php esc_html_e( 'Duplicate Handling', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<select id="alynt-duplicate-mode" name="alynt_drime_backups_settings[duplicate_mode]" aria-describedby="alynt-duplicate-mode-description">
					<option value="skip" <?php selected( $settings['duplicate_mode'], 'skip' ); ?>><?php esc_html_e( 'Skip existing files', 'alynt-drime-backups-uploader' ); ?></option>
					<option value="rename" <?php selected( $settings['duplicate_mode'], 'rename' ); ?>><?php esc_html_e( 'Rename new uploads', 'alynt-drime-backups-uploader' ); ?></option>
				</select>
				<p id="alynt-duplicate-mode-description" class="description"><?php esc_html_e( 'Choose whether existing Drime filenames are skipped or renamed during upload.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-auto-scan"><?php esc_html_e( 'Automatic Scanning', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td><label><input id="alynt-auto-scan" name="alynt_drime_backups_settings[auto_scan_enabled]" type="checkbox" value="1" <?php checked( ! empty( $settings['auto_scan_enabled'] ) ); ?>> <?php esc_html_e( 'Scan with WP-Cron every 15 minutes.', 'alynt-drime-backups-uploader' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-server-cron-expected"><?php esc_html_e( 'Server Cron Expected', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<label><input id="alynt-server-cron-expected" name="alynt_drime_backups_settings[server_cron_expected]" type="checkbox" value="1" <?php checked( ! empty( $settings['server_cron_expected'] ) ); ?> aria-describedby="alynt-server-cron-expected-description"> <?php esc_html_e( 'Remind me if scheduled scans have not been observed from WP-CLI.', 'alynt-drime-backups-uploader' ); ?></label>
				<p id="alynt-server-cron-expected-description" class="description"><?php esc_html_e( 'Use this when the site should be driven by a server cron job instead of visitor traffic. The plugin records runtime evidence; it does not read server cron files.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-min-file-age"><?php esc_html_e( 'Minimum File Age', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<input id="alynt-min-file-age" name="alynt_drime_backups_settings[min_file_age_seconds]" type="number" min="60" step="60" value="<?php echo esc_attr( (string) $settings['min_file_age_seconds'] ); ?>" aria-describedby="alynt-min-file-age-description">
				<p id="alynt-min-file-age-description" class="description"><?php esc_html_e( 'Enter the minimum age in seconds. Files must also keep the same size across scans before they are queued.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-multipart-chunk-size"><?php esc_html_e( 'Multipart Chunk Size', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<input id="alynt-multipart-chunk-size" name="alynt_drime_backups_settings[multipart_chunk_size_mb]" type="number" min="<?php echo esc_attr( (string) $min_chunk_size_mb ); ?>" max="<?php echo esc_attr( (string) $max_chunk_size_mb ); ?>" step="1" value="<?php echo esc_attr( (string) $settings['multipart_chunk_size_mb'] ); ?>" aria-describedby="alynt-multipart-chunk-size-description">
				<p id="alynt-multipart-chunk-size-description" class="description">
					<?php
					printf(
						/* translators: %d: recommended multipart chunk size in MB. */
						esc_html__( 'Set the size of each Drime multipart upload part. %d MB is recommended for large backups.', 'alynt-drime-backups-uploader' ),
						(int) $default_chunk_size_mb
					);
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-delete-local"><?php esc_html_e( 'Delete Local Files', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<label><input id="alynt-delete-local" name="alynt_drime_backups_settings[delete_local_after_upload]" type="checkbox" value="1" <?php checked( ! empty( $settings['delete_local_after_upload'] ) ); ?> aria-describedby="alynt-delete-local-description"> <?php esc_html_e( 'Delete local backup files after confirmed Drime upload.', 'alynt-drime-backups-uploader' ); ?></label>
				<p id="alynt-delete-local-description" class="description"><?php esc_html_e( 'For production, enable this only after Drime uploads and restore procedures are verified and your local retention policy allows removing WPvivid files.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-remote-retention-enabled"><?php esc_html_e( 'Remote Retention', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<label><input id="alynt-remote-retention-enabled" name="alynt_drime_backups_settings[remote_retention_enabled]" type="checkbox" value="1" <?php checked( ! empty( $settings['remote_retention_enabled'] ) ); ?> aria-describedby="alynt-remote-retention-description"> <?php esc_html_e( 'Allow manual cleanup of old Drime files uploaded by this plugin.', 'alynt-drime-backups-uploader' ); ?></label>
				<p id="alynt-remote-retention-description" class="description"><?php esc_html_e( 'Cleanup moves eligible Drime files to trash only. It does not permanently delete remote files or delete local backup files.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-remote-retention-days"><?php esc_html_e( 'Remote Retention Age', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<input id="alynt-remote-retention-days" name="alynt_drime_backups_settings[remote_retention_days]" type="number" min="<?php echo esc_attr( (string) Alynt_Drime_Backups_Uploader_Settings::MIN_REMOTE_RETENTION_DAYS ); ?>" max="<?php echo esc_attr( (string) Alynt_Drime_Backups_Uploader_Settings::MAX_REMOTE_RETENTION_DAYS ); ?>" step="1" value="<?php echo esc_attr( (string) $settings['remote_retention_days'] ); ?>" aria-describedby="alynt-remote-retention-days-description">
				<p id="alynt-remote-retention-days-description" class="description"><?php esc_html_e( 'Uploaded registry records older than this many days become eligible for manual Drime trash cleanup.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-max-retries"><?php esc_html_e( 'Maximum Retries', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<input id="alynt-max-retries" name="alynt_drime_backups_settings[max_retries]" type="number" min="0" max="10" value="<?php echo esc_attr( (string) $settings['max_retries'] ); ?>" aria-describedby="alynt-max-retries-description">
				<p id="alynt-max-retries-description" class="description"><?php esc_html_e( 'Set the number of failed upload attempts before a queued file is removed.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders failure email notification settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private function render_failure_email_settings( array $settings ) {
		?>
		<tr>
			<th scope="row"><label for="alynt-failure-email-enabled"><?php esc_html_e( 'Failure Emails', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<label><input id="alynt-failure-email-enabled" name="alynt_drime_backups_settings[failure_email_enabled]" type="checkbox" value="1" <?php checked( ! empty( $settings['failure_email_enabled'] ) ); ?> aria-describedby="alynt-failure-email-description"> <?php esc_html_e( 'Email administrators when an upload reaches a final failure state.', 'alynt-drime-backups-uploader' ); ?></label>
				<p id="alynt-failure-email-description" class="description"><?php esc_html_e( 'Emails are plain text and use the site WordPress mail stack.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-failure-email-recipients"><?php esc_html_e( 'Failure Email Recipients', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<textarea id="alynt-failure-email-recipients" name="alynt_drime_backups_settings[failure_email_recipients]" class="large-text code" rows="3" aria-describedby="alynt-failure-email-recipients-description"><?php echo esc_textarea( (string) $settings['failure_email_recipients'] ); ?></textarea>
				<p id="alynt-failure-email-recipients-description" class="description"><?php esc_html_e( 'Enter one email per line or separate multiple addresses with commas.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders diagnostics settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private function render_diagnostics_settings( array $settings ) {
		$level_labels = array(
			'debug'    => __( 'Debug', 'alynt-drime-backups-uploader' ),
			'info'     => __( 'Info', 'alynt-drime-backups-uploader' ),
			'warning'  => __( 'Warning', 'alynt-drime-backups-uploader' ),
			'error'    => __( 'Error', 'alynt-drime-backups-uploader' ),
			'critical' => __( 'Critical', 'alynt-drime-backups-uploader' ),
		);

		?>
		<tr>
			<th scope="row"><label for="alynt-diagnostics-enabled"><?php esc_html_e( 'Diagnostics', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<label><input id="alynt-diagnostics-enabled" name="alynt_drime_backups_settings[diagnostics_enabled]" type="checkbox" value="1" <?php checked( ! empty( $settings['diagnostics_enabled'] ) ); ?> aria-describedby="alynt-diagnostics-enabled-description"> <?php esc_html_e( 'Store redacted diagnostic events.', 'alynt-drime-backups-uploader' ); ?></label>
				<p id="alynt-diagnostics-enabled-description" class="description"><?php esc_html_e( 'Events are stored locally and exclude tokens, request bodies, and signed URLs.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-diagnostics-min-level"><?php esc_html_e( 'Diagnostics Level', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<select id="alynt-diagnostics-min-level" name="alynt_drime_backups_settings[diagnostics_min_level]" aria-describedby="alynt-diagnostics-min-level-description">
					<?php foreach ( array_keys( Alynt_Drime_Backups_Uploader_Settings::severity_levels() ) as $level ) : ?>
						<option value="<?php echo esc_attr( $level ); ?>" <?php selected( $settings['diagnostics_min_level'], $level ); ?>><?php echo esc_html( isset( $level_labels[ $level ] ) ? $level_labels[ $level ] : $level ); ?></option>
					<?php endforeach; ?>
				</select>
				<p id="alynt-diagnostics-min-level-description" class="description"><?php esc_html_e( 'Only events at this severity or higher are stored.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-diagnostics-retention"><?php esc_html_e( 'Diagnostics Retention', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<input id="alynt-diagnostics-retention" name="alynt_drime_backups_settings[diagnostics_retention]" type="number" min="25" max="500" step="25" value="<?php echo esc_attr( (string) $settings['diagnostics_retention'] ); ?>" aria-describedby="alynt-diagnostics-retention-description">
				<p id="alynt-diagnostics-retention-description" class="description"><?php esc_html_e( 'Maximum local diagnostic events to retain.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<?php
	}
}
