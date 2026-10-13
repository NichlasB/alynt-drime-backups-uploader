<?php
/**
 * Read-only model-change rollback safety inspection; never execution permission.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Derives candidate evidence from local native journals rather than legacy actions. */
trait Alynt_Drime_Backups_Uploader_Cadence_Authority_Evidence_Inspection {
	/**
	 * Inspect a native model change. Does not authenticate or reserve an intent.
	 *
	 * @param string $source_change_action_id Native model-change owner ID.
	 * @param string $preview_action_id Caller inspection ID, not an executed action.
	 * @param int    $intent_expires_at Candidate UTC intent expiry.
	 * @return array|WP_Error
	 */
	public function inspect( $source_change_action_id, $preview_action_id, $intent_expires_at ) {
		$now = call_user_func( $this->clock );
		if ( ! is_int( $now ) || $now <= 0 || $now > PHP_INT_MAX - 3600 || ! $this->uuid( $source_change_action_id ) || ! $this->uuid( $preview_action_id )
			|| $source_change_action_id === $preview_action_id || ! is_int( $intent_expires_at ) || $intent_expires_at <= $now || $intent_expires_at > $now + 300 ) {
			return $this->error( 'invalid_intent' );
		}
		$context = $this->context();
		$state   = $this->store->snapshot();
		if ( is_wp_error( $context ) || is_wp_error( $state ) ) {
			return $this->error( 'unavailable' );
		}
		if ( ! $context['preview_opt_in'] || ! $state['authority']['enabled'] || 'none' !== $this->pending_phase( $state ) ) {
			return $this->error( 'inspection_blocked' );
		}
		if ( ! hash_equals( $state['enrollment_binding'], $context['binding'] ) ) {
			return $this->error( 'binding_changed' );
		}
		if ( isset( $state['claims'][ $source_change_action_id ] ) ) {
			return $this->error( 'source_consumed' );
		}
		$source = null;
		foreach ( $state['claims'] as $record ) {
			if ( $record['action_id'] === $source_change_action_id ) {
				if ( null !== $source ) {
					return $this->error( 'source_invalid' );
				}
				$source = $record;
			}
		}
		if ( null === $source || ! isset( $source['authority_evidence_version'] ) || 1 !== $source['authority_evidence_version'] || 'succeeded' !== $source['phase'] ) {
			return $this->error( 'source_not_native' );
		}
		if ( $source['enrollment_binding'] !== $context['binding'] || $source['schedule_revision'] + 1 !== $state['schedule_revision']
			|| $source['intended_cadence'] !== $state['authority']['cadence'] || $source['intended_fingerprint'] !== $this->store->authority_fingerprint( $state ) ) {
			return $this->error( 'source_stale' );
		}
		if ( $source['completed_at'] > $now || $source['completed_at'] + 3600 <= $now || $source['completed_at'] > PHP_INT_MAX - 3600 ) {
			return $this->error( 'source_expired' );
		}
		$result                        = array(
			'contract_version'        => 1,
			'mode'                    => 'client_owned_v1',
			'kind'                    => 'model_change_preview',
			'site_uuid'               => $context['site_uuid'],
			'key_id'                  => $context['key_id'],
			'source_change_action_id' => $source_change_action_id,
			'preview_action_id'       => $preview_action_id,
			'authority_revision'      => $state['schedule_revision'],
			'authority_fingerprint'   => $this->store->authority_fingerprint( $state ),
			'current_cadence'         => $state['authority']['cadence'],
			'rollback_cadence'        => $source['previous_cadence'],
			'source_created_at'       => $source['completed_at'],
			'source_expires_at'       => $source['completed_at'] + 3600,
			'preview_created_at'      => $now,
			'preview_expires_at'      => min( $now + 900, $source['completed_at'] + 3600, $intent_expires_at ),
			'intent_expires_at'       => $intent_expires_at,
			'safety_checks_passed'    => true,
			'eligible'                => false,
			'dispatch_supported'      => false,
			'execution_supported'     => false,
		);
		$result['preview_fingerprint'] = hash( 'sha256', wp_json_encode( $result ) );
		if ( $state !== $this->store->snapshot() || $context !== $this->context() ) {
			return $this->error( 'observation_changed' );
		}
		return $result;
	}
}
