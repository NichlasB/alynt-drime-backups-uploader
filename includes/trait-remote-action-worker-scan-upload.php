<?php
/**
 * Remote action worker scan/upload helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.21
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds scan counts and schedules the upload worker for scan/upload actions.
 *
 * @since 0.5.21
 */
trait Alynt_Drime_Backups_Uploader_Remote_Action_Worker_Scan_Upload {
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

		if ( function_exists( 'wp_next_scheduled' ) && wp_next_scheduled( Alynt_Drime_Backups_Uploader_Cron::UPLOAD_EVENT ) ) {
			return true;
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
