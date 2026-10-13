<?php
/**
 * Client-local journal transitions; no schedule execution.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Protects unresolved state across expiry, revision and enrollment changes. */
trait Alynt_Drime_Backups_Uploader_Schedule_Safety_State_Transitions {
	/**
	 * Advance a claimed journal without extending evidence or stealing ownership.
	 *
	 * @param string $binding Enrollment hash.
	 * @param string $source Source UUID.
	 * @param string $owner Action UUID.
	 * @param string $hash Request hash.
	 * @param string $from Expected phase.
	 * @param string $to New phase.
	 * @return array|WP_Error
	 */
	public function transition( $binding, $source, $owner, $hash, $from, $to ) {
		$edges = array(
			'claimed'           => array( 'write_started', 'failed_closed' ),
			'write_started'     => array( 'succeeded', 'failed_closed', 'recovery_required' ),
			'recovery_required' => array( 'succeeded', 'failed_closed' ),
		);
		if ( ! $this->valid_uuid( $source ) || ! $this->valid_uuid( $owner ) || ! $this->valid_hash( $hash ) || ! is_string( $from ) || ! is_string( $to ) || ! isset( $edges[ $from ] ) || ! in_array( $to, $edges[ $from ], true ) ) {
			return $this->error( 'transition_invalid' );
		}
		return $this->update(
			$binding,
			function ( &$state ) use ( $source, $owner, $hash, $from, $to ) {
				if ( ! isset( $state['claims'][ $source ] ) ) {
					return $this->error( 'source_missing' );
				}
				$record = $state['claims'][ $source ];
				if ( $record['action_id'] !== $owner || $record['request_hash'] !== $hash || $record['enrollment_binding'] !== $state['enrollment_binding'] ) {
					return $this->error( 'owner_mismatch' );
				}
				if ( $record['phase'] === $to ) {
					return $record;
				}
				if ( $record['phase'] !== $from ) {
					return $this->error( 'transition_invalid' );
				}
				$now = call_user_func( $this->clock );
				if ( 'write_started' === $to ) {
					foreach ( $state['claims'] as $id => $other ) {
						if ( $id !== $source && in_array( $other['phase'], array( 'write_started', 'recovery_required' ), true ) ) {
							return $this->error( 'recovery_pending' );
						}
					}
				}
				if ( in_array( $to, array( 'write_started', 'succeeded' ), true ) && ( $record['deadline'] <= $now || $record['lease_deadline'] <= $now || $record['schedule_revision'] !== $state['schedule_revision'] ) ) {
					return $this->error( 'inspection_required' );
				}
				$record['phase']            = $to;
				$record['completed_at']     = in_array( $to, array( 'succeeded', 'failed_closed' ), true ) ? $now : 0;
				$state['claims'][ $source ] = $record;
				return $record;
			}
		);
	}

	/**
	 * Invalidate execution after a schedule revision; keep all unfinished evidence.
	 *
	 * @param string      $binding Enrollment hash.
	 * @param string|null $replacement_binding Explicit replacement enrollment, if any.
	 * @return int|WP_Error
	 */
	public function invalidate( $binding, $replacement_binding = null ) {
		if ( null !== $replacement_binding && ! $this->valid_hash( $replacement_binding ) ) {
			return $this->error( 'invalid_state' );
		}
		return $this->update(
			$binding,
			function ( &$state ) use ( $replacement_binding ) {
				$state['schedule_revision']++;
				if ( null !== $replacement_binding ) {
					$state['enrollment_binding'] = $replacement_binding;
				}
				foreach ( $state['claims'] as &$record ) {
					if ( 'claimed' === $record['phase'] ) {
						$record['phase']        = 'failed_closed';
						$record['completed_at'] = call_user_func( $this->clock );
					} elseif ( 'write_started' === $record['phase'] ) {
						$record['phase'] = 'recovery_required';
					}
				}
				unset( $record );
				return $state['schedule_revision'];
			}
		);
	}
}
