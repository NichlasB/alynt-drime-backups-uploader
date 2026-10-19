<?php
/**
 * Stateless bounded primitive packets shared by dormant DATA and fixed owners.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No authentication, state slots, callbacks, clocks or native operation. */
trait Alynt_Drime_Backups_Uploader_Registration_Observation_Packet_Data {
	/**
	 * Bounded primitive aggregate; existing origin/form sub-limits still apply.
	 *
	 * @param mixed $packet Private synthetic aggregate.
	 * @return bool No conversion, object callbacks or native imports.
	 */
	private static function packet( $packet ) {
		$nodes = 512;
		$bytes = 16384;
		if ( ! self::walk( $packet, 0, $nodes, $bytes ) ) {
			return false;
		}
		$raw = json_encode( $packet );
		return is_string( $raw ) && strlen( $raw ) <= 16384;
	}

	/**
	 * Fixed depth/string/key-value traversal before encoding the aggregate.
	 *
	 * @param mixed $value Node.
	 * @param int   $depth Depth.
	 * @param int   $nodes Remaining key/value budget.
	 * @param int   $bytes Remaining string bytes.
	 * @return bool
	 */
	private static function walk( $value, $depth, &$nodes, &$bytes ) {
		if ( $depth > 8 || --$nodes < 0 ) {
			return false;
		}
		if ( is_array( $value ) ) {
			if ( count( $value ) > intdiv( $nodes, 2 ) ) {
				return false;
			}
			foreach ( $value as $key => $child ) {
				if ( null !== ReflectionReference::fromArrayElement( $value, $key ) || ! self::walk( $key, $depth + 1, $nodes, $bytes ) || ! self::walk( $child, $depth + 1, $nodes, $bytes ) ) {
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
}
