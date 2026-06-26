<?php
/**
 * Server runner security tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use PHPUnit\Framework\TestCase;

/**
 * Covers server runner security invariants.
 */
class ServerRunnerSecurityTest extends TestCase {
	/**
	 * Returns the server runner source.
	 *
	 * @return string
	 */
	private function runner_source() {
		return (string) file_get_contents( ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/server-runner/alynt-backup-runner.php' );
	}

	/**
	 * Drime download redirects must not automatically forward the bearer token.
	 *
	 * @return void
	 */
	public function test_download_redirects_do_not_forward_authorization_header() {
		$source = $this->runner_source();

		$this->assertStringContainsString( 'CURLOPT_FOLLOWLOCATION, false', $source );
		$this->assertStringContainsString( 'validate_download_redirect_url', $source );
		$this->assertStringContainsString( '$this->download_url_to_temp_path( $redirect_url, $temp_path, \'\' )', $source );
	}
}
