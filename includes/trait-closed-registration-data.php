<?php
/**
 * Bounded data primitives for the closed registration contract only.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No callbacks, normalization, credentials, IO or identity issuance. */
trait Alynt_Drime_Backups_Uploader_Closed_Registration_Data {
	/**
	 * Encode only bounded plain values, refusing PHP references and callbacks.
	 *
	 * @param mixed $value Candidate.
	 * @param int   $max Encoded byte bound.
	 * @return string|false
	 */
	private static function encoded( $value, $max ) {
		$nodes = 4096;
		$bytes = 131072;
		if ( ! self::plain( $value, 0, $nodes, $bytes ) ) {
			return false;
		}
		$raw = json_encode( $value );
		return is_string( $raw ) && strlen( $raw ) <= $max ? $raw : false;
	}

	/**
	 * Key/value node and UTF-8 string-byte budgets before JSON allocation.
	 *
	 * @param mixed $value Candidate.
	 * @param int   $depth Depth.
	 * @param int   $nodes Remaining nodes.
	 * @param int   $bytes Remaining string bytes.
	 * @return bool
	 */
	private static function plain( $value, $depth, &$nodes, &$bytes ) {
		if ( $depth > 15 || --$nodes < 0 ) {
			return false;
		}
		if ( is_array( $value ) ) {
			if ( count( $value ) > intdiv( $nodes, 2 ) ) {
				return false;
			}
			foreach ( $value as $key => $child ) {
				if ( null !== ReflectionReference::fromArrayElement( $value, $key ) || ! self::plain( $key, $depth + 1, $nodes, $bytes ) || ! self::plain( $child, $depth + 1, $nodes, $bytes ) ) {
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
	 * Exact ordered map.
	 *
	 * @param mixed $value Map.
	 * @param array $keys Ordered fields.
	 * @return bool
	 */
	private static function keys( $value, $keys ) {
		if ( ! is_array( $value ) || count( $value ) !== count( $keys ) || array_keys( $value ) !== $keys ) {
			return false;
		}
		foreach ( $keys as $key ) {
			if ( null !== ReflectionReference::fromArrayElement( $value, $key ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * UUIDv4 syntax only, NOT proof of genuine construction.
	 *
	 * @param mixed $value Candidate.
	 * @return bool
	 */
	private static function uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $value );
	}

	/**
	 * Fixed lowercase hash.
	 *
	 * @param mixed $value Candidate.
	 * @return bool
	 */
	private static function hash_value( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $value );
	}

	/**
	 * Positive native integer with five increments of headroom.
	 *
	 * @param mixed $value Candidate.
	 * @return bool
	 */
	private static function number( $value ) {
		return is_int( $value ) && $value > 0 && $value <= PHP_INT_MAX - 5;
	}

	/**
	 * Exact pinned closed scope; cannot adopt a replaced genesis.
	 *
	 * @param mixed $scope Synthetic data.
	 * @return bool
	 */
	private static function scope( $scope ) {
		return self::keys( $scope, array( 'site_uuid', 'enrollment_binding', 'authority_generation', 'ledger_id', 'origin_created_at' ) ) && self::uuid( $scope['site_uuid'] ) && self::hash_value( $scope['enrollment_binding'] ) && self::number( $scope['authority_generation'] ) && self::uuid( $scope['ledger_id'] ) && $scope['site_uuid'] !== $scope['ledger_id'] && self::number( $scope['origin_created_at'] );
	}

	/**
	 * Half-open original deadline, not extended by a later invocation.
	 *
	 * @param mixed $issued Original time.
	 * @param mixed $expires Deadline.
	 * @param mixed $now Synthetic clock.
	 * @return bool
	 */
	private static function window( $issued, $expires, $now ) {
		return self::number( $issued ) && self::number( $expires ) && self::number( $now ) && $expires > $issued && $expires - $issued <= 300 && $now >= $issued && $now < $expires;
	}

	/**
	 * Canonical data fingerprint with explicit domain separation.
	 *
	 * @param array $value Ordered data.
	 * @return string|false
	 */
	private static function digest( $value ) {
		$raw = self::encoded( $value, 131072 );
		return false === $raw ? false : hash( 'sha256', $raw );
	}

	/**
	 * Every public result remains non-authorizing.
	 *
	 * @return array
	 */
	private static function disposition() {
		return Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::disposition() + array(
			'retry_supported' => false,
			'authenticated'   => false,
		);
	}
}
