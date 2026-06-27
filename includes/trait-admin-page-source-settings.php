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
				<th scope="row"><label for="alynt-server-cron-review-commands"><?php esc_html_e( 'Server Cron Review Commands', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<textarea id="alynt-server-cron-review-commands" class="large-text code alynt-drime-command-snippet" readonly rows="14" aria-describedby="alynt-server-cron-review-commands-description"><?php echo esc_textarea( $this->server_cron_review_commands( $settings ) ); ?></textarea>
					<p id="alynt-server-cron-review-commands-description" class="description"><?php esc_html_e( 'Run these as the site user to build and review a proposed crontab file. The final install command stays commented until you approve it.', 'alynt-drime-backups-uploader' ); ?></p>
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
	 * Builds conservative server cron review commands.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private function server_cron_review_commands( array $settings ) {
		$current = '$HOME/alynt-drime-backups-crontab.current';
		$next    = '$HOME/alynt-drime-backups-crontab.new';

		return implode(
			"\n",
			array(
				'crontab -l > "' . $current . '" 2>/dev/null || true',
				'cp "' . $current . '" "' . $next . '"',
				'cat >> "' . $next . '" <<\'ALYNT_DRIME_CRON\'',
				$this->gridpane_cron_snippet( $settings ),
				'ALYNT_DRIME_CRON',
				'diff -u "' . $current . '" "' . $next . '" || true',
				'# After reviewing the diff, install with:',
				'# crontab "' . $next . '"',
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
			'consistency_mode'         => 'light',
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
}
