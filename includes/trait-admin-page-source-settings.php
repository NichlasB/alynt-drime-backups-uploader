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
		$upload_command = $this->wp_cli_scheduled_upload_command();
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
		$lines   = explode( "\n", $this->gridpane_cron_snippet( $settings ) );
		$printf  = 'printf ' . $this->posix_shell_arg( '%s\n' );
		foreach ( $lines as $line ) {
			$printf .= ' ' . $this->posix_shell_arg( $line );
		}
		$printf .= ' >> "' . $next . '"';

		return implode(
			"\n",
			array(
				'crontab -l > "' . $current . '" 2>/dev/null || true',
				'cp "' . $current . '" "' . $next . '"',
				$printf,
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
	 * @param bool                $pretty   Whether to pretty-print JSON.
	 * @return string
	 */
	private function server_runner_config_json( array $settings, $pretty = true ) {
		$runner_base    = $this->runner_base_path( $settings );
		$wordpress_path = untrailingslashit( ABSPATH );
		$site_url       = untrailingslashit( $this->site_url_for_runner() );
		$site_host      = $this->site_host_for_runner( $site_url );
		$config         = array(
			'site_id'                  => $site_host,
			'site_uuid'                => isset( $settings['site_uuid'] ) ? (string) $settings['site_uuid'] : '',
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

		$options = JSON_UNESCAPED_SLASHES;
		if ( $pretty ) {
			$options |= JSON_PRETTY_PRINT;
		}

		if ( function_exists( 'wp_json_encode' ) ) {
			return wp_json_encode( $config, $options );
		}

		return json_encode( $config, $options );
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
		$config_json    = $this->server_runner_config_json( $settings, false );

		return implode(
			' && ',
			array(
				'mkdir -p ' . $this->posix_shell_arg( $runner_dir ) . ' ' . $this->posix_shell_arg( $outbox_path ) . ' ' . $this->posix_shell_arg( $work_path ) . ' ' . $this->posix_shell_arg( $restore_path ),
				'cp ' . $this->posix_shell_arg( $source_runner ) . ' ' . $this->posix_shell_arg( $target_runner ),
				"printf '%s' " . $this->posix_shell_arg( $config_json ) . ' > ' . $this->posix_shell_arg( $target_config ),
				'chmod 750 ' . $this->posix_shell_arg( $target_runner ),
				'chmod 640 ' . $this->posix_shell_arg( $target_config ),
				$health_command,
			)
		);
	}

	/**
	 * Builds a one-line first package creation and verify command.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private function server_runner_test_command( array $settings ) {
		$run_command    = $this->server_runner_command( 'run', $settings );
		$verify_command = $this->server_runner_command( 'verify', $settings );

		return 'PACKAGE=$(' . $run_command . ' | tee /dev/stderr | awk \'/^Created package:/ {print $3}\') && if test -n "$PACKAGE"; then ' . $verify_command . ' --package="$PACKAGE"; else echo \'Could not detect created package path from runner output.\' >&2; exit 1; fi';
	}

	/**
	 * Builds a direct WP-CLI scan/upload command for first setup.
	 *
	 * @return string
	 */
	private function wp_cli_scan_upload_command() {
		return $this->wp_cli_command_posix( 'run --max-uploads=1' );
	}

	/**
	 * Builds a WP-CLI command that runs scheduled scan/upload hooks.
	 *
	 * @return string
	 */
	private function wp_cli_scheduled_upload_command() {
		return 'wp --path=' . $this->posix_shell_arg( untrailingslashit( ABSPATH ) ) . ' cron event run alynt_drime_backups_scan_event alynt_drime_backups_upload_event';
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
