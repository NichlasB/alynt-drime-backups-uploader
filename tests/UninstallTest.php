<?php
/**
 * Uninstall cleanup tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_uninstall_executes_complete_single_site_cleanup() {
		$deleted = array();
		$cleared = array();

		Functions\expect( 'is_multisite' )->once()->andReturn( false );
		Functions\when( 'delete_option' )->alias(
			function ( $option ) use ( &$deleted ) {
				$deleted[] = $option;
			}
		);
		Functions\when( 'wp_clear_scheduled_hook' )->alias(
			function ( $hook ) use ( &$cleared ) {
				$cleared[] = $hook;
			}
		);

		include ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/uninstall.php';

		$this->assertSame( $this->plugin_options(), $deleted );
		$this->assertSame( $this->plugin_cron_hooks(), $cleared );
	}

	public function test_uninstall_executes_cleanup_for_every_multisite_blog() {
		$current_blog = 0;
		$deleted      = array();
		$cleared      = array();
		$switched     = array();
		$restored     = 0;

		Functions\expect( 'is_multisite' )->once()->andReturn( true );
		Functions\expect( 'get_sites' )->once()->with( array( 'fields' => 'ids' ) )->andReturn( array( 11, 22 ) );
		Functions\when( 'switch_to_blog' )->alias(
			function ( $site_id ) use ( &$current_blog, &$switched ) {
				$current_blog = (int) $site_id;
				$switched[]   = $current_blog;
			}
		);
		Functions\when( 'restore_current_blog' )->alias(
			function () use ( &$current_blog, &$restored ) {
				$current_blog = 0;
				++$restored;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( $option ) use ( &$current_blog, &$deleted ) {
				$deleted[ $current_blog ][] = $option;
			}
		);
		Functions\when( 'wp_clear_scheduled_hook' )->alias(
			function ( $hook ) use ( &$current_blog, &$cleared ) {
				$cleared[ $current_blog ][] = $hook;
			}
		);

		include ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/uninstall.php';

		$this->assertSame( array( 11, 22 ), $switched );
		$this->assertSame( 2, $restored );
		$this->assertSame( $this->plugin_options(), $deleted[11] );
		$this->assertSame( $this->plugin_options(), $deleted[22] );
		$this->assertSame( $this->plugin_cron_hooks(), $cleared[11] );
		$this->assertSame( $this->plugin_cron_hooks(), $cleared[22] );
	}

	public function test_uninstall_guards_direct_access() {
		$uninstall = file_get_contents( ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/uninstall.php' );

		$this->assertIsString( $uninstall );
		$this->assertStringContainsString( "defined( 'WP_UNINSTALL_PLUGIN' )", $uninstall );
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

	/**
	 * Returns plugin cron hooks that uninstall must clear.
	 *
	 * @return array<int,string>
	 */
	private function plugin_cron_hooks() {
		return array(
			'alynt_drime_backups_scan_event',
			'alynt_drime_backups_upload_event',
		);
	}
}
