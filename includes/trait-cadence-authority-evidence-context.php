<?php
/**
 * Authority evidence local enrollment binding; never exports secrets.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Reads existing local pairing; does not create or modify permission. */
trait Alynt_Drime_Backups_Uploader_Cadence_Authority_Evidence_Context {
	/**
	 * Read local context without accepting request-provided enrollment evidence.
	 *
	 * @return array|WP_Error
	 */
	private function context() {
		$state    = $this->connection->get();
		$uuid     = $this->connection->site_uuid_for_remote_actions();
		$required = array( 'connection_status', 'status_endpoint_enabled', 'remote_actions_enabled', 'schedule_rollback_preview_enabled', 'action_key_id', 'dashboard_site_public_id', 'dashboard_origin', 'expected_client_origin', 'polling_key_id', 'polling_credential_verifier', 'action_public_key', 'paired_at', 'remote_actions_opted_in_at' );
		if ( ! is_array( $state ) || array_diff( $required, array_keys( $state ) ) ) {
			return $this->error( 'binding_invalid' );
		}
		if ( ! $this->uuid( $uuid ) || 'paired' !== $state['connection_status'] || true !== $state['status_endpoint_enabled'] || true !== $state['remote_actions_enabled'] ) {
			return $this->error( 'local_opt_in_required' );
		}
		$key = $state['action_key_id'];
		if ( ! is_string( $key ) || 1 !== preg_match( '/^[A-Za-z0-9_.-]{1,128}$/D', $key ) || ! $this->uuid( $state['dashboard_site_public_id'] ) ) {
			return $this->error( 'binding_invalid' );
		}
		$binding = array(
			'domain'    => 'adb-cadence-authority-evidence-v1',
			'site_uuid' => $uuid,
		);
		foreach ( array( 'dashboard_origin', 'expected_client_origin', 'dashboard_site_public_id', 'polling_key_id', 'polling_credential_verifier', 'action_key_id', 'action_public_key' ) as $field ) {
			if ( ! is_string( $state[ $field ] ) || '' === $state[ $field ] || strlen( $state[ $field ] ) > 2048 ) {
				return $this->error( 'binding_invalid' );
			}
			$binding[ $field ] = $state[ $field ];
		}
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/D', $state['polling_credential_verifier'] ) || 1 !== preg_match( '/^[A-Za-z0-9_.-]{1,128}$/D', $state['polling_key_id'] )
			|| 1 !== preg_match( '/^[A-Za-z0-9_-]{32,128}$/D', $state['action_public_key'] ) ) {
			return $this->error( 'binding_invalid' );
		}
		foreach ( array( 'paired_at', 'remote_actions_opted_in_at' ) as $field ) {
			if ( ! is_int( $state[ $field ] ) || $state[ $field ] <= 0 || $state[ $field ] > call_user_func( $this->clock ) ) {
				return $this->error( 'binding_invalid' );
			}
			$binding[ $field ] = $state[ $field ];
		}
		return array(
			'site_uuid'      => $uuid,
			'key_id'         => $key,
			'binding'        => hash( 'sha256', wp_json_encode( $binding ) ),
			'preview_opt_in' => true === $state['schedule_rollback_preview_enabled'],
		);
	}

	/**
	 * Validate an identifier without coercion.
	 *
	 * @param mixed $value UUID.
	 * @return bool
	 */
	private function uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/D', $value );
	}
}
