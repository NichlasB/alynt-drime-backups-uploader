<?php
/**
 * Fixed read-only observation intent; no issuance, authority or target selection.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Separate from every existing closed-writer command/receipt nonce domain. */
final class Alynt_Drime_Backups_Uploader_Scan_Observation_Request {
	/**
	 * Strict once-unslashed flat form and independently bounded original deadline.
	 *
	 * @param mixed $body Original request data.
	 * @return array|false Typed envelope and nonce, not authentication.
	 */
	public static function parse( $body ) {
		$keys = array( 'contract', 'purpose', 'command_id', 'site_uuid', 'issued_at', 'expires_at', 'confirm', 'nonce' );
		if ( ! is_array( $body ) || count( $body ) !== count( $keys ) || array_diff( $keys, array_keys( $body ) ) ) {
			return false;
		}
		$bytes = 0;
		$f     = array();
		foreach ( $keys as $key ) {
			$v = $body[ $key ];
			if ( ! is_string( $v ) || strlen( $v ) > 2048 || 1 !== preg_match( '//u', $v ) ) {
				return false;
			}
			$bytes    += strlen( $key ) + strlen( $v );
			$f[ $key ] = stripslashes( $v );
		}
		if ( $bytes > 4096 || strlen( json_encode( $f ) ) > 4096 || 'scan_effect_observation_v1' !== $f['contract'] || 'observe_closed_manifest' !== $f['purpose'] || 'observe' !== $f['confirm'] || '' === $f['nonce'] ) {
			return false;
		}
		foreach ( array( 'command_id', 'site_uuid' ) as $key ) {
			if ( 1 !== preg_match( '/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/D', $f[ $key ] ) ) {
				return false;
			}
		}
		$max = (string) ( PHP_INT_MAX - 3600 );
		foreach ( array( 'issued_at', 'expires_at' ) as $key ) {
			$v = $f[ $key ];
			if ( 1 !== preg_match( '/^[1-9][0-9]*$/D', $v ) || strlen( $v ) > strlen( $max ) || ( strlen( $v ) === strlen( $max ) && strcmp( $v, $max ) > 0 ) ) {
				return false;
			}
			$f[ $key ] = (int) $v;
		}
		if ( $f['expires_at'] <= $f['issued_at'] || $f['expires_at'] - $f['issued_at'] > 300 ) {
			return false;
		}
		$nonce = $f['nonce'];
		unset( $f['nonce'] );
		return array(
			'envelope' => $f,
			'nonce'    => $nonce,
			'action'   => 'alynt_drime_backups_scan_observation_v1:' . hash( 'sha256', json_encode( $f ) ),
		);
	}
}
