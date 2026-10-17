<?php
/**
 * Immutable bounded fixture-only records and legacy identity segregation.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No constructor or request flag can select reserved real provenance. */
trait Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Records {
	/**
	 * Exact key set.
	 *
	 * @param array $value Map.
	 * @param array $keys Keys.
	 * @return bool
	 */
	private static function keys( array $value, array $keys ) {
		return Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::keys( $value, $keys );
	}

	/**
	 * Bounded immutable simulated receipt, never real authenticated evidence.
	 *
	 * @param string      $purpose Purpose.
	 * @param array       $e Original typed command.
	 * @param int         $now Commit clock.
	 * @param string|null $completion Reserved completion UUID.
	 * @return array
	 */
	private static function record( $purpose, array $e, $now, $completion = null ) {
		return array(
			'domain'            => self::SOURCE,
			'purpose'           => $purpose,
			'command'           => $e,
			'request_digest'    => self::record_digest( $purpose, $e ),
			'result_generation' => $e['expected_generation'] + 1,
			'committed_at'      => $now,
			'completion_id'     => $completion,
		);
	}

	/**
	 * Bind the full normalized command, including internal fixture purpose.
	 *
	 * @param string $purpose Purpose.
	 * @param array  $e Command.
	 * @return string
	 */
	private static function record_digest( $purpose, array $e ) {
		return Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::hash(
			array(
				'domain'  => self::SOURCE,
				'purpose' => $purpose,
				'command' => $e,
			)
		);
	}

	/**
	 * Reject real/internal domains and malformed or unbounded fixture records.
	 *
	 * @param mixed  $r Record.
	 * @param string $site Fixed scope.
	 * @return bool
	 */
	private static function valid_record( $r, $site ) {
		if ( ! is_array( $r ) || ! self::keys( $r, array( 'domain', 'purpose', 'command', 'request_digest', 'result_generation', 'committed_at', 'completion_id' ) ) || self::SOURCE !== $r['domain'] || ! is_array( $r['command'] ) ) {
			return false;
		}
		$e        = $r['command'];
		$internal = 'complete_fixture' === $r['purpose'];
		if ( $internal ? ! self::valid_terminal( $e ) : ( ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::valid( $e ) || $e['purpose'] !== $r['purpose'] || 'receipt_read' === $r['purpose'] ) ) {
			return false;
		}
		$begin = 'begin_disable' === $r['purpose'];
		$raw   = json_encode( $r );
		return $e['site_uuid'] === $site && is_int( $r['committed_at'] ) && $r['committed_at'] >= $e['issued_at'] && $r['committed_at'] < $e['expires_at'] && $r['result_generation'] === $e['expected_generation'] + 1
			&& self::record_digest( $r['purpose'], $e ) === $r['request_digest'] && ( $begin ? Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::uuid( $r['completion_id'] ) : null === $r['completion_id'] ) && is_string( $raw ) && strlen( $raw ) <= 1024;
	}

	/**
	 * Completion is not a form command; only simulated locked facts construct it.
	 *
	 * @param array $e Internal candidate.
	 * @return bool
	 */
	private static function valid_terminal( array $e ) {
		if ( ! self::keys( $e, array( 'command_id', 'operation_id', 'site_uuid', 'enrollment_binding', 'expected_generation', 'closure_generation', 'observed_hash', 'issued_at', 'expires_at' ) ) ) {
			return false;
		}
		$copy                   = $e;
		$copy['contract']       = Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::CONTRACT;
		$copy['purpose']        = 'abandon_closed';
		$copy['confirm']        = 'abandon';
		$copy['context_digest'] = str_repeat( '0', 64 );
		return Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::valid( $copy );
	}

	/**
	 * Original protected IDs cannot be relabeled or reused.
	 *
	 * @param array $s Strict schema 6 or 7.
	 * @return array Identity set and optional count.
	 */
	private static function legacy( array $s ) {
		$b    = 6 === $s['schema_version'] ? $s['barrier'] : $s['legacy_writer'];
		$l    = $b['checkpoint']['provenance']['legacy']['ledger'];
		$ids  = array();
		$maps = array( $b['receipts'], null === $l ? array() : $l['receipts'] );
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
	 * All current/new historical identities, including unused reserved UUIDs.
	 *
	 * @param array $s Strict successor.
	 * @return array
	 */
	private static function identities( array $s ) {
		$ids = self::legacy( $s )['ids'];
		foreach ( self::all_records( $s ) as $r ) {
			$ids[ $r['command']['command_id'] ] = true;
			if ( null !== $r['completion_id'] ) {
				$ids[ $r['completion_id'] ] = true;
			}
		}
		return $ids;
	}

	/**
	 * Include mandatory slots without moving them into optional history.
	 *
	 * @param array $s Successor.
	 * @return array
	 */
	private static function all_records( array $s ) {
		$all = array_values( $s['writer']['fixture_receipts'] );
		foreach ( array( 'upgrade', 'closure', 'completion' ) as $slot ) {
			if ( null !== $s['writer'][ $slot ] ) {
				$all[] = $s['writer'][ $slot ];
			}
		}
		return $all;
	}
}
