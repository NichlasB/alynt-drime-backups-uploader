<?php
/**
 * WP-CLI command tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'WP_CLI' ) ) {
	class WP_CLI {
		public static $log = array();
		public static $success = array();
		public static $warning = array();
		public static $error = array();

		public static function reset() {
			self::$log     = array();
			self::$success = array();
			self::$warning = array();
			self::$error   = array();
		}

		public static function log( $message ) {
			self::$log[] = $message;
		}

		public static function success( $message ) {
			self::$success[] = $message;
		}

		public static function warning( $message ) {
			self::$warning[] = $message;
		}

		public static function error( $message, $exit = true ) {
			self::$error[] = array(
				'message' => $message,
				'exit'    => $exit,
			);
		}
	}
}

class CLICommandTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		WP_CLI::reset();
	}

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

	public function test_scan_records_manual_runner_and_reports_counts() {
		$cron_health = $this->createMock( Alynt_Drime_Backups_Uploader_Cron_Health::class );
		$cron_health->expects( $this->once() )->method( 'record_manual_scan' );

		$plugin = $this->createMock( Alynt_Drime_Backups_Uploader_Plugin::class );
		$plugin->method( 'cron_health' )->willReturn( $cron_health );
		$plugin->expects( $this->once() )
			->method( 'scan_and_queue' )
			->willReturn(
				array(
					'candidates' => array(
						array( 'name' => 'one.tar.gz' ),
						array( 'name' => 'two.tar.gz' ),
					),
					'queued'     => 1,
				)
			);

		$command = new Alynt_Drime_Backups_Uploader_CLI_Command( $plugin );
		$command->scan();

		$this->assertSame( array( 'Scan complete. Found: 2. Queued: 1.' ), WP_CLI::$success );
		$this->assertSame( array(), WP_CLI::$error );
	}

	public function test_scan_reports_errors_without_success() {
		$cron_health = $this->createMock( Alynt_Drime_Backups_Uploader_Cron_Health::class );
		$cron_health->expects( $this->once() )->method( 'record_manual_scan' );

		$plugin = $this->createMock( Alynt_Drime_Backups_Uploader_Plugin::class );
		$plugin->method( 'cron_health' )->willReturn( $cron_health );
		$plugin->method( 'scan_and_queue' )->willReturn( array( 'errors' => array( 'Outbox unreadable.' ) ) );

		$command = new Alynt_Drime_Backups_Uploader_CLI_Command( $plugin );
		$command->scan();

		$this->assertSame( array(), WP_CLI::$success );
		$this->assertSame( 'Scan failed: Outbox unreadable.', WP_CLI::$error[0]['message'] );
		$this->assertFalse( WP_CLI::$error[0]['exit'] );
	}

	public function test_upload_next_reports_success() {
		$uploader = $this->createMock( Alynt_Drime_Backups_Uploader_Uploader::class );
		$uploader->expects( $this->once() )->method( 'upload_next' )->willReturn( true );

		$plugin = $this->createMock( Alynt_Drime_Backups_Uploader_Plugin::class );
		$plugin->method( 'uploader' )->willReturn( $uploader );

		$command = new Alynt_Drime_Backups_Uploader_CLI_Command( $plugin );
		$command->upload_next();

		$this->assertSame( array( 'Upload complete.' ), WP_CLI::$success );
		$this->assertSame( array(), WP_CLI::$warning );
		$this->assertSame( array(), WP_CLI::$error );
	}

	public function test_upload_next_warns_when_queue_is_empty() {
		$uploader = $this->createMock( Alynt_Drime_Backups_Uploader_Uploader::class );
		$uploader->expects( $this->once() )
			->method( 'upload_next' )
			->willReturn( new WP_Error( 'alynt_drime_queue_empty', 'No queued backups.' ) );

		$plugin = $this->createMock( Alynt_Drime_Backups_Uploader_Plugin::class );
		$plugin->method( 'uploader' )->willReturn( $uploader );

		$command = new Alynt_Drime_Backups_Uploader_CLI_Command( $plugin );
		$command->upload_next();

		$this->assertSame( array( 'No queued backups.' ), WP_CLI::$warning );
		$this->assertSame( array(), WP_CLI::$success );
		$this->assertSame( array(), WP_CLI::$error );
	}

	public function test_run_uploads_until_max_or_empty_queue() {
		$cron_health = $this->createMock( Alynt_Drime_Backups_Uploader_Cron_Health::class );
		$cron_health->expects( $this->once() )->method( 'record_manual_scan' );

		$uploader = $this->createMock( Alynt_Drime_Backups_Uploader_Uploader::class );
		$uploader->expects( $this->exactly( 3 ) )
			->method( 'upload_next' )
			->willReturnOnConsecutiveCalls(
				true,
				true,
				new WP_Error( 'alynt_drime_queue_empty', 'No queued backups.' )
			);

		$plugin = $this->createMock( Alynt_Drime_Backups_Uploader_Plugin::class );
		$plugin->method( 'cron_health' )->willReturn( $cron_health );
		$plugin->method( 'uploader' )->willReturn( $uploader );
		$plugin->method( 'scan_and_queue' )->willReturn(
			array(
				'candidates' => array(
					array( 'name' => 'one.tar.gz' ),
					array( 'name' => 'two.tar.gz' ),
				),
				'queued'     => 2,
			)
		);

		$command = new Alynt_Drime_Backups_Uploader_CLI_Command( $plugin );
		$command->run( array(), array( 'max-uploads' => 3 ) );

		$this->assertSame( array( 'Run complete. Found: 2. Queued: 2. Uploaded: 2.' ), WP_CLI::$success );
		$this->assertSame( array(), WP_CLI::$error );
	}

	public function test_run_reports_upload_errors() {
		$cron_health = $this->createMock( Alynt_Drime_Backups_Uploader_Cron_Health::class );
		$cron_health->expects( $this->once() )->method( 'record_manual_scan' );

		$uploader = $this->createMock( Alynt_Drime_Backups_Uploader_Uploader::class );
		$uploader->expects( $this->once() )
			->method( 'upload_next' )
			->willReturn( new WP_Error( 'alynt_drime_upload_failed', 'Upload failed.' ) );

		$plugin = $this->createMock( Alynt_Drime_Backups_Uploader_Plugin::class );
		$plugin->method( 'cron_health' )->willReturn( $cron_health );
		$plugin->method( 'uploader' )->willReturn( $uploader );
		$plugin->method( 'scan_and_queue' )->willReturn(
			array(
				'candidates' => array( array( 'name' => 'one.tar.gz' ) ),
				'queued'     => 1,
			)
		);

		$command = new Alynt_Drime_Backups_Uploader_CLI_Command( $plugin );
		$command->run( array(), array( 'max-uploads' => 1 ) );

		$this->assertSame( array(), WP_CLI::$success );
		$this->assertSame( 'Upload failed.', WP_CLI::$error[0]['message'] );
		$this->assertFalse( WP_CLI::$error[0]['exit'] );
	}
}
