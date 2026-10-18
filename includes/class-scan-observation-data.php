<?php
/**
 * Private closed manifest/codec consistency, never storage or grant evidence.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-closed-scan-effect-ledger.php';
require_once __DIR__ . '/class-scan-target-codec.php';

/** Pure data; the native owner independently supplies its own observed frames. */
final class Alynt_Drime_Backups_Uploader_Scan_Observation_Data {
	/**
	 * Both exact sweeps, closed-ledger reference scope and no-op target codecs.
	 * Safety/connection observation is NOT complete runtime admission validation.
	 *
	 * @param mixed  $first First fixed frame.
	 * @param mixed  $second Re-observation.
	 * @param string $site Original read-intent site UUID, checked against settings.
	 * @return bool Non-authorizing data consistency only.
	 */
	public static function consistent( $first, $second, $site ) {
		if ( ! Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::compare( $first, $second )['same_candidate'] ) {
			return false;
		}
		$rows     = $first['rows'];
		$safety   = Alynt_Drime_Backups_Uploader_Closed_Writer_Json_Contract::decode( $rows['safety']['value'] );
		$settings = Alynt_Drime_Backups_Uploader_Scan_Target_Bytes::parse( $rows['settings']['value'] );
		$connect  = Alynt_Drime_Backups_Uploader_Scan_Target_Bytes::parse( $rows['connection']['value'] );
		if ( false === $safety || false === $settings || false === $connect || ( $settings['value']['site_uuid'] ?? null ) !== $site ) {
			return false;
		}
		$scope = array(
			'site_uuid'            => $site,
			'enrollment_binding'   => $safety['enrollment_binding'] ?? null,
			'authority_generation' => $safety['lifecycle']['permission_generation'] ?? null,
		);
		if ( false === Alynt_Drime_Backups_Uploader_Closed_Scan_Effect_Ledger::inspect( $rows['ledger']['value'], $scope ) ) {
			return false;
		}
		foreach ( array( 'wpvivid_snapshot', 'outbox_snapshot' ) as $role ) {
			if ( false === Alynt_Drime_Backups_Uploader_Scan_Target_Codec::snapshot( $role, $rows[ $role ]['value'], $rows[ $role ]['value'] ) ) {
				return false;
			}
		}
		return false !== Alynt_Drime_Backups_Uploader_Scan_Target_Codec::queue(
			$rows['queue']['value'],
			'append_new',
			'a:0:{}',
			array(
				'active'   => $rows['active']['value'],
				'uploaded' => $rows['uploaded']['value'],
				'failed'   => $rows['failed']['value'],
			)
		);
	}
}
