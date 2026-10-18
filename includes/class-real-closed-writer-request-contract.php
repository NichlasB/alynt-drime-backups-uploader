<?php
/**
 * Separate real-purpose request grammar; no authentication or persistence.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Explicitly loaded only; forms cannot select provenance, provider or scope. */
final class Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract {
	const CONTRACT = 'closed_writer_request_v1';

	/**
	 * Fixed original typed field order.
	 *
	 * @param mixed $purpose Purpose.
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
	 * Flat strings only; bound raw and once-unslashed encoded sizes.
	 *
	 * @param mixed $body Raw form.
	 * @return array|false
	 */
	private static function strings( $body ) {
		if ( ! is_array( $body ) || count( $body ) > 16 ) {
			return false;
		}
		$total = 0;
		$out   = array();
		foreach ( $body as $key => $value ) {
			if ( ! is_string( $key ) || ! is_string( $value ) || strlen( $key ) > 2048 || strlen( $value ) > 2048 ) {
				return false;
			}
			$total += strlen( $key ) + strlen( $value );
			if ( $total > 4096 || 1 !== preg_match( '//u', $key . $value ) ) {
				return false;
			}
			$out[ $key ] = stripslashes( $value );
		}
		$raw = json_encode( $out );
		return is_string( $raw ) && strlen( $raw ) <= 4096 ? $out : false;
	}

	/**
	 * Parse an exact body without coercion or double unslashing.
	 *
	 * @param mixed $body Form.
	 * @return array|false Original typed envelope and nonce.
	 */
	public static function parse( $body ) {
		$f      = self::strings( $body );
		$fields = false === $f ? array() : self::fields( $f['purpose'] ?? null );
		if ( ! $fields || ! self::keys( $f, array_merge( $fields, array( 'nonce' ) ) ) || '' === $f['nonce'] ) {
			return false;
		}
		$e = array();
		foreach ( $fields as $key ) {
			$v = $f[ $key ];
			if ( in_array( $key, array( 'expected_generation', 'closure_generation', 'issued_at', 'expires_at' ), true ) ) {
				$clock = in_array( $key, array( 'issued_at', 'expires_at' ), true );
				if ( ! self::decimal( $v, $clock ) ) {
					return false;
				}
				$v = (int) $v;
			}
			$e[ $key ] = $v;
		}
		return self::valid( $e ) ? array(
			'envelope' => $e,
			'nonce'    => $f['nonce'],
		) : false;
	}

	/**
	 * Exact small issuance form, before any protected context read.
	 *
	 * @param mixed $body Form.
	 * @return array|false
	 */
	public static function issuance( $body ) {
		$f = self::strings( $body );
		if ( false === $f || ! self::fields( $f['purpose'] ?? null ) || ( $f['confirm'] ?? null ) !== self::confirmation( $f['purpose'] ) || '' === ( $f['nonce'] ?? '' ) ) {
			return false;
		}
		$keys = array( 'purpose', 'confirm', 'nonce' );
		if ( in_array( $f['purpose'], array( 'abandon_closed', 'receipt_read' ), true ) ) {
			$key    = 'abandon_closed' === $f['purpose'] ? 'operation_id' : 'observed_command_id';
			$keys[] = $key;
			if ( ! self::uuid( $f[ $key ] ?? null ) ) {
				return false;
			}
		}
		return self::keys( $f, $keys ) ? $f : false;
	}

	/**
	 * Typed original envelope only; nonce validity never extends this window.
	 *
	 * @param array $e Envelope.
	 * @return bool
	 */
	public static function valid( array $e ) {
		$fields = self::fields( $e['purpose'] ?? null );
		if ( ! $fields || ! self::keys( $e, $fields ) || self::CONTRACT !== $e['contract'] || self::confirmation( $e['purpose'] ) !== $e['confirm'] ) {
			return false;
		}
		foreach ( $e as $key => $v ) {
			if ( in_array( $key, array( 'expected_generation', 'closure_generation', 'issued_at', 'expires_at' ), true ) ) {
				$clock = in_array( $key, array( 'issued_at', 'expires_at' ), true );
				if ( ! is_int( $v ) || $v < ( $clock ? 1 : 0 ) || $v > PHP_INT_MAX - ( $clock ? 3600 : 1 ) ) {
					return false;
				}
			} elseif ( ! is_string( $v ) || ( in_array( $key, array( 'command_id', 'site_uuid', 'operation_id', 'observed_command_id' ), true ) ? ! self::uuid( $v ) : ( in_array( $key, array( 'enrollment_binding', 'origin_digest', 'context_digest', 'before_hash', 'after_hash', 'observed_hash' ), true ) && 1 !== preg_match( '/^[a-f0-9]{64}$/D', $v ) ) ) ) {
				return false;
			}
		}
		return $e['expires_at'] > $e['issued_at'] && $e['expires_at'] - $e['issued_at'] <= 300;
	}

	/**
	 * Real command/read action bound to every original normalized field.
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
		return ( 'receipt_read' === $e['purpose'] ? 'alynt_drime_backups_closed_writer_receipt_v1:' : 'alynt_drime_backups_closed_writer_command_v1:' ) . hash( 'sha256', json_encode( $ordered ) );
	}

	/**
	 * Public action syntax only, no private UUID lookup or nonce creation.
	 *
	 * @param mixed $blog Server-fixed blog.
	 * @param mixed $purpose Purpose.
	 * @return string|false
	 */
	public static function issue_action( $blog, $purpose ) {
		return is_int( $blog ) && $blog > 0 && $blog < PHP_INT_MAX && self::fields( $purpose ) ? 'alynt_drime_backups_closed_writer_issue_v1:' . $blog . ':' . $purpose : false;
	}

	/**
	 * Fixed explicit confirmation.
	 *
	 * @param string $purpose Supported purpose.
	 * @return string
	 */
	private static function confirmation( $purpose ) {
		return array(
			'upgrade_closed' => 'close',
			'begin_disable'  => 'disable',
			'abandon_closed' => 'abandon',
			'receipt_read'   => 'observe',
		)[ $purpose ];
	}

	/**
	 * Exact fields, independent of posted order.
	 *
	 * @param array $value Map.
	 * @param array $keys Fields.
	 * @return bool
	 */
	private static function keys( array $value, array $keys ) {
		return count( $value ) === count( $keys ) && ! array_diff( $keys, array_keys( $value ) );
	}

	/**
	 * Validate decimal magnitude before casting.
	 *
	 * @param string $v Digits.
	 * @param bool   $clock Clock or epoch.
	 * @return bool
	 */
	private static function decimal( $v, $clock ) {
		$max = (string) ( PHP_INT_MAX - ( $clock ? 3600 : 1 ) );
		return 1 === preg_match( $clock ? '/^[1-9][0-9]*$/D' : '/^(?:0|[1-9][0-9]*)$/D', $v ) && ( strlen( $v ) < strlen( $max ) || ( strlen( $v ) === strlen( $max ) && strcmp( $v, $max ) <= 0 ) );
	}

	/**
	 * Canonical existing identity, never generated here.
	 *
	 * @param mixed $v Identity.
	 * @return bool
	 */
	private static function uuid( $v ) {
		return is_string( $v ) && 1 === preg_match( '/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/D', $v );
	}
}
