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

	const STALE_ACTIVE_UPLOAD_SECONDS = 6 * 60 * 60;
	const UPLOAD_LOCK_OPTION          = 'alynt_drime_backups_upload_lock';
	const UPLOAD_LOCK_TTL             = 10 * 60;

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

			return is_wp_error( $result ) ? $this->handle_failed_upload( $item, $result ) : $this->complete_successful_upload( $item, $result );
		} finally {
			$this->release_upload_lock();
		}
	}

	/**
	 * Handles a failed queued upload.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param WP_Error            $result Upload error.
	 * @return WP_Error
	 *
	 * @since 0.1.0
	 */
	private function handle_failed_upload( array $item, WP_Error $result ) {
		if ( ! $this->queue->set_active( null ) ) {
			return $this->state_persistence_error();
		}

		$attempts = $this->queue->increment_attempts( (string) $item['signature'] );
		if ( 0 === $attempts ) {
			return $this->state_persistence_error();
		}

		if (
			! $this->registry->mark_failed(
				(string) $item['signature'],
				$result->get_error_message(),
				$this->registry_item_context( $item, $attempts )
			)
		) {
			return $this->state_persistence_error();
		}

		$this->logger->event(
			'upload',
			'error',
			'upload_failed',
			'Upload failed.',
			array(
				'file'   => basename( (string) $item['path'] ),
				'reason' => $result->get_error_message(),
			)
		);

		if ( 'alynt_drime_file_changed' === $result->get_error_code() ) {
			if ( ! $this->queue->remove( (string) $item['signature'] ) ) {
				return $this->state_persistence_error();
			}

			$this->logger->event(
				'upload',
				'warning',
				'changed_file_queue_item_removed',
				'A changed backup file was removed from the queue so a fresh scan can requeue it.',
				array(
					'file' => basename( (string) $item['path'] ),
				)
			);

			return $result;
		}

		if ( $this->attempts_reached_limit( $attempts ) ) {
			$removed = $this->remove_retry_limited_item( $item, $attempts );
			if ( is_wp_error( $removed ) ) {
				return $removed;
			}
		}

		return $result;
	}

	/**
	 * Sends a failure notification when the notifier is available.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param string              $failure_state Failure state.
	 * @param string              $reason Failure reason.
	 * @param int                 $attempts Attempt count.
	 * @return void
	 */
	public function notify_failure( array $item, $failure_state, $reason, $attempts = 0 ) {
		if ( null === $this->notifier ) {
			return;
		}

		$this->notifier->notify_failure( $item, $failure_state, $reason, $attempts );
	}

	/**
	 * Removes a queued item that reached the retry limit.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param int                 $attempts Attempts.
	 * @return true|WP_Error
	 */
	private function remove_retry_limited_item( array $item, $attempts ) {
		if ( ! $this->queue->remove( (string) $item['signature'] ) ) {
			return $this->state_persistence_error();
		}

		$this->logger->event(
			'upload',
			'error',
			'upload_retry_limit_reached',
			'Upload retry limit reached; item removed from queue.',
			array(
				'file'     => basename( (string) $item['path'] ),
				'attempts' => $attempts,
			)
		);

		$this->notify_failure( $item, 'retry_limit_reached', __( 'The queued backup reached the retry limit.', 'alynt-drime-backups-uploader' ), $attempts );

		return true;
	}

	/**
	 * Handles a successful queued upload.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param array<string,mixed> $result Upload result.
	 * @return array<string,mixed>|WP_Error
	 */
	private function complete_successful_upload( array $item, array $result ) {
		$this->remember_remote_parent_from_result( $result );

		$record = array_merge( $result, $this->registry_item_context( $item ) );

		if ( ! $this->registry->mark_uploaded( (string) $item['signature'], $record ) ) {
			return $this->state_persistence_error();
		}

		if ( ! $this->queue->remove( (string) $item['signature'] ) ) {
			return $this->state_persistence_error();
		}

		if ( ! $this->queue->set_active( null ) ) {
			return $this->state_persistence_error();
		}

		$this->logger->event( 'upload', 'info', 'upload_completed', 'Upload completed.', array( 'file' => basename( (string) $item['path'] ) ) );
		$this->maybe_delete_local_file( $item );

		return $result;
	}

	/**
	 * Deletes a local backup after successful upload when enabled.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @return void
	 */
	private function maybe_delete_local_file( array $item ) {
		$settings = $this->settings->get();

		if ( empty( $settings['delete_local_after_upload'] ) ) {
			return;
		}

		if ( $this->is_wpvivid_listed_multi_file_item( $item ) ) {
			$this->maybe_delete_wpvivid_set_files( $item );
			return;
		}

		$path = isset( $item['path'] ) ? (string) $item['path'] : '';
		if ( '' === $path || ! is_file( $path ) ) {
			$this->logger->event( 'filesystem', 'warning', 'local_delete_missing_file', 'Local backup deletion was skipped because the file no longer exists.' );
			return;
		}

		if ( ! wp_delete_file( $path ) ) {
			$this->logger->event( 'filesystem', 'error', 'local_delete_failed', 'Local backup deletion failed after upload.', array( 'file' => basename( $path ) ) );
			return;
		}

		$this->delete_local_sidecars( $item, $path );
		$this->logger->event( 'filesystem', 'info', 'local_delete_succeeded', 'Local backup file deleted after upload.', array( 'file' => basename( $path ) ) );
	}

	/**
	 * Deletes sidecar files that belong to a deleted local package.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param string              $path Deleted package path.
	 * @return void
	 */
	private function delete_local_sidecars( array $item, $path ) {
		$package_dir  = dirname( $path );
		$package_name = basename( $path );

		foreach ( array( 'manifest_path', 'checksum_path', 'remote_index_path' ) as $key ) {
			$sidecar = isset( $item[ $key ] ) && is_scalar( $item[ $key ] ) ? (string) $item[ $key ] : '';
			if ( '' === $sidecar || ! is_file( $sidecar ) ) {
				continue;
			}

			if ( ! $this->is_package_sidecar_path( $sidecar, $path ) ) {
				$this->logger->event( 'filesystem', 'warning', 'local_delete_sidecar_skipped', 'Local backup sidecar deletion was skipped because the path did not match the deleted package.', array( 'file' => basename( $sidecar ) ) );
				continue;
			}

			if ( ! wp_delete_file( $sidecar ) ) {
				$this->logger->event( 'filesystem', 'warning', 'local_delete_sidecar_failed', 'Local backup sidecar deletion failed after upload.', array( 'file' => basename( $sidecar ) ) );
			}
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

	/**
	 * Returns a consistent state-persistence error.
	 *
	 * @return WP_Error
	 */
	private function state_persistence_error() {
		return new WP_Error( 'alynt_drime_state_save_failed', __( 'The upload state could not be saved. Check that the WordPress database is writable, then try again.', 'alynt-drime-backups-uploader' ) );
	}

	/**
	 * Acquires a short upload worker lock.
	 *
	 * @return bool
	 */
	private function acquire_upload_lock() {
		$lock = get_option( self::UPLOAD_LOCK_OPTION, array() );

		if ( is_array( $lock ) && ! empty( $lock['expires'] ) && absint( $lock['expires'] ) > time() ) {
			return false;
		}

		if ( ! empty( $lock ) ) {
			delete_option( self::UPLOAD_LOCK_OPTION );
			$this->flush_upload_lock_cache();
		}

		$acquired = add_option(
			self::UPLOAD_LOCK_OPTION,
			array(
				'expires' => time() + self::UPLOAD_LOCK_TTL,
			),
			'',
			false
		);
		$this->flush_upload_lock_cache();
		if ( $acquired && function_exists( 'wp_cache_set' ) ) {
			wp_cache_set(
				self::UPLOAD_LOCK_OPTION,
				array(
					'expires' => time() + self::UPLOAD_LOCK_TTL,
				),
				'options'
			);
		}

		return $acquired;
	}

	/**
	 * Releases the upload worker lock.
	 *
	 * @return void
	 */
	private function release_upload_lock() {
		delete_option( self::UPLOAD_LOCK_OPTION );
		$this->flush_upload_lock_cache();
	}

	/**
	 * Clears the upload lock option cache after mutation.
	 *
	 * @return void
	 */
	private function flush_upload_lock_cache() {
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::UPLOAD_LOCK_OPTION, 'options' );
		}
	}

	/**
	 * Uploads one queued item.
	 *
	 * @param array<string,mixed> $item Item.
	 * @return array<string,mixed>|WP_Error
	 */
	private function upload_item( array $item ) {
		$path = isset( $item['path'] ) ? (string) $item['path'] : '';
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'alynt_drime_file_missing', __( 'The queued backup file is no longer readable.', 'alynt-drime-backups-uploader' ) );
		}

		$settings = $this->settings->get();
		if ( ! Alynt_Drime_Backups_Uploader_Settings::is_workspace_id_allowed( absint( $settings['workspace_id'] ) ) ) {
			return new WP_Error( 'alynt_drime_workspace_not_allowed', Alynt_Drime_Backups_Uploader_Settings::workspace_not_allowed_message() );
		}

		$size        = filesize( $path );
		$mtime       = filemtime( $path );
		$remote_name = basename( $path );
		$parent_id   = $this->prepare_upload_parent_id( $settings );

		if ( false === $size || false === $mtime || $size <= 0 ) {
			return new WP_Error( 'alynt_drime_empty_file', __( 'The queued backup file is empty.', 'alynt-drime-backups-uploader' ) );
		}

		if ( ! $this->queued_file_state_matches( $item, (int) $size, (int) $mtime ) ) {
			return new WP_Error( 'alynt_drime_file_changed', __( 'The queued backup file changed after it was scanned. Run a new scan before uploading it.', 'alynt-drime-backups-uploader' ) );
		}

		if ( is_wp_error( $parent_id ) ) {
			return $parent_id;
		}

		$remote_name = $this->preflight_remote_name( $remote_name, (int) $size, $settings, $parent_id );
		if ( is_wp_error( $remote_name ) ) {
			return $remote_name;
		}

		if ( false === $remote_name ) {
			$sidecars = $this->upload_package_sidecars( $item, $path, $settings, $parent_id );
			if ( is_wp_error( $sidecars ) ) {
				return $sidecars;
			}

			if ( ! empty( $sidecars ) ) {
				return array(
					'path'        => $path,
					'remote_name' => basename( $path ),
					'size'        => (int) $size,
					'drime'       => array(
						'duplicate_skipped' => true,
					),
					'sidecars'    => $sidecars,
				);
			}

			return new WP_Error( 'alynt_drime_duplicate_skipped', __( 'A file with this name already exists in Drime, so the upload was skipped.', 'alynt-drime-backups-uploader' ) );
		}

		$result = $size < Alynt_Drime_Backups_Uploader_Drime_Client::MIN_MULTIPART_CHUNK_SIZE
			? $this->simple_upload_item( $path, $remote_name, (int) $size, $parent_id )
			: $this->multipart_upload( $path, $remote_name, (int) $size, $item, $parent_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$sidecars = $this->upload_package_sidecars( $item, $path, $settings, $parent_id );
		if ( is_wp_error( $sidecars ) ) {
			return $sidecars;
		}

		if ( ! empty( $sidecars ) ) {
			$result['sidecars'] = $sidecars;
		}

		return $result;
	}

	/**
	 * Checks whether a queued item still matches the scanned file state.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param int                 $size Current file size.
	 * @param int                 $mtime Current modified timestamp.
	 * @return bool
	 */
	private function queued_file_state_matches( array $item, $size, $mtime ) {
		$queued_size  = isset( $item['size'] ) ? absint( $item['size'] ) : 0;
		$queued_mtime = isset( $item['mtime'] ) ? absint( $item['mtime'] ) : 0;

		if ( $queued_size > 0 && $queued_size !== (int) $size ) {
			return false;
		}

		return 0 === $queued_mtime || $queued_mtime === (int) $mtime;
	}

	/**
	 * Returns the configured multipart chunk size in bytes.
	 *
	 * @return int
	 */
	private function multipart_chunk_size() {
		$settings = $this->settings->get();
		$mb       = isset( $settings['multipart_chunk_size_mb'] ) ? absint( $settings['multipart_chunk_size_mb'] ) : Alynt_Drime_Backups_Uploader_Drime_Client::DEFAULT_MULTIPART_SIZE_MB;
		$bytes    = $mb * 1048576;

		return max(
			Alynt_Drime_Backups_Uploader_Drime_Client::MIN_MULTIPART_CHUNK_SIZE,
			min( Alynt_Drime_Backups_Uploader_Drime_Client::MAX_MULTIPART_CHUNK_SIZE, $bytes )
		);
	}

	/**
	 * Runs connection and duplicate preflight checks.
	 *
	 * @param string              $remote_name Remote name.
	 * @param int                 $size Size.
	 * @param array<string,mixed> $settings Settings.
	 * @param int|null            $parent_id Concrete upload parent folder ID.
	 * @return string|false|WP_Error
	 */
	private function preflight_remote_name( $remote_name, $size, array $settings, $parent_id = null ) {
		$connection = $this->client->test_connection();
		if ( is_wp_error( $connection ) ) {
			return $connection;
		}

		return $this->resolve_duplicate_mode( $remote_name, $size, $settings, $parent_id );
	}

	/**
	 * Uploads a small queued item.
	 *
	 * @param string   $path File path.
	 * @param string   $remote_name Remote name.
	 * @param int      $size Size.
	 * @param int|null $parent_id Concrete upload parent folder ID.
	 * @return array<string,mixed>|WP_Error
	 */
	private function simple_upload_item( $path, $remote_name, $size, $parent_id = null ) {
		$response = $this->client->simple_upload( $path, $remote_name, $parent_id );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'path'        => $path,
			'remote_name' => $remote_name,
			'size'        => $size,
			'drime'       => $response,
		);
	}

	/**
	 * Handles duplicate mode.
	 *
	 * @param string              $remote_name Remote name.
	 * @param int                 $size Size.
	 * @param array<string,mixed> $settings Settings.
	 * @param int|null            $parent_id Concrete upload parent folder ID.
	 * @return string|false|WP_Error
	 */
	private function resolve_duplicate_mode( $remote_name, $size, array $settings, $parent_id = null ) {
		$has_parent_override = null !== $parent_id;
		$parent_id           = $has_parent_override ? absint( $parent_id ) : $this->resolved_drime_parent_id( $settings );
		$file                = array(
			'name' => $remote_name,
			'size' => $size,
		);

		if ( ! $has_parent_override && '' !== $settings['relative_path'] && ! empty( $settings['parent_folder_id'] ) ) {
			$file['relativePath'] = $settings['relative_path'];
		} elseif ( $parent_id <= 0 ) {
			$file['relativePath'] = '/';
		}

		$validation = $this->client->validate_upload( array( $file ), $parent_id > 0 ? $parent_id : null );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$duplicates = isset( $validation['duplicates'] ) && is_array( $validation['duplicates'] ) ? $validation['duplicates'] : array();
		if ( empty( $duplicates ) ) {
			return $remote_name;
		}

		if ( 'skip' === $settings['duplicate_mode'] ) {
			return false;
		}

		return $this->client->get_available_name( $remote_name, $parent_id > 0 ? $parent_id : null );
	}

	/**
	 * Resolves the Drime parent ID available for duplicate checks.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return int
	 */
	private function resolved_drime_parent_id( array $settings ) {
		if ( ! empty( $settings['parent_folder_id'] ) ) {
			return absint( $settings['parent_folder_id'] );
		}

		if ( empty( $settings['relative_path'] ) ) {
			return 0;
		}

		return $this->registry->get_drime_parent_id( absint( $settings['workspace_id'] ), (string) $settings['relative_path'] );
	}

	/**
	 * Remembers the Drime parent ID returned after a relative-path upload.
	 *
	 * @param array<string,mixed> $result Upload result.
	 * @return void
	 */
	private function remember_remote_parent_from_result( array $result ) {
		$settings = $this->settings->get();

		if ( empty( $settings['relative_path'] ) ) {
			return;
		}

		if ( empty( $result['drime']['fileEntry'] ) || ! is_array( $result['drime']['fileEntry'] ) || empty( $result['drime']['fileEntry']['parent_id'] ) ) {
			return;
		}

		$this->registry->remember_drime_location( absint( $settings['workspace_id'] ), (string) $settings['relative_path'], absint( $result['drime']['fileEntry']['parent_id'] ), absint( $settings['parent_folder_id'] ) );
	}
}
