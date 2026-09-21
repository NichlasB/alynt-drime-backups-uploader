<?php
/**
 * Upload worker.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes queued uploads one at a time.
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_Uploader {
	use Alynt_Drime_Backups_Uploader_Uploader_Active_Upload;
	use Alynt_Drime_Backups_Uploader_Uploader_Destination;
	use Alynt_Drime_Backups_Uploader_Uploader_Multipart;
	use Alynt_Drime_Backups_Uploader_Uploader_Multipart_Session;
	use Alynt_Drime_Backups_Uploader_Uploader_Multipart_Parts;
	use Alynt_Drime_Backups_Uploader_Uploader_Retry_State;
	use Alynt_Drime_Backups_Uploader_Uploader_Sidecars;
	use Alynt_Drime_Backups_Uploader_Uploader_WPvivid_Set_Cleanup;
	use Alynt_Drime_Backups_Uploader_Uploader_Local_Server_Retention;
	use Alynt_Drime_Backups_Uploader_Uploader_Failure_Handling;
	use Alynt_Drime_Backups_Uploader_Uploader_Local_Cleanup;
	use Alynt_Drime_Backups_Uploader_Uploader_Lock;
	use Alynt_Drime_Backups_Uploader_Uploader_Item_Preparation;
	use Alynt_Drime_Backups_Uploader_Uploader_Remote_Preflight;

	const STALE_ACTIVE_UPLOAD_SECONDS = 6 * 60 * 60;
	const UPLOAD_LOCK_OPTION          = 'alynt_drime_backups_upload_lock';
	const UPLOAD_LOCK_TTL             = 10 * 60;

	/**
	 * Unique owner token for the current upload-worker lease.
	 *
	 * @var string
	 */
	private $upload_lock_owner = '';

	/**
	 * Settings.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Settings
	 */
	private $settings;

	/**
	 * Drime client.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Drime_Client
	 */
	private $client;

	/**
	 * Queue.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Queue
	 */
	private $queue;

	/**
	 * Registry.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Backup_Registry
	 */
	private $registry;

	/**
	 * Logger.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Logger
	 */
	private $logger;

	/**
	 * Failure notifier.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Failure_Notifier|null
	 */
	private $notifier;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Settings              $settings Settings.
	 * @param Alynt_Drime_Backups_Uploader_Drime_Client          $client Client.
	 * @param Alynt_Drime_Backups_Uploader_Queue                 $queue Queue.
	 * @param Alynt_Drime_Backups_Uploader_Backup_Registry       $registry Registry.
	 * @param Alynt_Drime_Backups_Uploader_Logger                $logger Logger.
	 * @param Alynt_Drime_Backups_Uploader_Failure_Notifier|null $notifier Failure notifier.
	 *
	 * @since 0.1.0
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Settings $settings, Alynt_Drime_Backups_Uploader_Drime_Client $client, Alynt_Drime_Backups_Uploader_Queue $queue, Alynt_Drime_Backups_Uploader_Backup_Registry $registry, Alynt_Drime_Backups_Uploader_Logger $logger, ?Alynt_Drime_Backups_Uploader_Failure_Notifier $notifier = null ) {
		$this->settings = $settings;
		$this->client   = $client;
		$this->queue    = $queue;
		$this->registry = $registry;
		$this->logger   = $logger;
		$this->notifier = $notifier;
	}

	/**
	 * Uploads the next queued item.
	 *
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.1.0
	 */
	public function upload_next() {
		if ( ! $this->acquire_upload_lock() ) {
			return new WP_Error( 'alynt_drime_upload_locked', __( 'Another backup upload is already running. Please try again shortly.', 'alynt-drime-backups-uploader' ) );
		}

		try {
			$item = $this->queue->next();
			if ( null === $item ) {
				$this->maybe_prune_uploaded_server_packages();
				return new WP_Error( 'alynt_drime_queue_empty', __( 'There are no queued backups to upload.', 'alynt-drime-backups-uploader' ) );
			}

			$active_check = $this->recover_active_upload_state( $item );
			if ( is_wp_error( $active_check ) ) {
				return $active_check;
			}

			if ( $this->has_exhausted_retries( $item ) ) {
				return $this->fail_exhausted_item( $item );
			}

			$result = $this->upload_item( $item );
			if ( is_wp_error( $result ) && 'alynt_drime_upload_lock_lost' === $result->get_error_code() ) {
				return $result;
			}

			if ( ! is_wp_error( $result ) && ! $this->renew_upload_lock() ) {
				return $this->upload_lock_lost_error();
			}

			return is_wp_error( $result ) ? $this->handle_failed_upload( $item, $result ) : $this->complete_successful_upload( $item, $result );
		} finally {
			$this->release_upload_lock();
		}
	}

	/**
	 * Clears active upload state and aborts the remote multipart upload when possible.
	 *
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.1.0
	 */
	public function clear_active_upload() {
		$active = $this->queue->get_active();

		$abort = $this->abort_active_upload( $active, 'manual_active_upload_abort' );
		if ( is_wp_error( $abort ) ) {
			return $abort;
		}

		if ( ! $this->queue->clear_active() ) {
			return $this->state_persistence_error();
		}

		return $active;
	}
}
