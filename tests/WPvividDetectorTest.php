<?php
/**
 * WPvivid detector tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class WPvividDetectorTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_detects_content_relative_wpvivid_path() {
		Functions\expect( 'get_option' )
			->once()
			->with( 'wpvivid_common_setting', array() )
			->andReturn( array() );

		Functions\expect( 'get_option' )
			->once()
			->with( 'wpvivid_local_setting', array() )
			->andReturn(
				array(
					'path'       => 'custombackups',
					'save_local' => 1,
				)
			);

		$detector = new Alynt_Drime_Backups_Uploader_WPvivid_Detector();

		$this->assertSame( wp_normalize_path( WP_CONTENT_DIR . '/custombackups' ), $detector->get_backup_dir( array() ) );
	}

	public function test_detects_pro_outside_folder_path() {
		Functions\expect( 'get_option' )
			->once()
			->with( 'wpvivid_common_setting', array() )
			->andReturn( array( 'local_backup_folder' => 'outside_folder' ) );

		Functions\expect( 'get_option' )
			->once()
			->with( 'wpvivid_local_setting', array() )
			->andReturn( array( 'outside_path' => 'C:\\Backups\\wpvividbackups\\' ) );

		$detector = new Alynt_Drime_Backups_Uploader_WPvivid_Detector();

		$this->assertSame( 'C:/Backups/wpvividbackups', $detector->get_backup_dir( array() ) );
	}
}
