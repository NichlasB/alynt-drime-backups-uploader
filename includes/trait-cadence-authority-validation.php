<?php
/**
 * Dormant internal authority validation; no wire contract.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Validates schema 2 while retaining the unchanged schema-1 journal contract. */
trait Alynt_Drime_Backups_Uploader_Cadence_Authority_Validation {
	/**
	 * Validate the complete model state.
	 *
	 * @param array $state Internal state.
	 * @return bool
	 */
	protected function valid_state( array $state ) {
		if ( ! $this->exact_keys( $state, array( 'schema_version', 'generation', 'enrollment_binding', 'schedule_revision', 'claims', 'authority', 'dispatch' ) ) || 2 !== $state['schema_version'] ) {
			return false;
		}
		$legacy = $state;
		unset( $legacy['authority'], $legacy['dispatch'] );
		$legacy['schema_version'] = 1;
		if ( ! parent::valid_state( $legacy ) || ! $this->valid_authority( $state['authority'] ) ) {
			return false;
		}
		if ( null === $state['dispatch'] ) {
			return true;
		}
		$d = $state['dispatch'];
		if ( ! is_array( $d ) || ! $this->exact_keys( $d, array( 'owner', 'request_hash', 'enrollment_binding', 'schedule_revision', 'due_at', 'claimed_at', 'lease_deadline', 'phase', 'completed_at' ) )
			|| ! $this->valid_uuid( $d['owner'] ) || ! $this->valid_hash( $d['request_hash'] ) || ! $this->valid_hash( $d['enrollment_binding'] )
			|| ! in_array( $d['phase'], array( 'claimed', 'started', 'recovery_required', 'completed', 'cancelled' ), true ) ) {
			return false;
		}
		foreach ( array( 'schedule_revision', 'due_at', 'claimed_at', 'lease_deadline', 'completed_at' ) as $key ) {
			if ( ! $this->valid_integer( $d[ $key ] ) ) {
				return false;
			}
		}
		$terminal = in_array( $d['phase'], array( 'completed', 'cancelled' ), true );
		if ( $d['due_at'] <= 0 || $d['claimed_at'] < $d['due_at'] || $d['claimed_at'] + 300 !== $d['lease_deadline'] || ( $terminal ? $d['completed_at'] < $d['claimed_at'] : 0 !== $d['completed_at'] ) ) {
			return false;
		}
		if ( 'completed' === $d['phase'] && $d['completed_at'] >= $d['lease_deadline'] ) {
			return false;
		}
		return ! in_array( $d['phase'], array( 'claimed', 'started' ), true ) || ( $state['authority']['enabled'] && $d['enrollment_binding'] === $state['enrollment_binding'] && $d['schedule_revision'] === $state['schedule_revision'] && $d['due_at'] === $state['authority']['next_due_at'] );
	}

	/**
	 * Validate one fixed authority model.
	 *
	 * @param mixed $authority Authority.
	 * @return bool
	 */
	private function valid_authority( $authority ) {
		return is_array( $authority ) && $this->exact_keys( $authority, array( 'mode', 'cadence', 'next_due_at', 'enabled' ) )
			&& 'client_owned_v1' === $authority['mode'] && $this->interval( $authority['cadence'] ) > 0 && is_bool( $authority['enabled'] )
			&& $this->valid_integer( $authority['next_due_at'] ) && $authority['next_due_at'] > 0;
	}

	/**
	 * Resolve only supported cadence intervals.
	 *
	 * @param mixed $cadence Cadence.
	 * @return int
	 */
	private function interval( $cadence ) {
		$map = array(
			'every_15_minutes' => 900,
			'every_30_minutes' => 1800,
			'hourly'           => 3600,
		);
		return is_string( $cadence ) && isset( $map[ $cadence ] ) ? $map[ $cadence ] : 0;
	}

	/**
	 * Identify uncertainty that must block model mutations and dispatch.
	 *
	 * @param array $state State.
	 * @return bool
	 */
	private function pending( array $state ) {
		if ( null !== $state['dispatch'] && in_array( $state['dispatch']['phase'], array( 'claimed', 'started', 'recovery_required' ), true ) ) {
			return true;
		}
		return $this->pending_journal( $state );
	}

	/**
	 * Protect any valid but unfinished legacy journal retained in model state.
	 *
	 * @param array $state State.
	 * @return bool
	 */
	private function pending_journal( array $state ) {
		foreach ( $state['claims'] as $record ) {
			if ( in_array( $record['phase'], array( 'write_started', 'recovery_required' ), true ) ) {
				return true;
			}
		}
		return false;
	}
}
