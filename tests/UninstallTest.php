<?php
/**
 * Uninstall cleanup tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase {
	public function test_uninstall_removes_all_plugin_owned_options() {
		$uninstall = file_get_contents( ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/uninstall.php' );

		$this->assertIsString( $uninstall );

		foreach ( $this->plugin_options() as $option ) {
			$this->assertStringContainsString( "'{$option}'", $uninstall );
			$this->assertStringContainsString( 'delete_option( $alynt_drime_backups_option )', $uninstall );
		}
	}

	public function test_uninstall_clears_plugin_cron_hooks() {
		$uninstall = file_get_contents( ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/uninstall.php' );

		$this->assertIsString( $uninstall );
		$this->assertStringContainsString( "'alynt_drime_backups_scan_event'", $uninstall );
		$this->assertStringContainsString( "'alynt_drime_backups_upload_event'", $uninstall );
		$this->assertStringContainsString( 'wp_clear_scheduled_hook( $alynt_drime_backups_cron_hook )', $uninstall );
	}

	public function test_uninstall_guards_direct_access_and_supports_multisite_cleanup() {
		$uninstall = file_get_contents( ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/uninstall.php' );

		$this->assertIsString( $uninstall );
		$this->assertStringContainsString( "defined( 'WP_UNINSTALL_PLUGIN' )", $uninstall );
		$this->assertStringContainsString( 'is_multisite()', $uninstall );
		$this->assertStringContainsString( "get_sites( array( 'fields' => 'ids' ) )", $uninstall );
		$this->assertStringContainsString( 'switch_to_blog( (int) $alynt_drime_backups_site_id )', $uninstall );
		$this->assertStringContainsString( 'restore_current_blog()', $uninstall );
	}

	public function test_uninstall_does_not_delete_local_backup_or_runner_files() {
		$uninstall = file_get_contents( ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/uninstall.php' );

		$this->assertIsString( $uninstall );
		$this->assertStringNotContainsString( 'wp_upload_dir', $uninstall );
		$this->assertStringNotContainsString( 'unlink(', $uninstall );
		$this->assertStringNotContainsString( 'rmdir(', $uninstall );
		$this->assertStringNotContainsString( 'scandir(', $uninstall );
	}

	/**
	 * Returns the plugin-owned options that uninstall.php must remove.
	 *
	 * @return array<int,string>
	 */
	private function plugin_options() {
		return array(
			'alynt_drime_backups_settings',
			'alynt_drime_backups_uploaded_files',
			'alynt_drime_backups_failed_uploads',
			'alynt_drime_backups_drime_locations',
			'alynt_drime_backups_failure_notifications',
			'alynt_drime_backups_cron_health',
			'alynt_drime_backups_upload_queue',
			'alynt_drime_backups_active_upload',
			'alynt_drime_backups_upload_lock',
			'alynt_drime_backups_logs',
			'alynt_drime_backups_file_snapshots',
			'alynt_drime_backups_outbox_file_snapshots',
		);
	}
}
