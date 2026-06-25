<?php
/**
 * WP-CLI command tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use PHPUnit\Framework\TestCase;

class CLICommandTest extends TestCase {
	public function test_status_data_reports_queue_registry_and_settings_counts() {
		$settings = $this->createMock( Alynt_Drime_Backups_Uploader_Settings::class );
		$settings->method( 'get' )->willReturn(
			array(
				'auto_scan_enabled'    => true,
				'server_cron_expected' => true,
				'server_outbox_path'   => '/var/www/example/private/backups',
				'backup_path_override' => '',
			)
		);

		$queue = $this->createMock( Alynt_Drime_Backups_Uploader_Queue::class );
		$queue->method( 'all' )->willReturn(
			array(
				'sig-one' => array( 'name' => 'one.zip' ),
				'sig-two' => array( 'name' => 'two.zip' ),
			)
		);
		$queue->method( 'get_active' )->willReturn( array( 'signature' => 'sig-one' ) );

		$registry = $this->createMock( Alynt_Drime_Backups_Uploader_Backup_Registry::class );
		$registry->method( 'get_uploaded' )->willReturn( array( 'sig-uploaded' => array() ) );
		$registry->method( 'get_failed' )->willReturn(
			array(
				'sig-failed-one' => array(),
				'sig-failed-two' => array(),
			)
		);

		$plugin = $this->createMock( Alynt_Drime_Backups_Uploader_Plugin::class );
		$plugin->method( 'settings' )->willReturn( $settings );
		$plugin->method( 'queue' )->willReturn( $queue );
		$plugin->method( 'registry' )->willReturn( $registry );

		$command = new Alynt_Drime_Backups_Uploader_CLI_Command( $plugin );
		$status  = $command->status_data();

		$this->assertSame( 2, $status['queue_count'] );
		$this->assertSame( 1, $status['uploaded_count'] );
		$this->assertSame( 2, $status['failed_count'] );
		$this->assertTrue( $status['active_upload'] );
		$this->assertTrue( $status['auto_scan_enabled'] );
		$this->assertTrue( $status['server_cron_expected'] );
		$this->assertSame( '/var/www/example/private/backups', $status['server_outbox_path'] );
	}
}
