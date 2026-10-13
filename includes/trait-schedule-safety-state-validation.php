<?php
/**
 * Strict internal safety-state validation; never a wire-protocol sanitizer.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Validates bounded scalar-only records without coercion or repair. */
trait Alynt_Drime_Backups_Uploader_Schedule_Safety_State_Validation {
	/**
	 * Validate a complete stored state.
	 *
	 * @param array $state Internal state.
	 * @return bool
	 */
	private function valid_state( array $state ) {
		if ( ! $this->exact_keys( $state, array( 'schema_version', 'generation', 'enrollment_binding', 'schedule_revision', 'claims' ) )
			|| 1 !== $state['schema_version'] || ! $this->valid_hash( $state['enrollment_binding'] )
			|| ! $this->valid_integer( $state['generation'] ) || ! $this->valid_integer( $state['schedule_revision'] )
			|| ! is_array( $state['claims'] ) || count( $state['claims'] ) > self::MAX_CLAIMS ) {
			return false;
		}
		foreach ( $state['claims'] as $source => $record ) {
			if ( ! $this->valid_uuid( $source ) || ! is_array( $record ) || ! $this->valid_record( $record ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Validate the caller-owned evidence shape. Eligibility remains a later gate.
	 *
	 * @param array $evidence Evidence fields.
	 * @return bool
	 */
	private function valid_evidence( array $evidence ) {
		if ( ! $this->exact_keys( $evidence, $this->evidence_keys() ) ) {
			return false;
		}
		foreach ( array( 'action_id', 'preview_action_id' ) as $key ) {
			if ( ! $this->valid_uuid( $evidence[ $key ] ) ) {
				return false;
			}
		}
		foreach ( array( 'request_hash', 'preview_fingerprint', 'rollback_metadata_fingerprint', 'before_fingerprint', 'intended_fingerprint' ) as $key ) {
			if ( ! $this->valid_hash( $evidence[ $key ] ) ) {
				return false;
			}
		}
		foreach ( array( 'metadata_captured_at', 'metadata_expires_at', 'preview_created_at', 'preview_expires_at', 'intent_expires_at' ) as $key ) {
			if ( ! $this->valid_integer( $evidence[ $key ] ) || 0 === $evidence[ $key ] ) {
				return false;
			}
		}
		$cadences = array( 'every_15_minutes', 'every_30_minutes', 'hourly' );
		return in_array( $evidence['previous_cadence'], $cadences, true ) && in_array( $evidence['intended_cadence'], $cadences, true ) && $evidence['previous_cadence'] !== $evidence['intended_cadence'];
	}

	/**
	 * Validate a stored journal.
	 *
	 * @param array $record Journal.
	 * @return bool
	 */
	private function valid_record( array $record ) {
		$extra = array( 'enrollment_binding', 'schedule_revision', 'created_at', 'deadline', 'lease_owner', 'lease_deadline', 'phase', 'completed_at' );
		if ( ! $this->exact_keys( $record, array_merge( $this->evidence_keys(), $extra ) ) || ! $this->valid_hash( $record['enrollment_binding'] ) || ! $this->valid_uuid( $record['lease_owner'] ) ) {
			return false;
		}
		foreach ( array( 'schedule_revision', 'created_at', 'deadline', 'lease_deadline', 'completed_at' ) as $key ) {
			if ( ! $this->valid_integer( $record[ $key ] ) ) {
				return false;
			}
		}
		$evidence = array_intersect_key( $record, array_flip( $this->evidence_keys() ) );
		$terminal = in_array( $record['phase'], array( 'succeeded', 'failed_closed' ), true );
		return $this->valid_evidence( $evidence )
			&& in_array( $record['phase'], array( 'claimed', 'write_started', 'recovery_required', 'succeeded', 'failed_closed' ), true )
			&& $record['lease_owner'] === $record['action_id'] && $record['deadline'] > $record['created_at']
			&& $record['created_at'] >= $record['preview_created_at'] && $record['preview_created_at'] >= $record['metadata_captured_at']
			&& $record['metadata_expires_at'] <= $record['metadata_captured_at'] + 3600 && $record['intent_expires_at'] <= $record['created_at'] + 300
			&& min( $record['metadata_expires_at'], $record['preview_expires_at'], $record['preview_created_at'] + 900, $record['intent_expires_at'] ) === $record['deadline']
			&& min( $record['deadline'], $record['created_at'] + 300 ) === $record['lease_deadline']
			&& ( $terminal ? $record['completed_at'] >= $record['created_at'] : 0 === $record['completed_at'] );
	}

	/**
	 * Get the names of scalar evidence fields.
	 *
	 * @return array
	 */
	private function evidence_keys() {
		return array( 'action_id', 'request_hash', 'preview_action_id', 'preview_fingerprint', 'rollback_metadata_fingerprint', 'previous_cadence', 'intended_cadence', 'before_fingerprint', 'intended_fingerprint', 'metadata_captured_at', 'metadata_expires_at', 'preview_created_at', 'preview_expires_at', 'intent_expires_at' );
	}

	/**
	 * Require all and only the allowed fields.
	 *
	 * @param array $value Data.
	 * @param array $keys Exact allowed fields.
	 * @return bool
	 */
	private function exact_keys( array $value, array $keys ) {
		return count( $value ) === count( $keys ) && ! array_diff( array_keys( $value ), $keys );
	}

	/**
	 * Validate a hash without coercion.
	 *
	 * @param mixed $value Hash.
	 * @return bool
	 */
	private function valid_hash( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $value );
	}

	/**
	 * Validate a UUID without coercion.
	 *
	 * @param mixed $value UUID.
	 * @return bool
	 */
	private function valid_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/D', $value );
	}

	/**
	 * Validate a nonnegative integer without coercion.
	 *
	 * @param mixed $value Nonnegative integer.
	 * @return bool
	 */
	private function valid_integer( $value ) {
		return is_int( $value ) && $value >= 0 && $value < PHP_INT_MAX;
	}
}
