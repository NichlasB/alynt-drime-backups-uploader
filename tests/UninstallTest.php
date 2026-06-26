<?php
/**
 * Uninstall cleanup tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase {
	public function test_uninstall_removes_generic_outbox_snapshot_option() {
		$uninstall = file_get_contents( ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/uninstall.php' );

		$this->assertIsString( $uninstall );
		$this->assertStringContainsString( "'alynt_drime_backups_outbox_file_snapshots'", $uninstall );
	}
}
