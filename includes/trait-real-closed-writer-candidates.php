<?php
/**
 * Closed-only pure candidate transitions; never a persistent writer.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Only strict original schema 6 can seed new real-shaped data. */
trait Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Candidates {
	/**
	 * Preserve the complete legacy group without relabeling any receipt.
	 *
	 * @param array $s Strict schema 6.
	 * @param array $e Original command.
	 * @param int   $now Clock.
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
		$s['schema_version'] = 8;
		$s['legacy_writer']  = $s['barrier'];
		unset( $s['barrier'] );
		$s['writer'] = array(
			'source'                 => Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::SOURCE,
			'origin'                 => $origin,
			'upgrade'                => Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::record( 'upgrade_closed', $e, $now ),
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
	 * Derive the immediately preceding snapshot from the complete retained chain.
	 * Used only to recheck original begin context, never to roll storage back.
	 *
	 * @param array $s Valid pending data.
	 * @return array Reconstructed pre-begin data.
	 */
	private static function before_begin( array $s ) {
		$w          = $s['writer'];
		$closure    = null;
		$completion = null;
		$last       = $w['upgrade'];
		foreach ( array_merge( $w['authenticated_receipts'], $w['internal_receipts'] ) as $r ) {
			if ( $r['result_generation'] > $last['result_generation'] ) {
				$last = $r;
			}
			if ( 'begin_disable' === $r['purpose'] && ( null === $closure || $r['result_generation'] > $closure['result_generation'] ) ) {
				$closure = $r;
			}
			if ( in_array( $r['purpose'], array( 'abandon_closed', 'complete_disable' ), true ) && ( null === $completion || $r['result_generation'] > $completion['result_generation'] ) ) {
				$completion = $r;
			}
		}
		foreach ( array( $closure, $completion ) as $r ) {
			if ( null !== $r ) {
				$map = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::AUTH === $r['domain'] ? 'authenticated_receipts' : 'internal_receipts';
				unset( $w[ $map ][ $r['command']['command_id'] ] );
			}
		}
		$w['closure']                 = $closure;
		$w['completion']              = $completion;
		$w['pending']                 = null;
		$s['writer']                  = $w;
		$s['lifecycle']['changed_at'] = $last['committed_at'];
		--$s['lifecycle']['permission_generation'];
		--$s['generation'];
		--$s['schedule_revision'];
		return $s;
	}

	/**
	 * Increment all three counters once and validate the whole candidate.
	 *
	 * @param array  $s Data.
	 * @param int    $now Clock.
	 * @param string $site Fixed scope.
	 * @return array|false
	 */
	private static function advanced( array $s, $now, $site ) {
		++$s['generation'];
		++$s['schedule_revision'];
		++$s['lifecycle']['permission_generation'];
		$s['lifecycle']['changed_at'] = $now;
		return self::valid( $s, $site ) ? $s : false;
	}

	/**
	 * Bounded original window, exclusive expiry and no clock regression.
	 *
	 * @param array $s Data.
	 * @param array $e Command.
	 * @param int   $now Clock.
	 * @return bool
	 */
	private static function time( array $s, array $e, $now ) {
		return is_int( $now ) && $now >= $s['lifecycle']['changed_at'] && $now >= $e['issued_at'] && $now < $e['expires_at'];
	}

	/**
	 * Explicitly non-admitting output; private bytes must never become a response.
	 *
	 * @param array|false $s Candidate.
	 * @param string|null $target Private candidate bytes.
	 * @return array|false
	 */
	private static function candidate( $s, $target = null ) {
		return false === $s ? false : array(
			'candidate_only'      => true,
			'admission_proven'    => false,
			'dispatch_supported'  => false,
			'execution_supported' => false,
			'state'               => $s,
			'target'              => $target,
		);
	}
}
