<?php
/**
 * Strict private fact slices; semantic binding matches existing version-1 evidence.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No public accessor, normalization, option reader, identity generation or permission inference. */
trait Alynt_Drime_Backups_Uploader_Cadence_Authority_Context_Facts {
	/**
	 * Validate exact type-preserving slices and build only private comparison facts.
	 *
	 * @param int $now Valid UTC time.
	 * @return array|false No authenticated consent or history is inferred.
	 */
	private function read_facts( $now ) {
		$s = call_user_func( $this->settings_reader );
		if ( ! $this->fact_keys( $s, array( 'site_uuid', 'auto_scan_enabled' ) ) || ! $this->fact_uuid( $s['site_uuid'] ) || ! is_bool( $s['auto_scan_enabled'] ) ) {
			return false;
		}
		$c       = call_user_func( $this->connection_reader );
		$strings = array( 'dashboard_origin', 'expected_client_origin', 'dashboard_site_public_id', 'polling_key_id', 'polling_credential_verifier', 'action_key_id', 'action_public_key' );
		$times   = array( 'paired_at', 'remote_actions_opted_in_at' );
		$keys    = array_merge( array( 'connection_status', 'status_endpoint_enabled', 'remote_actions_enabled' ), $strings, $times );
		if ( ! $this->fact_keys( $c, $keys )
			|| 'paired' !== $c['connection_status'] || true !== $c['status_endpoint_enabled'] || true !== $c['remote_actions_enabled'] ) {
			return false;
		}
		$binding = array(
			'domain'    => 'adb-cadence-authority-evidence-v1',
			'site_uuid' => $s['site_uuid'],
		);
		foreach ( $strings as $field ) {
			if ( ! is_string( $c[ $field ] ) || '' === $c[ $field ] || strlen( $c[ $field ] ) > 2048 ) {
				return false;
			}
			$binding[ $field ] = $c[ $field ];
		}
		if ( ! $this->fact_uuid( $c['dashboard_site_public_id'] ) || ! $this->fact_origin( $c['dashboard_origin'] ) || ! $this->fact_origin( $c['expected_client_origin'] )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/D', $c['polling_credential_verifier'] )
			|| 1 !== preg_match( '/^[A-Za-z0-9_.-]{1,128}$/D', $c['polling_key_id'] ) || 1 !== preg_match( '/^[A-Za-z0-9_.-]{1,128}$/D', $c['action_key_id'] )
			|| 1 !== preg_match( '/^[A-Za-z0-9_-]{32,128}$/D', $c['action_public_key'] ) ) {
			return false;
		}
		foreach ( $times as $field ) {
			if ( ! is_int( $c[ $field ] ) || $c[ $field ] <= 0 || $c[ $field ] > $now ) {
				return false;
			}
			$binding[ $field ] = $c[ $field ];
		}
		$raw = wp_json_encode( $binding );
		return is_string( $raw ) ? array(
			'binding'           => hash( 'sha256', $raw ),
			'auto_scan_enabled' => $s['auto_scan_enabled'],
		) : false;
	}

	/**
	 * Exact internal keys, no unknown trust assertions or nested state traversal.
	 *
	 * @param mixed $value Provider slice.
	 * @param array $keys Exact fields.
	 * @return bool
	 */
	private function fact_keys( $value, array $keys ) {
		return is_array( $value ) && count( $value ) === count( $keys ) && ! array_diff( $keys, array_keys( $value ) );
	}

	/**
	 * Existing lowercase UUID shape; no trimming/coercion or generation.
	 *
	 * @param mixed $value Identity.
	 * @return bool
	 */
	private function fact_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/D', $value );
	}

	/**
	 * Narrow canonical HTTPS origin subset, not normalization or network authorization.
	 *
	 * @param string $value Bounded raw origin.
	 * @return bool
	 */
	private function fact_origin( $value ) {
		if ( 1 !== preg_match( '~^https://[a-z0-9][a-z0-9.-]*(?::[0-9]{1,5})?$~D', $value ) ) {
			return false;
		}
		$parts = parse_url( $value );
		return is_array( $parts ) && ( ! isset( $parts['port'] ) || ( $parts['port'] > 0 && $parts['port'] <= 65535 ) );
	}
}
