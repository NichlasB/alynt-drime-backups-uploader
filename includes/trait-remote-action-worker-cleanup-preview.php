<?php
/**
 * Remote action worker cleanup preview handler.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.23
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds read-only cleanup previews for dashboard remote actions.
 *
 * @since 0.5.23
 */
trait Alynt_Drime_Backups_Uploader_Remote_Action_Worker_Cleanup_Preview {
	/**
	 * Handles cleanup preview actions.
	 *
	 * @since 0.5.23
	 *
	 * @param array<string,mixed> $record Stored action record.
	 * @return void
	 */
	private function handle_cleanup_preview( array $record ) {
		$this->store->upsert_action( $record, 'running', 'cleanup_preview_running', __( 'Remote cleanup preview action is running on the client site.', 'alynt-drime-backups-uploader' ) );

		try {
			$preview = $this->cleanup_preview_from_record( $record );
			if ( is_wp_error( $preview ) ) {
				$this->store->upsert_action( $record, 'failed', $preview->get_error_code(), __( 'Remote cleanup preview could not be completed.', 'alynt-drime-backups-uploader' ) );
				return;
			}

			$this->store->upsert_action( $record, 'succeeded', 'cleanup_preview_ready', __( 'Cleanup preview is ready. No files or records were changed.', 'alynt-drime-backups-uploader' ), array(), 0, array(), array(), array(), $preview );
		} catch ( Exception $exception ) {
			unset( $exception );
			$this->store->upsert_action( $record, 'failed', 'cleanup_preview_failed', __( 'Remote cleanup preview failed without storing unsafe error details.', 'alynt-drime-backups-uploader' ) );
		}
	}

	/**
	 * Builds a redacted, aggregate-only cleanup preview.
	 *
	 * @since 0.5.23
	 *
	 * @param array<string,mixed> $record Stored action record.
	 * @return array<string,mixed>|WP_Error
	 */
	private function cleanup_preview_from_record( array $record ) {
		$connection = $this->plugin->dashboard_connection();
		if ( ! $connection instanceof Alynt_Drime_Backups_Uploader_Dashboard_Connection || ! $connection->is_cleanup_preview_enabled() ) {
			return new WP_Error( 'cleanup_preview_opt_in_required', __( 'Cleanup preview is not enabled on this client site.', 'alynt-drime-backups-uploader' ) );
		}

		$request = isset( $record['cleanup_preview'] ) && is_array( $record['cleanup_preview'] ) ? $record['cleanup_preview'] : array();
		if (
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::CLEANUP_CAPABILITY_VERSION !== absint( isset( $request['capability_version'] ) ? $request['capability_version'] : 0 )
			|| Alynt_Drime_Backups_Uploader_Dashboard_Connection::CLEANUP_SCOPE_SAFE_LOCAL !== ( isset( $request['scope'] ) ? sanitize_key( (string) $request['scope'] ) : '' )
			|| empty( $request['categories'] )
			|| ! is_array( $request['categories'] )
		) {
			return new WP_Error( 'action_cleanup_preview_invalid', __( 'The cleanup preview request is invalid.', 'alynt-drime-backups-uploader' ) );
		}

		$categories = array();
		foreach ( $request['categories'] as $category ) {
			if ( is_array( $category ) ) {
				$category = isset( $category['category'] ) ? $category['category'] : '';
			}

			$category = sanitize_key( (string) $category );
			if ( '' !== $category ) {
				$categories[] = $category;
			}
		}
		if ( ! in_array( Alynt_Drime_Backups_Uploader_Dashboard_Connection::CLEANUP_CATEGORY_UPLOADER_TEMP, $categories, true ) ) {
			return new WP_Error( 'cleanup_preview_unknown_category', __( 'The cleanup preview category is not supported.', 'alynt-drime-backups-uploader' ) );
		}

		$category = $this->cleanup_preview_uploader_temp_category();
		$preview  = array(
			'preview_action_id'    => isset( $record['action_id'] ) ? (string) $record['action_id'] : '',
			'capability_version'   => Alynt_Drime_Backups_Uploader_Dashboard_Connection::CLEANUP_CAPABILITY_VERSION,
			'scope'                => Alynt_Drime_Backups_Uploader_Dashboard_Connection::CLEANUP_SCOPE_SAFE_LOCAL,
			'preview_created_at'   => gmdate( 'c' ),
			'expires_at'           => gmdate( 'c', time() + Alynt_Drime_Backups_Uploader_Dashboard_Connection::CLEANUP_PREVIEW_MAX_AGE ),
			'categories'           => array( $category ),
			'total_eligible_count' => $category['eligible_count'],
			'total_approx_bytes'   => $category['approx_bytes'],
			'apply_supported'      => false,
		);

		$preview['preview_fingerprint'] = $this->cleanup_preview_fingerprint( $preview );

		return $preview;
	}

	/**
	 * Returns aggregate-only uploader-owned temp artifact evidence.
	 *
	 * @since 0.5.23
	 *
	 * @return array<string,mixed>
	 */
	private function cleanup_preview_uploader_temp_category() {
		$active     = $this->plugin->queue()->get_active();
		$updated_at = isset( $active['updated_at'] ) ? absint( $active['updated_at'] ) : 0;
		$is_stale   = ! empty( $active ) && ( 0 === $updated_at || $updated_at <= time() - Alynt_Drime_Backups_Uploader_Uploader::STALE_ACTIVE_UPLOAD_SECONDS );

		return array(
			'category'       => Alynt_Drime_Backups_Uploader_Dashboard_Connection::CLEANUP_CATEGORY_UPLOADER_TEMP,
			'eligible_count' => $is_stale ? 1 : 0,
			'approx_bytes'   => 0,
			'age_band'       => $is_stale ? $this->cleanup_preview_age_band( $updated_at ) : 'none',
			'reason_code'    => $is_stale ? 'stale_active_upload_state' : 'no_stale_temp_artifacts',
		);
	}

	/**
	 * Converts a timestamp into a coarse age band without exposing names or paths.
	 *
	 * @since 0.5.23
	 *
	 * @param int $updated_at Timestamp.
	 * @return string
	 */
	private function cleanup_preview_age_band( $updated_at ) {
		if ( $updated_at <= 0 ) {
			return 'unknown_age';
		}

		$age = max( 0, time() - absint( $updated_at ) );
		if ( $age >= 7 * 86400 ) {
			return 'older_than_7_days';
		}
		if ( $age >= 86400 ) {
			return 'older_than_1_day';
		}

		return 'older_than_6_hours';
	}

	/**
	 * Builds a fingerprint over support-safe preview fields only.
	 *
	 * @since 0.5.23
	 *
	 * @param array<string,mixed> $preview Preview.
	 * @return string
	 */
	private function cleanup_preview_fingerprint( array $preview ) {
		$fingerprint_source = array(
			'preview_action_id'    => isset( $preview['preview_action_id'] ) ? (string) $preview['preview_action_id'] : '',
			'capability_version'   => isset( $preview['capability_version'] ) ? absint( $preview['capability_version'] ) : 0,
			'scope'                => isset( $preview['scope'] ) ? sanitize_key( (string) $preview['scope'] ) : '',
			'preview_created_at'   => isset( $preview['preview_created_at'] ) ? (string) $preview['preview_created_at'] : '',
			'expires_at'           => isset( $preview['expires_at'] ) ? (string) $preview['expires_at'] : '',
			'categories'           => isset( $preview['categories'] ) && is_array( $preview['categories'] ) ? $preview['categories'] : array(),
			'total_eligible_count' => isset( $preview['total_eligible_count'] ) ? absint( $preview['total_eligible_count'] ) : 0,
			'total_approx_bytes'   => isset( $preview['total_approx_bytes'] ) ? absint( $preview['total_approx_bytes'] ) : 0,
			'apply_supported'      => false,
		);

		$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $fingerprint_source ) : json_encode( $fingerprint_source );

		return hash( 'sha256', (string) $encoded );
	}
}
