<?php
/**
 * Pure closed bookkeeping successor; never operation registration or consent.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-scan-observation-data.php';

/** No new identities, receipts, effects, generation changes or active variant. */
final class Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Plan {
	/**
	 * Private-data context over every byte/metadata field and installation scope.
	 * This digest is not public diagnostics or evidence of event provenance.
	 *
	 * @param mixed $frame Complete fixed frame.
	 * @return string|false Pure candidate fingerprint only.
	 */
	public static function context( $frame ) {
		if ( ! Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::compare( $frame, $frame )['same_candidate'] ) {
			return false;
		}
		$raw = json_encode( $frame );
		return is_string( $raw ) ? hash( 'sha256', 'closed_scan_ledger_checkpoint_v1:' . $raw ) : false;
	}

	/**
	 * Increment ONLY the closed ledger revision, retaining every record/slot.
	 * Refuse pending work and protect incoming-record plus terminal headroom.
	 *
	 * @param mixed  $frame Observed fixed frame.
	 * @param string $site Original site UUID.
	 * @param string $context Original nonce-bound complete-frame fingerprint.
	 * @return string|false Private candidate bytes, never a writable capability.
	 */
	public static function prepare( $frame, $site, $context ) {
		$current = self::context( $frame );
		if ( ! is_string( $context ) || false === $current || ! hash_equals( $current, $context ) || ! Alynt_Drime_Backups_Uploader_Scan_Observation_Data::consistent( $frame, $frame, $site ) ) {
			return false;
		}
		$s     = Alynt_Drime_Backups_Uploader_Closed_Writer_Json_Contract::decode( $frame['rows']['ledger']['value'] );
		$scope = array(
			'site_uuid'            => $site,
			'enrollment_binding'   => $s['enrollment_binding'],
			'authority_generation' => $s['authority_generation'],
		);
		if ( $s['ledger_revision'] >= PHP_INT_MAX - 5 ) {
			return false;
		}
		++$s['ledger_revision'];
		$raw   = json_encode( $s );
		$check = Alynt_Drime_Backups_Uploader_Closed_Scan_Effect_Ledger::inspect( $raw, $scope );
		return false !== $check && $check['registration_room_candidate'] ? $raw : false;
	}
}
