<?php
/**
 * Plugin admin action handlers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin admin action handlers.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Plugin_Admin_Actions {
	/**

	 * Saves settings.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function handle_save_settings() {

		$this->verify_admin_action( 'alynt_drime_backups_save_settings' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified above; settings object sanitizes all fields.
		$raw = isset( $_POST['alynt_drime_backups_settings'] ) && is_array( $_POST['alynt_drime_backups_settings'] ) ? wp_unslash( $_POST['alynt_drime_backups_settings'] ) : array();

		$settings = $this->settings->update( $raw );

		if ( ! $this->settings->is_persisted( $settings ) ) {

			$this->logger->event( 'admin_action', 'error', 'settings_save_failed', 'Settings could not be saved.' );

			$this->redirect( 'settings_save_failed' );

		}

		$this->logger->event( 'admin_action', 'info', 'settings_saved', 'Settings saved.' );

		$this->redirect( 'settings_saved' );
	}

	/**

	 * Tests Drime connection.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function handle_test_connection() {

		$this->verify_admin_action( 'alynt_drime_backups_test_connection' );

		$result = $this->client->test_connection();

		if ( is_wp_error( $result ) ) {

			$this->logger->event( 'external_api', 'error', 'connection_test_failed', 'Drime connection test failed.', array( 'reason' => $result->get_error_message() ) );

			$this->redirect( 'action_failed' );

		}

		$this->logger->event( 'external_api', 'info', 'connection_test_succeeded', 'Drime connection test succeeded.' );

		$this->redirect( 'connected' );
	}

	/**

	 * Scans now.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function handle_scan_now() {

		$this->verify_admin_action( 'alynt_drime_backups_scan_now' );

		$this->cron_health->record_manual_scan();

		$result = $this->scan_and_queue();

		if ( ! empty( $result['errors'] ) ) {

			$this->redirect( 'scan_failed' );

		}

		$this->redirect( 'scan_complete' );
	}

	/**

	 * Uploads next queued backup.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function handle_upload_next() {

		$this->verify_admin_action( 'alynt_drime_backups_upload_next' );

		$item   = $this->queue->next();
		$result = $this->uploader->upload_next();

		if ( is_wp_error( $result ) ) {

			$this->logger->event( 'upload', 'error', 'manual_upload_failed', 'Manual upload failed.', array( 'reason' => $result->get_error_message() ) );
			$this->notify_manual_upload_failure( $item, $result );

			$this->redirect( 'action_failed' );

		}

		$this->redirect( 'upload_done' );
	}

	/**
	 * Previews remote Drime retention candidates.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function handle_preview_remote_retention() {

		$this->verify_admin_action( 'alynt_drime_backups_preview_remote_retention' );

		$candidates = $this->retention->preview();

		$this->logger->event( 'retention', 'info', 'retention_previewed', 'Remote Drime retention previewed.', array( 'candidates' => count( $candidates ) ) );

		$this->redirect( empty( $candidates ) ? 'retention_preview_empty' : 'retention_preview_ready' );
	}

	/**
	 * Runs manual remote Drime retention cleanup.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function handle_run_remote_retention() {

		$this->verify_admin_action( 'alynt_drime_backups_run_remote_retention' );

		$result = $this->retention->cleanup();

		if ( is_wp_error( $result ) || ! empty( $result['failed'] ) ) {

			$this->redirect( 'retention_failed' );

		}

		$this->redirect( empty( $result['trashed'] ) ? 'retention_nothing_done' : 'retention_done' );
	}

	/**
	 * Clears active multipart upload state.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function handle_clear_active_upload() {

		$this->verify_admin_action( 'alynt_drime_backups_clear_active_upload' );

		$active = $this->uploader->clear_active_upload();

		if ( is_wp_error( $active ) ) {

			$this->logger->event( 'upload', 'error', 'active_upload_clear_failed', 'Active upload state could not be cleared.', array( 'reason' => $active->get_error_message() ) );

			$this->redirect( 'action_failed' );

		}

		$this->logger->event(
			'upload',
			'warning',
			'active_upload_cleared',
			'Active upload state was cleared by an administrator.',
			array(
				'file'       => isset( $active['remote_name'] ) ? basename( (string) $active['remote_name'] ) : '',
				'signature'  => isset( $active['signature'] ) ? (string) $active['signature'] : '',
				'updated_at' => isset( $active['updated_at'] ) ? absint( $active['updated_at'] ) : 0,
			)
		);

		$this->redirect( 'active_upload_cleared' );
	}

	/**

	 * Exports diagnostics as JSON.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function handle_export_diagnostics() {

		$this->verify_admin_action( 'alynt_drime_backups_export_diagnostics' );

		nocache_headers();

		header( 'Content-Type: application/json; charset=utf-8' );

		header( 'Content-Disposition: attachment; filename="alynt-drime-wpvivid-diagnostics-' . gmdate( 'Ymd-His' ) . '.json"' );

		$json = wp_json_encode( $this->logger->export_payload(), JSON_PRETTY_PRINT );

		if ( false === $json ) {

			$json = '{"error":"Diagnostics export could not be encoded."}';

		}

		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download payload.

		exit;
	}

	/**

	 * Clears diagnostics events.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function handle_clear_diagnostics() {

		$this->verify_admin_action( 'alynt_drime_backups_clear_diagnostics' );

		$this->logger->clear();

		$this->redirect( 'diagnostics_cleared' );
	}

	/**
	 * Lists Drime folders for the admin folder browser.
	 *
	 * @return void
	 *
	 * @since 0.3.0
	 */
	public function handle_ajax_list_folders() {

		$this->verify_ajax_action();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by verify_ajax_action().
		$folder_hash = isset( $_POST['folder_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_hash'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by verify_ajax_action().
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by verify_ajax_action().
		$query  = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
		$result = $this->folder_browser->list_folders( $folder_hash, $page, $query );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Lists Drime workspaces for the admin workspace selector.
	 *
	 * @return void
	 *
	 * @since 0.5.0
	 */
	public function handle_ajax_list_workspaces() {

		$this->verify_ajax_action();

		$result = $this->workspace_browser->list_workspaces();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Previews the resolved Drime upload destination.
	 *
	 * @return void
	 *
	 * @since 0.3.0
	 */
	public function handle_ajax_preview_destination() {

		$this->verify_ajax_action();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by verify_ajax_action().
		$parent_folder_id = isset( $_POST['parent_folder_id'] ) ? sanitize_text_field( wp_unslash( $_POST['parent_folder_id'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by verify_ajax_action().
		$parent_folder_hash = isset( $_POST['parent_folder_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['parent_folder_hash'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by verify_ajax_action().
		$relative_path = isset( $_POST['relative_path'] ) ? sanitize_text_field( wp_unslash( $_POST['relative_path'] ) ) : '';
		$result        = $this->folder_browser->preview_destination( $parent_folder_id, $parent_folder_hash, $relative_path );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( $result );
	}

	/**

	 * Verifies an admin action.
	 *
	 * @param string $action Action.

	 * @return void
	 */
	private function verify_admin_action( $action ) {

		if ( ! current_user_can( 'manage_options' ) ) {

			wp_die( esc_html__( 'You do not have permission to manage this plugin.', 'alynt-drime-backups-uploader' ) );

		}

		check_admin_referer( $action );
	}

	/**
	 * Verifies an AJAX admin action.
	 *
	 * @return void
	 */
	private function verify_ajax_action() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage this plugin.', 'alynt-drime-backups-uploader' ) ), 403 );
		}

		if ( ! check_ajax_referer( 'alynt_drime_backups_folder_browser', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'The folder browser request could not be verified.', 'alynt-drime-backups-uploader' ) ), 403 );
		}
	}

	/**

	 * Redirects to admin page.
	 *
	 * @param string $notice Notice key.

	 * @return void
	 */
	private function redirect( $notice ) {

		wp_safe_redirect(
			add_query_arg(
				array(

					'page'         => 'alynt-drime-backups-uploader',

					'alynt_notice' => sanitize_key( $notice ),

				),
				admin_url( 'tools.php' )
			)
		);

		exit;
	}
}
