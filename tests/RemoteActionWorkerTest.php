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
}
