<?php
/**
 * Plugin dashboard connection admin action handlers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin dashboard connection admin action handlers.
 *
 * @since 0.5.3
 */
trait Alynt_Drime_Backups_Uploader_Plugin_Dashboard_Actions {
	/**
	 * Saves local dashboard pairing shell state.
	 *
	 * @return void
	 */
	public function handle_save_dashboard_connection() {
		$this->verify_admin_action( 'alynt_drime_backups_save_dashboard_connection' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified above; dashboard connection storage sanitizes all fields.
		$raw = isset( $_POST['alynt_drime_backups_dashboard_connection'] ) && is_array( $_POST['alynt_drime_backups_dashboard_connection'] ) ? wp_unslash( $_POST['alynt_drime_backups_dashboard_connection'] ) : array();

		$state = $this->dashboard_connection->update_shell( $raw );
		$this->logger->event(
			'dashboard',
			'info',
			'dashboard_connection_shell_saved',
			'Dashboard pairing shell state saved.',
			array(
				'connection_status'       => isset( $state['connection_status'] ) ? (string) $state['connection_status'] : '',
				'status_endpoint_enabled' => ! empty( $state['status_endpoint_enabled'] ),
			)
		);

		$this->redirect( 'dashboard_connection_saved' );
	}
}
