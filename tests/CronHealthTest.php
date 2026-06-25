<?php
/**
 * Cron health tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class CronHealthTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_manual_scan_records_manual_runner_without_scheduled_scan() {
		$options = array();
		$health  = $this->health_with_options( $options );

		$health->record_manual_scan();
		$state = $health->get();

		$this->assertSame( Alynt_Drime_Backups_Uploader_Cron_Health::RUNNER_MANUAL_ADMIN, $state['last_runner'] );
		$this->assertGreaterThan( 0, $state['last_manual_scan_at'] );
		$this->assertSame( 0, $state['last_scheduled_scan_at'] );
	}

	public function test_scheduled_scan_records_unknown_runner_by_default() {
		$options = array();
		$health  = $this->health_with_options( $options );

		$runner = $health->record_scheduled_scan();
		$state  = $health->get();

		$this->assertSame( Alynt_Drime_Backups_Uploader_Cron_Health::RUNNER_UNKNOWN, $runner );
		$this->assertSame( Alynt_Drime_Backups_Uploader_Cron_Health::RUNNER_UNKNOWN, $state['last_runner'] );
		$this->assertGreaterThan( 0, $state['last_scheduled_scan_at'] );
	}

	public function test_status_requires_wp_cli_when_server_cron_is_expected() {
		$options = array();
		$health  = $this->health_with_options( $options );

		$status = $health->status(
			array(
				'auto_scan_enabled'    => true,
				'server_cron_expected' => true,
			),
			time() + 60
		);

		$this->assertSame( Alynt_Drime_Backups_Uploader_Cron_Health::STATUS_ATTENTION_NEEDED, $status['status'] );
	}

	public function test_status_is_likely_configured_after_wp_cli_scan_evidence() {
		$options = array(
			Alynt_Drime_Backups_Uploader_Cron_Health::OPTION_NAME => array(
				'last_wp_cli_scan_at' => time() - 60,
			),
		);
		$health  = $this->health_with_options( $options );

		$status = $health->status(
			array(
				'auto_scan_enabled'    => true,
				'server_cron_expected' => true,
			),
			time() + 60
		);

		$this->assertSame( Alynt_Drime_Backups_Uploader_Cron_Health::STATUS_LIKELY_CONFIGURED, $status['status'] );
	}

	/**
	 * Creates cron health storage with mocked options.
	 *
	 * @param array<string,mixed> $options Option storage.
	 * @return Alynt_Drime_Backups_Uploader_Cron_Health
	 */
	private function health_with_options( array &$options ) {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) use ( &$options ) {
				return array_key_exists( $name, $options ) ? $options[ $name ] : $default;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) use ( &$options ) {
				$options[ $name ] = $value;
				return true;
			}
		);

		return new Alynt_Drime_Backups_Uploader_Cron_Health();
	}
}
