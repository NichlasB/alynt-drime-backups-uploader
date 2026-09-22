<?php
/**
 * Uploader lock helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.21
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the single upload-worker lease and state-persistence errors.
 *
 * @since 0.5.21
 */
trait Alynt_Drime_Backups_Uploader_Uploader_Lock {
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
}
