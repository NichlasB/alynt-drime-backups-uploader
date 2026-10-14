<?php
/**
 * Exact dormant successor/provenance validation; no authenticated trust claims.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Reuses schema-2 validation, conservatively retains schema-3 lifecycle semantics. */
trait Alynt_Drime_Backups_Uploader_Consent_Ledger_Validation {
	/**
	 * Validate only fixture schema 4; older readers remain unchanged.
	 *
	 * @param array $state Stored state.
	 * @return bool
	 */
	protected function valid_state( array $state ) {
		if ( ! $this->exact_keys( $state, array( 'schema_version', 'generation', 'enrollment_binding', 'schedule_revision', 'claims', 'authority', 'dispatch', 'lifecycle', 'ledger' ) ) || 4 !== $state['schema_version'] || ! is_array( $state['ledger'] ) ) {
			return false;
		}
		$older = $state;
		unset( $older['ledger'] );
		$older['schema_version'] = 3;
		$l                       = $state['ledger'];
		if ( ! $this->valid_lifecycle_state( $older ) || min( $state['generation'], $state['schedule_revision'] ) < $state['lifecycle']['permission_generation'] || ! $this->exact_keys( $l, array( 'site_uuid', 'source', 'grant_command_id', 'last_receipt', 'receipts' ) )
			|| $l['site_uuid'] !== $this->site_uuid || ! $this->valid_uuid( $l['site_uuid'] ) || 'fixture_intent_v1' !== $l['source'] || ! is_array( $l['receipts'] ) || count( $l['receipts'] ) > self::MAX_RECEIPTS ) {
			return false;
		}
		$last = $l['last_receipt'];
		if ( null === $last ) {
			return null === $l['grant_command_id'] && empty( $l['receipts'] ) && ! $state['lifecycle']['consent'] && 'disabled' === $state['lifecycle']['phase'];
		}
		if ( ! $this->valid_receipt( $last ) || $last['command']['site_uuid'] !== $this->site_uuid || $last['result_generation'] !== $state['lifecycle']['permission_generation']
			|| $last['committed_at'] !== $state['lifecycle']['changed_at'] || $state['schedule_revision'] < $last['result_generation'] || $state['generation'] < $last['result_generation'] ) {
			return false;
		}
		$current_binding = 'rebind' === $last['command']['purpose'] ? $last['command']['replacement_binding'] : $last['command']['enrollment_binding'];
		if ( $current_binding !== $state['enrollment_binding'] ) {
			return false;
		}
		$generations = array();
		foreach ( $l['receipts'] as $id => $receipt ) {
			if ( ! $this->valid_uuid( $id ) || ! $this->valid_receipt( $receipt ) || $receipt['command']['command_id'] !== $id || $receipt['command']['site_uuid'] !== $this->site_uuid
				|| $id === $last['command']['command_id'] || $receipt['result_generation'] >= $last['result_generation'] || isset( $generations[ $receipt['result_generation'] ] ) || $receipt['committed_at'] > $last['committed_at'] ) {
				return false;
			}
			$generations[ $receipt['result_generation'] ] = true;
		}
		$grant = 'grant' === $last['command']['purpose'];
		$raw   = wp_json_encode( $state );
		return $grant ? $l['grant_command_id'] === $last['command']['command_id'] && $state['lifecycle']['consent'] && count( $l['receipts'] ) < self::MAX_RECEIPTS && $this->headroom( $state, 1 ) && is_string( $raw ) && strlen( $raw ) <= self::MAX_BYTES - self::CLOSURE_BYTES
			: null === $l['grant_command_id'] && ! $state['lifecycle']['consent'] && 'preparing' !== $state['lifecycle']['phase'];
	}

	/**
	 * Validate the exact older dormant shape before an explicit migration.
	 *
	 * @param array $state Schema-3 state.
	 * @return bool
	 */
	private function valid_lifecycle_state( array $state ) {
		if ( ! $this->exact_keys( $state, array( 'schema_version', 'generation', 'enrollment_binding', 'schedule_revision', 'claims', 'authority', 'dispatch', 'lifecycle' ) ) || 3 !== $state['schema_version'] || ! is_array( $state['lifecycle'] ) ) {
			return false;
		}
		$older = $state;
		unset( $older['lifecycle'] );
		$older['schema_version'] = 2;
		if ( ! $this->valid_authority_state( $older ) || false !== $state['authority']['enabled'] ) {
			return false;
		}
		$l = $state['lifecycle'];
		if ( ! $this->exact_keys( $l, array( 'phase', 'consent', 'permission_generation', 'history', 'changed_at' ) ) || ! in_array( $l['phase'], array( 'preparing', 'draining', 'disabled' ), true )
			|| ! is_bool( $l['consent'] ) || ! $this->valid_integer( $l['permission_generation'] ) || ! in_array( $l['history'], array( 'new_model', 'unknown' ), true ) || ! $this->valid_integer( $l['changed_at'] ) || $l['changed_at'] <= 0
			|| ( $l['consent'] && ( 'preparing' !== $l['phase'] || 0 === $l['permission_generation'] ) ) ) {
			return false;
		}
		return $this->ledger_unfinished( $state ) ? 'draining' === $l['phase'] && ! $l['consent'] : 'draining' !== $l['phase'];
	}

	/**
	 * Require an exact scalar synthetic intent, not a trust/authentication assertion.
	 *
	 * @param mixed $command Intent.
	 * @return bool
	 */
	private function valid_command( $command ) {
		if ( ! is_array( $command ) || ! $this->exact_keys( $command, array( 'command_id', 'purpose', 'site_uuid', 'enrollment_binding', 'expected_generation', 'issued_at', 'expires_at', 'replacement_binding' ) )
			|| ! $this->valid_uuid( $command['command_id'] ) || ! $this->valid_uuid( $command['site_uuid'] ) || ! $this->valid_hash( $command['enrollment_binding'] ) || ! $this->valid_integer( $command['expected_generation'] )
			|| ! in_array( $command['purpose'], array( 'grant', 'revoke', 'rebind' ), true ) || ! $this->valid_integer( $command['issued_at'] ) || ! $this->valid_integer( $command['expires_at'] ) ) {
			return false;
		}
		if ( 'rebind' === $command['purpose'] ? ! $this->valid_hash( $command['replacement_binding'] ) || $command['replacement_binding'] === $command['enrollment_binding'] : null !== $command['replacement_binding'] ) {
			return false;
		}
		return 'grant' === $command['purpose'] ? $command['issued_at'] > 0 && $command['issued_at'] <= PHP_INT_MAX - 300 && $command['expires_at'] > $command['issued_at'] && $command['expires_at'] <= $command['issued_at'] + 300
			: 0 === $command['issued_at'] && 0 === $command['expires_at'];
	}

	/**
	 * Validate receipt integrity and internal generation/time consistency.
	 *
	 * @param mixed $receipt Receipt.
	 * @return bool
	 */
	private function valid_receipt( $receipt ) {
		if ( ! is_array( $receipt ) || ! $this->exact_keys( $receipt, array( 'command', 'request_hash', 'result_generation', 'committed_at' ) ) || ! $this->valid_command( $receipt['command'] )
			|| ! $this->valid_hash( $receipt['request_hash'] ) || ! $this->valid_integer( $receipt['result_generation'] ) || ! $this->valid_integer( $receipt['committed_at'] ) || $receipt['committed_at'] <= 0
			|| $receipt['command']['expected_generation'] >= PHP_INT_MAX - 1 || $receipt['result_generation'] !== $receipt['command']['expected_generation'] + 1 || ! hash_equals( $receipt['request_hash'], $this->command_digest( $receipt['command'] ) ) ) {
			return false;
		}
		$raw = wp_json_encode( $receipt );
		$c   = $receipt['command'];
		return is_string( $raw ) && strlen( $raw ) <= self::RECEIPT_BYTES && ( 'grant' !== $c['purpose'] || ( $c['issued_at'] <= $receipt['committed_at'] && $receipt['committed_at'] < $c['expires_at'] ) );
	}

	/**
	 * Identify every unfinished journal/dispatch without treating expiry as resolution.
	 *
	 * @param array $state Model.
	 * @return bool
	 */
	private function ledger_unfinished( array $state ) {
		foreach ( $state['claims'] as $record ) {
			if ( ! in_array( $record['phase'], array( 'succeeded', 'failed_closed' ), true ) ) {
				return true;
			}
		}
		return null !== $state['dispatch'] && ! in_array( $state['dispatch']['phase'], array( 'completed', 'cancelled' ), true );
	}
}
