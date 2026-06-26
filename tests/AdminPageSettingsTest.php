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
