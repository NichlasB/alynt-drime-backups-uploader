<?php
/**
 * Exact closed ledger record/receipt grammar, not event provenance.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Internal helpers owned only by the closed data validator. */
trait Alynt_Drime_Backups_Uploader_Closed_Scan_Effect_Records {
	/**
	 * Enforce immutable identities, reserved slots and closed terminal accounting.
	 *
	 * @param mixed  $o Operation.
	 * @param string $id Map key.
	 * @param array  $s Root.
	 * @param array  $ids All consumed identities, never pruned.
	 * @param array  $revisions All receipt revisions.
	 * @return bool
	 */
	private static function operation( $o, $id, $s, &$ids, &$revisions ) {
		if ( ! self::keys( $o, array( 'operation_id', 'command_id', 'context_digest', 'entry_point', 'generation', 'participant_digest', 'issued_at', 'expires_at', 'phase', 'effects', 'terminal_id', 'resolution_id', 'terminal' ) ) || $o['operation_id'] !== $id || ! self::hash_value( $o['context_digest'] ) || 'scheduled' !== $o['entry_point'] || ! self::number( $o['generation'] ) || $o['generation'] > $s['authority_generation'] || $o['participant_digest'] !== $s['participant_digest'] || ! self::number( $o['issued_at'] ) || ! self::number( $o['expires_at'] ) || $o['expires_at'] <= $o['issued_at'] || $o['expires_at'] - $o['issued_at'] > 300 || ! in_array( $o['phase'], array( 'registered', 'intent_reserved', 'draining', 'unresolved', 'terminal' ), true ) || ! self::keys( $o['effects'], array( 'wpvivid_snapshot', 'outbox_snapshot', 'queue_addition' ) ) || strlen( json_encode( $o ) ) > 6144 ) {
			return false;
		}
		foreach ( array( $id, $o['command_id'], $o['terminal_id'], $o['resolution_id'] ) as $uuid ) {
			if ( ! self::identity( $uuid, $ids ) ) {
				return false;
			}
		}
		$states   = array();
		$receipts = array();
		$last     = 0;
		foreach ( $o['effects'] as $purpose => $effect ) {
			if ( ! self::effect( $effect, $purpose, $o, $s, $ids, $revisions ) ) {
				return false;
			}
			$states[] = $effect['state'];
			if ( null !== $effect['receipt'] ) {
				$receipts[ $purpose ] = hash( 'sha256', json_encode( $effect['receipt'] ) );
				$last                 = max( $last, $effect['result_revision'] );
			}
		}
		$binding = array( self::DOMAIN, $s['site_uuid'], $s['enrollment_binding'], $o['operation_id'], $o['command_id'], $o['entry_point'], $o['generation'], $o['participant_digest'], $o['issued_at'], $o['expires_at'], $o['terminal_id'], $o['resolution_id'], array_column( $o['effects'], 'effect_id' ) );
		if ( hash( 'sha256', json_encode( $binding ) ) !== $o['context_digest'] ) {
			return false;
		}
		if ( 'terminal' === $o['phase'] ) {
			$t = $o['terminal'];
			return 3 === count( $receipts ) && self::keys( $t, array( 'domain', 'purpose', 'terminal_id', 'operation_id', 'context_digest', 'result_revision', 'effect_receipts' ) ) && self::DOMAIN === $t['domain'] && 'terminal' === $t['purpose'] && $t['terminal_id'] === $o['terminal_id'] && $t['operation_id'] === $id && $t['context_digest'] === $o['context_digest'] && $t['effect_receipts'] === $receipts && $t['result_revision'] > $last && self::revision( $t['result_revision'], $s['ledger_revision'], $revisions );
		}
		if ( null !== $o['terminal'] ) {
			return false;
		}
		$counts = array_count_values( $states );
		if ( 'registered' === $o['phase'] ) {
			return 3 === ( $counts['reserved'] ?? 0 );
		}
		if ( 'unresolved' === $o['phase'] ) {
			return 1 === ( $counts['unresolved'] ?? 0 ) && 0 === ( $counts['intent_reserved'] ?? 0 );
		}
		return 0 === ( $counts['unresolved'] ?? 0 ) && ( $counts['intent_reserved'] ?? 0 ) <= 1 && ( 'draining' === $o['phase'] || 1 === ( $counts['intent_reserved'] ?? 0 ) );
	}

	/**
	 * Slot/receipt agreement is structural only, never proof of a native commit.
	 *
	 * @param mixed  $e Slot.
	 * @param string $purpose Fixed slot.
	 * @param array  $o Owner data.
	 * @param array  $s Root.
	 * @param array  $ids Used identities.
	 * @param array  $revisions Used revisions.
	 * @return bool
	 */
	private static function effect( $e, $purpose, $o, $s, &$ids, &$revisions ) {
		if ( ! self::keys( $e, array( 'effect_id', 'purpose', 'state', 'dependency_digest', 'before_hash', 'after_hash', 'result_revision', 'receipt' ) ) || $e['purpose'] !== $purpose || ! self::identity( $e['effect_id'], $ids ) || ! in_array( $e['state'], array( 'reserved', 'intent_reserved', 'committed', 'cancelled', 'unresolved' ), true ) ) {
			return false;
		}
		if ( 'reserved' === $e['state'] ) {
			return array( null, null, null, null, null ) === array( $e['dependency_digest'], $e['before_hash'], $e['after_hash'], $e['result_revision'], $e['receipt'] );
		}
		if ( ! self::hash_value( $e['dependency_digest'] ) || ( 'cancelled' === $e['state'] ? null !== $e['before_hash'] || null !== $e['after_hash'] : ! self::hash_value( $e['before_hash'] ) || ! self::hash_value( $e['after_hash'] ) ) ) {
			return false;
		}
		if ( in_array( $e['state'], array( 'intent_reserved', 'unresolved' ), true ) ) {
			return null === $e['result_revision'] && null === $e['receipt'];
		}
		$r                = $e['receipt'];
		$expected_purpose = 'cancelled' === $e['state'] ? 'cancel_' . $purpose : $purpose;
		if ( ! self::keys( $r, array( 'domain', 'purpose', 'operation_id', 'effect_id', 'context_digest', 'dependency_digest', 'before_hash', 'after_hash', 'result_revision', 'classification' ) ) || self::DOMAIN !== $r['domain'] || $r['purpose'] !== $expected_purpose || $r['operation_id'] !== $o['operation_id'] || $r['effect_id'] !== $e['effect_id'] || $r['context_digest'] !== $o['context_digest'] ) {
			return false;
		}
		foreach ( array( 'dependency_digest', 'before_hash', 'after_hash', 'result_revision' ) as $key ) {
			if ( $r[ $key ] !== $e[ $key ] ) {
				return false;
			}
		}
		$classification = 'cancelled' === $e['state'] ? 'cancelled' : ( $e['before_hash'] === $e['after_hash'] ? 'no_change' : 'committed' );
		return $r['classification'] === $classification && self::revision( $e['result_revision'], $s['ledger_revision'], $revisions );
	}

	/**
	 * No reuse across operation, command, terminal, resolution or effect domains.
	 *
	 * @param mixed $id Candidate UUID.
	 * @param array $ids Used identities.
	 * @return bool
	 */
	private static function identity( $id, &$ids ) {
		if ( ! self::uuid( $id ) || isset( $ids[ $id ] ) ) {
			return false;
		}
		$ids[ $id ] = true;
		return true;
	}

	/**
	 * Distinct bounded receipt revision, never a generated successor.
	 *
	 * @param mixed $revision Receipt revision.
	 * @param int   $root Root revision.
	 * @param array $seen Used revisions.
	 * @return bool
	 */
	private static function revision( $revision, $root, &$seen ) {
		if ( ! self::number( $revision ) || $revision > $root || isset( $seen[ $revision ] ) ) {
			return false;
		}
		$seen[ $revision ] = true;
		return true;
	}
}
