<?php
/**
 * Complete pending-aware frame DATA; never a native barrier or protected reader.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-registration-observation-origin.php';
require_once __DIR__ . '/class-real-closed-writer-model.php';
require_once __DIR__ . '/class-serialized-settings-target.php';

/** Test-loaded only. No auth, nonce verification, clocks, storage or SQL port. */
final class Alynt_Drime_Backups_Uploader_Registration_Observation_Candidate {
	/**
	 * Classify only after complete frame validation against original DATA scope.
	 *
	 * @param mixed $frame Complete synthetic current ten-row frame.
	 * @param mixed $data Original primitive context, never a capability object.
	 * @param mixed $intent Exact small synthetic read form.
	 * @param mixed $form Exact full synthetic read form.
	 * @param mixed $now Explicit synthetic UTC.
	 * @return array|false Redacted candidate only; false means unavailable.
	 */
	public static function observe( $frame, $data, $intent, $form, $now ) {
		if ( 5 !== func_num_args() ) {
			return false;
		}
		$origin = Alynt_Drime_Backups_Uploader_Registration_Observation_Origin::prepare( $data );
		$query  = Alynt_Drime_Backups_Uploader_Registration_Observation_Request::query( $intent, $form, $now );
		if ( false === $origin || false === $query || ! $origin->accepts( $intent, $form, $now ) || ! self::frame( $frame, $data, $query['command_id'] ) ) {
			return false;
		}
		return $origin->observe( $frame['rows']['ledger']['value'], $intent, $form, $data['installation'], $data['scope'], $now );
	}

	/**
	 * Fixed tuple/bounds/refusal checks before any payload parser allocation.
	 *
	 * @param mixed $frame Synthetic complete frame.
	 * @return bool Never certifies physical row origin or coexistence.
	 */
	private static function tuples( $frame ) {
		if ( ! is_array( $frame ) || 4 !== count( $frame ) || ! is_array( $frame['rows'] ?? null ) || 10 !== count( $frame['rows'] ) ) {
			return false;
		}
		foreach ( $frame['rows'] as $row ) {
			if ( ! is_array( $row ) || 5 !== count( $row ) ) {
				return false;
			}
		}
		foreach ( array_merge( array( $frame, $frame['rows'] ), array_values( $frame['rows'] ) ) as $map ) {
			foreach ( array_keys( $map ) as $key ) {
				if ( null !== ReflectionReference::fromArrayElement( $map, $key ) ) {
					return false;
				}
			}
		}
		return Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::compare( $frame, $frame )['same_candidate'];
	}

	/**
	 * Unlike admission, valid registration pending history is required to work.
	 * Does not compare current rows to the mutation's obsolete pre-write frame.
	 *
	 * @param mixed  $frame Complete candidate bytes, never a reader.
	 * @param array  $data Already validated original DATA.
	 * @param string $lookup Fresh query identity label.
	 * @return bool Complete closed candidate, not storage evidence.
	 */
	private static function frame( $frame, $data, $lookup ) {
		if ( ! self::tuples( $frame ) || array( $frame['blog_id'], $frame['prefix'], $frame['database'] ) !== array_values( $data['installation'] ) ) {
			return false;
		}
		foreach ( array( 'Closed_Writer_Admission_Snapshot', 'Closed_Writer_Barrier_Store', 'Closed_Writer_Admission_Projection', 'Closed_Writer_Admission_Contract' ) as $name ) {
			if ( ! class_exists( 'Alynt_Drime_Backups_Uploader_' . $name, false ) ) {
				return false;
			}
		}
		$rows     = $frame['rows'];
		$scope    = $data['scope'];
		$site     = $scope['site_uuid'];
		$settings = Alynt_Drime_Backups_Uploader_Serialized_Settings_Target::inspect( $rows['settings']['value'], $site, $rows['settings']['autoload'] );
		$safety   = Alynt_Drime_Backups_Uploader_Closed_Writer_Json_Contract::decode( $rows['safety']['value'] );
		if ( false === $settings || false !== $settings['enabled'] || false === $safety || 8 !== ( $safety['schema_version'] ?? null ) || ! Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::valid( $safety, $site ) || null !== $safety['writer']['pending'] || $safety['enrollment_binding'] !== $scope['enrollment_binding'] || $safety['lifecycle']['permission_generation'] !== $scope['authority_generation'] ) {
			return false;
		}
		// Strict inspection validates the entire canonical history, including pending.
		if ( false === Alynt_Drime_Backups_Uploader_Closed_Registration_Contract::inspect( $rows['ledger']['value'], $scope ) || false === Alynt_Drime_Backups_Uploader_Scan_Target_Bytes::parse( $rows['connection']['value'] ) ) {
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
		return self::identities( $safety, json_decode( $rows['ledger']['value'], true ), $scope, $lookup );
	}

	/**
	 * No lookup reuse or overlap with safety's retained used/reserved domain.
	 * Registration grammar already checks its own complete identity uniqueness.
	 *
	 * @param array  $safety Valid complete schema-8 chain.
	 * @param array  $ledger Valid complete schema-2 history.
	 * @param array  $scope Original pinned DATA scope.
	 * @param string $lookup Query identity.
	 * @return bool No identity issuance or history search.
	 */
	private static function identities( $safety, $ledger, $scope, $lookup ) {
		$used = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::identities( $safety );
		if ( isset( $used[ $scope['ledger_id'] ] ) || isset( $used[ $lookup ] ) ) {
			return false;
		}
		foreach ( $ledger['operations'] as $operation ) {
			$ids = array_merge( array( $operation['operation_id'], $operation['command']['command_id'], $operation['terminal_id'], $operation['resolution_id'] ), array_column( $operation['effects'], 'effect_id' ), array_values( $operation['reservation_command_ids'] ) );
			foreach ( $ids as $id ) {
				if ( $lookup === $id || isset( $used[ $id ] ) ) {
					return false;
				}
			}
		}
		return true;
	}
}
