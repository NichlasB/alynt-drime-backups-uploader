<?php
/**
 * Bounded synthetic proposal/clock checks; never native provenance or release.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Reuse existing bounded graphs and candidate parsing, not a second parser. */
trait Alynt_Drime_Backups_Uploader_Registration_Admission_Lifecycle_Data {
	/**
	 * Pin all ten proposed identities and context before any simulated DML event.
	 *
	 * @param mixed $data Synthetic original provenance.
	 * @param mixed $proposal Original synthetic proposal, not retained.
	 * @param mixed $ids Complete supplied used/reserved set, not issuance proof.
	 * @return bool
	 */
	private static function original( $data, $proposal, $ids ) {
		if ( ! self::packet( array( $data, $proposal, $ids ) ) || ! self::bounded( $proposal ) || false === Alynt_Drime_Backups_Uploader_Registration_Observation_Origin::prepare( $data ) || ! self::keys( $proposal, array( 'command', 'actor_id', 'operation_id', 'effect_ids', 'reservation_command_ids', 'terminal_id', 'resolution_id', 'dependency_digest' ) ) ) {
			return false;
		}
		$roles = array( 'wpvivid_snapshot', 'outbox_snapshot', 'queue_addition' );
		if ( $proposal['command'] !== $data['mutation'] || $proposal['actor_id'] !== $data['actor_id'] || $proposal['operation_id'] !== $data['operation_id'] || ! self::keys( $proposal['effect_ids'], $roles ) || ! self::keys( $proposal['reservation_command_ids'], $roles ) || ! is_string( $proposal['dependency_digest'] ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $proposal['dependency_digest'] ) || Alynt_Drime_Backups_Uploader_Closed_Registration_Contract::registration_context( $data['scope'], $proposal ) !== $data['context_digest'] ) {
			return false;
		}
		$required = array_merge( array( $data['scope']['site_uuid'], $data['scope']['ledger_id'], $proposal['command']['command_id'], $proposal['operation_id'], $proposal['terminal_id'], $proposal['resolution_id'] ), array_values( $proposal['effect_ids'] ), array_values( $proposal['reservation_command_ids'] ) );
		foreach ( $required as $id ) {
			if ( ! self::uuid( $id ) ) {
				return false;
			}
		}
		if ( ! is_array( $ids ) || count( $ids ) < 12 || count( $ids ) > 128 || array_keys( $ids ) !== range( 0, count( $ids ) - 1 ) || count( array_unique( $required ) ) !== 12 ) {
			return false;
		}
		$seen = array();
		foreach ( $ids as $id ) {
			if ( ! self::uuid( $id ) || isset( $seen[ $id ] ) ) {
				return false;
			}
			$seen[ $id ] = true;
		}
		foreach ( $required as $id ) {
			if ( ! self::uuid( $id ) || ! isset( $seen[ $id ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Original root start and nonregressing observations, never actual timers.
	 *
	 * @param mixed $clock Synthetic current clock.
	 * @param array $prior Original/last clock.
	 * @param int   $limit Half-open phase limit in nanoseconds.
	 * @return bool
	 */
	private static function root_time( $clock, $prior, $limit ) {
		if ( ! self::bounded( $clock ) || ! self::keys( $clock, array( 'started_ns', 'now_ns', 'utc' ) ) ) {
			return false;
		}
		foreach ( $clock as $value ) {
			if ( ! is_int( $value ) || $value < 0 ) {
				return false;
			}
		}
		return $clock['utc'] > 0 && $clock['utc'] <= PHP_INT_MAX - 3600 && $clock['started_ns'] === $prior['started_ns'] && $clock['now_ns'] >= $prior['now_ns'] && $clock['utc'] >= $prior['utc'] && $clock['now_ns'] - $clock['started_ns'] >= 0 && $clock['now_ns'] - $clock['started_ns'] < $limit;
	}
}
