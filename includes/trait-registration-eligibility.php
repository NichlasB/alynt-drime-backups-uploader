<?php
/**
 * Private complete closed-frame validation; no protected reader or authority.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Existing schema-8 chain and all-ten data grammar, never minimal safety shape. */
trait Alynt_Drime_Backups_Uploader_Registration_Eligibility {
	/**
	 * Inspect already captured data AFTER authorization; never acquire it here.
	 *
	 * @param mixed $observed Full fixed ten-row frame.
	 * @param array $core Latched genuine-core installation metadata.
	 * @return array|false Private data only.
	 */
	private function eligible_registration( $observed, $core ) {
		if ( ! is_array( $observed ) || 4 !== count( $observed ) || ! is_array( $observed['rows'] ?? null ) || 10 !== count( $observed['rows'] ) ) {
			return false;
		}
		foreach ( $observed['rows'] as $row ) {
			if ( ! is_array( $row ) || 5 !== count( $row ) ) {
				return false;
			}
		}
		foreach ( array( 'Closed_Writer_Admission_Snapshot', 'Closed_Writer_Barrier_Store', 'Closed_Writer_Admission_Projection', 'Closed_Writer_Admission_Contract' ) as $name ) {
			if ( ! class_exists( 'Alynt_Drime_Backups_Uploader_' . $name, false ) ) {
				return false;
			}
		}
		if ( ! Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::compare( $observed, $observed )['same_candidate'] || ! defined( 'DB_NAME' ) || $observed['blog_id'] !== $core['blog'] || $observed['prefix'] !== $core['prefix'] || DB_NAME !== $observed['database'] ) {
			return false;
		}
		foreach ( array_merge( array( $observed, $observed['rows'] ), array_values( $observed['rows'] ) ) as $map ) {
			foreach ( array_keys( $map ) as $key ) {
				if ( null !== ReflectionReference::fromArrayElement( $map, $key ) ) {
					return false;
				}
			}
		}
		$rows     = $observed['rows'];
		$parsed   = Alynt_Drime_Backups_Uploader_Scan_Target_Bytes::parse( $rows['settings']['value'] );
		$site     = false === $parsed ? null : ( $parsed['value']['site_uuid'] ?? null );
		$settings = Alynt_Drime_Backups_Uploader_Serialized_Settings_Target::inspect( $rows['settings']['value'], $site, $rows['settings']['autoload'] );
		$safety   = Alynt_Drime_Backups_Uploader_Closed_Writer_Json_Contract::decode( $rows['safety']['value'] );
		$ledger   = Alynt_Drime_Backups_Uploader_Closed_Writer_Json_Contract::decode( $rows['ledger']['value'] );
		if ( false === $settings || false !== $settings['enabled'] || false === $safety || 8 !== ( $safety['schema_version'] ?? null ) || ! Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::valid( $safety, $site ) || null !== $safety['writer']['pending'] || false === $ledger || ! is_array( $ledger['origin'] ?? null ) ) {
			return false;
		}
		$scope      = array(
			'site_uuid'            => $site,
			'enrollment_binding'   => $safety['enrollment_binding'],
			'authority_generation' => $safety['lifecycle']['permission_generation'],
			'ledger_id'            => $ledger['origin']['ledger_id'] ?? null,
			'origin_created_at'    => $ledger['origin']['created_at'] ?? null,
		);
		$inspection = Alynt_Drime_Backups_Uploader_Closed_Registration_Contract::inspect( $rows['ledger']['value'], $scope );
		if ( false === $inspection || 0 !== $inspection['pending_count'] || false === Alynt_Drime_Backups_Uploader_Scan_Target_Bytes::parse( $rows['connection']['value'] ) ) {
			return false;
		}
		foreach ( array( 'wpvivid_snapshot', 'outbox_snapshot' ) as $role ) {
			if ( false === Alynt_Drime_Backups_Uploader_Scan_Target_Codec::snapshot( $role, $rows[ $role ]['value'], $rows[ $role ]['value'] ) ) {
				return false;
			}
		}
		if ( false === Alynt_Drime_Backups_Uploader_Scan_Target_Codec::queue(
			$rows['queue']['value'],
			'append_new',
			'a:0:{}',
			array(
				'active'   => $rows['active']['value'],
				'uploaded' => $rows['uploaded']['value'],
				'failed'   => $rows['failed']['value'],
			)
		) ) {
			return false;
		}
		$encoded = json_encode( $observed );
		if ( ! is_string( $encoded ) ) {
			return false;
		}
		$used = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::identities( $safety );
		if ( isset( $used[ $scope['ledger_id'] ] ) ) {
			return false;
		}
		$used[ $site ]               = true;
		$used[ $scope['ledger_id'] ] = true;
		return array(
			'scope'             => $scope,
			'before'            => $rows['ledger']['value'],
			'revision'          => $ledger['ledger_revision'],
			'dependency_digest' => hash( 'sha256', 'registration_full_frame_v2:' . $encoded ),
			'used'              => $used,
		);
	}
}
