<?php
/**
 * Pure fixture successor proposals; no persistence, grants or target writes.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Test-loaded candidate schema; reserved real-domain construction is absent. */
final class Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Model {
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Records;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Validation;

	const SOURCE = 'closed_writer_admission_fixture_v1';

	/**
	 * Admit only strict already-existing closed data; never bootstrap or repair.
	 *
	 * @param array  $s Snapshot.
	 * @param string $site Fixed UUID.
	 * @return bool
	 */
	public static function supported( array $s, $site ) {
		return 6 === ( $s['schema_version'] ?? null ) ? Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::source( $s, $site ) : self::valid( $s, $site );
	}

	/**
	 * Observe exact retained intent before considering current context equality.
	 *
	 * @param array $s Validated successor.
	 * @param array $e Original envelope.
	 * @return string
	 */
	public static function observe( array $s, array $e ) {
		$id = $e['command_id'];
		if ( isset( self::legacy( $s )['ids'][ $id ] ) ) {
			return 'domain_conflict';
		}
		if ( 7 !== $s['schema_version'] ) {
			return 'not_found';
		}
		foreach ( self::all_records( $s ) as $r ) {
			if ( $id === $r['command']['command_id'] ) {
				if ( $r['purpose'] !== $e['purpose'] || self::record_digest( $e['purpose'], $e ) !== $r['request_digest'] ) {
					return 'domain_conflict';
				}
				return $r['result_generation'] === $s['lifecycle']['permission_generation'] ? 'observed_current' : 'observed_superseded';
			}
		}
		return isset( self::identities( $s )[ $id ] ) ? 'domain_conflict' : 'not_found';
	}

	/**
	 * Independent private receipt lookup, without returning commands or hashes.
	 *
	 * @param array  $s Validated snapshot.
	 * @param string $id Observed UUID.
	 * @return string
	 */
	public static function lookup( array $s, $id ) {
		if ( isset( self::legacy( $s )['ids'][ $id ] ) ) {
			return 'domain_conflict';
		}
		if ( 7 === $s['schema_version'] ) {
			foreach ( self::all_records( $s ) as $r ) {
				if ( $id === $r['command']['command_id'] ) {
					return $r['result_generation'] === $s['lifecycle']['permission_generation'] ? 'observed_current' : 'observed_superseded';
				}
			}
		}
		return 'not_found';
	}

	/**
	 * Pure closed-only proposal from original intent and fresh synthetic context.
	 *
	 * @param array       $s Existing state.
	 * @param array       $e Original envelope.
	 * @param array       $context Fresh fixed-target facts.
	 * @param int         $now Fixture UTC.
	 * @param string|null $reserved Completion ID allocated once outside retries.
	 * @return array|false Candidate only, never a durable acknowledgement.
	 */
	public static function propose( array $s, array $e, array $context, $now, $reserved = null ) {
		$site = $e['site_uuid'] ?? null;
		if ( ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::valid( $e ) || ! self::supported( $s, $site ) || 'receipt_read' === $e['purpose'] || ! self::time( $s, $e, $now ) || $e['expected_generation'] !== $s['lifecycle']['permission_generation'] || $e['enrollment_binding'] !== $s['enrollment_binding'] || 'not_found' !== self::observe( $s, $e ) ) {
			return false;
		}
		if ( 'upgrade_closed' === $e['purpose'] ) {
			return self::upgrade_candidate( $s, $e, $now );
		}
		if ( 7 !== $s['schema_version'] || ! isset( $context['context_digest'], $context['before_hash'], $context['after_hash'], $context['enabled'] ) || $e['context_digest'] !== $context['context_digest'] ) {
			return false;
		}
		$w = $s['writer'];
		if ( 'begin_disable' === $e['purpose'] ) {
			if ( null !== $w['pending'] || $e['before_hash'] !== $context['before_hash'] || $e['after_hash'] !== $context['after_hash'] || ! is_bool( $context['enabled'] ) ) {
				return false;
			}
			if ( false === $context['enabled'] ) {
				return $s;
			}
			if ( ! self::room( $s, 3 ) || ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::uuid( $reserved ) || $reserved === $e['command_id'] || isset( self::identities( $s )[ $reserved ] ) || $e['before_hash'] === $e['after_hash'] ) {
				return false;
			}
			foreach ( array( 'closure', 'completion' ) as $slot ) {
				if ( null !== $w[ $slot ] ) {
					$w['fixture_receipts'][ $w[ $slot ]['command']['command_id'] ] = $w[ $slot ];
				}
			}
			$w['closure']    = self::record( 'begin_disable', $e, $now, $reserved );
			$w['completion'] = null;
			$w['pending']    = self::pending( $w['closure'] );
		} else {
			if ( null === $w['pending'] || ! self::room( $s, 2 ) || $e['observed_hash'] !== $context['before_hash'] ) {
				return false;
			}
			$w['completion'] = self::record( 'abandon_closed', $e, $now );
			if ( ! self::terminal_matches( $w['completion'], $w['closure'] ) ) {
				return false;
			}
			$w['pending'] = null;
		}
		$s['writer'] = $w;
		return self::advanced( $s, $now, $site );
	}

	/**
	 * Finite closed upgrade preserves the complete legacy group verbatim.
	 *
	 * @param array $s Strict source.
	 * @param array $e Original command.
	 * @param int   $now UTC.
	 * @return array|false
	 */
	private static function upgrade_candidate( array $s, array $e, $now ) {
		if ( 6 !== $s['schema_version'] || ! self::room( $s, 4 ) || Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::hash( $s ) !== $e['origin_digest'] ) {
			return false;
		}
		$origin              = array(
			'generation'            => $s['generation'],
			'schedule_revision'     => $s['schedule_revision'],
			'permission_generation' => $s['lifecycle']['permission_generation'],
			'changed_at'            => $s['lifecycle']['changed_at'],
			'source_digest'         => $e['origin_digest'],
		);
		$s['schema_version'] = 7;
		$s['legacy_writer']  = $s['barrier'];
		unset( $s['barrier'] );
		$s['writer'] = array(
			'source'                 => self::SOURCE,
			'origin'                 => $origin,
			'upgrade'                => self::record( 'upgrade_closed', $e, $now ),
			'authenticated_grant'    => null,
			'authenticated_receipts' => array(),
			'internal_receipts'      => array(),
			'fixture_receipts'       => array(),
			'pending'                => null,
			'closure'                => null,
			'completion'             => null,
		);
		return self::advanced( $s, $now, $e['site_uuid'] );
	}

	/**
	 * Pure fixture terminal proposal, not a public hash/finish or storage sink.
	 * The fixture coordinator uses this only inside its one-shot continuation;
	 * direct pure calls confer no admission and have no IO or durable effect.
	 *
	 * @param array  $s Expected closed successor under simulated locks.
	 * @param array  $e Original begin envelope.
	 * @param string $raw Exact locked synthetic target bytes, not a posted hash.
	 * @param int    $now Original-window clock.
	 * @return array|false State and derived target proposal.
	 */
	public static function simulate_locked( array $s, array $e, $raw, $now ) {
		if ( ! self::valid( $s, $e['site_uuid'] ?? null ) || ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::valid( $e ) || 'begin_disable' !== $e['purpose'] || ! self::time( $s, $e, $now ) || ! self::room( $s, 2 ) ) {
			return false;
		}
		$w = $s['writer'];
		$c = Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::context( $s, $raw, $e['site_uuid'] );
		if ( null === $w['pending'] || $w['closure']['command'] !== $e || $s['lifecycle']['permission_generation'] !== $e['expected_generation'] + 1 || false === $c || true !== $c['enabled'] || $c['before_hash'] !== $e['before_hash'] || $c['after_hash'] !== $e['after_hash'] ) {
			return false;
		}
		$t                         = array(
			'command_id'          => $w['pending']['completion_id'],
			'operation_id'        => $e['command_id'],
			'site_uuid'           => $e['site_uuid'],
			'enrollment_binding'  => $e['enrollment_binding'],
			'expected_generation' => $s['lifecycle']['permission_generation'],
			'closure_generation'  => $s['lifecycle']['permission_generation'],
			'observed_hash'       => $c['after_hash'],
			'issued_at'           => $e['issued_at'],
			'expires_at'          => $e['expires_at'],
		);
		$s['writer']['completion'] = self::record( 'complete_fixture', $t, $now );
		$s['writer']['pending']    = null;
		$next                      = self::advanced( $s, $now, $e['site_uuid'] );
		return false === $next ? false : array(
			'state'  => $next,
			'target' => $c['after'],
		);
	}

	/**
	 * Original bounded clock, with no expiry renewal or regression.
	 *
	 * @param array $s State.
	 * @param array $e Command.
	 * @param int   $now Clock.
	 * @return bool
	 */
	private static function time( array $s, array $e, $now ) {
		return is_int( $now ) && $now >= $s['lifecycle']['changed_at'] && $now >= $e['issued_at'] && $now < $e['expires_at'];
	}

	/**
	 * Increment all fencing counters once, validate complete proposed state.
	 *
	 * @param array  $s Candidate.
	 * @param int    $now Clock.
	 * @param string $site Site.
	 * @return array|false
	 */
	private static function advanced( array $s, $now, $site ) {
		++$s['generation'];
		++$s['schedule_revision'];
		++$s['lifecycle']['permission_generation'];
		$s['lifecycle']['changed_at'] = $now;
		return self::valid( $s, $site ) ? $s : false;
	}
}
