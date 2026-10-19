<?php
/**
 * Small pure observation graphs only; never a parser or native authority port.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** New origin/request bounds do not widen existing ledger or parser contracts. */
trait Alynt_Drime_Backups_Uploader_Registration_Observation_Data {
	/**
	 * Bound strings and traversal BEFORE JSON allocation; reject every reference.
	 *
	 * @param mixed $value Synthetic graph.
	 * @return bool
	 */
	private static function bounded( $value ) {
		$nodes = 256;
		$bytes = 4096;
		if ( ! self::plain_observation( $value, 0, $nodes, $bytes ) ) {
			return false;
		}
		$raw = json_encode( $value );
		return is_string( $raw ) && strlen( $raw ) <= 4096;
	}

	/**
	 * Fixed shallow primitive traversal; no conversion, callback or object access.
	 *
	 * @param mixed $value Node.
	 * @param int   $depth Depth.
	 * @param int   $nodes Remaining key/value nodes.
	 * @param int   $bytes Remaining string bytes.
	 * @return bool
	 */
	private static function plain_observation( $value, $depth, &$nodes, &$bytes ) {
		if ( $depth > 6 || --$nodes < 0 ) {
			return false;
		}
		if ( is_array( $value ) ) {
			if ( count( $value ) > intdiv( $nodes, 2 ) ) {
				return false;
			}
			foreach ( $value as $key => $child ) {
				if ( null !== ReflectionReference::fromArrayElement( $value, $key ) || ! self::plain_observation( $key, $depth + 1, $nodes, $bytes ) || ! self::plain_observation( $child, $depth + 1, $nodes, $bytes ) ) {
					return false;
				}
			}
			return true;
		}
		if ( is_string( $value ) ) {
			$bytes -= strlen( $value );
			return $bytes >= 0 && 1 === preg_match( '//u', $value );
		}
		return null === $value || is_int( $value ) || is_bool( $value );
	}

	/**
	 * Exact ordered data map, not a normalized request form.
	 *
	 * @param mixed $value Map.
	 * @param array $keys Fields.
	 * @return bool
	 */
	private static function keys( $value, $keys ) {
		return is_array( $value ) && array_keys( $value ) === $keys;
	}

	/**
	 * Positive integer with conservative increment headroom.
	 *
	 * @param mixed $value Scalar.
	 * @return bool
	 */
	private static function number( $value ) {
		return is_int( $value ) && $value > 0 && $value <= PHP_INT_MAX - 5;
	}

	/**
	 * Canonical UUIDv4 DATA syntax only, never genuine issuance.
	 *
	 * @param mixed $value Scalar.
	 * @return bool
	 */
	private static function uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $value );
	}
}
