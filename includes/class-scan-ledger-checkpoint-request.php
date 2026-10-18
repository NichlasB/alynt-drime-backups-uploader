<?php
/**
 * Closed checkpoint intent, separate from observation, effect and receipt domains.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-scan-observation-request.php';

/** No issuance, target selection, identity minting or scheduled admission. */
final class Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Request {
	/**
	 * Reuse the strict flat grammar without reusing its purpose or nonce action.
	 *
	 * @param mixed $body Original once-slashed form.
	 * @return array|false Typed intent only, not authorization.
	 */
	public static function parse( $body ) {
		if ( ! is_array( $body ) || 9 !== count( $body ) || 'scan_ledger_checkpoint_v1' !== ( $body['contract'] ?? null ) || 'checkpoint_closed_ledger' !== ( $body['purpose'] ?? null ) || 'checkpoint' !== ( $body['confirm'] ?? null ) || ! is_string( $body['context_digest'] ?? null ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $body['context_digest'] ) ) {
			return false;
		}
		$context = $body['context_digest'];
		$bytes   = 0;
		foreach ( $body as $key => $value ) {
			if ( ! is_string( $value ) ) {
				return false;
			}
			$bytes += strlen( $key ) + strlen( $value );
		}
		if ( $bytes > 4096 ) {
			return false;
		}
		unset( $body['context_digest'] );
		$body['contract'] = 'scan_effect_observation_v1';
		$body['purpose']  = 'observe_closed_manifest';
		$body['confirm']  = 'observe';
		$parsed           = Alynt_Drime_Backups_Uploader_Scan_Observation_Request::parse( $body );
		if ( false === $parsed ) {
			return false;
		}
		$e                   = $parsed['envelope'];
		$e['contract']       = 'scan_ledger_checkpoint_v1';
		$e['purpose']        = 'checkpoint_closed_ledger';
		$e['confirm']        = 'checkpoint';
		$e['context_digest'] = $context;
		$encoded             = $e;
		$encoded['nonce']    = $parsed['nonce'];
		if ( strlen( json_encode( $encoded ) ) > 4096 ) {
			return false;
		}
		return array(
			'envelope' => $e,
			'nonce'    => $parsed['nonce'],
			'action'   => 'alynt_drime_backups_scan_ledger_checkpoint_v1:' . hash( 'sha256', json_encode( $e ) ),
		);
	}
}
