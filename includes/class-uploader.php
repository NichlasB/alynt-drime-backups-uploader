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
		$this->remember_remote_parent_from_result( $result, $item );

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
		$this->maybe_prune_uploaded_server_packages();

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

		foreach ( array( 'manifest_path', 'checksum_path', 'remote_index_path', 'remote_catalog_path' ) as $key ) {
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

		$owner    = wp_generate_uuid4();
		$expires  = time() + self::UPLOAD_LOCK_TTL;
		$acquired = add_option(
			self::UPLOAD_LOCK_OPTION,
			array(
				'owner'   => $owner,
				'expires' => $expires,
			),
			'',
			false
		);
		$this->flush_upload_lock_cache();
		if ( $acquired && function_exists( 'wp_cache_set' ) ) {
			wp_cache_set(
				self::UPLOAD_LOCK_OPTION,
				array(
					'owner'   => $owner,
					'expires' => $expires,
				),
				'options'
			);
		}
		if ( $acquired ) {
			$this->upload_lock_owner = $owner;
		}

		return $acquired;
	}

	/**
	 * Renews the current upload-worker lease if this worker still owns it.
	 *
	 * @return bool
	 */
	private function renew_upload_lock() {
		if ( '' === $this->upload_lock_owner ) {
			return false;
		}

		$lock = get_option( self::UPLOAD_LOCK_OPTION, array() );
		if ( ! is_array( $lock ) || empty( $lock['owner'] ) || ! hash_equals( $this->upload_lock_owner, (string) $lock['owner'] ) ) {
			return false;
		}

		$lock['expires'] = time() + self::UPLOAD_LOCK_TTL;
		update_option( self::UPLOAD_LOCK_OPTION, $lock, false );
		$this->flush_upload_lock_cache();

		$stored = get_option( self::UPLOAD_LOCK_OPTION, array() );

		return is_array( $stored )
			&& ! empty( $stored['owner'] )
			&& hash_equals( $this->upload_lock_owner, (string) $stored['owner'] )
			&& ! empty( $stored['expires'] )
			&& absint( $stored['expires'] ) > time();
	}

	/**
	 * Returns the consistent error used when a worker loses its lease.
	 *
	 * @return WP_Error
	 */
	private function upload_lock_lost_error() {
		return new WP_Error( 'alynt_drime_upload_lock_lost', __( 'This backup upload stopped because another upload worker took ownership. The next worker can safely resume it.', 'alynt-drime-backups-uploader' ) );
	}

	/**
	 * Releases the upload worker lock.
	 *
	 * @return void
	 */
	private function release_upload_lock() {
		if ( '' === $this->upload_lock_owner ) {
			return;
		}

		$lock = get_option( self::UPLOAD_LOCK_OPTION, array() );
		if ( is_array( $lock ) && ! empty( $lock['owner'] ) && hash_equals( $this->upload_lock_owner, (string) $lock['owner'] ) ) {
			delete_option( self::UPLOAD_LOCK_OPTION );
			$this->flush_upload_lock_cache();
		}

		$this->upload_lock_owner = '';
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

		$settings = $this->effective_upload_settings( $this->settings->get(), $item );
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
					'path'                      => $path,
					'remote_name'               => basename( $path ),
					'size'                      => (int) $size,
					'destination_relative_path' => (string) $settings['relative_path'],
					'drime'                     => array(
						'duplicate_skipped' => true,
					),
					'sidecars'                  => $sidecars,
				);
			}

			return new WP_Error( 'alynt_drime_duplicate_skipped', __( 'A file with this name already exists in Drime, so the upload was skipped.', 'alynt-drime-backups-uploader' ) );
		}

		$result = $size < Alynt_Drime_Backups_Uploader_Drime_Client::MIN_MULTIPART_CHUNK_SIZE
			? $this->simple_upload_item( $path, $remote_name, (int) $size, $parent_id, $settings )
			: $this->multipart_upload( $path, $remote_name, (int) $size, $item, $parent_id, $settings );

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

		$result['destination_relative_path'] = (string) $settings['relative_path'];

		return $result;
	}

	/**
	 * Returns settings with a producer-specific Drime relative path applied.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param array<string,mixed> $item Queue item.
	 * @return array<string,mixed>
	 */
	private function effective_upload_settings( array $settings, array $item ) {
		$relative_path = $this->source_relative_path( $settings, $item );

		if ( '' !== $relative_path ) {
			$settings['relative_path'] = $relative_path;
		}

		if ( $this->is_generic_outbox_item( $item ) ) {
			$package_folder = $this->generic_package_folder_name( $item );
			if ( '' !== $package_folder ) {
				$settings['relative_path'] = $this->append_upload_relative_segment( (string) $settings['relative_path'], $package_folder );
			}
		}

		return $settings;
	}

	/**
	 * Returns a source-specific Drime relative path when configured.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param array<string,mixed> $item Queue item.
	 * @return string
	 */
	private function source_relative_path( array $settings, array $item ) {
		$producer_key = isset( $item['producer_key'] ) ? (string) $item['producer_key'] : '';

		if ( 'generic_outbox' === $producer_key && ! empty( $settings['server_relative_path'] ) ) {
			return (string) $settings['server_relative_path'];
		}

		if ( 'wpvivid' === $producer_key && ! empty( $settings['wpvivid_relative_path'] ) ) {
			return (string) $settings['wpvivid_relative_path'];
		}

		return '';
	}

	/**
	 * Checks whether a queued item is a generic server outbox package.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @return bool
	 */
	private function is_generic_outbox_item( array $item ) {
		return isset( $item['producer_key'] ) && 'generic_outbox' === (string) $item['producer_key'];
	}

	/**
	 * Returns the Drime package folder name for a generic outbox package.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @return string
	 */
	private function generic_package_folder_name( array $item ) {
		$name = '';

		foreach ( array( 'package_id', 'backup_set_id' ) as $key ) {
			if ( ! empty( $item[ $key ] ) && is_scalar( $item[ $key ] ) ) {
				$name = (string) $item[ $key ];
				break;
			}
		}

		if ( '' === $name && ! empty( $item['path'] ) && is_scalar( $item['path'] ) ) {
			$name = basename( $this->package_archive_stem( (string) $item['path'] ) );
		}

		if ( '' === $name && ! empty( $item['name'] ) && is_scalar( $item['name'] ) ) {
			$name = basename( $this->package_archive_stem( (string) $item['name'] ) );
		}

		$name = trim( preg_replace( '/[^A-Za-z0-9._-]+/', '-', str_replace( array( '\\', '/' ), '-', $name ) ), '-._' );

		if ( '' === $name || false !== strpos( $name, '..' ) ) {
			return '';
		}

		return substr( $name, 0, Alynt_Drime_Backups_Uploader_Settings::MAX_RELATIVE_PATH_SEGMENT_CHARS );
	}

	/**
	 * Appends a safe folder segment to a Drime relative path.
	 *
	 * @param string $relative_path Existing relative path.
	 * @param string $segment Folder segment.
	 * @return string
	 */
	private function append_upload_relative_segment( $relative_path, $segment ) {
		$relative_path = '/' . trim( str_replace( '\\', '/', (string) $relative_path ), '/' );
		$segment       = trim( str_replace( array( '\\', '/' ), '-', (string) $segment ), '/' );

		if ( '' === $segment ) {
			return '/' === $relative_path ? '' : $relative_path;
		}

		$path = '/' . trim( trim( $relative_path, '/' ) . '/' . $segment, '/' );

		return '/' === $path ? '' : $path;
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
	 * @param string                   $path File path.
	 * @param string                   $remote_name Remote name.
	 * @param int                      $size Size.
	 * @param int|null                 $parent_id Concrete upload parent folder ID.
	 * @param array<string,mixed>|null $settings Effective upload settings.
	 * @return array<string,mixed>|WP_Error
	 */
	private function simple_upload_item( $path, $remote_name, $size, $parent_id = null, ?array $settings = null ) {
		$response = $this->client->simple_upload( $path, $remote_name, $parent_id, $settings );
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
	 * @param array<string,mixed> $item Queue item.
	 * @return void
	 */
	private function remember_remote_parent_from_result( array $result, array $item ) {
		$settings = $this->effective_upload_settings( $this->settings->get(), $item );

		if ( empty( $settings['relative_path'] ) ) {
			return;
		}

		if ( empty( $result['drime']['fileEntry'] ) || ! is_array( $result['drime']['fileEntry'] ) || empty( $result['drime']['fileEntry']['parent_id'] ) ) {
			return;
		}

		$this->registry->remember_drime_location( absint( $settings['workspace_id'] ), (string) $settings['relative_path'], absint( $result['drime']['fileEntry']['parent_id'] ), absint( $settings['parent_folder_id'] ) );
	}
}
