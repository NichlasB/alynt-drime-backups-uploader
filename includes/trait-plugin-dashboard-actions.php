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

		$action = isset( $raw['connection_action'] ) ? sanitize_key( wp_unslash( $raw['connection_action'] ) ) : '';

		if ( 'complete_pairing' === $action ) {
			if ( empty( $raw['read_only_opt_in'] ) ) {
				$state = $this->dashboard_connection->record_pairing_error( 'client_read_only_opt_in_required' );
			} else {
				$token = isset( $raw['pairing_token'] ) ? (string) wp_unslash( $raw['pairing_token'] ) : '';
				$state = $this->dashboard_connection->complete_pairing_from_token(
					$token,
					home_url(),
					$this->settings->site_uuid(),
					ALYNT_DRIME_BACKUPS_UPLOADER_VERSION
				);
			}
			$notice = empty( $state['last_error_code'] ) ? 'dashboard_connection_paired' : 'dashboard_connection_pairing_failed';
		} else {
			$state  = $this->dashboard_connection->update_shell( $raw );
			$notice = empty( $state['last_error_code'] ) ? 'dashboard_connection_saved' : 'dashboard_connection_invalid_token';
		}

		$this->logger->event(
			'dashboard',
			empty( $state['last_error_code'] ) ? 'info' : 'warning',
			empty( $state['last_error_code'] ) ? 'dashboard_connection_saved' : 'dashboard_connection_failed',
			'Dashboard connection state saved.',
			array(
				'connection_status'       => isset( $state['connection_status'] ) ? (string) $state['connection_status'] : '',
				'status_endpoint_enabled' => ! empty( $state['status_endpoint_enabled'] ),
				'last_error_code'         => isset( $state['last_error_code'] ) ? (string) $state['last_error_code'] : '',
			)
		);

		$this->redirect( $notice );
	}
}
