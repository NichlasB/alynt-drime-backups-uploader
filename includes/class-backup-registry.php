<?php
/**
 * Uploaded backup registry.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks uploaded and failed local files.
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_Backup_Registry {
	use Alynt_Drime_Backups_Uploader_Backup_Registry_Failed_Context;
	use Alynt_Drime_Backups_Uploader_Option_Storage;

	const UPLOADED_OPTION       = 'alynt_drime_backups_uploaded_files';
	const FAILED_OPTION         = 'alynt_drime_backups_failed_uploads';
	const DRIME_LOCATION_OPTION = 'alynt_drime_backups_drime_locations';

	/**
	 * Checks whether a signature is uploaded.
	 *
	 * @param string $signature Signature.
	 * @return bool
	 *
	 * @since 0.1.0
	 */
	public function is_uploaded( $signature ) {
		$uploaded = $this->get_uploaded();

		return isset( $uploaded[ $signature ] );
	}

	/**
	 * Marks a file uploaded.
	 *
	 * @param string              $signature Signature.
	 * @param array<string,mixed> $record Record.
	 * @return bool
	 *
	 * @since 0.1.0
	 */
	public function mark_uploaded( $signature, array $record ) {
		$uploaded               = $this->get_uploaded();
		$uploaded[ $signature ] = array_merge(
			$record,
			array(
				'uploaded_at'    => time(),
				'remote_status'  => 'uploaded',
				'remote_updated' => time(),
			)
		);

		if ( ! $this->persist_array_option( self::UPLOADED_OPTION, $uploaded ) ) {
			return false;
		}

		return $this->clear_failed( $signature );
	}

	/**
	 * Marks a file failed.
	 *
	 * @param string              $signature Signature.
	 * @param string              $message Failure message.
	 * @param array<string,mixed> $context Failure context.
	 * @return bool
	 *
	 * @since 0.1.0
	 */
	public function mark_failed( $signature, $message, array $context = array() ) {
		$failed               = $this->get_failed();
		$failed[ $signature ] = array_merge(
			$this->sanitize_failed_context( $context ),
			array(
				'message'   => sanitize_text_field( $message ),
				'failed_at' => time(),
			)
		);

		return $this->persist_array_option( self::FAILED_OPTION, $failed );
	}

	/**
	 * Returns one failed record.
	 *
	 * @param string $signature Signature.
	 * @return array<string,mixed>
	 *
	 * @since 0.5.0
	 */
	public function get_failed_record( $signature ) {
		$failed = $this->get_failed();

		return isset( $failed[ $signature ] ) && is_array( $failed[ $signature ] ) ? $failed[ $signature ] : array();
	}

	/**
	 * Clears a failed record.
	 *
	 * @param string $signature Signature.
	 * @return bool
	 *
	 * @since 0.1.0
	 */
	public function clear_failed( $signature ) {
		$failed = $this->get_failed();
		unset( $failed[ $signature ] );

		return $this->persist_array_option( self::FAILED_OPTION, $failed );
	}

	/**
	 * Marks remote retention state for an uploaded record.
	 *
	 * @param string              $signature Signature.
	 * @param string              $status Remote status.
	 * @param array<string,mixed> $context Optional context.
	 * @return bool
	 *
	 * @since 0.1.0
	 */
	public function mark_remote_retention_status( $signature, $status, array $context = array() ) {
		$uploaded = $this->get_uploaded();

		if ( empty( $uploaded[ $signature ] ) || ! is_array( $uploaded[ $signature ] ) ) {
			return false;
		}

		$uploaded[ $signature ]['remote_status']  = sanitize_key( $status );
		$uploaded[ $signature ]['remote_updated'] = time();

		if ( ! empty( $context ) ) {
			$uploaded[ $signature ]['remote_status_context'] = $this->sanitize_context( $context );
		} else {
			unset( $uploaded[ $signature ]['remote_status_context'] );
		}

		return $this->persist_array_option( self::UPLOADED_OPTION, $uploaded );
	}

	/**
	 * Remembers a resolved Drime parent folder for a relative path.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $relative_path Relative path.
	 * @param int    $parent_id Parent folder ID.
	 * @param int    $base_parent_id Optional selected base folder ID.
	 * @return bool
	 *
	 * @since 0.1.0
	 */
	public function remember_drime_location( $workspace_id, $relative_path, $parent_id, $base_parent_id = 0 ) {
		$workspace_id   = absint( $workspace_id );
		$relative_path  = $this->normalize_relative_path( $relative_path );
		$parent_id      = absint( $parent_id );
		$base_parent_id = absint( $base_parent_id );

		if ( '' === $relative_path || $parent_id <= 0 ) {
			return true;
		}

		$locations = $this->get_drime_locations();
		$locations[ $this->location_key( $workspace_id, $relative_path, $base_parent_id ) ] = array(
			'workspace_id'   => $workspace_id,
			'relative_path'  => $relative_path,
			'base_parent_id' => $base_parent_id,
			'parent_id'      => $parent_id,
			'updated_at'     => time(),
		);

		return $this->persist_array_option( self::DRIME_LOCATION_OPTION, $locations );
	}

	/**
	 * Gets a remembered Drime parent folder for a relative path.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $relative_path Relative path.
	 * @param int    $base_parent_id Optional selected base folder ID.
	 * @return int
	 *
	 * @since 0.1.0
	 */
	public function get_drime_parent_id( $workspace_id, $relative_path, $base_parent_id = 0 ) {
		$workspace_id   = absint( $workspace_id );
		$relative_path  = $this->normalize_relative_path( $relative_path );
		$base_parent_id = absint( $base_parent_id );

		if ( '' === $relative_path ) {
			return 0;
		}

		$locations = $this->get_drime_locations();
		$key       = $this->location_key( $workspace_id, $relative_path, $base_parent_id );

		if ( ! empty( $locations[ $key ] ) && is_array( $locations[ $key ] ) ) {
			return empty( $locations[ $key ]['parent_id'] ) ? 0 : absint( $locations[ $key ]['parent_id'] );
		}

		$legacy_key = $this->location_key( $workspace_id, $relative_path );
		if ( empty( $locations[ $legacy_key ] ) || ! is_array( $locations[ $legacy_key ] ) ) {
			return 0;
		}

		if ( $base_parent_id > 0 && ! empty( $locations[ $legacy_key ]['base_parent_id'] ) && absint( $locations[ $legacy_key ]['base_parent_id'] ) !== $base_parent_id ) {
			return 0;
		}

		return empty( $locations[ $legacy_key ]['parent_id'] ) ? 0 : absint( $locations[ $legacy_key ]['parent_id'] );
	}

	/**
	 * Returns uploaded records.
	 *
	 * @return array<string,array<string,mixed>>
	 *
	 * @since 0.1.0
	 */
	public function get_uploaded() {
		return $this->get_array_option( self::UPLOADED_OPTION );
	}

	/**
	 * Returns failed records.
	 *
	 * @return array<string,array<string,mixed>>
	 *
	 * @since 0.1.0
	 */
	public function get_failed() {
		return $this->get_array_option( self::FAILED_OPTION );
	}

	/**
	 * Returns Drime relative path location records.
	 *
	 * @return array<string,array<string,mixed>>
	 *
	 * @since 0.1.0
	 */
	public function get_drime_locations() {
		return $this->get_array_option( self::DRIME_LOCATION_OPTION );
	}

	/**
	 * Normalizes a Drime relative path.
	 *
	 * @param string $relative_path Relative path.
	 * @return string
	 */
	private function normalize_relative_path( $relative_path ) {
		$relative_path = trim( str_replace( '\\', '/', (string) $relative_path ) );
		$relative_path = '/' . trim( $relative_path, '/' );

		return '/' === $relative_path ? '' : $relative_path;
	}

	/**
	 * Builds the option key for a Drime relative path.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $relative_path Relative path.
	 * @param int    $base_parent_id Optional selected base folder ID.
	 * @return string
	 */
	private function location_key( $workspace_id, $relative_path, $base_parent_id = 0 ) {
		$base_parent_id = absint( $base_parent_id );
		$key            = absint( $workspace_id ) . '|';
		$key           .= $base_parent_id > 0 ? $base_parent_id . '|' : '';
		return hash( 'sha256', $key . $relative_path );
	}

	/**
	 * Sanitizes small registry context values.
	 *
	 * @param array<string,mixed> $context Context.
	 * @return array<string,string|int>
	 */
	private function sanitize_context( array $context ) {
		$sanitized = array();

		foreach ( $context as $key => $value ) {
			$key = sanitize_key( $key );
			if ( '' === $key ) {
				continue;
			}

			$sanitized[ $key ] = is_numeric( $value ) ? absint( $value ) : sanitize_text_field( (string) $value );
		}

		return $sanitized;
	}
}
