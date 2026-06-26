<?php
/**
 * Health summary tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class HealthSummaryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'get_option' )->justReturn( array() );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_status_returns_redacted_health_payload_by_default() {
		$outbox  = $this->create_outbox();
		$summary = $this->summary( $outbox );
		$status  = $summary->status( 1234567890 );

		$this->assertSame( 1, $status['schema_version'] );
		$this->assertSame( '12345678-1234-4234-9234-123456789abc', $status['site_uuid'] );
		$this->assertSame( ALYNT_DRIME_BACKUPS_UPLOADER_VERSION, $status['plugin_version'] );
		$this->assertSame( 2, $status['queue_count'] );
		$this->assertSame( 1, $status['uploaded_count'] );
		$this->assertSame( 2, $status['failed_count'] );
		$this->assertTrue( $status['active_upload'] );
		$this->assertTrue( $status['server_outbox_configured'] );
		$this->assertTrue( $status['server_outbox_readable'] );
		$this->assertTrue( $status['wpvivid_override_configured'] );
		$this->assertFalse( $status['old_wpvivid_uploader_active'] );
		$this->assertSame( 0, $status['warning_count'] );
		$this->assertSame( Alynt_Drime_Backups_Uploader_Cron_Health::STATUS_LIKELY_CONFIGURED, $status['cron_status'] );
		$this->assertArrayNotHasKey( 'server_outbox_path', $status );
		$this->assertArrayNotHasKey( 'backup_path_override', $status );
		$this->assertSame( $this->expected_redacted_status_keys(), array_keys( $status ) );
		$this->assert_status_payload_contains_no_sensitive_keys( $status );

		rmdir( $outbox );
	}

	public function test_status_can_include_local_paths_for_cli_output() {
		$summary = $this->summary( '/var/www/example/private/backups' );
		$status  = $summary->status( false, true );

		$this->assertSame( '/var/www/example/private/backups', $status['server_outbox_path'] );
		$this->assertSame( '/var/www/example/wp-content/uploads/wpvividbackups', $status['backup_path_override'] );
	}

	public function test_status_warns_when_outbox_is_not_readable() {
		$summary = $this->summary( '/missing/outbox' );
		$status  = $summary->status();

		$this->assertFalse( $status['server_outbox_readable'] );
		$this->assertSame( 1, $status['warning_count'] );
		$this->assertSame( 'server_outbox_unreadable', $status['warnings'][0]['code'] );
	}

	public function test_status_warns_when_old_wpvivid_uploader_is_active_for_wpvivid_source() {
		$outbox = $this->create_outbox();

		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) {
				if ( 'active_plugins' === $name ) {
					return array( 'alynt-drime-wpvivid-uploader/alynt-drime-wpvivid-uploader.php' );
				}

				return $default;
			}
		);

		$summary = $this->summary( $outbox );
		$status  = $summary->status();

		$this->assertTrue( $status['old_wpvivid_uploader_active'] );
		$this->assertSame( 1, $status['warning_count'] );
		$this->assertSame( 'old_wpvivid_uploader_active', $status['warnings'][0]['code'] );

		rmdir( $outbox );
	}

	/**
	 * Builds a health summary with mocked dependencies.
	 *
	 * @return Alynt_Drime_Backups_Uploader_Health_Summary
	 */
	private function summary( $outbox_path ) {
		$settings = $this->createMock( Alynt_Drime_Backups_Uploader_Settings::class );
		$settings->method( 'site_uuid' )->willReturn( '12345678-1234-4234-9234-123456789abc' );
		$settings->method( 'get' )->willReturn(
			array(
				'auto_scan_enabled'    => true,
				'server_cron_expected' => true,
				'server_outbox_path'   => $outbox_path,
				'backup_path_override' => '/var/www/example/wp-content/uploads/wpvividbackups',
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

		$cron_health = $this->createMock( Alynt_Drime_Backups_Uploader_Cron_Health::class );
		$cron_health->method( 'get' )->willReturn(
			array(
				'last_runner'            => Alynt_Drime_Backups_Uploader_Cron_Health::RUNNER_WP_CLI,
				'last_runner_at'         => 1782403200,
				'last_scheduled_scan_at' => 1782403200,
				'last_wp_cli_scan_at'    => 1782403200,
			)
		);
		$cron_health->method( 'status' )->willReturn(
			array(
				'status' => Alynt_Drime_Backups_Uploader_Cron_Health::STATUS_LIKELY_CONFIGURED,
				'reason' => 'A scheduled scan has run from WP-CLI.',
			)
		);
		$cron_health->method( 'is_wp_cron_disabled' )->willReturn( true );

		return new Alynt_Drime_Backups_Uploader_Health_Summary( $settings, $queue, $registry, $cron_health );
	}

	/**
	 * Creates a temporary readable outbox directory.
	 *
	 * @return string
	 */
	private function create_outbox() {
		$outbox = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'alynt-health-summary-' . uniqid( '', true );
		mkdir( $outbox );

		return $outbox;
	}

	/**
	 * Returns the default redacted status payload keys.
	 *
	 * @return array<int,string>
	 */
	private function expected_redacted_status_keys() {
		return array(
			'schema_version',
			'site_uuid',
			'plugin_version',
			'queue_count',
			'uploaded_count',
			'failed_count',
			'active_upload',
			'auto_scan_enabled',
			'server_cron_expected',
			'server_outbox_configured',
			'server_outbox_readable',
			'wpvivid_override_configured',
			'old_wpvivid_uploader_active',
			'wp_cron_disabled',
			'cron_status',
			'cron_reason',
			'warning_count',
			'warnings',
			'last_runner',
			'last_runner_at',
			'last_scheduled_scan_at',
			'last_wp_cli_scan_at',
		);
	}

	/**
	 * Asserts that a status payload does not include secret/path-like keys.
	 *
	 * @param array<string,mixed> $status Status payload.
	 * @return void
	 */
	private function assert_status_payload_contains_no_sensitive_keys( array $status ) {
		$encoded = json_encode( $status );

		foreach ( array( 'api_token', 'authorization', 'cookie', 'nonce', 'password', 'secret', 'server_outbox_path', 'backup_path_override', 'database', 'presigned', 'signed_url' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, strtolower( (string) $encoded ) );
		}
	}
}
