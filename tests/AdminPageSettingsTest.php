<?php
/**
 * Admin page settings rendering tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use PHPUnit\Framework\TestCase;

class AdminPageSettingsTest extends TestCase {
	public function test_gridpane_cron_snippet_uses_configured_server_outbox_base() {
		$page    = $this->admin_page();
		$snippet = $this->call_private(
			$page,
			'gridpane_cron_snippet',
			array(
				array(
					'server_outbox_path' => '/var/www/example.com/private/alynt-drime-backups/outbox',
					'api_token'          => 'secret-token',
				),
			)
		);

		$this->assertStringContainsString( "17 2 * * * php '/var/www/example.com/private/alynt-drime-backups/runner/alynt-backup-runner.php' run --config='/var/www/example.com/private/alynt-drime-backups/runner/config.json'", $snippet );
		$this->assertStringContainsString( "*/15 * * * * wp --path='", $snippet );
		$this->assertStringContainsString( 'cron event run alynt_drime_backups_scan_event alynt_drime_backups_upload_event', $snippet );
		$this->assertStringContainsString( "alynt-drime-backups status --format=json", $snippet );
		$this->assertStringNotContainsString( 'secret-token', $snippet );
	}

	public function test_server_cron_review_commands_build_review_file_without_auto_install() {
		$page     = $this->admin_page();
		$commands = $this->call_private(
			$page,
			'server_cron_review_commands',
			array(
				array(
					'server_outbox_path' => '/var/www/example.com/private/alynt-drime-backups/outbox',
					'api_token'          => 'secret-token',
				),
			)
		);

		$this->assertStringContainsString( 'crontab -l > "$HOME/alynt-drime-backups-crontab.current" 2>/dev/null || true', $commands );
		$this->assertStringContainsString( "printf '%s\\n'", $commands );
		$this->assertStringNotContainsString( 'ALYNT_DRIME_CRON', $commands );
		$this->assertStringContainsString( "17 2 * * * php '\\''/var/www/example.com/private/alynt-drime-backups/runner/alynt-backup-runner.php'\\'' run --config='\\''/var/www/example.com/private/alynt-drime-backups/runner/config.json'\\'''", $commands );
		$this->assertStringContainsString( 'cron event run alynt_drime_backups_scan_event alynt_drime_backups_upload_event', $commands );
		$this->assertStringContainsString( 'diff -u "$HOME/alynt-drime-backups-crontab.current" "$HOME/alynt-drime-backups-crontab.new" || true', $commands );
		$this->assertStringContainsString( '# crontab "$HOME/alynt-drime-backups-crontab.new"', $commands );
		$this->assertStringNotContainsString( "\ncrontab \"\$HOME/alynt-drime-backups-crontab.new\"", $commands );
		$this->assertStringNotContainsString( 'secret-token', $commands );
	}

	public function test_server_runner_config_json_uses_configured_server_outbox_base() {
		$page = $this->admin_page();
		$json = $this->call_private(
			$page,
			'server_runner_config_json',
			array(
				array(
					'server_outbox_path' => '/var/www/example.com/private/alynt-drime-backups/outbox',
					'api_token'          => 'secret-token',
				),
			)
		);
		$data = json_decode( $json, true );

		$this->assertSame( 'example.test', $data['site_id'] );
		$this->assertSame( '', $data['site_uuid'] );
		$this->assertSame( 'https://example.test', $data['site_url'] );
		$this->assertSame( '/var/www/example.com/private/alynt-drime-backups/outbox', $data['outbox_path'] );
		$this->assertSame( '/var/www/example.com/private/alynt-drime-backups/work', $data['work_path'] );
		$this->assertSame( '/var/www/example.com/private/alynt-drime-backups', $this->call_private( $page, 'runner_base_path', array( array( 'server_outbox_path' => '/var/www/example.com/private/alynt-drime-backups/outbox' ) ) ) );
		$this->assertSame( 1073741824, $data['minimum_free_space_bytes'] );
		$this->assertSame( 21600, $data['drime_download_timeout_seconds'] );
		$this->assertSame( 'light', $data['consistency_mode'] );
		$this->assertSame( 'example-test', $data['package_prefix'] );
		$this->assertSame( 'wp', $data['wp_cli_path'] );
		$this->assertTrue( $data['database']['enabled'] );
		$this->assertContains( 'wp-content/cache', $data['exclude_paths'] );
		$this->assertStringNotContainsString( 'secret-token', $json );
	}

	public function test_server_runner_health_command_uses_runner_config_path() {
		$page    = $this->admin_page();
		$command = $this->call_private(
			$page,
			'server_runner_command',
			array(
				'health',
				array(
					'server_outbox_path' => '/var/www/example.com/private/alynt-drime-backups/outbox',
				),
			)
		);

		$this->assertSame( "php '/var/www/example.com/private/alynt-drime-backups/runner/alynt-backup-runner.php' health --config='/var/www/example.com/private/alynt-drime-backups/runner/config.json'", $command );
	}

	public function test_server_runner_install_commands_install_runner_without_cron_or_backup_run() {
		$page     = $this->admin_page();
		$commands = $this->call_private(
			$page,
			'server_runner_install_commands',
			array(
				array(
					'server_outbox_path' => '/var/www/example.com/private/alynt-drime-backups/outbox',
				),
			)
		);

		$this->assertStringContainsString( "mkdir -p '/var/www/example.com/private/alynt-drime-backups/runner'", $commands );
		$this->assertStringContainsString( "cp '" . untrailingslashit( ALYNT_DRIME_BACKUPS_UPLOADER_PATH ) . "/server-runner/alynt-backup-runner.php' '/var/www/example.com/private/alynt-drime-backups/runner/alynt-backup-runner.php'", $commands );
		$this->assertStringContainsString( "printf '%s' '{\"site_id\":\"example.test\"", $commands );
		$this->assertStringContainsString( "chmod 750 '/var/www/example.com/private/alynt-drime-backups/runner/alynt-backup-runner.php'", $commands );
		$this->assertStringContainsString( "chmod 640 '/var/www/example.com/private/alynt-drime-backups/runner/config.json'", $commands );
		$this->assertStringContainsString( "php '/var/www/example.com/private/alynt-drime-backups/runner/alynt-backup-runner.php' health --config='/var/www/example.com/private/alynt-drime-backups/runner/config.json'", $commands );
		$this->assertStringNotContainsString( "\n", $commands );
		$this->assertStringNotContainsString( 'crontab', $commands );
		$this->assertStringNotContainsString( ' run --config=', $commands );
	}

	public function test_server_runner_test_command_is_single_line_and_verifies_created_package() {
		$page    = $this->admin_page();
		$command = $this->call_private(
			$page,
			'server_runner_test_command',
			array(
				array(
					'server_outbox_path' => '/var/www/example.com/private/alynt-drime-backups/outbox',
					'api_token'          => 'secret-token',
				),
			)
		);

		$this->assertStringContainsString( 'PACKAGE=$(', $command );
		$this->assertStringContainsString( "run --config='/var/www/example.com/private/alynt-drime-backups/runner/config.json'", $command );
		$this->assertStringContainsString( 'tee /dev/stderr', $command );
		$this->assertStringContainsString( "awk '/^Created package:/ {print $3}'", $command );
		$this->assertStringContainsString( 'verify --config=', $command );
		$this->assertStringContainsString( '--package="$PACKAGE"', $command );
		$this->assertStringContainsString( 'Could not detect created package path from runner output.', $command );
		$this->assertStringNotContainsString( "\n", $command );
		$this->assertStringNotContainsString( 'secret-token', $command );
	}

	public function test_wp_cli_scheduled_upload_command_is_single_line_and_runs_cron_hooks() {
		$page    = $this->admin_page();
		$command = $this->call_private( $page, 'wp_cli_scheduled_upload_command', array() );

		$this->assertStringContainsString( 'cron event run alynt_drime_backups_scan_event alynt_drime_backups_upload_event', $command );
		$this->assertStringNotContainsString( 'alynt-drime-backups run --max-uploads=1', $command );
		$this->assertStringNotContainsString( "\n", $command );
	}

	public function test_wp_cli_scan_upload_command_is_single_line_and_does_not_require_scheduled_events() {
		$page    = $this->admin_page();
		$command = $this->call_private( $page, 'wp_cli_scan_upload_command', array() );

		$this->assertStringContainsString( 'alynt-drime-backups run --max-uploads=1', $command );
		$this->assertStringNotContainsString( 'cron event run', $command );
		$this->assertStringNotContainsString( "\n", $command );
	}

	public function test_gridpane_cron_snippet_falls_back_to_gridpane_private_path() {
		$page    = $this->admin_page();
		$snippet = $this->call_private(
			$page,
			'gridpane_cron_snippet',
			array(
				array(
					'server_outbox_path' => '',
				),
			)
		);

		$this->assertStringContainsString( 'private/alynt-drime-backups/runner/alynt-backup-runner.php', $snippet );
		$this->assertStringContainsString( 'private/alynt-drime-backups/runner/config.json', $snippet );
	}

	public function test_posix_shell_arg_escapes_single_quotes() {
		$page   = $this->admin_page();
		$quoted = $this->call_private( $page, 'posix_shell_arg', array( "/tmp/site's path" ) );

		$this->assertSame( "'/tmp/site'\\''s path'", $quoted );
	}

	/**
	 * Creates an admin page with a mocked plugin.
	 *
	 * @return Alynt_Drime_Backups_Uploader_Admin_Page
	 */
	private function admin_page() {
		return new Alynt_Drime_Backups_Uploader_Admin_Page( $this->createMock( Alynt_Drime_Backups_Uploader_Plugin::class ) );
	}

	/**
	 * Calls a private method.
	 *
	 * @param object            $object Object.
	 * @param string            $method Method.
	 * @param array<int,mixed>  $args Args.
	 * @return mixed
	 */
	private function call_private( $object, $method, array $args ) {
		$reflection = new ReflectionMethod( $object, $method );

		return $reflection->invokeArgs( $object, $args );
	}
}
