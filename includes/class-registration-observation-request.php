<?php
/**
 * Dormant read-issuance grammar and query DATA binding; no authentication.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/trait-registration-observation-data.php';
require_once __DIR__ . '/class-registration-request-contract.php';

/** Existing mutation grammar and production owners remain unchanged. */
final class Alynt_Drime_Backups_Uploader_Registration_Observation_Request {
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Data;

	/**
	 * Small exact read form, raw and once-unslashed encoded bounds checked.
	 *
	 * @param mixed $body Synthetic flat form, not actual superglobals.
	 * @return array|false Syntax only; nonce bytes are NOT verified.
	 */
	public static function intent( $body ) {
		$keys = array( 'purpose', 'confirm', 'observed_command_id', 'nonce' );
		if ( 1 !== func_num_args() || ! self::bounded( $body ) || ! is_array( $body ) || count( $body ) !== 4 || array_diff( $keys, array_keys( $body ) ) ) {
			return false;
		}
		$f = array();
		foreach ( $keys as $key ) {
			if ( ! is_string( $body[ $key ] ) || strlen( $body[ $key ] ) > 2048 ) {
				return false;
			}
			$f[ $key ] = stripslashes( $body[ $key ] );
		}
		return self::bounded( $f ) && 'observe_registration' === $f['purpose'] && 'observe' === $f['confirm'] && self::uuid( $f['observed_command_id'] ) && '' !== $f['nonce'] ? $f : false;
	}

	/**
	 * Deterministic action DATA only; never calls core nonce or UUID functions.
	 *
	 * @param mixed $blog Explicit synthetic blog ID.
	 * @param mixed $observed Exact original command ID.
	 * @return string|false Separate read-issuance domain.
	 */
	public static function issue_action( $blog, $observed ) {
		return 2 === func_num_args() && self::number( $blog ) && self::uuid( $observed ) ? 'alynt_drime_backups_scan_registration_observe_issue_v2:' . hash( 'sha256', json_encode( array( $blog, 'observe_registration', $observed ) ) ) : false;
	}

	/**
	 * Reuse strict full grammar without widening purposes or checking real auth.
	 *
	 * @param mixed $intent Original small read form.
	 * @param mixed $form Original full read form.
	 * @param mixed $now Synthetic UTC observation.
	 * @return array|false Typed envelope DATA only; not a barrier certificate.
	 */
	public static function query( $intent, $form, $now ) {
		$i = self::intent( $intent );
		$p = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $form );
		if ( 3 !== func_num_args() || false === $i || false === $p || ! self::number( $now ) || $now > PHP_INT_MAX - 3600 ) {
			return false;
		}
		$e = $p['envelope'];
		return 'observe_registration' === $e['purpose'] && $i['observed_command_id'] === $e['observed_command_id'] && $e['command_id'] !== $e['observed_command_id'] && $now >= $e['issued_at'] && $now < $e['expires_at'] ? $e : false;
	}
}
