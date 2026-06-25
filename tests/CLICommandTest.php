<?php
/**
 * WP-CLI command tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use PHPUnit\Framework\TestCase;

class CLICommandTest extends TestCase {
	public function test_status_data_reports_queue_registry_and_settings_counts() {
		$summary = $this->createMock( Alynt_Drime_Backups_Uploader_Health_Summary::class );
		$summary->expects( $this->once() )
			->method( 'status' )
			->with( false, true )
			->willReturn(
			array(
				'queue_count'          => 2,
				'uploaded_count'       => 1,
				'failed_count'         => 2,
				'active_upload'        => true,
				'auto_scan_enabled'    => true,
				'server_cron_expected' => true,
				'server_outbox_path'   => '/var/www/example/private/backups',
			)
		);

		$plugin = $this->createMock( Alynt_Drime_Backups_Uploader_Plugin::class );
		$plugin->method( 'health_summary' )->willReturn( $summary );

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
