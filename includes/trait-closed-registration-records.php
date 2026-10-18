<?php
/**
 * Exact closed bookkeeping grammar, never authenticated provenance.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Private record helpers used only by the dormant pure contract. */
trait Alynt_Drime_Backups_Uploader_Closed_Registration_Records {
	/**
	 * Fixed roles; additional reservation/effect execution is unsupported.
	 *
	 * @return array
	 */
	private static function roles() {
		return array( 'wpvivid_snapshot', 'outbox_snapshot', 'queue_addition' );
	}

	/**
	 * Exact synthetic registration proposal, never a native callback port.
	 *
	 * @return array
	 */
	private static function proposal_keys() {
		return array( 'command', 'actor_id', 'operation_id', 'effect_ids', 'reservation_command_ids', 'terminal_id', 'resolution_id', 'dependency_digest' );
	}

	/**
	 * Typed envelope only: no request parsing, nonce issuance or authentication.
	 *
	 * @param mixed  $e Candidate.
	 * @param string $purpose Fixed mutation purpose.
	 * @return bool
	 */
	private static function command( $e, $purpose ) {
		$keys = array( 'contract', 'purpose', 'command_id', 'site_uuid', 'enrollment_binding', 'expected_generation', 'expected_ledger_revision', 'context_digest', 'issued_at', 'expires_at', 'confirm' );
		if ( 'reserve_wpvivid_intent' === $purpose ) {
			$keys[] = 'operation_id';
		}
		$confirm = 'register_closed' === $purpose ? 'register' : 'reserve';
		return self::keys( $e, $keys ) && false !== self::encoded( $e, 4096 ) && 'scan_registration_request_v2' === $e['contract'] && $purpose === $e['purpose'] && $confirm === $e['confirm'] && self::uuid( $e['command_id'] ) && self::uuid( $e['site_uuid'] ) && self::hash_value( $e['enrollment_binding'] ) && self::number( $e['expected_generation'] ) && self::number( $e['expected_ledger_revision'] ) && self::hash_value( $e['context_digest'] ) && self::window( $e['issued_at'], $e['expires_at'], $e['issued_at'] ) && ( 'register_closed' === $purpose || self::uuid( $e['operation_id'] ) );
	}

	/**
	 * Remove only the circular field before domain-separated context hashing.
	 *
	 * @param array $command Typed command.
	 * @return array
	 */
	private static function without_context( $command ) {
		unset( $command['context_digest'] );
		return $command;
	}

	/**
	 * Reservation command is derivable from original times and once-reserved ID.
	 *
	 * @param array $o Strict operation.
	 * @return array
	 */
	private static function reserve_command( $o ) {
		$e                             = $o['command'];
		$e['purpose']                  = 'reserve_wpvivid_intent';
		$e['command_id']               = $o['reservation_command_ids']['wpvivid_snapshot'];
		$e['expected_ledger_revision'] = $o['registration_revision'];
		$e['confirm']                  = 'reserve';
		$e['operation_id']             = $o['operation_id'];
		return $e;
	}

	/**
	 * Reconstructable intent binding without retaining candidate bytes.
	 *
	 * @param string $registration Original context.
	 * @param array  $e Typed envelope.
	 * @param int    $actor Synthetic actor label.
	 * @param string $dependency New frame digest label.
	 * @param string $before Before hash.
	 * @param string $after After hash.
	 * @return string|false
	 */
	private static function intent_digest( $registration, $e, $actor, $dependency, $before, $after ) {
		return self::digest( array( self::DOMAIN, 'reserve_wpvivid_intent', $registration, self::without_context( $e ), $actor, $dependency, $before, $after ) );
	}

	/**
	 * Exact bookkeeping receipt; no effect completion or native commit claim.
	 *
	 * @param array  $e Typed envelope.
	 * @param string $operation Operation ID.
	 * @param int    $revision Result ledger revision.
	 * @param int    $now Historical data clock.
	 * @return array
	 */
	private static function receipt( $e, $operation, $revision, $now ) {
		return array(
			'domain'          => self::DOMAIN,
			'purpose'         => $e['purpose'],
			'command_id'      => $e['command_id'],
			'operation_id'    => $operation,
			'context_digest'  => $e['context_digest'],
			'result_revision' => $revision,
			'recorded_at'     => $now,
			'classification'  => 'register_closed' === $e['purpose'] ? 'registered_closed' : 'intent_recorded_closed',
		);
	}

	/**
	 * Validate one complete operation and both independent context/receipt chains.
	 * Only registered and first reserved-intent phases exist in this profile.
	 *
	 * @param mixed  $o Candidate operation.
	 * @param string $id Map key.
	 * @param array  $s Root.
	 * @param array  $scope Pinned genesis.
	 * @return bool
	 */
	private static function record( $o, $id, $s, $scope ) {
		if ( ! self::keys( $o, array( 'operation_id', 'command', 'provenance', 'context_digest', 'registration_dependency_digest', 'entry_point', 'generation', 'participant_digest', 'issued_at', 'expires_at', 'registration_revision', 'reservation_command_ids', 'phase', 'effects', 'terminal_id', 'resolution_id', 'registration_receipt', 'reservation_receipt', 'terminal' ) ) || false === self::encoded( $o, self::MAX_RECORD ) || ! self::command( $o['command'], 'register_closed' ) || ! self::keys( $o['provenance'], array( 'source', 'actor_id', 'issued_command_digest', 'recorded_at' ) ) || 'genuine_core_closed_registration_v2' !== $o['provenance']['source'] || ! self::number( $o['provenance']['actor_id'] ) || 'closed_native_proof' !== $o['entry_point'] || $o['operation_id'] !== $id || ! self::keys( $o['effects'], self::roles() ) || ! self::keys( $o['reservation_command_ids'], self::roles() ) || ! self::hash_value( $o['registration_dependency_digest'] ) || null !== $o['terminal'] || ! in_array( $o['phase'], array( 'registered', 'intent_reserved' ), true ) ) {
			return false;
		}
		$e = $o['command'];
		if ( $e['site_uuid'] !== $s['site_uuid'] || $e['enrollment_binding'] !== $s['enrollment_binding'] || $e['expected_generation'] !== $s['authority_generation'] || $o['generation'] !== $s['authority_generation'] || $o['participant_digest'] !== $s['participant_digest'] || $o['issued_at'] !== $e['issued_at'] || $o['expires_at'] !== $e['expires_at'] || $o['issued_at'] < $s['origin']['created_at'] || $o['context_digest'] !== $e['context_digest'] || $o['registration_revision'] !== $e['expected_ledger_revision'] + 1 || self::digest( array( self::DOMAIN, 'issued_command', $e ) ) !== $o['provenance']['issued_command_digest'] || ! self::window( $o['issued_at'], $o['expires_at'], $o['provenance']['recorded_at'] ) ) {
			return false;
		}
		$p = array(
			'command'                 => $e,
			'actor_id'                => $o['provenance']['actor_id'],
			'operation_id'            => $id,
			'effect_ids'              => array_column( $o['effects'], 'effect_id', 'purpose' ),
			'reservation_command_ids' => $o['reservation_command_ids'],
			'terminal_id'             => $o['terminal_id'],
			'resolution_id'           => $o['resolution_id'],
			'dependency_digest'       => $o['registration_dependency_digest'],
		);
		if ( self::registration_context( $scope, $p ) !== $o['context_digest'] || ! self::distinct( array_merge( array( $s['site_uuid'], $s['origin']['ledger_id'], $id, $e['command_id'], $o['terminal_id'], $o['resolution_id'] ), array_values( $p['effect_ids'] ), array_values( $o['reservation_command_ids'] ) ) ) ) {
			return false;
		}
		$registered = self::receipt( $e, $id, $o['registration_revision'], $o['provenance']['recorded_at'] );
		if ( $registered !== $o['registration_receipt'] || false === self::encoded( $registered, self::MAX_RECEIPT ) ) {
			return false;
		}
		foreach ( $o['effects'] as $role => $effect ) {
			if ( ! self::keys( $effect, array( 'effect_id', 'purpose', 'state', 'dependency_digest', 'before_hash', 'after_hash', 'result_revision', 'receipt' ) ) || $effect['purpose'] !== $role || null !== $effect['result_revision'] || null !== $effect['receipt'] ) {
				return false;
			}
			$intent = 'intent_reserved' === $o['phase'] && 'wpvivid_snapshot' === $role;
			if ( $intent ? 'intent_reserved' !== $effect['state'] || ! self::hash_value( $effect['dependency_digest'] ) || ! self::hash_value( $effect['before_hash'] ) || ! self::hash_value( $effect['after_hash'] ) : array( 'reserved', null, null, null ) !== array( $effect['state'], $effect['dependency_digest'], $effect['before_hash'], $effect['after_hash'] ) ) {
				return false;
			}
		}
		if ( 'registered' === $o['phase'] ) {
			return null === $o['reservation_receipt'] && $s['ledger_revision'] === $o['registration_revision'];
		}
		$r                   = $o['reservation_receipt'];
		$effect              = $o['effects']['wpvivid_snapshot'];
		$e                   = self::reserve_command( $o );
		$e['context_digest'] = self::intent_digest( $o['context_digest'], $e, $o['provenance']['actor_id'], $effect['dependency_digest'], $effect['before_hash'], $effect['after_hash'] );
		return is_array( $r ) && self::window( $o['issued_at'], $o['expires_at'], $r['recorded_at'] ?? null ) && $r['recorded_at'] >= $o['provenance']['recorded_at'] && self::receipt( $e, $id, $o['registration_revision'] + 1, $r['recorded_at'] ) === $r && false !== self::encoded( $r, self::MAX_RECEIPT ) && $s['ledger_revision'] === $o['registration_revision'] + 1;
	}

	/**
	 * No UUID reuse across any once-reserved role or genesis/site ID.
	 *
	 * @param array $ids Synthetic UUIDs.
	 * @return bool
	 */
	private static function distinct( $ids ) {
		$seen = array();
		foreach ( $ids as $id ) {
			if ( ! self::uuid( $id ) || isset( $seen[ $id ] ) ) {
				return false;
			}
			$seen[ $id ] = true;
		}
		return true;
	}

	/**
	 * Fixed bounded set; not a database search or consumed-ID certificate.
	 *
	 * @param array $s Strict candidate root.
	 * @return array
	 */
	private static function identities( $s ) {
		$ids = array( $s['site_uuid'], $s['origin']['ledger_id'] );
		foreach ( $s['operations'] as $o ) {
			$ids = array_merge( $ids, array( $o['operation_id'], $o['command']['command_id'], $o['terminal_id'], $o['resolution_id'] ), array_column( $o['effects'], 'effect_id' ), array_values( $o['reservation_command_ids'] ) );
		}
		return array_fill_keys( $ids, true );
	}
}
