<?php
/**
 * Dormant model dispatch ownership. No callback, scanner or uploader invocation.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Guards a synthetic start and due-time advancement without cron authority. */
trait Alynt_Drime_Backups_Uploader_Cadence_Authority_Dispatch {
	/**
	 * Claim a due model wake-up. A claim is not permission to run work.
	 *
	 * @param string $binding Enrollment.
	 * @param int    $revision Observed revision.
	 * @param string $fingerprint Observed authority fingerprint.
	 * @param string $owner Fresh dispatch UUID.
	 * @param string $hash Request hash.
	 * @return array|WP_Error
	 */
	public function claim_dispatch( $binding, $revision, $fingerprint, $owner, $hash ) {
		if ( ! $this->valid_integer( $revision ) || ! $this->valid_hash( $fingerprint ) || ! $this->valid_uuid( $owner ) || ! $this->valid_hash( $hash ) ) {
			return $this->error( 'authority_invalid' );
		}
		return $this->update(
			$binding,
			function ( &$state ) use ( $revision, $fingerprint, $owner, $hash ) {
				$now = call_user_func( $this->clock );
				$d   = $state['dispatch'];
				if ( null !== $d && $d['owner'] === $owner ) {
					return $d['request_hash'] === $hash && $d['enrollment_binding'] === $state['enrollment_binding'] ? array(
						'observed' => true,
						'dispatch' => $d,
					) : $this->error( 'owner_mismatch' );
				}
				if ( ! $state['authority']['enabled'] || $this->pending( $state ) ) {
					return $this->error( 'authority_blocked' );
				}
				if ( $revision !== $state['schedule_revision'] || $fingerprint !== $this->authority_fingerprint( $state ) ) {
					return $this->error( 'authority_stale' );
				}
				if ( $now < $state['authority']['next_due_at'] ) {
					return $this->error( 'not_due' );
				}
				$state['dispatch'] = array(
					'owner'              => $owner,
					'request_hash'       => $hash,
					'enrollment_binding' => $state['enrollment_binding'],
					'schedule_revision'  => $revision,
					'due_at'             => $state['authority']['next_due_at'],
					'claimed_at'         => $now,
					'lease_deadline'     => $now + 300,
					'phase'              => 'claimed',
					'completed_at'       => 0,
				);
				return array(
					'observed' => false,
					'dispatch' => $state['dispatch'],
				);
			}
		);
	}

	/**
	 * Single-use model start; an observed duplicate never grants another start.
	 *
	 * @param string $binding Enrollment.
	 * @param string $owner Owner.
	 * @param string $hash Request hash.
	 * @return array|WP_Error
	 */
	public function start_dispatch( $binding, $owner, $hash ) {
		return $this->dispatch_transition( $binding, $owner, $hash, 'claimed', 'started' );
	}

	/**
	 * Atomically finish the model and advance due time. Executes no work.
	 *
	 * @param string $binding Enrollment.
	 * @param string $owner Owner.
	 * @param string $hash Request hash.
	 * @return array|WP_Error
	 */
	public function complete_dispatch( $binding, $owner, $hash ) {
		return $this->dispatch_transition( $binding, $owner, $hash, 'started', 'completed' );
	}

	/**
	 * Explicitly cancel a never-started claim, including expired claims.
	 *
	 * @param string $binding Enrollment.
	 * @param string $owner Owner.
	 * @param string $hash Request hash.
	 * @return array|WP_Error
	 */
	public function cancel_dispatch( $binding, $owner, $hash ) {
		return $this->dispatch_transition( $binding, $owner, $hash, 'claimed', 'cancelled' );
	}

	/**
	 * Preserve uncertain started work, never restore execution permission.
	 *
	 * @param string $binding Enrollment.
	 * @param string $owner Owner.
	 * @param string $hash Request hash.
	 * @return array|WP_Error
	 */
	public function require_dispatch_recovery( $binding, $owner, $hash ) {
		return $this->dispatch_transition( $binding, $owner, $hash, 'started', 'recovery_required' );
	}

	/**
	 * Perform one owner-bound phase/due update through the same CAS.
	 *
	 * @param string $binding Enrollment.
	 * @param string $owner Owner.
	 * @param string $hash Request hash.
	 * @param string $from Required phase.
	 * @param string $to Result phase.
	 * @return array|WP_Error
	 */
	private function dispatch_transition( $binding, $owner, $hash, $from, $to ) {
		if ( ! $this->valid_uuid( $owner ) || ! $this->valid_hash( $hash ) ) {
			return $this->error( 'authority_invalid' );
		}
		return $this->update(
			$binding,
			function ( &$state ) use ( $owner, $hash, $from, $to ) {
				$d   = $state['dispatch'];
				$now = call_user_func( $this->clock );
				if ( null === $d || $d['owner'] !== $owner || $d['request_hash'] !== $hash || $d['enrollment_binding'] !== $state['enrollment_binding'] ) {
					return $this->error( 'owner_mismatch' );
				}
				if ( $d['phase'] !== $from ) {
					return $this->error( 'dispatch_phase_invalid' );
				}
				if ( in_array( $to, array( 'started', 'completed' ), true ) && ( ! $state['authority']['enabled'] || $this->pending_journal( $state ) || $now < $d['claimed_at'] || $d['lease_deadline'] <= $now || $d['schedule_revision'] !== $state['schedule_revision'] || $d['due_at'] !== $state['authority']['next_due_at'] ) ) {
					return $this->error( 'inspection_required' );
				}
				$d['phase']        = $to;
				$d['completed_at'] = in_array( $to, array( 'completed', 'cancelled' ), true ) ? $now : 0;
				if ( 'completed' === $to ) {
					$state['authority']['next_due_at'] = $now + $this->interval( $state['authority']['cadence'] );
				}
				$state['dispatch'] = $d;
				return $d;
			}
		);
	}
}
