<?php
/**
 * Dormant authority mutation and invalidation; no remote execution.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps source journal and model authority on one CAS boundary. */
trait Alynt_Drime_Backups_Uploader_Cadence_Authority_Mutations {
	/**
	 * Do not create reservations for disabled or uncertain model state.
	 *
	 * @param array $state Validated model state.
	 * @return true|WP_Error
	 */
	protected function can_reserve( array $state ) {
		return $state['authority']['enabled'] && ! $this->pending( $state ) ? true : $this->error( 'authority_blocked' );
	}
	/**
	 * Reserve synthetic/trusted future evidence without changing authority.
	 *
	 * @param string $binding Enrollment.
	 * @param string $source Source.
	 * @param array  $evidence Internal evidence, not legacy wire metadata.
	 * @return array|WP_Error
	 */
	public function reserve_change( $binding, $source, array $evidence ) {
		return parent::claim( $binding, $source, $evidence );
	}

	/**
	 * Atomically commit an internal cadence/due change plus its journal result.
	 *
	 * @param string $binding Enrollment.
	 * @param string $source Reserved source UUID.
	 * @param string $owner Owner UUID.
	 * @param string $hash Request hash.
	 * @param int    $due Candidate due time bound to evidence.
	 * @return array|WP_Error
	 */
	public function commit_change( $binding, $source, $owner, $hash, $due ) {
		if ( ! $this->valid_uuid( $source ) || ! $this->valid_uuid( $owner ) || ! $this->valid_hash( $hash ) || ! $this->valid_integer( $due ) ) {
			return $this->error( 'authority_invalid' );
		}
		return $this->update(
			$binding,
			function ( &$state ) use ( $source, $owner, $hash, $due ) {
				if ( ! isset( $state['claims'][ $source ] ) ) {
					return $this->error( 'source_missing' );
				}
				$record = $state['claims'][ $source ];
				$now    = call_user_func( $this->clock );
				if ( $record['action_id'] !== $owner || $record['request_hash'] !== $hash || $record['enrollment_binding'] !== $state['enrollment_binding'] ) {
					return $this->error( 'owner_mismatch' );
				}
				if ( 'succeeded' === $record['phase'] ) {
					return array(
						'changed'  => false,
						'observed' => true,
						'claim'    => $record,
					);
				}
				if ( ! $state['authority']['enabled'] || $this->pending( $state ) ) {
					return $this->error( 'authority_blocked' );
				}
				if ( 'claimed' !== $record['phase'] || $record['created_at'] > $now || $record['schedule_revision'] !== $state['schedule_revision'] || $record['deadline'] <= $now || $record['lease_deadline'] <= $now ) {
					return $this->error( 'inspection_required' );
				}
				if ( $record['previous_cadence'] !== $state['authority']['cadence'] || $record['before_fingerprint'] !== $this->authority_fingerprint( $state ) || $due <= $now || $due > $now + $this->interval( $record['intended_cadence'] ) ) {
					return $this->error( 'authority_stale' );
				}
				$state['authority']['cadence']     = $record['intended_cadence'];
				$state['authority']['next_due_at'] = $due;
				++$state['schedule_revision'];
				if ( $record['intended_fingerprint'] !== $this->authority_fingerprint( $state ) ) {
					return $this->error( 'authority_stale' );
				}
				foreach ( $state['claims'] as &$other ) {
					if ( 'claimed' === $other['phase'] ) {
								$other['phase']        = 'failed_closed';
								$other['completed_at'] = $now;
					}
				} unset( $other );
				$record['phase']            = 'succeeded';
				$record['completed_at']     = $now;
				$state['claims'][ $source ] = $record;
				return array(
					'changed'  => true,
					'observed' => false,
					'claim'    => $record,
				);
			}
		);
	}

	/**
	 * Disable the model and retain uncertain evidence on revision/enrollment change.
	 *
	 * @param string      $binding Current enrollment.
	 * @param string|null $replacement_binding Explicit replacement, if any.
	 * @return int|WP_Error
	 */
	public function invalidate( $binding, $replacement_binding = null ) {
		if ( null !== $replacement_binding && ! $this->valid_hash( $replacement_binding ) ) {
			return $this->error( 'invalid_state' );
		}
		return $this->update(
			$binding,
			function ( &$state ) use ( $replacement_binding ) {
				$now = call_user_func( $this->clock );
				++$state['schedule_revision'];
				$state['authority']['enabled'] = false;
				if ( null !== $replacement_binding ) {
					$state['enrollment_binding'] = $replacement_binding;
				}
				foreach ( $state['claims'] as &$record ) {
					if ( 'claimed' === $record['phase'] ) {
						$record['phase']        = 'failed_closed';
						$record['completed_at'] = $now; } elseif ( 'write_started' === $record['phase'] ) {
										$record['phase'] = 'recovery_required';
						}
				} unset( $record );
				if ( null !== $state['dispatch'] ) {
					if ( 'claimed' === $state['dispatch']['phase'] ) {
						$state['dispatch']['phase']        = 'cancelled';
						$state['dispatch']['completed_at'] = $now; } elseif ( 'started' === $state['dispatch']['phase'] ) {
										$state['dispatch']['phase'] = 'recovery_required';
						}
				}
				return $state['schedule_revision'];
			}
		);
	}
}
