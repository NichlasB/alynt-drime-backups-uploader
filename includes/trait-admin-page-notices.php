<?php
/**
 * Admin page notice rendering.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page notice rendering.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Admin_Page_Notices {
	/**
	 * Renders a notice.
	 *
	 * @param string $notice Notice key.
	 * @return void
	 */
	private function render_notice( $notice ) {
		$messages = array(
			'settings_saved'               => array( 'success', __( 'Settings saved.', 'alynt-drime-backups-uploader' ) ),
			'connected'                    => array( 'success', __( 'Connected to Drime successfully.', 'alynt-drime-backups-uploader' ) ),
			'scan_complete'                => array( 'success', __( 'Backup scan completed.', 'alynt-drime-backups-uploader' ) ),
			'scan_failed'                  => array( 'error', __( 'The backup scan could not be completed. Check the detected backup path and recent events, then try again.', 'alynt-drime-backups-uploader' ) ),
			'upload_done'                  => array( 'success', __( 'Upload completed.', 'alynt-drime-backups-uploader' ) ),
			'upload_failed'                => array( 'error', __( 'The backup upload could not be completed. Review recent events, adjust the settings if needed, and try again.', 'alynt-drime-backups-uploader' ) ),
			'failure_email_test_sent'      => array( 'success', __( 'Test email handed to the WordPress mail stack.', 'alynt-drime-backups-uploader' ) ),
			'failure_email_test_failed'    => array( 'error', __( 'The test email could not be sent. Check the saved recipients and site mail configuration, then try again.', 'alynt-drime-backups-uploader' ) ),
			'retention_preview_ready'      => array( 'success', __( 'Remote retention preview completed. Review the candidate count below before running cleanup.', 'alynt-drime-backups-uploader' ) ),
			'retention_preview_empty'      => array( 'success', __( 'Remote retention preview completed. No eligible Drime files were found.', 'alynt-drime-backups-uploader' ) ),
			'retention_done'               => array( 'success', __( 'Remote retention cleanup completed. Eligible Drime files were moved to trash.', 'alynt-drime-backups-uploader' ) ),
			'retention_nothing_done'       => array( 'success', __( 'Remote retention cleanup completed. No Drime files needed cleanup.', 'alynt-drime-backups-uploader' ) ),
			'retention_failed'             => array( 'error', __( 'Remote retention cleanup could not be completed for every candidate. Review recent events before trying again.', 'alynt-drime-backups-uploader' ) ),
			'active_upload_cleared'        => array( 'success', __( 'Active upload state cleared.', 'alynt-drime-backups-uploader' ) ),
			'failed_upload_requeued'       => array( 'success', __( 'Failed upload requeued. Run the upload worker when you are ready to retry it.', 'alynt-drime-backups-uploader' ) ),
			'failed_upload_missing'        => array( 'error', __( 'The failed upload could not be requeued because the local file is no longer readable.', 'alynt-drime-backups-uploader' ) ),
			'failed_upload_requeue_failed' => array( 'error', __( 'The failed upload could not be requeued. Review recent events, then try again.', 'alynt-drime-backups-uploader' ) ),
			'diagnostics_cleared'          => array( 'success', __( 'Diagnostics cleared.', 'alynt-drime-backups-uploader' ) ),
			'diagnostics_clear_failed'     => array( 'error', __( 'Diagnostics could not be cleared. Confirm the site database is writable, then try again.', 'alynt-drime-backups-uploader' ) ),
			'settings_validation_failed'   => array( 'error', __( 'Settings were not saved because the selected Drime workspace is not allowed for backup destinations. Choose an allowed non-personal workspace, then save again.', 'alynt-drime-backups-uploader' ) ),
			'settings_save_failed'         => array( 'error', __( 'Settings could not be saved. Confirm the site database is writable, then try again.', 'alynt-drime-backups-uploader' ) ),
			'action_failed'                => array( 'error', __( 'The action could not be completed. Review the recent events, adjust the settings, and try again.', 'alynt-drime-backups-uploader' ) ),
		);

		if ( empty( $messages[ $notice ] ) ) {
			return;
		}

		list( $type, $message ) = $messages[ $notice ];
		$role                   = 'error' === $type ? 'alert' : 'status';

		if ( 'retention_failed' === $notice ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Notice query args are read-only feedback values.
			$failed = isset( $_GET['failed'] ) ? absint( wp_unslash( $_GET['failed'] ) ) : 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Notice query args are read-only feedback values.
			$trashed = isset( $_GET['trashed'] ) ? absint( wp_unslash( $_GET['trashed'] ) ) : 0;

			if ( $failed > 0 ) {
				$message = sprintf(
					/* translators: 1: number of failed files, 2: number of files moved to trash. */
					__( 'Remote retention cleanup could not be completed for %1$d candidate(s). %2$d file(s) were moved to trash. Review recent events before trying again.', 'alynt-drime-backups-uploader' ),
					$failed,
					$trashed
				);
			}
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible" role="%2$s"><p>%3$s</p></div>',
			esc_attr( $type ),
			esc_attr( $role ),
			esc_html( $message )
		);
	}
}
