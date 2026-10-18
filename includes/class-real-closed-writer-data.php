<?php
/**
 * Pure real-shaped closed provenance data; never authenticates a request.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Consistency helpers only: synthetic records confer no write admission. */
final class Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data {
	const SOURCE   = 'closed_writer_admission_v1';
	const AUTH     = 'authenticated_closed_writer_v1';
	const INTERNAL = 'internal_closed_writer_v1';

	/**
	 * Exact field set, independent of map order.
	 *
	 * @param array $value Map.
	 * @param array $keys Fields.
	 * @return bool
	 */
	public static function keys( array $value, array $keys ) {
		return count( $value ) === count( $keys ) && ! array_diff( $keys, array_keys( $value ) );
	}

	/**
	 * Reuse only the pure typed layout, never fixture parsing/actions/authority.
	 *
	 * @param array $e Original typed data.
	 * @return bool
	 */
	public static function command( array $e ) {
		return Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::valid( $e );
	}

	/**
	 * Separate real-shaped data domain; a hash is not an authentication proof.
	 *
	 * @param array $value Already checked plain data.
	 * @return string|false
	 */
	public static function hash( array $value ) {
		if ( ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::plain( $value ) ) {
			return false;
		}
		return Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::hash(
			array(
				'real_closed_data_version' => 1,
				'data'                     => $value,
			)
		);
	}

	/**
	 * Construct candidate data only; no authenticated-event assertion.
	 *
	 * @param string      $purpose Purpose.
	 * @param array       $e Original command.
	 * @param int         $now Clock.
	 * @param string|null $reserved Once-reserved identity.
	 * @return array|false
	 */
	public static function record( $purpose, array $e, $now, $reserved = null ) {
		if ( ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::plain( $e ) || ! is_int( $e['expected_generation'] ?? null ) || $e['expected_generation'] < 0 || $e['expected_generation'] >= PHP_INT_MAX || ! is_int( $now ) || ! in_array( $purpose, array( 'upgrade_closed', 'begin_disable', 'abandon_closed', 'complete_disable' ), true ) ) {
			return false;
		}
		$domain = 'complete_disable' === $purpose ? self::INTERNAL : self::AUTH;
		return array(
			'domain'            => $domain,
			'purpose'           => $purpose,
			'command'           => $e,
			'request_digest'    => self::hash(
				array(
					'domain'  => $domain,
					'purpose' => $purpose,
					'command' => $e,
				)
			),
			'result_generation' => $e['expected_generation'] + 1,
			'committed_at'      => $now,
			'completion_id'     => $reserved,
		);
	}

	/**
	 * Strict bounded receipt grammar with disjoint authenticated/internal roles.
	 *
	 * @param mixed  $r Candidate data.
	 * @param string $site Fixed site.
	 * @return bool
	 */
	public static function receipt( $r, $site ) {
		if ( ! is_array( $r ) || ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::plain( $r ) || ! self::keys( $r, array( 'domain', 'purpose', 'command', 'request_digest', 'result_generation', 'committed_at', 'completion_id' ) ) || ! is_array( $r['command'] ) ) {
			return false;
		}
		$e = $r['command'];
		if ( 'complete_disable' === $r['purpose'] ) {
			if ( self::INTERNAL !== $r['domain'] || ! self::keys( $e, array( 'command_id', 'operation_id', 'site_uuid', 'enrollment_binding', 'expected_generation', 'closure_generation', 'observed_hash', 'issued_at', 'expires_at' ) ) ) {
				return false;
			}
			$typed                   = $e;
			$typed['contract']       = 'closed_writer_request_v1';
			$typed['purpose']        = 'abandon_closed';
			$typed['confirm']        = 'abandon';
			$typed['context_digest'] = str_repeat( '0', 64 );
		} else {
			if ( self::AUTH !== $r['domain'] || ! in_array( $r['purpose'], array( 'upgrade_closed', 'begin_disable', 'abandon_closed' ), true ) || ( $e['purpose'] ?? null ) !== $r['purpose'] ) {
				return false;
			}
			$typed = $e;
		}
		if ( ! self::command( $typed ) || $e['site_uuid'] !== $site || ! is_int( $r['committed_at'] ) || $r['committed_at'] < $e['issued_at'] || $r['committed_at'] >= $e['expires_at'] ) {
			return false;
		}
		$expected = self::record( $r['purpose'], $e, $r['committed_at'], $r['completion_id'] );
		$begin    = 'begin_disable' === $r['purpose'];
		$raw      = json_encode( $r );
		return $expected['result_generation'] === $r['result_generation'] && $expected['request_digest'] === $r['request_digest'] && ( $begin ? Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::uuid( $r['completion_id'] ) && $e['before_hash'] !== $e['after_hash'] : null === $r['completion_id'] ) && is_string( $raw ) && strlen( $raw ) <= 1024;
	}

	/**
	 * Frozen schema-6 receipt identities/count; never migrate its provenance.
	 *
	 * @param array $s Strict schema 6 or 8.
	 * @return array
	 */
	public static function legacy( array $s ) {
		$b    = 6 === $s['schema_version'] ? $s['barrier'] : $s['legacy_writer'];
		$l    = $b['checkpoint']['provenance']['legacy']['ledger'];
		$maps = array( $b['receipts'], null === $l ? array() : $l['receipts'] );
		$ids  = array();
		foreach ( array( $b['closure'], $b['completion'], null === $l ? null : $l['last_receipt'] ) as $r ) {
			if ( null !== $r ) {
				$ids[ $r['command']['command_id'] ] = true;
			}
		}
		foreach ( $maps as $map ) {
			foreach ( $map as $id => $r ) {
				$ids[ $id ] = true;
			}
		}
		return array(
			'ids'   => $ids,
			'count' => count( $maps[0] ) + count( $maps[1] ),
		);
	}

	/**
	 * All new records, including mandatory current slots (not optional history).
	 *
	 * @param array $s Strict successor.
	 * @return array
	 */
	public static function records( array $s ) {
		$all = array_merge( array_values( $s['writer']['authenticated_receipts'] ), array_values( $s['writer']['internal_receipts'] ) );
		foreach ( array( 'upgrade', 'closure', 'completion' ) as $slot ) {
			if ( null !== $s['writer'][ $slot ] ) {
				$all[] = $s['writer'][ $slot ];
			}
		}
		return $all;
	}

	/**
	 * Reserved identities remain used even after abandonment.
	 *
	 * @param array $s Strict snapshot.
	 * @return array
	 */
	public static function identities( array $s ) {
		$ids = self::legacy( $s )['ids'];
		if ( 8 === $s['schema_version'] ) {
			foreach ( self::records( $s ) as $r ) {
				$ids[ $r['command']['command_id'] ] = true;
				if ( null !== $r['completion_id'] ) {
					$ids[ $r['completion_id'] ] = true;
				}
			}
		}
		return $ids;
	}

	/**
	 * Fixed descriptor derived only from an original begin record.
	 *
	 * @param array $r Begin record.
	 * @return array
	 */
	public static function pending( array $r ) {
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
