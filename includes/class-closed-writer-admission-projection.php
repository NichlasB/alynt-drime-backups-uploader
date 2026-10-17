<?php
/**
 * Finite lossless schema-6 projection and fixed synthetic JSON target context.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Pure fixture consistency, never a raw WordPress settings provider. */
final class Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection {
	/**
	 * Reject objects before encoding (including JsonSerializable callbacks).
	 *
	 * @param mixed $value Data.
	 * @param int   $depth Current bounded depth.
	 * @return bool
	 */
	public static function plain( $value, $depth = 0 ) {
		if ( $depth > 15 || is_object( $value ) || is_resource( $value ) || is_float( $value ) ) {
			return false;
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child ) {
				if ( ! self::plain( $key, $depth + 1 ) || ! self::plain( $child, $depth + 1 ) ) {
					return false;
				}
			}
		}
		return ! is_string( $value ) || 1 === preg_match( '//u', $value );
	}

	/**
	 * Strict original validation from frozen bounded bytes, no backend reread.
	 *
	 * @param array  $s Source.
	 * @param string $site Fixed UUID.
	 * @return bool
	 */
	public static function source( array $s, $site ) {
		if ( ! self::plain( $s ) ) {
			return false;
		}
		$raw = json_encode( $s );
		if ( ! is_string( $raw ) || strlen( $raw ) > 131072 || ! is_array( json_decode( $raw, true, 16 ) ) ) {
			return false;
		}
		$reader = new Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Snapshot( $raw );
		$model  = new Alynt_Drime_Backups_Uploader_Closed_Writer_Barrier_Store(
			$reader,
			$site,
			static function () {
				return 1;
			},
			true
		);
		return $s === $model->snapshot() && null === $s['barrier']['pending'];
	}

	/**
	 * Reconstruct only the original typed schema 6, never another successor.
	 *
	 * @param array $s Structurally checked successor.
	 * @return array
	 */
	public static function original( array $s ) {
		$o = $s['writer']['origin'];
		return array(
			'schema_version'     => 6,
			'generation'         => $o['generation'],
			'enrollment_binding' => $s['enrollment_binding'],
			'schedule_revision'  => $o['schedule_revision'],
			'claims'             => $s['claims'],
			'authority'          => $s['authority'],
			'dispatch'           => $s['dispatch'],
			'lifecycle'          => array(
				'phase'                 => 'disabled',
				'consent'               => false,
				'permission_generation' => $o['permission_generation'],
				'history'               => $s['lifecycle']['history'],
				'changed_at'            => $o['changed_at'],
			),
			'barrier'            => $s['legacy_writer'],
		);
	}

	/**
	 * Deterministic structural/map order for hashing only; lists retain order.
	 *
	 * @param mixed $value Already checked plain data.
	 * @return mixed
	 */
	private static function ordered( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( $value && array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $child ) {
			$value[ $key ] = self::ordered( $child );
		}
		return $value;
	}

	/**
	 * Versioned consistency hash, not an authentication signature.
	 *
	 * @param array $s Validated data.
	 * @return string
	 */
	public static function hash( array $s ) {
		return hash( 'sha256', 'adb-closed-admission-consistency-v1:' . json_encode( self::ordered( $s ) ) );
	}

	/**
	 * Synthetic existing JSON target only; no serializer, getters or bootstrap.
	 *
	 * @param array  $s Validated safety state.
	 * @param string $raw Exact existing bytes.
	 * @param string $site Fixed site.
	 * @return array|false Opaque facts and internal candidate bytes.
	 */
	public static function context( array $s, $raw, $site ) {
		if ( ! is_string( $raw ) || strlen( $raw ) > 1048576 ) {
			return false;
		}
		$t = json_decode( $raw, true, 16 );
		if ( ! is_array( $t ) || ! self::plain( $t ) || ( $t['site_uuid'] ?? null ) !== $site || ! isset( $t['auto_scan_enabled'] ) || ! is_bool( $t['auto_scan_enabled'] ) ) {
			return false;
		}
		$enabled                = $t['auto_scan_enabled'];
		$t['auto_scan_enabled'] = false;
		$after                  = json_encode( $t );
		$facts                  = array(
			'version'             => 1,
			'site_uuid'           => $site,
			'target'              => 'settings',
			'writer'              => 'disable_auto_scan',
			'enrollment_binding'  => $s['enrollment_binding'],
			'expected_generation' => $s['lifecycle']['permission_generation'],
			'source_digest'       => self::hash( $s ),
			'before_hash'         => hash( 'sha256', $raw ),
			'after_hash'          => hash( 'sha256', $after ),
		);
		return array(
			'context_digest' => self::hash( $facts ),
			'before_hash'    => $facts['before_hash'],
			'after_hash'     => $facts['after_hash'],
			'enabled'        => $enabled,
			'after'          => $after,
		);
	}
}
