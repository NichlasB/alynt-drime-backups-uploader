<?php
/**
 * Strict new-domain form grammar only, never authorization or issuance.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Explicitly test-loaded; no hooks, providers or storage. */
final class Alynt_Drime_Backups_Uploader_Registration_Request_Contract {
	/**
	 * Exact ordered command vocabulary, independent of posted field order.
	 *
	 * @param mixed $purpose Fixed purpose.
	 * @return array
	 */
	public static function fields( $purpose ) {
		if ( in_array( $purpose, array( 'observe_registration', 'observe_reservation' ), true ) ) {
			return array( 'contract', 'purpose', 'command_id', 'site_uuid', 'observed_command_id', 'issued_at', 'expires_at', 'confirm' );
		}
		if ( ! in_array( $purpose, array( 'register_closed', 'reserve_wpvivid_intent' ), true ) ) {
			return array();
		}
		$keys = array( 'contract', 'purpose', 'command_id', 'site_uuid', 'enrollment_binding', 'expected_generation', 'expected_ledger_revision', 'context_digest', 'issued_at', 'expires_at', 'confirm' );
		return 'register_closed' === $purpose ? $keys : array_merge( $keys, array( 'operation_id' ) );
	}

	/**
	 * Bound both raw and once-unslashed JSON, including nonce/key overhead.
	 *
	 * @param mixed $body Untrusted flat form.
	 * @return array|false
	 */
	private static function strings( $body ) {
		if ( ! is_array( $body ) || count( $body ) > 13 ) {
			return false;
		}
		$out   = array();
		$total = 0;
		foreach ( $body as $key => $value ) {
			if ( null !== ReflectionReference::fromArrayElement( $body, $key ) || ! is_string( $key ) || ! is_string( $value ) || strlen( $key ) > 2048 || strlen( $value ) > 2048 || 1 !== preg_match( '//u', $key . $value ) ) {
				return false;
			}
			$total += strlen( $key ) + strlen( $value );
			if ( $total > 4096 ) {
				return false;
			}
			$out[ $key ] = stripslashes( $value );
		}
		foreach ( array( $body, $out ) as $form ) {
			$raw = json_encode( $form );
			if ( ! is_string( $raw ) || strlen( $raw ) > 4096 ) {
				return false;
			}
		}
		return $out;
	}

	/**
	 * Small fixed issuance intent before any protected context inspection.
	 *
	 * @param mixed $body Flat form.
	 * @return array|false
	 */
	public static function issuance( $body ) {
		$f = self::strings( $body );
		if ( false === $f || ! in_array( $f['purpose'] ?? null, array( 'register_closed', 'reserve_wpvivid_intent' ), true ) || self::confirmation( $f['purpose'] ) !== ( $f['confirm'] ?? null ) || '' === ( $f['nonce'] ?? '' ) ) {
			return false;
		}
		$keys = array( 'purpose', 'confirm', 'nonce' );
		if ( 'reserve_wpvivid_intent' === $f['purpose'] ) {
			$keys[] = 'operation_id';
			if ( ! self::uuid( $f['operation_id'] ?? null ) ) {
				return false;
			}
		}
		return self::keys( $f, $keys ) ? $f : false;
	}

	/**
	 * Normalize typed envelope once, with positive canonical decimals only.
	 *
	 * @param mixed $body Original form.
	 * @return array|false
	 */
	public static function parse( $body ) {
		$f    = self::strings( $body );
		$keys = false === $f ? array() : self::fields( $f['purpose'] ?? null );
		if ( ! $keys || ! self::keys( $f, array_merge( $keys, array( 'nonce' ) ) ) || '' === $f['nonce'] ) {
			return false;
		}
		$e = array();
		foreach ( $keys as $key ) {
			$v = $f[ $key ];
			if ( in_array( $key, array( 'expected_generation', 'expected_ledger_revision', 'issued_at', 'expires_at' ), true ) ) {
				$max = (string) ( PHP_INT_MAX - ( in_array( $key, array( 'issued_at', 'expires_at' ), true ) ? 3600 : 5 ) );
				if ( 1 !== preg_match( '/^[1-9][0-9]*$/D', $v ) || strlen( $v ) > strlen( $max ) || ( strlen( $v ) === strlen( $max ) && strcmp( $v, $max ) > 0 ) ) {
					return false;
				}
				$v = (int) $v;
			}
			$e[ $key ] = $v;
		}
		$observe = in_array( $e['purpose'], array( 'observe_registration', 'observe_reservation' ), true );
		if ( ( $observe ? 'scan_registration_observation_v2' : 'scan_registration_request_v2' ) !== $e['contract'] || self::confirmation( $e['purpose'] ) !== $e['confirm'] || $e['expires_at'] <= $e['issued_at'] || $e['expires_at'] - $e['issued_at'] > 300 ) {
			return false;
		}
		foreach ( array( 'command_id', 'site_uuid', $observe ? 'observed_command_id' : ( 'register_closed' === $e['purpose'] ? 'command_id' : 'operation_id' ) ) as $key ) {
			if ( ! self::uuid( $e[ $key ] ) ) {
				return false;
			}
		}
		if ( ! $observe && ( 1 !== preg_match( '/^[a-f0-9]{64}$/D', $e['context_digest'] ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $e['enrollment_binding'] ) ) ) {
			return false;
		}
		$prefix = $observe ? 'receipt' : ( 'register_closed' === $e['purpose'] ? 'command' : 'reservation' );
		$action = 'alynt_drime_backups_scan_' . ( 'reservation' === $prefix ? 'reservation_command' : 'registration_' . $prefix ) . '_v2:' . hash( 'sha256', json_encode( $e ) );
		return array(
			'envelope' => $e,
			'nonce'    => $f['nonce'],
			'action'   => $action,
		);
	}

	/**
	 * Separate server-blog-bound issuance domain.
	 *
	 * @param int    $blog Server blog ID.
	 * @param string $purpose Fixed issuance purpose.
	 * @return string|false
	 */
	public static function issue_action( $blog, $purpose ) {
		return is_int( $blog ) && $blog > 0 && $blog < PHP_INT_MAX && in_array( $purpose, array( 'register_closed', 'reserve_wpvivid_intent' ), true ) ? 'alynt_drime_backups_scan_registration_issue_v2:' . $blog . ':' . $purpose : false;
	}

	/**
	 * Canonical field set only, no defaults.
	 *
	 * @param array $f Form.
	 * @param array $keys Fields.
	 * @return bool
	 */
	private static function keys( $f, $keys ) {
		return count( $f ) === count( $keys ) && ! array_diff( $keys, array_keys( $f ) );
	}

	/**
	 * UUIDv4 syntax, never a provenance assertion.
	 *
	 * @param mixed $id Value.
	 * @return bool
	 */
	private static function uuid( $id ) {
		return is_string( $id ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $id );
	}

	/**
	 * Fixed confirmation for each known purpose.
	 *
	 * @param string $purpose Purpose.
	 * @return string
	 */
	private static function confirmation( $purpose ) {
		return 'register_closed' === $purpose ? 'register' : ( 'reserve_wpvivid_intent' === $purpose ? 'reserve' : 'observe' );
	}
}
