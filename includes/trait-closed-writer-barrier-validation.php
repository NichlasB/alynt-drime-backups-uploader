<?php
/**
 * Strict closed successor and contiguous immutable receipt-chain validation.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Never infer authenticated consent, active history or resolved work. */
trait Alynt_Drime_Backups_Uploader_Closed_Writer_Barrier_Validation {
	/**
	 * Validate a schema-6 closed fixture and its frozen schema-5 origin.
	 *
	 * @param array $s State.
	 * @return bool
	 */
	protected function valid_state( array $s ) {
		if ( ! $this->exact_keys( $s, array( 'schema_version', 'generation', 'enrollment_binding', 'schedule_revision', 'claims', 'authority', 'dispatch', 'lifecycle', 'barrier' ) ) || 6 !== $s['schema_version'] || ! is_array( $s['barrier'] ) ) {
			return false;
		}
		$b = $s['barrier'];
		if ( ! $this->exact_keys( $b, array( 'site_uuid', 'source', 'checkpoint', 'upgrade', 'authenticated_grant', 'authenticated_receipts', 'pending', 'closure', 'completion', 'receipts' ) ) || $b['site_uuid'] !== $this->site_uuid || self::BARRIER_SOURCE !== $b['source']
			|| null !== $b['authenticated_grant'] || array() !== $b['authenticated_receipts'] || ! is_array( $b['checkpoint'] ) || ! $this->valid_checkpoint( $b['checkpoint'] ) || ! is_array( $b['upgrade'] ) || ! is_array( $b['receipts'] ) ) {
			return false;
		}
		$old = $b['checkpoint'];
		$u   = $b['upgrade'];
		if ( ! $this->exact_keys( $u, array( 'checkpoint_digest', 'expected_generation', 'result_generation', 'committed_at' ) ) || ! $this->valid_hash( $u['checkpoint_digest'] ) || ! hash_equals( $u['checkpoint_digest'], $this->checkpoint_digest( $old ) )
			|| $u['expected_generation'] !== $old['lifecycle']['permission_generation'] || $u['result_generation'] !== $u['expected_generation'] + 1 || ! is_int( $u['committed_at'] ) || $u['committed_at'] < $old['lifecycle']['changed_at'] || $u['committed_at'] > PHP_INT_MAX - 3600 ) {
			return false;
		}
		foreach ( array( 'enrollment_binding', 'claims', 'authority', 'dispatch' ) as $key ) {
			if ( $s[ $key ] !== $old[ $key ] ) {
				return false;
			}
		}
		if ( ! is_array( $s['lifecycle'] ) || ! $this->exact_keys( $s['lifecycle'], array( 'phase', 'consent', 'permission_generation', 'history', 'changed_at' ) )
			|| false !== $s['lifecycle']['consent'] || 'disabled' !== $s['lifecycle']['phase'] || $s['lifecycle']['history'] !== $old['lifecycle']['history'] || ! $this->valid_integer( $s['lifecycle']['permission_generation'] ) || $s['lifecycle']['permission_generation'] < $u['result_generation'] ) {
			return false;
		}
		$delta = $s['lifecycle']['permission_generation'] - $old['lifecycle']['permission_generation'];
		foreach ( array( 'generation', 'schedule_revision' ) as $key ) {
			if ( $old[ $key ] > PHP_INT_MAX - 1 - $delta || $s[ $key ] !== $old[ $key ] + $delta ) {
				return false;
			}
		}
		$raw     = wp_json_encode( $s );
		$waiting = null !== $b['pending'];
		$reserve = $waiting || null === $b['closure'];
		return $this->headroom( $s, $waiting ? 2 : ( null === $b['closure'] ? 3 : 1 ) ) && count( $b['receipts'] ) + $this->archive_count( $s ) <= self::MAX_RECEIPTS
			&& $this->valid_barrier_chain( $s ) && is_string( $raw ) && strlen( $raw ) <= self::MAX_BYTES - ( $reserve ? self::CLOSURE_BYTES : 0 );
	}

	/**
	 * Enforce a lossless ordered chain; no omitted receipt, counter reset or replay.
	 *
	 * @param array $s Structurally checked state.
	 * @return bool
	 */
	private function valid_barrier_chain( array $s ) {
		$b   = $s['barrier'];
		$all = $b['receipts'];
		foreach ( array( 'closure', 'completion' ) as $slot ) {
			$r = $b[ $slot ];
			if ( null !== $r ) {
				if ( ! $this->valid_barrier_receipt( $r ) || isset( $all[ $r['command']['command_id'] ] ) ) {
					return false;
				}
				$all[ $r['command']['command_id'] ] = $r;
			}
		}
		$ordered = array();
		foreach ( $all as $id => $r ) {
			if ( ! $this->valid_uuid( $id ) || ! $this->valid_barrier_receipt( $r ) || $id !== $r['command']['command_id'] || $this->archived_id( $s, $id ) || $r['command']['enrollment_binding'] !== $s['enrollment_binding'] || isset( $ordered[ $r['result_generation'] ] ) ) {
				return false;
			}
			$ordered[ $r['result_generation'] ] = $r;
		}
		ksort( $ordered, SORT_NUMERIC );
		$epoch      = $b['upgrade']['result_generation'];
		$stamp      = $b['upgrade']['committed_at'];
		$open       = null;
		$closure    = null;
		$completion = null;
		foreach ( $ordered as $generation => $r ) {
			if ( $r['command']['expected_generation'] !== $epoch || $generation !== $epoch + 1 || $r['committed_at'] < $stamp ) {
				return false;
			}
			if ( 'begin' === $r['purpose'] ) {
				if ( null !== $open ) {
					return false;
				}
				$open       = $r;
				$closure    = $r;
				$completion = null;
			} else {
				if ( null === $open || ! $this->valid_barrier_completion( $r, $open ) ) {
					return false;
				}
				$open       = null;
				$completion = $r;
			}
			$epoch = $generation;
			$stamp = $r['committed_at'];
		}
		$pending = null === $open ? null : $open['command']['command_id'];
		return $b['closure'] === $closure && $b['completion'] === $completion && $b['pending'] === $pending
			&& $epoch === $s['lifecycle']['permission_generation'] && $stamp === $s['lifecycle']['changed_at'];
	}

	/**
	 * Frozen optional history shares the 50-entry budget, never evicted.
	 *
	 * @param array $s State.
	 * @return int
	 */
	private function archive_count( array $s ) {
		$l = $s['barrier']['checkpoint']['provenance']['legacy']['ledger'];
		return null === $l ? 0 : count( $l['receipts'] );
	}

	/**
	 * Canonical frozen checkpoint consistency, not a signature.
	 *
	 * @param array $checkpoint Strict closed schema 5.
	 * @return string
	 */
	private function checkpoint_digest( array $checkpoint ) {
		return hash(
			'sha256',
			wp_json_encode(
				array(
					'domain'     => 'adb-closed-writer-checkpoint-v1',
					'site_uuid'  => $this->site_uuid,
					'checkpoint' => $checkpoint,
				)
			)
		);
	}
}
