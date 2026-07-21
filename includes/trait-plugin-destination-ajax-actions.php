<?php
/**
 * Plugin destination browser AJAX action handlers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin destination browser AJAX action handlers.
 *
 * @since 0.4.0
 */
trait Alynt_Drime_Backups_Uploader_Plugin_Destination_Ajax_Actions {
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
	 * @since 0.2.0
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
}
