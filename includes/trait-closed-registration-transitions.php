<?php
/**
 * In-memory closed successors only. No writes, dispatch or native capability.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Exact fixed transitions; not an admission decision or executor. */
trait Alynt_Drime_Backups_Uploader_Closed_Registration_Transitions {
	/**
	 * Append a single pending synthetic record with every identity reserved once.
	 *
	 * @param mixed $raw Original private candidate.
	 * @param mixed $scope Explicit synthetic scope.
	 * @param mixed $proposal Synthetic identities/actor/frame labels.
	 * @param mixed $now Synthetic clock.
	 * @return string|false Private successor DATA, not a write-capable handle.
	 */
	public static function register( $raw, $scope, $proposal, $now ) {
		$s = self::read( $raw, $scope );
		if ( false === $s || array() !== $s['operations'] || ! self::keys( $proposal, self::proposal_keys() ) || false === self::encoded( $proposal, self::MAX_RECORD ) || ! self::command( $proposal['command'], 'register_closed' ) || ! self::number( $proposal['actor_id'] ) || ! self::keys( $proposal['effect_ids'], self::roles() ) || ! self::keys( $proposal['reservation_command_ids'], self::roles() ) || ! self::hash_value( $proposal['dependency_digest'] ) ) {
			return false;
		}
		$e = $proposal['command'];
		if ( ! self::matches_root( $e, $s ) || $s['ledger_revision'] >= PHP_INT_MAX - 6 || ! self::window( $e['issued_at'], $e['expires_at'], $now ) || $e['issued_at'] < $s['origin']['created_at'] || self::registration_context( $scope, $proposal ) !== $e['context_digest'] ) {
			return false;
		}
		$effects = array();
		foreach ( self::roles() as $role ) {
			$effects[ $role ] = array(
				'effect_id'         => $proposal['effect_ids'][ $role ],
				'purpose'           => $role,
				'state'             => 'reserved',
				'dependency_digest' => null,
				'before_hash'       => null,
				'after_hash'        => null,
				'result_revision'   => null,
				'receipt'           => null,
			);
		}
		++$s['ledger_revision'];
		$o = array(
			'operation_id'                   => $proposal['operation_id'],
			'command'                        => $e,
			'provenance'                     => array(
				'source'                => 'genuine_core_closed_registration_v2',
				'actor_id'              => $proposal['actor_id'],
				'issued_command_digest' => self::digest( array( self::DOMAIN, 'issued_command', $e ) ),
				'recorded_at'           => $now,
			),
			'context_digest'                 => $e['context_digest'],
			'registration_dependency_digest' => $proposal['dependency_digest'],
			'entry_point'                    => 'closed_native_proof',
			'generation'                     => $s['authority_generation'],
			'participant_digest'             => $s['participant_digest'],
			'issued_at'                      => $e['issued_at'],
			'expires_at'                     => $e['expires_at'],
			'registration_revision'          => $s['ledger_revision'],
			'reservation_command_ids'        => $proposal['reservation_command_ids'],
			'phase'                          => 'registered',
			'effects'                        => $effects,
			'terminal_id'                    => $proposal['terminal_id'],
			'resolution_id'                  => $proposal['resolution_id'],
			'registration_receipt'           => self::receipt( $e, $proposal['operation_id'], $s['ledger_revision'], $now ),
			'reservation_receipt'            => null,
			'terminal'                       => null,
		);
		// 3 intent hashes plus a maximum 1024-byte bookkeeping receipt and syntax.
		$record = self::encoded( $o, self::MAX_RECORD - self::INTENT_HEADROOM );
		if ( false === $record ) {
			return false;
		}
		$s['operations'][ $proposal['operation_id'] ] = $o;
		return self::successor( $s, $scope );
	}

	/**
	 * Reserve only the original actor's fixed WPvivid intent; never apply it.
	 *
	 * @param mixed $raw Registered candidate.
	 * @param mixed $scope Explicit synthetic scope.
	 * @param mixed $proposal Typed snapshot and independent command labels.
	 * @param mixed $now Synthetic clock.
	 * @return string|false Private successor DATA only.
	 */
	public static function reserve( $raw, $scope, $proposal, $now ) {
		$s = self::read( $raw, $scope );
		if ( false === $s || ! self::keys( $proposal, array( 'command', 'actor_id', 'dependency_digest', 'before', 'replacement' ) ) || ! self::command( $proposal['command'], 'reserve_wpvivid_intent' ) || ! self::number( $proposal['actor_id'] ) || ! self::hash_value( $proposal['dependency_digest'] ) ) {
			return false;
		}
		$e = $proposal['command'];
		if ( ! isset( $s['operations'][ $e['operation_id'] ] ) || ! self::matches_root( $e, $s ) || ! self::window( $e['issued_at'], $e['expires_at'], $now ) ) {
			return false;
		}
		$o                          = $s['operations'][ $e['operation_id'] ];
		$expected                   = self::reserve_command( $o );
		$context                    = self::reservation_context( $o['context_digest'], $proposal );
		$expected['context_digest'] = $context;
		if ( 'registered' !== $o['phase'] || $proposal['actor_id'] !== $o['provenance']['actor_id'] || $now < $o['provenance']['recorded_at'] || false === $context || $expected !== $e ) {
			return false;
		}
		$target                    = Alynt_Drime_Backups_Uploader_Scan_Target_Codec::snapshot( 'wpvivid_snapshot', $proposal['before'], $proposal['replacement'] );
		$slot                      = &$o['effects']['wpvivid_snapshot'];
		$slot['state']             = 'intent_reserved';
		$slot['dependency_digest'] = $proposal['dependency_digest'];
		$slot['before_hash']       = $target['before_hash'];
		$slot['after_hash']        = $target['after_hash'];
		unset( $slot );
		++$s['ledger_revision'];
		$o['phase']                            = 'intent_reserved';
		$o['reservation_receipt']              = self::receipt( $e, $o['operation_id'], $s['ledger_revision'], $now );
		$s['operations'][ $o['operation_id'] ] = $o;
		return self::successor( $s, $scope );
	}

	/**
	 * Exact current data scope/revision before any arithmetic or transition.
	 *
	 * @param array $e Strict typed command.
	 * @param array $s Strict root.
	 * @return bool
	 */
	private static function matches_root( $e, $s ) {
		return $e['site_uuid'] === $s['site_uuid'] && $e['enrollment_binding'] === $s['enrollment_binding'] && $e['expected_generation'] === $s['authority_generation'] && $e['expected_ledger_revision'] === $s['ledger_revision'] && $s['ledger_revision'] < PHP_INT_MAX - 5;
	}

	/**
	 * Preserve terminal plus incoming-record byte reserve; revalidate full output.
	 *
	 * @param array $s Synthetic successor.
	 * @param array $scope Pinned scope.
	 * @return string|false
	 */
	private static function successor( $s, $scope ) {
		$raw = self::encoded( $s, 131072 - self::TERMINAL_BYTES - self::MAX_RECORD );
		return false !== $raw && false !== self::read( $raw, $scope ) ? $raw : false;
	}
}
