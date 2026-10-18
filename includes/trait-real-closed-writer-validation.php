<?php
/**
 * Strict non-admitting schema-8 validation; no schema-7 promotion.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Reject incomplete, mixed-domain, reordered-chain or open-authority data. */
trait Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Validation {
	/**
	 * Validate consistency only, not the provenance of an authenticated event.
	 *
	 * @param array  $s Candidate data.
	 * @param string $site Fixed UUID.
	 * @return bool
	 */
	public static function valid( array $s, $site ) {
		if ( ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::plain( $s ) || ! self::keys( $s, array( 'schema_version', 'generation', 'enrollment_binding', 'schedule_revision', 'claims', 'authority', 'dispatch', 'lifecycle', 'legacy_writer', 'writer' ) ) || 8 !== $s['schema_version'] || ! is_array( $s['writer'] ) || ! is_array( $s['lifecycle'] ) || ! is_array( $s['legacy_writer'] ) ) {
			return false;
		}
		$w = $s['writer'];
		if ( ! self::keys( $w, array( 'source', 'origin', 'upgrade', 'authenticated_grant', 'authenticated_receipts', 'internal_receipts', 'fixture_receipts', 'pending', 'closure', 'completion' ) ) || Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::SOURCE !== $w['source'] || null !== $w['authenticated_grant'] || array() !== $w['fixture_receipts'] || ! is_array( $w['origin'] ) || ! is_array( $w['authenticated_receipts'] ) || ! is_array( $w['internal_receipts'] ) || ! self::keys( $w['origin'], array( 'generation', 'schedule_revision', 'permission_generation', 'changed_at', 'source_digest' ) ) || ! self::keys( $s['lifecycle'], array( 'phase', 'consent', 'permission_generation', 'history', 'changed_at' ) ) || 'disabled' !== $s['lifecycle']['phase'] || false !== $s['lifecycle']['consent'] ) {
			return false;
		}
		$old = Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::original( $s );
		if ( ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::source( $old, $site ) || Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::hash( $old ) !== $w['origin']['source_digest'] || ! Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::receipt( $w['upgrade'], $site ) || 'upgrade_closed' !== $w['upgrade']['purpose'] ) {
			return false;
		}
		$u = $w['upgrade'];
		if ( $u['command']['origin_digest'] !== $w['origin']['source_digest'] || $u['command']['expected_generation'] !== $old['lifecycle']['permission_generation'] || $u['committed_at'] < $old['lifecycle']['changed_at'] || ! is_int( $s['lifecycle']['permission_generation'] ) || $s['lifecycle']['permission_generation'] < $u['result_generation'] ) {
			return false;
		}
		$delta = $s['lifecycle']['permission_generation'] - $old['lifecycle']['permission_generation'];
		foreach ( array( 'generation', 'schedule_revision' ) as $key ) {
			if ( $old[ $key ] > PHP_INT_MAX - 1 - $delta || $s[ $key ] !== $old[ $key ] + $delta ) {
				return false;
			}
		}
		$pending = null !== $w['pending'];
		$reserve = $pending || null === $w['closure'];
		$raw     = json_encode( $s );
		return self::room( $s, $pending ? 2 : ( null === $w['closure'] ? 3 : 1 ) ) && Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::legacy( $s )['count'] + count( $w['authenticated_receipts'] ) + count( $w['internal_receipts'] ) <= 50 && self::chain( $s, $site ) && is_string( $raw ) && strlen( $raw ) <= 131072 - ( $reserve ? 4096 : 0 ) && is_array( json_decode( $raw, true, 16 ) );
	}

	/**
	 * Keep a closed terminal reserve and reject overflow before arithmetic.
	 *
	 * @param array $s State.
	 * @param int   $increments Required headroom.
	 * @return bool
	 */
	private static function room( array $s, $increments ) {
		foreach ( array( $s['generation'], $s['schedule_revision'], $s['lifecycle']['permission_generation'] ) as $n ) {
			if ( ! is_int( $n ) || $n < 1 || $n > PHP_INT_MAX - 1 - $increments ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * All slots/history share identities, epochs and nonregressing time.
	 *
	 * @param array  $s Structurally checked state.
	 * @param string $site Scope.
	 * @return bool
	 */
	private static function chain( array $s, $site ) {
		$w        = $s['writer'];
		$legacy   = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::legacy( $s )['ids'];
		$ids      = $legacy;
		$ordered  = array();
		$reserved = array();
		foreach ( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::records( $s ) as $r ) {
			if ( ! Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::receipt( $r, $site ) || isset( $ids[ $r['command']['command_id'] ] ) || isset( $ordered[ $r['result_generation'] ] ) || $r['command']['enrollment_binding'] !== $s['enrollment_binding'] ) {
				return false;
			}
			$ids[ $r['command']['command_id'] ] = true;
			$ordered[ $r['result_generation'] ] = $r;
			if ( null !== $r['completion_id'] ) {
				if ( isset( $reserved[ $r['completion_id'] ] ) || isset( $legacy[ $r['completion_id'] ] ) || $r['completion_id'] === $r['command']['command_id'] ) {
					return false;
				}
				$reserved[ $r['completion_id'] ] = $r['command']['command_id'];
			}
		}
		foreach ( array(
			'authenticated_receipts' => Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::AUTH,
			'internal_receipts'      => Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::INTERNAL,
		) as $map => $domain ) {
			foreach ( $w[ $map ] as $id => $r ) {
				if ( $id !== $r['command']['command_id'] || $domain !== $r['domain'] || 'upgrade_closed' === $r['purpose'] ) {
					return false;
				}
			}
		}
		foreach ( $ordered as $r ) {
			$id = $r['command']['command_id'];
			if ( isset( $reserved[ $id ] ) && ( 'complete_disable' !== $r['purpose'] || $reserved[ $id ] !== $r['command']['operation_id'] ) ) {
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
		return $w['closure'] === $closure && $w['completion'] === $completion && ( null === $open ? null : Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::pending( $open ) ) === $w['pending'] && $s['lifecycle']['permission_generation'] === $epoch && $s['lifecycle']['changed_at'] === $stamp;
	}

	/**
	 * Match terminal facts to the original begin, never renewed expiry.
	 *
	 * @param array $r Terminal record.
	 * @param array $begin Original begin record.
	 * @return bool
	 */
	private static function terminal_matches( array $r, array $begin ) {
		$e = $r['command'];
		$b = $begin['command'];
		if ( ! in_array( $r['purpose'], array( 'abandon_closed', 'complete_disable' ), true ) || $e['operation_id'] !== $b['command_id'] || $e['closure_generation'] !== $begin['result_generation'] || ! in_array( $e['observed_hash'], array( $b['before_hash'], $b['after_hash'] ), true ) ) {
			return false;
		}
		return 'abandon_closed' === $r['purpose'] || ( $e['command_id'] === $begin['completion_id'] && $e['observed_hash'] === $b['after_hash'] && $e['issued_at'] === $b['issued_at'] && $e['expires_at'] === $b['expires_at'] );
	}

	/**
	 * Exact fields helper.
	 *
	 * @param array $value Map.
	 * @param array $keys Fields.
	 * @return bool
	 */
	private static function keys( array $value, array $keys ) {
		return Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::keys( $value, $keys );
	}
}
