<?php
/**
 * Remote action worker.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.12
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes accepted local V2 remote actions.
 *
 * @since 0.5.12
 */
class Alynt_Drime_Backups_Uploader_Remote_Action_Worker {
	const EVENT = 'alynt_drime_backups_remote_action_event';

	/**
	 * Plugin.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Plugin
	 */
	private $plugin;

	/**
	 * Store.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Remote_Action_Store
	 */
	private $store;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Plugin              $plugin Plugin.
	 * @param Alynt_Drime_Backups_Uploader_Remote_Action_Store $store Store.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Plugin $plugin, Alynt_Drime_Backups_Uploader_Remote_Action_Store $store ) {
		$this->plugin = $plugin;
		$this->store  = $store;
	}

	/**
	 * Registers worker hook.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( self::EVENT, array( $this, 'handle' ), 10, 1 );
	}

	/**
	 * Handles a queued action.
	 *
	 * @param string $action_id Action ID.
	 * @return void
	 */
	public function handle( $action_id ) {
		$record = $this->store->record( $action_id );
		if ( empty( $record ) || empty( $record['action_id'] ) ) {
			return;
		}

		if ( ! $this->store->acquire_lock( $record['action_id'] ) ) {
			$this->store->upsert_action( $record, 'busy', 'action_already_running', __( 'Another remote action is already running on this client site.', 'alynt-drime-backups-uploader' ), array(), Alynt_Drime_Backups_Uploader_Remote_Action_Store::LOCK_TTL );
			return;
		}

		$this->store->upsert_action( $record, 'running', 'action_running', __( 'Remote scan/upload action is running on the client site.', 'alynt-drime-backups-uploader' ) );

		try {
			$this->plugin->cron_health()->record_manual_scan();
			$result = $this->plugin->scan_and_queue();
			$counts = $this->counts_from_scan_result( is_array( $result ) ? $result : array() );

			if ( ! empty( $result['errors'] ) ) {
				$counts['failed'] = count( $result['errors'] );
				$this->store->upsert_action( $record, 'failed', 'action_scan_failed', __( 'Remote scan/upload action finished with scan errors; no unsafe details were stored.', 'alynt-drime-backups-uploader' ), $counts );
				return;
			}

			$scheduled = $this->schedule_upload_worker();
			if ( is_wp_error( $scheduled ) ) {
				$this->store->upsert_action( $record, 'failed', 'action_upload_schedule_failed', __( 'Remote scan/upload action scanned for eligible packages but could not schedule the upload worker.', 'alynt-drime-backups-uploader' ), $counts );
				return;
			}

			$this->store->upsert_action( $record, 'succeeded', 'action_scan_completed', __( 'Remote scan/upload action scanned for eligible packages and scheduled the upload worker.', 'alynt-drime-backups-uploader' ), $counts );
		} catch ( Exception $exception ) {
			unset( $exception );
			$this->store->upsert_action( $record, 'failed', 'action_worker_failed', __( 'Remote scan/upload action failed without storing unsafe error details.', 'alynt-drime-backups-uploader' ) );
		} finally {
			$this->store->release_lock( $record['action_id'] );
		}
	}

	/**
	 * Builds safe counts from scan result.
	 *
	 * @param array<string,mixed> $result Scan result.
	 * @return array<string,int>
	 */
	private function counts_from_scan_result( array $result ) {
		$found  = isset( $result['candidates'] ) && is_array( $result['candidates'] ) ? count( $result['candidates'] ) : 0;
		$queued = isset( $result['queued'] ) ? max( 0, absint( $result['queued'] ) ) : 0;

		return array(
			'found'            => $found,
			'queued'           => $queued,
			'already_known'    => max( 0, $found - $queued ),
			'upload_attempted' => 0,
			'failed'           => 0,
		);
	}

	/**
	 * Schedules the existing upload worker.
	 *
	 * @return true|WP_Error
	 */
	private function schedule_upload_worker() {
		if ( ! function_exists( 'wp_schedule_single_event' ) ) {
			return new WP_Error( 'action_upload_scheduler_unavailable', __( 'WordPress cron scheduling is not available for the upload worker.', 'alynt-drime-backups-uploader' ) );
		}

		$scheduled = wp_schedule_single_event( time() + 5, Alynt_Drime_Backups_Uploader_Cron::UPLOAD_EVENT, array(), true );

		if ( is_wp_error( $scheduled ) ) {
			return $scheduled;
		}

		if ( false === $scheduled ) {
			return new WP_Error( 'action_upload_schedule_failed', __( 'WordPress could not schedule the upload worker.', 'alynt-drime-backups-uploader' ) );
		}

		return true;
	}
}
