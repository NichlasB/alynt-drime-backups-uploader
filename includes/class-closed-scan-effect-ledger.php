<?php
/**
 * Closed-only bounded ledger data validation; never a store or request factory.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-closed-writer-json-contract.php';
require_once __DIR__ . '/class-scan-effect-manifest.php';
require_once __DIR__ . '/trait-closed-scan-effect-records.php';

/** No writable port, identity creation, admission or active schema variant. */
final class Alynt_Drime_Backups_Uploader_Closed_Scan_Effect_Ledger {
	use Alynt_Drime_Backups_Uploader_Closed_Scan_Effect_Records;

	const DOMAIN = 'scan_effect_storage_closed_v1';

	/**
	 * Validate exact canonical closed bytes against explicit synthetic scope.
	 *
	 * @param mixed $raw Private bounded JSON.
	 * @param mixed $scope Exact site/enrollment/generation context, not authority.
	 * @return array|false Redacted candidate classification, no private payloads.
	 */
	public static function inspect( $raw, $scope ) {
		$s        = Alynt_Drime_Backups_Uploader_Closed_Writer_Json_Contract::decode( $raw );
		$n        = 4096;
		$encoded  = json_encode( $s );
		$manifest = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::digest();
		if ( 2 !== func_num_args() || false === $s || ! self::plain( $s, $n, 0 ) || $raw !== $encoded || ! self::keys( $s, array( 'schema_version', 'domain', 'site_uuid', 'enrollment_binding', 'authority_generation', 'ledger_revision', 'participant_digest', 'closed', 'operations' ) ) || 1 !== $s['schema_version'] || self::DOMAIN !== $s['domain'] || true !== $s['closed'] || ! self::uuid( $s['site_uuid'] ) || ! self::hash_value( $s['enrollment_binding'] ) || ! self::number( $s['authority_generation'] ) || ! self::number( $s['ledger_revision'] ) || $s['participant_digest'] !== $manifest || ! is_array( $scope ) || ! self::keys( $scope, array( 'site_uuid', 'enrollment_binding', 'authority_generation' ) ) || array( $s['site_uuid'], $s['enrollment_binding'], $s['authority_generation'] ) !== array( $scope['site_uuid'], $scope['enrollment_binding'], $scope['authority_generation'] ) || ! is_array( $s['operations'] ) || count( $s['operations'] ) > 16 ) {
			return false;
		}
		$header               = $s;
		$header['operations'] = array();
		if ( strlen( json_encode( $header ) ) > 4096 ) {
			return false;
		}
		$ids       = array( $s['site_uuid'] => true );
		$revisions = array();
		$pending   = 0;
		foreach ( $s['operations'] as $id => $operation ) {
			if ( ! self::operation( $operation, $id, $s, $ids, $revisions ) ) {
				return false;
			}
			$pending += 'terminal' === $operation['phase'] ? 0 : 1;
		}
		if ( $pending > 1 ) {
			return false;
		}
		return Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::disposition() + array(
			'closed_candidate'            => true,
			'operation_count'             => count( $s['operations'] ),
			'pending_count'               => $pending,
			'registration_room_candidate' => self::budget_candidate( strlen( $raw ), count( $s['operations'] ), $pending ),
		);
	}

	/**
	 * Pure arithmetic on synthetic counts, NOT a capacity/admission certificate.
	 * Reserve maximum incoming record plus all closed-first accounting headroom.
	 *
	 * @param mixed $bytes Encoded original byte count.
	 * @param mixed $count Retained identity count.
	 * @param mixed $pending Nonterminal count.
	 * @return bool Non-authorizing budget comparison only.
	 */
	public static function budget_candidate( $bytes, $count, $pending ) {
		return is_int( $bytes ) && $bytes > 0 && $bytes <= 131072 - 16384 - 6144 && is_int( $count ) && $count >= 0 && $count < 16 && 0 === $pending;
	}

	/**
	 * Strict bounded scalar/key/value traversal after byte-bounded JSON decoding.
	 * The shared decoder's 65536-node ceiling is not this ledger's 4096-node cap.
	 *
	 * @param mixed $value Decoded private data.
	 * @param int   $left Remaining nodes.
	 * @param int   $depth Nesting.
	 * @return bool
	 */
	private static function plain( $value, &$left, $depth ) {
		if ( $depth > 15 || --$left < 0 ) {
			return false;
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child ) {
				if ( ! self::plain( $key, $left, $depth + 1 ) || ! self::plain( $child, $left, $depth + 1 ) ) {
					return false;
				}
			}
			return true;
		}
		return null === $value || is_int( $value ) || is_bool( $value ) || is_string( $value );
	}

	/**
	 * Exact canonical key order, refusing missing/defaulted or extra fields.
	 *
	 * @param mixed $value Object-like array.
	 * @param array $keys Fixed keys.
	 * @return bool
	 */
	private static function keys( $value, $keys ) {
		return is_array( $value ) && array_keys( $value ) === $keys;
	}

	/**
	 * Fixed lowercase digest grammar.
	 *
	 * @param mixed $value Data.
	 * @return bool
	 */
	private static function hash_value( $value ) {
		return is_string( $value ) && 64 === strlen( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $value );
	}

	/**
	 * Canonical existing UUID only; never mint or infer an owner.
	 *
	 * @param mixed $value Data.
	 * @return bool
	 */
	private static function uuid( $value ) {
		return is_string( $value ) && 36 === strlen( $value ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $value );
	}

	/**
	 * Headroom before any increment; never coerce a decimal string/float.
	 *
	 * @param mixed $value Data.
	 * @return bool
	 */
	private static function number( $value ) {
		return is_int( $value ) && $value > 0 && $value < PHP_INT_MAX - 4;
	}
}
