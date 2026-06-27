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

	/**
	 * Restore staging notes must keep production restore boundaries explicit.
	 *
	 * @return void
	 */
	public function test_restore_notes_keep_destructive_restore_manual() {
		$source = $this->runner_source();

		$this->assertStringContainsString( 'No database import was performed.', $source );
		$this->assertStringContainsString( 'No live WordPress files were overwritten.', $source );
		$this->assertStringContainsString( 'Keep production restore steps manual until separately approved.', $source );
		$this->assertStringContainsString( 'Review database.sql before any database import.', $source );
	}

	/**
	 * Restore dry run must remain a read-only preflight.
	 *
	 * @return void
	 */
	public function test_restore_dry_run_is_read_only_preflight() {
		$source = $this->runner_source();

		$this->assertStringContainsString( "case 'restore-dry-run'", $source );
		$this->assertStringContainsString( 'restore_dry_run_command', $source );
		$this->assertStringContainsString( "'destructive_actions_performed'   => false", $source );
		$this->assertStringContainsString( "'database_imported'               => false", $source );
		$this->assertStringContainsString( "'live_files_overwritten'          => false", $source );
		$this->assertStringContainsString( "'restore_apply_command_available' => false", $source );
		$this->assertStringNotContainsString( "case 'restore-apply'", $source );
		$this->assertSame( 1, preg_match( '/private function restore_dry_run_command\(.*?private function print_verify_next_steps/s', $source, $matches ) );

		$method_source = $matches[0];
		$this->assertStringNotContainsString( 'run_shell_command', $method_source );
		$this->assertStringNotContainsString( 'write_json', $method_source );
		$this->assertStringNotContainsString( 'write_file', $method_source );
		$this->assertStringNotContainsString( 'mkdir(', $method_source );
		$this->assertStringNotContainsString( 'rename(', $method_source );
		$this->assertStringNotContainsString( 'unlink(', $method_source );
		$this->assertStringNotContainsString( 'rmdir(', $method_source );
	}

	/**
	 * Inventory output should stay local, read-only, and sidecar-focused.
	 *
	 * @return void
	 */
	public function test_list_json_outputs_read_only_inventory_fields() {
		$source = $this->runner_source();

		$this->assertStringContainsString( 'package_inventory_record', $source );
		$this->assertStringContainsString( "'verification_ready'", $source );
		$this->assertStringContainsString( 'read_package_manifest_quiet', $source );
		$this->assertStringContainsString( 'read_checksum_sidecar', $source );
		$this->assertStringNotContainsString( "'archive_path'", $source );
	}

	/**
	 * Cleanup preview must stay read-only and operator-reviewed.
	 *
	 * @return void
	 */
	public function test_cleanup_preview_does_not_perform_destructive_actions() {
		$source = $this->runner_source();

		$this->assertStringContainsString( 'cleanup_preview_command', $source );
		$this->assertStringContainsString( "'destructive_actions_performed' => false", $source );
		$this->assertStringContainsString( "'suggested_action'", $source );
		$this->assertStringContainsString( "'operator_review'", $source );
		$this->assertSame( 1, preg_match( '/private function cleanup_preview_command\(.*?private function cleanup_command/s', $source, $matches ) );

		$method_source = $matches[0];
		$this->assertStringNotContainsString( 'unlink(', $method_source );
		$this->assertStringNotContainsString( 'rmdir(', $method_source );
		$this->assertStringNotContainsString( 'remove_directory', $method_source );
		$this->assertStringNotContainsString( 'write_file', $method_source );
	}

	/**
	 * Local cleanup execution must require an explicit operator confirmation phrase.
	 *
	 * @return void
	 */
	public function test_cleanup_execution_requires_confirmation_phrase() {
		$source = $this->runner_source();

		$this->assertStringContainsString( "case 'cleanup'", $source );
		$this->assertStringContainsString( 'cleanup_command', $source );
		$this->assertStringContainsString( "'delete-local-artifacts' !== \$confirm", $source );
		$this->assertStringContainsString( '--confirm=delete-local-artifacts', $source );
		$this->assertStringContainsString( "'destructive_actions_performed' => true", $source );
	}
}
