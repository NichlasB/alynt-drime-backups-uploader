<?php
/**
 * Remote action worker tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests the bounded V2 remote action worker.
 */
class RemoteActionWorkerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'sanitize_key' )->alias(
			function ( $value ) {
				return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) );
			}
		);
		Functions\when( 'sanitize_text_field' )->alias(
			function ( $value ) {
				return trim( (string) $value );
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_no_new_candidates_succeeds_without_scheduling_upload_worker() {
		$options         = $this->options_with_accepted_action();
		$schedule_called = false;
		$this->mock_options( $options );

		Functions\when( 'wp_schedule_single_event' )->alias(
			function () use ( &$schedule_called ) {
				$schedule_called = true;
				return false;
			}
		);
		Functions\when( 'wp_next_scheduled' )->justReturn( false );

		$worker = new Alynt_Drime_Backups_Uploader_Remote_Action_Worker(
			$this->plugin_with_scan_result(
				array(
					'candidates' => array(),
					'queued'     => 0,
					'errors'     => array(),
				)
			),
			new Alynt_Drime_Backups_Uploader_Remote_Action_Store()
		);

		$worker->handle( '4bbe899b-1742-48b9-906a-8640aad12c45' );

		$this->assertFalse( $schedule_called );
		$this->assertSame( 'succeeded', $options[ Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME ]['latest_action']['state'] );
		$this->assertSame( 'action_scan_completed', $options[ Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME ]['latest_action']['code'] );
	}

	public function test_existing_upload_event_is_treated_as_scheduled_when_items_are_queued() {
		$options         = $this->options_with_accepted_action();
		$schedule_called = false;
		$this->mock_options( $options );

		Functions\when( 'wp_next_scheduled' )->alias(
			function ( $hook ) {
				return Alynt_Drime_Backups_Uploader_Cron::UPLOAD_EVENT === $hook ? time() + 300 : false;
			}
		);
		Functions\when( 'wp_schedule_single_event' )->alias(
			function () use ( &$schedule_called ) {
				$schedule_called = true;
				return false;
			}
		);

		$worker = new Alynt_Drime_Backups_Uploader_Remote_Action_Worker(
			$this->plugin_with_scan_result(
				array(
					'candidates' => array( 'backup.zip' ),
					'queued'     => 1,
					'errors'     => array(),
				)
			),
			new Alynt_Drime_Backups_Uploader_Remote_Action_Store()
		);

		$worker->handle( '4bbe899b-1742-48b9-906a-8640aad12c45' );

		$this->assertFalse( $schedule_called );
		$this->assertSame( 'succeeded', $options[ Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME ]['latest_action']['state'] );
		$this->assertSame( 'action_scan_completed', $options[ Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME ]['latest_action']['code'] );
	}

	public function test_schedule_preview_succeeds_without_scanning_or_scheduling_upload_worker() {
		$options         = $this->options_with_accepted_schedule_preview_action();
		$schedule_called = false;
		$this->mock_options( $options );

		Functions\when( 'wp_get_schedule' )->alias(
			function ( $hook ) {
				return Alynt_Drime_Backups_Uploader_Cron::SCAN_EVENT === $hook ? 'fifteen_minutes' : false;
			}
		);
		Functions\when( 'wp_get_schedules' )->justReturn(
			array(
				'fifteen_minutes' => array(
					'interval' => 900,
				),
			)
		);
		Functions\when( 'wp_next_scheduled' )->alias(
			function ( $hook ) {
				return Alynt_Drime_Backups_Uploader_Cron::SCAN_EVENT === $hook ? 1782405900 : false;
			}
		);
		Functions\when( 'wp_schedule_single_event' )->alias(
			function () use ( &$schedule_called ) {
				$schedule_called = true;
				return false;
			}
		);

		$worker = new Alynt_Drime_Backups_Uploader_Remote_Action_Worker(
			$this->plugin_for_schedule_preview(),
			new Alynt_Drime_Backups_Uploader_Remote_Action_Store()
		);

		$worker->handle( '6b650de7-c7ee-4f90-a5cb-59c4753a2c50' );

		$latest = $options[ Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME ]['latest_action'];
		$this->assertFalse( $schedule_called );
		$this->assertSame( 'succeeded', $latest['state'] );
		$this->assertSame( 'schedule_preview_ready', $latest['code'] );
		$this->assertSame( 'schedule_preview', $latest['action_type'] );
		$this->assertSame( 'every_15_minutes', $latest['schedule_preview']['current_cadence'] );
		$this->assertSame( 'every_30_minutes', $latest['schedule_preview']['proposed_cadence'] );
		$this->assertSame( '2026-06-25T16:45:00+00:00', $latest['schedule_preview']['current_next_run_at'] );
		$this->assertTrue( $latest['schedule_preview']['would_change'] );
		$this->assertFalse( $latest['schedule_preview']['apply_supported'] );
		$this->assertFalse( $latest['schedule_preview']['rollback_supported'] );
	}

	/**
	 * Mocks option storage.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return void
	 */
	private function mock_options( array &$options ) {
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
	}

	/**
	 * Creates a plugin mock with a scan result.
	 *
	 * @param array<string,mixed> $scan_result Scan result.
	 * @return Alynt_Drime_Backups_Uploader_Plugin
	 */
	private function plugin_with_scan_result( array $scan_result ) {
		$cron_health = $this->createMock( Alynt_Drime_Backups_Uploader_Cron_Health::class );
		$cron_health->expects( $this->once() )->method( 'record_manual_scan' );

		$plugin = $this->createMock( Alynt_Drime_Backups_Uploader_Plugin::class );
		$plugin->expects( $this->once() )->method( 'cron_health' )->willReturn( $cron_health );
		$plugin->expects( $this->once() )->method( 'scan_and_queue' )->willReturn( $scan_result );

		return $plugin;
	}

	/**
	 * Creates a plugin mock for schedule preview without scan/upload side effects.
	 *
	 * @return Alynt_Drime_Backups_Uploader_Plugin
	 */
	private function plugin_for_schedule_preview() {
		$settings = $this->createMock( Alynt_Drime_Backups_Uploader_Settings::class );
		$settings->expects( $this->once() )->method( 'get' )->willReturn(
			array(
				'auto_scan_enabled' => true,
			)
		);

		$plugin = $this->createMock( Alynt_Drime_Backups_Uploader_Plugin::class );
		$plugin->expects( $this->once() )->method( 'settings' )->willReturn( $settings );
		$plugin->expects( $this->never() )->method( 'cron_health' );
		$plugin->expects( $this->never() )->method( 'scan_and_queue' );

		return $plugin;
	}

	/**
	 * Returns option state with one accepted remote action.
	 *
	 * @return array<string,mixed>
	 */
	private function options_with_accepted_action() {
		$action_id = '4bbe899b-1742-48b9-906a-8640aad12c45';
		$record    = array(
			'action_id'                => $action_id,
			'action_type'              => 'scan_upload_now',
			'dashboard_site_public_id' => 'd277c82f-6d75-4d80-93ff-fa842dcdd80b',
			'idempotency_key'          => 'idem-1',
			'request_hash'             => str_repeat( 'a', 64 ),
			'state'                    => 'accepted',
			'code'                     => 'action_accepted',
			'summary'                  => 'Remote action accepted and queued for local scan/upload processing.',
			'counts'                   => array(),
			'created_at'               => time(),
			'updated_at'               => time(),
			'retry_after'              => 0,
		);

		return array(
			Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME => array(
				'records'          => array(
					$action_id => $record,
				),
				'idempotency'      => array(),
				'running_lock'     => array(
					'action_id'  => '',
					'expires_at' => 0,
				),
				'last_accepted_at' => array(),
				'latest_action'    => $record,
			),
		);
	}

	/**
	 * Returns option state with one accepted schedule preview action.
	 *
	 * @return array<string,mixed>
	 */
	private function options_with_accepted_schedule_preview_action() {
		$action_id = '6b650de7-c7ee-4f90-a5cb-59c4753a2c50';
		$record    = array(
			'action_id'                => $action_id,
			'action_type'              => 'schedule_preview',
			'dashboard_site_public_id' => 'd277c82f-6d75-4d80-93ff-fa842dcdd80b',
			'idempotency_key'          => 'idem-preview-1',
			'request_hash'             => str_repeat( 'b', 64 ),
			'state'                    => 'accepted',
			'code'                     => 'action_accepted',
			'summary'                  => 'Remote schedule preview accepted and queued for local read-only processing.',
			'counts'                   => array(),
			'schedule_preview'         => array(
				'schedule_id'        => 'alynt_scan_upload',
				'proposed_cadence'   => 'every_30_minutes',
				'capability_version' => 1,
			),
			'created_at'               => time(),
			'updated_at'               => time(),
			'retry_after'              => 0,
		);

		return array(
			Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME => array(
				'records'          => array(
					$action_id => $record,
				),
				'idempotency'      => array(),
				'running_lock'     => array(
					'action_id'  => '',
					'expires_at' => 0,
				),
				'last_accepted_at' => array(),
				'latest_action'    => $record,
			),
		);
	}
}
