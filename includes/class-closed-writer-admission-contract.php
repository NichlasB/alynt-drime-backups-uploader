<?php
/**
 * Pure, test-loaded closed-writer request contract; no real nonce or admission.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Fixed fixture domain; never chooses authenticated provenance from a form. */
final class Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract {
	const CONTRACT = 'closed_writer_request_v1';
	const SOURCE   = 'closed_writer_admission_fixture_v1';
	const PREFIX   = 'alynt_drime_backups_closed_writer_fixture_';

	/**
	 * Exact purpose-specific typed field order.
	 *
	 * @param string $purpose Candidate purpose.
	 * @return array
	 */
	public static function fields( $purpose ) {
		$common = array( 'contract', 'purpose', 'command_id', 'site_uuid', 'enrollment_binding', 'expected_generation', 'issued_at', 'expires_at', 'confirm' );
		$extras = array(
			'upgrade_closed' => array( 'origin_digest' ),
			'begin_disable'  => array( 'context_digest', 'before_hash', 'after_hash' ),
			'abandon_closed' => array( 'operation_id', 'closure_generation', 'context_digest', 'observed_hash' ),
			'receipt_read'   => array(),
		);
		if ( ! is_string( $purpose ) || ! isset( $extras[ $purpose ] ) ) {
			return array();
		}
		return 'receipt_read' === $purpose ? array( 'contract', 'purpose', 'command_id', 'site_uuid', 'observed_command_id', 'issued_at', 'expires_at', 'confirm' ) : array_merge( $common, $extras[ $purpose ] );
	}

	/**
	 * Validate and normalize a form once; no WP reads or scalar coercion.
	 *
	 * @param mixed $body Slashed scalar-string form.
	 * @return array|false Typed envelope and nonce.
	 */
	public static function parse( $body ) {
		$body = self::strings( $body );
		if ( false === $body || ! isset( $body['purpose'] ) ) {
			return false;
		}
		$fields = self::fields( $body['purpose'] );
		if ( ! $fields || ! self::keys( $body, array_merge( $fields, array( 'nonce' ) ) ) || '' === $body['nonce'] ) {
			return false;
		}
		$typed = array();
		foreach ( $fields as $key ) {
			$value = $body[ $key ];
			if ( in_array( $key, array( 'expected_generation', 'closure_generation', 'issued_at', 'expires_at' ), true ) ) {
				if ( ! self::decimal( $value, in_array( $key, array( 'issued_at', 'expires_at' ), true ) ) ) {
					return false;
				}
				$value = (int) $value;
			}
			$typed[ $key ] = $value;
		}
		return self::valid( $typed ) ? array(
			'envelope' => $typed,
			'nonce'    => $body['nonce'],
		) : false;
	}

	/**
	 * Bound raw and once-unslashed form bytes before interpreting fields.
	 *
	 * @param mixed $body Form.
	 * @return array|false
	 */
	public static function strings( $body ) {
		if ( ! is_array( $body ) || count( $body ) > 16 ) {
			return false;
		}
		$raw = 0;
		$out = array();
		foreach ( $body as $key => $value ) {
			if ( ! is_string( $key ) || ! is_string( $value ) || strlen( $value ) > 2048 || 1 !== preg_match( '//u', $key . $value ) ) {
				return false;
			}
			$raw += strlen( $key ) + strlen( $value );
			if ( $raw > 4096 ) {
				return false;
			}
			$out[ $key ] = stripslashes( $value );
		}
		$encoded = json_encode( $out );
		return is_string( $encoded ) && strlen( $encoded ) <= 4096 ? $out : false;
	}

	/**
	 * Exact normalized envelope, including positive bounded independent window.
	 *
	 * @param array $e Envelope.
	 * @return bool
	 */
	public static function valid( array $e ) {
		$fields = self::fields( $e['purpose'] ?? null );
		if ( ! $fields || ! self::keys( $e, $fields ) || self::CONTRACT !== $e['contract'] ) {
			return false;
		}
		$confirm = array(
			'upgrade_closed' => 'close',
			'begin_disable'  => 'disable',
			'abandon_closed' => 'abandon',
			'receipt_read'   => 'observe',
		);
		foreach ( $e as $key => $value ) {
			if ( in_array( $key, array( 'issued_at', 'expires_at', 'expected_generation', 'closure_generation' ), true ) ) {
				$is_clock = in_array( $key, array( 'issued_at', 'expires_at' ), true );
				$ceiling  = $is_clock ? PHP_INT_MAX - 3600 : PHP_INT_MAX - 1;
				if ( ! is_int( $value ) || $value < ( $is_clock ? 1 : 0 ) || $value > $ceiling ) {
					return false;
				}
			} elseif ( ! is_string( $value ) ) {
				return false;
			} elseif ( in_array( $key, array( 'command_id', 'site_uuid', 'operation_id', 'observed_command_id' ), true ) && ! self::uuid( $value ) ) {
				return false;
			} elseif ( in_array( $key, array( 'enrollment_binding', 'origin_digest', 'context_digest', 'before_hash', 'after_hash', 'observed_hash' ), true ) && ! self::digest( $value ) ) {
				return false;
			}
		}
		return $confirm[ $e['purpose'] ] === $e['confirm'] && $e['expires_at'] > $e['issued_at'] && $e['expires_at'] - $e['issued_at'] <= 300;
	}

	/**
	 * Fixture nonce action binds every normalized field, never a signature.
	 *
	 * @param array $e Envelope.
	 * @return string|false
	 */
	public static function action( array $e ) {
		if ( ! self::valid( $e ) ) {
			return false;
		}
		$ordered = array();
		foreach ( self::fields( $e['purpose'] ) as $key ) {
			$ordered[ $key ] = $e[ $key ];
		}
		return self::PREFIX . ( 'receipt_read' === $e['purpose'] ? 'receipt_v1:' : 'command_v1:' ) . hash( 'sha256', json_encode( $ordered ) );
	}

	/**
	 * Exact key set, independent of submitted order.
	 *
	 * @param array $value Map.
	 * @param array $keys Keys.
	 * @return bool
	 */
	public static function keys( array $value, array $keys ) {
		return count( $value ) === count( $keys ) && ! array_diff( $keys, array_keys( $value ) );
	}

	/**
	 * Reject leading zeroes, signs and overflow before casting.
	 *
	 * @param string $value Decimal.
	 * @param bool   $clock Timestamp rather than fencing counter.
	 * @return bool
	 */
	private static function decimal( $value, $clock ) {
		$max     = (string) ( PHP_INT_MAX - ( $clock ? 3600 : 1 ) );
		$pattern = $clock ? '/^[1-9][0-9]*$/D' : '/^(?:0|[1-9][0-9]*)$/D';
		return 1 === preg_match( $pattern, $value ) && ( strlen( $value ) < strlen( $max ) || ( strlen( $value ) === strlen( $max ) && strcmp( $value, $max ) <= 0 ) );
	}

	/**
	 * Existing lowercase UUID only, no identity generation.
	 *
	 * @param mixed $value UUID.
	 * @return bool
	 */
	public static function uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $value );
	}

	/**
	 * Opaque lowercase consistency hash, never authorization.
	 *
	 * @param mixed $value Hash.
	 * @return bool
	 */
	public static function digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{64}$/D', $value );
	}
}
