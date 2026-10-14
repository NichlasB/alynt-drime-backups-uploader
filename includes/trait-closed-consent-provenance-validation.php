<?php
/**
 * Strict closed successor validation with segregated untrusted fixture archives.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No authenticated grants, receipts, dispatch or active lifecycle is accepted. */
trait Alynt_Drime_Backups_Uploader_Closed_Consent_Provenance_Validation {
	/**
	 * Validate the immutable closed schema-5 migration checkpoint.
	 *
	 * @param array $state Internal state.
	 * @return bool
	 */
	protected function valid_state( array $state ) {
		if ( ! $this->exact_keys( $state, array( 'schema_version', 'generation', 'enrollment_binding', 'schedule_revision', 'claims', 'authority', 'dispatch', 'lifecycle', 'provenance' ) ) || 5 !== $state['schema_version'] || ! is_array( $state['provenance'] ) || ! is_array( $state['lifecycle'] ) ) {
			return false;
		}
		$p = $state['provenance'];
		if ( ! $this->exact_keys( $p, array( 'site_uuid', 'source', 'authenticated_grant', 'authenticated_receipts', 'legacy', 'migration' ) ) || $p['site_uuid'] !== $this->site_uuid || self::SOURCE !== $p['source'] || null !== $p['authenticated_grant'] || array() !== $p['authenticated_receipts']
			|| ! is_array( $p['legacy'] ) || ! is_array( $p['migration'] ) || ! $this->exact_keys( $p['legacy'], array( 'schema_version', 'generation', 'enrollment_binding', 'schedule_revision', 'lifecycle', 'ledger', 'digest' ) )
			|| ! $this->exact_keys( $p['migration'], array( 'expected_generation', 'result_generation', 'committed_at' ) ) || ! $this->valid_hash( $p['legacy']['digest'] ) ) {
			return false;
		}
		if ( 3 === $p['legacy']['schema_version'] && null !== $p['legacy']['ledger'] ) {
			return false;
		}
		$old = $this->original( $state );
		if ( ! $this->valid_origin( $old ) || $this->ledger_unfinished( $old ) || ! $this->headroom( $old, 2 ) || ! hash_equals( $p['legacy']['digest'], $this->origin_digest( $old ) ) ) {
			return false;
		}
		$m = $p['migration'];
		$l = $old['lifecycle'];
		if ( ! $this->valid_integer( $m['committed_at'] ) || $m['committed_at'] < $l['changed_at'] || $m['committed_at'] <= 0 || $m['committed_at'] > PHP_INT_MAX - 3600 || $m['expected_generation'] !== $l['permission_generation'] || $m['result_generation'] !== $l['permission_generation'] + 1 ) {
			return false;
		}
		$closed                          = $l;
		$closed['consent']               = false;
		$closed['phase']                 = 'disabled';
		$closed['permission_generation'] = $m['result_generation'];
		$closed['changed_at']            = $m['committed_at'];
		$raw                             = wp_json_encode( $state );
		return $state['enrollment_binding'] === $old['enrollment_binding'] && $state['generation'] === $old['generation'] + 1 && $state['schedule_revision'] === $old['schedule_revision'] + 1 && $state['lifecycle'] === $closed
			&& is_string( $raw ) && strlen( $raw ) <= self::MAX_BYTES - self::CLOSURE_BYTES;
	}

	/**
	 * Reconstruct the validated source without duplicating journals/due/history.
	 *
	 * @param array $state Closed checkpoint.
	 * @return array
	 */
	private function original( array $state ) {
		$old = $state;
		$a   = $state['provenance']['legacy'];
		unset( $old['provenance'] );
		foreach ( array( 'schema_version', 'generation', 'enrollment_binding', 'schedule_revision', 'lifecycle' ) as $key ) {
			$old[ $key ] = $a[ $key ];
		}
		if ( 4 === $a['schema_version'] ) {
			$old['ledger'] = $a['ledger'];
		}
		return $old;
	}

	/**
	 * Validate only old strict fixture sources, never an authenticated marker.
	 *
	 * @param array $state Source state.
	 * @return bool
	 */
	private function valid_origin( array $state ) {
		return isset( $state['schema_version'] ) && ( 4 === $state['schema_version'] ? $this->valid_fixture_state( $state ) : ( 3 === $state['schema_version'] && $this->valid_lifecycle_state( $state ) ) );
	}

	/**
	 * Canonical archive consistency digest; not authorization or a signature.
	 *
	 * @param array $state Source state.
	 * @return string
	 */
	private function origin_digest( array $state ) {
		$ordered = array(
			'domain'    => 'adb-closed-migration-fixture-v1',
			'site_uuid' => $this->site_uuid,
		);
		foreach ( array( 'schema_version', 'generation', 'enrollment_binding', 'schedule_revision', 'claims', 'authority', 'dispatch', 'lifecycle' ) as $key ) {
			$ordered[ $key ] = $state[ $key ];
		}
		$ordered['ledger'] = isset( $state['ledger'] ) ? $state['ledger'] : null;
		return hash( 'sha256', wp_json_encode( $ordered ) );
	}

	/**
	 * Retain original schema-4 receipt digest semantics without executing commands.
	 *
	 * @param array $command Old synthetic command.
	 * @return string
	 */
	private function command_digest( array $command ) {
		$ordered = array( 'domain' => 'adb-consent-fixture-command-v1' );
		foreach ( array( 'command_id', 'purpose', 'site_uuid', 'enrollment_binding', 'expected_generation', 'issued_at', 'expires_at', 'replacement_binding' ) as $key ) {
			$ordered[ $key ] = $command[ $key ];
		}
		return hash( 'sha256', wp_json_encode( $ordered ) );
	}

	/**
	 * Reserve one additional closure increment without resetting any counter.
	 *
	 * @param array $state Source state.
	 * @param int   $steps Required headroom.
	 * @return bool
	 */
	private function headroom( array $state, $steps ) {
		return max( $state['generation'], $state['schedule_revision'], $state['lifecycle']['permission_generation'] ) <= PHP_INT_MAX - 1 - $steps;
	}
}
