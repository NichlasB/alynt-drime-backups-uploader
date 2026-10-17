<?php
/**
 * Strict finite candidate schema 7; real authenticated provenance stays denied.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Pure validation, not a storage migration or physical target proof. */
trait Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Validation {
	/**
	 * Validate exact successor, frozen origin and contiguous fixture chain.
	 *
	 * @param array  $s Candidate.
	 * @param string $site Fixed scope.
	 * @return bool
	 */
	public static function valid( array $s, $site ) {
		if ( ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::plain( $s ) || ! self::keys( $s, array( 'schema_version', 'generation', 'enrollment_binding', 'schedule_revision', 'claims', 'authority', 'dispatch', 'lifecycle', 'legacy_writer', 'writer' ) )
			|| 7 !== $s['schema_version'] || ! is_array( $s['writer'] ) || ! is_array( $s['lifecycle'] ) || ! is_array( $s['legacy_writer'] ) ) {
			return false;
		}
		$w = $s['writer'];
		if ( ! self::keys( $w, array( 'source', 'origin', 'upgrade', 'authenticated_grant', 'authenticated_receipts', 'internal_receipts', 'fixture_receipts', 'pending', 'closure', 'completion' ) ) || self::SOURCE !== $w['source'] || null !== $w['authenticated_grant'] || array() !== $w['authenticated_receipts'] || array() !== $w['internal_receipts'] || ! is_array( $w['origin'] ) || ! is_array( $w['fixture_receipts'] )
			|| ! self::keys( $w['origin'], array( 'generation', 'schedule_revision', 'permission_generation', 'changed_at', 'source_digest' ) ) || ! self::keys( $s['lifecycle'], array( 'phase', 'consent', 'permission_generation', 'history', 'changed_at' ) ) || 'disabled' !== $s['lifecycle']['phase'] || false !== $s['lifecycle']['consent'] ) {
			return false;
		}
		$old = Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::original( $s );
		if ( ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::source( $old, $site ) || Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::hash( $old ) !== $w['origin']['source_digest'] || ! self::valid_record( $w['upgrade'], $site ) || 'upgrade_closed' !== $w['upgrade']['purpose'] ) {
			return false;
		}
		$u = $w['upgrade'];
		if ( $u['command']['origin_digest'] !== $w['origin']['source_digest'] || $u['command']['expected_generation'] !== $old['lifecycle']['permission_generation'] || $u['committed_at'] < $old['lifecycle']['changed_at'] ) {
			return false;
		}
		$delta = $s['lifecycle']['permission_generation'];
		if ( ! is_int( $delta ) || $delta < $u['result_generation'] ) {
			return false;
		}
		$delta -= $old['lifecycle']['permission_generation'];
		foreach ( array( 'generation', 'schedule_revision' ) as $key ) {
			if ( $old[ $key ] > PHP_INT_MAX - 1 - $delta || $s[ $key ] !== $old[ $key ] + $delta ) {
				return false;
			}
		}
		$raw     = json_encode( $s );
		$pending = null !== $w['pending'];
		$reserve = $pending || null === $w['closure'];
		return self::room( $s, $pending ? 2 : ( null === $w['closure'] ? 3 : 1 ) ) && self::legacy( $s )['count'] + count( $w['fixture_receipts'] ) <= 50
			&& self::chain( $s, $site ) && is_string( $raw ) && strlen( $raw ) <= 131072 - ( $reserve ? 4096 : 0 ) && is_array( json_decode( $raw, true, 16 ) );
	}

	/**
	 * Checked headroom includes a closed reserve after terminal completion.
	 *
	 * @param array $s State.
	 * @param int   $increments Required increments.
	 * @return bool
	 */
	private static function room( array $s, $increments ) {
		foreach ( array( $s['generation'], $s['schedule_revision'], $s['lifecycle']['permission_generation'] ) as $value ) {
			if ( ! is_int( $value ) || $value < 1 || $value > PHP_INT_MAX - 1 - $increments ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check receipt order, all identities, pending ownership and terminal facts.
	 *
	 * @param array  $s Structural successor.
	 * @param string $site Scope.
	 * @return bool
	 */
	private static function chain( array $s, $site ) {
		$w        = $s['writer'];
		$ordered  = array();
		$ids      = self::legacy( $s )['ids'];
		$reserved = array();
		foreach ( self::all_records( $s ) as $r ) {
			if ( ! self::valid_record( $r, $site ) || isset( $ids[ $r['command']['command_id'] ] ) || isset( $ordered[ $r['result_generation'] ] ) || $r['command']['enrollment_binding'] !== $s['enrollment_binding'] ) {
				return false;
			}
			$ids[ $r['command']['command_id'] ] = true;
			$ordered[ $r['result_generation'] ] = $r;
			if ( null !== $r['completion_id'] ) {
				if ( isset( $reserved[ $r['completion_id'] ] ) || isset( self::legacy( $s )['ids'][ $r['completion_id'] ] ) || $r['completion_id'] === $r['command']['command_id'] ) {
					return false;
				}
				$reserved[ $r['completion_id'] ] = $r['command']['command_id'];
			}
		}
		foreach ( $w['fixture_receipts'] as $id => $r ) {
			if ( $id !== $r['command']['command_id'] || 'upgrade_closed' === $r['purpose'] ) {
				return false;
			}
		}
		foreach ( $ordered as $r ) {
			$id = $r['command']['command_id'];
			if ( isset( $reserved[ $id ] ) && ( 'complete_fixture' !== $r['purpose'] || $reserved[ $id ] !== $r['command']['operation_id'] ) ) {
				return false;
			}
		}
		ksort( $ordered, SORT_NUMERIC );
		$epoch      = $w['origin']['permission_generation'];
		$stamp      = $w['origin']['changed_at'];
		$open       = null;
		$closure    = null;
		$completion = null;
		foreach ( $ordered as $r ) {
			$e = $r['command'];
			if ( $e['expected_generation'] !== $epoch || $r['result_generation'] !== $epoch + 1 || $r['committed_at'] < $stamp ) {
				return false;
			}
			if ( 'upgrade_closed' === $r['purpose'] ) {
				if ( $r !== $w['upgrade'] || $epoch !== $w['origin']['permission_generation'] ) {
					return false;
				}
			} elseif ( 'begin_disable' === $r['purpose'] ) {
				if ( null !== $open ) {
					return false;
				}
				$open       = $r;
				$closure    = $r;
				$completion = null;
			} else {
				if ( null === $open || ! self::terminal_matches( $r, $open ) ) {
					return false;
				}
				$open       = null;
				$completion = $r;
			}
			$epoch = $r['result_generation'];
			$stamp = $r['committed_at'];
		}
		return $w['closure'] === $closure && $w['completion'] === $completion && ( null === $open ? null : self::pending( $open ) ) === $w['pending'] && $s['lifecycle']['permission_generation'] === $epoch && $s['lifecycle']['changed_at'] === $stamp;
	}

	/**
	 * Terminal owns exactly the begin and original reserved completion identity.
	 *
	 * @param array $r Terminal.
	 * @param array $begin Begin.
	 * @return bool
	 */
	private static function terminal_matches( array $r, array $begin ) {
		$e = $r['command'];
		$b = $begin['command'];
		if ( ! in_array( $r['purpose'], array( 'abandon_closed', 'complete_fixture' ), true ) || $e['operation_id'] !== $b['command_id'] || $e['closure_generation'] !== $begin['result_generation'] || ! in_array( $e['observed_hash'], array( $b['before_hash'], $b['after_hash'] ), true ) ) {
			return false;
		}
		return 'abandon_closed' === $r['purpose'] || ( $e['command_id'] === $begin['completion_id'] && $e['observed_hash'] === $b['after_hash'] && $e['issued_at'] === $b['issued_at'] && $e['expires_at'] === $b['expires_at'] );
	}

	/**
	 * Immutable fixed disable descriptor; no target/desired fields from a form.
	 *
	 * @param array $r Begin receipt.
	 * @return array
	 */
	private static function pending( array $r ) {
		$e = $r['command'];
		return array(
			'operation_id'        => $e['command_id'],
			'expected_generation' => $e['expected_generation'],
			'closure_generation'  => $r['result_generation'],
			'completion_id'       => $r['completion_id'],
			'before_hash'         => $e['before_hash'],
			'after_hash'          => $e['after_hash'],
			'context_digest'      => $e['context_digest'],
		);
	}
}
