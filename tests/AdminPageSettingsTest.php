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
		$this->assertStringContainsString( "alynt-drime-backups run --max-uploads=1", $snippet );
		$this->assertStringContainsString( "alynt-drime-backups status --format=json", $snippet );
		$this->assertStringNotContainsString( 'secret-token', $snippet );
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
		$this->assertSame( 'https://example.test', $data['site_url'] );
		$this->assertSame( '/var/www/example.com/private/alynt-drime-backups/outbox', $data['outbox_path'] );
		$this->assertSame( '/var/www/example.com/private/alynt-drime-backups/work', $data['work_path'] );
		$this->assertSame( '/var/www/example.com/private/alynt-drime-backups', $this->call_private( $page, 'runner_base_path', array( array( 'server_outbox_path' => '/var/www/example.com/private/alynt-drime-backups/outbox' ) ) ) );
		$this->assertSame( 1073741824, $data['minimum_free_space_bytes'] );
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
		$this->assertStringContainsString( "chmod 750 '/var/www/example.com/private/alynt-drime-backups/runner/alynt-backup-runner.php'", $commands );
		$this->assertStringContainsString( "chmod 640 '/var/www/example.com/private/alynt-drime-backups/runner/config.json' # after saving the generated config.json", $commands );
		$this->assertStringContainsString( "php '/var/www/example.com/private/alynt-drime-backups/runner/alynt-backup-runner.php' health --config='/var/www/example.com/private/alynt-drime-backups/runner/config.json'", $commands );
		$this->assertStringNotContainsString( 'crontab', $commands );
		$this->assertStringNotContainsString( ' run --config=', $commands );
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
