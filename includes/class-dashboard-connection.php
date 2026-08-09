<?php
/**
 * Dashboard connection storage.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores local central-dashboard pairing state.
 *
 * This class intentionally does not expose a status endpoint, parse pairing
 * tokens, or store raw credential material. Version 1 begins disabled and
 * remains disabled until a later authenticated pairing slice completes.
 *
 * @since 0.5.3
 */
class Alynt_Drime_Backups_Uploader_Dashboard_Connection {
	const OPTION_NAME     = 'alynt_drime_backups_dashboard_connection';
	const STATUS_DISABLED = 'disabled';
	const STATUS_READY    = 'ready';
	const STATUS_PAIRED   = 'paired';
	const STATUS_REVOKED  = 'revoked';

	/**
	 * Returns default connection state.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'connection_status'           => self::STATUS_DISABLED,
			'dashboard_origin'            => '',
			'dashboard_site_public_id'    => '',
			'polling_key_id'              => '',
			'polling_credential_verifier' => '',
			'paired_at'                   => 0,
			'last_authenticated_read_at'  => 0,
			'status_endpoint_enabled'     => false,
		);
	}

	/**
	 * Ensures the dashboard connection option exists with autoload disabled.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::defaults(), '', false );
		}
	}

	/**
	 * Returns stored connection state merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public function get() {
		$state = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $state ) ) {
			$state = array();
		}

		return $this->sanitize_state( array_merge( self::defaults(), $state ) );
	}

	/**
	 * Saves local shell intent without enabling the status endpoint.
	 *
	 * @param array<string,mixed> $raw Raw admin input.
	 * @return array<string,mixed>
	 */
	public function update_shell( array $raw ) {
		$current = $this->get();
		$state   = $current;

		$action = isset( $raw['connection_action'] ) ? sanitize_key( wp_unslash( $raw['connection_action'] ) ) : '';

		if ( 'prepare' === $action && ! empty( $raw['read_only_opt_in'] ) ) {
			$state['connection_status'] = self::STATUS_READY;
		} elseif ( 'disable' === $action ) {
			$state = self::defaults();
		}

		$state['status_endpoint_enabled'] = false;
		update_option( self::OPTION_NAME, $state, false );
		$this->sync_option_cache( $state );

		return $state;
	}

	/**
	 * Returns whether the future external status endpoint may be exposed.
	 *
	 * @return bool
	 */
	public function is_status_endpoint_enabled() {
		$state = $this->get();

		return self::STATUS_PAIRED === $state['connection_status'] && ! empty( $state['status_endpoint_enabled'] );
	}

	/**
	 * Sanitizes stored state.
	 *
	 * @param array<string,mixed> $state State.
	 * @return array<string,mixed>
	 */
	private function sanitize_state( array $state ) {
		$defaults = self::defaults();
		$status   = isset( $state['connection_status'] ) ? sanitize_key( $state['connection_status'] ) : self::STATUS_DISABLED;

		if ( ! in_array( $status, array( self::STATUS_DISABLED, self::STATUS_READY, self::STATUS_PAIRED, self::STATUS_REVOKED ), true ) ) {
			$status = self::STATUS_DISABLED;
		}

		return array(
			'connection_status'           => $status,
			'dashboard_origin'            => isset( $state['dashboard_origin'] ) ? esc_url_raw( (string) $state['dashboard_origin'] ) : $defaults['dashboard_origin'],
			'dashboard_site_public_id'    => isset( $state['dashboard_site_public_id'] ) ? $this->sanitize_token_identifier( (string) $state['dashboard_site_public_id'] ) : $defaults['dashboard_site_public_id'],
			'polling_key_id'              => isset( $state['polling_key_id'] ) ? $this->sanitize_token_identifier( (string) $state['polling_key_id'] ) : $defaults['polling_key_id'],
			'polling_credential_verifier' => isset( $state['polling_credential_verifier'] ) ? $this->sanitize_hash( (string) $state['polling_credential_verifier'] ) : $defaults['polling_credential_verifier'],
			'paired_at'                   => isset( $state['paired_at'] ) ? max( 0, absint( $state['paired_at'] ) ) : $defaults['paired_at'],
			'last_authenticated_read_at'  => isset( $state['last_authenticated_read_at'] ) ? max( 0, absint( $state['last_authenticated_read_at'] ) ) : $defaults['last_authenticated_read_at'],
			'status_endpoint_enabled'     => self::STATUS_PAIRED === $status && ! empty( $state['status_endpoint_enabled'] ),
		);
	}

	/**
	 * Sanitizes a non-secret identifier.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function sanitize_token_identifier( $value ) {
		return substr( preg_replace( '/[^A-Za-z0-9_\-\.]/', '', (string) $value ), 0, 128 );
	}

	/**
	 * Sanitizes a one-way verifier hash.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function sanitize_hash( $value ) {
		return preg_match( '/^[a-f0-9]{64}$/', (string) $value ) ? (string) $value : '';
	}

	/**
	 * Syncs the option cache after mutation.
	 *
	 * @param array<string,mixed> $state State.
	 * @return void
	 */
	private function sync_option_cache( array $state ) {
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::OPTION_NAME, 'options' );
		}
		if ( function_exists( 'wp_cache_set' ) ) {
			wp_cache_set( self::OPTION_NAME, $state, 'options' );
		}
	}
}
