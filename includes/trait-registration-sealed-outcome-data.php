<?php
/**
 * Conservative sealed-capture consistency DATA; no native evidence or IO.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Reuse bounded graphs and causal validators; never repair unknown observations. */
trait Alynt_Drime_Backups_Uploader_Registration_Sealed_Outcome_Data {
	/**
	 * Validate separate owner claims before interpreting any release claim.
	 *
	 * @param mixed $owner Explicit synthetic lifecycle/empty-slot claims.
	 * @return bool No actual owner, handle or resource is inspected.
	 */
	private static function sealed_owner( $owner ) {
		return self::bounded( $owner ) && self::keys( $owner, array( 'started', 'cleanup_done', 'sealed', 'tick', 'reader_remaining', 'writer_remaining', 'physical_remaining' ) ) && true === $owner['started'] && true === $owner['cleanup_done'] && true === $owner['sealed'] && is_int( $owner['tick'] ) && $owner['tick'] >= 0 && $owner['tick'] <= 32 && false === $owner['reader_remaining'] && false === $owner['writer_remaining'] && false === $owner['physical_remaining'];
	}

	/**
	 * Observe exact source-shaped raw/ack consistency without mutating either.
	 * False raw returns are unknown, never a definite server rejection.
	 *
	 * @param array $capture Structurally validated primitive capture claims.
	 * @return bool No synthetic ack no can enter this closed adapter.
	 */
	private static function sealed_returns( $capture ) {
		$facts = $capture['facts'];
		foreach ( $capture['raw_returns'] as $command => $raw ) {
			$sent = $facts['events'][ 'close' === $command ? 'detached' : $command . '_sent' ];
			$at   = $facts['events'][ 'close' === $command ? 'close_at' : $command . '_ack_at' ];
			$ack  = $facts['acks'][ $command ];
			if ( 0 === $sent ) {
				if ( 'not_called' !== $raw || 'not_sent' !== $ack || 0 !== $at ) {
					return false;
				}
			} elseif ( true === $raw ) {
				if ( 'yes' !== $ack || $at <= $sent ) {
					return false;
				}
			} elseif ( false === $raw || 'unknown' === $raw ) {
				if ( 'unknown' !== $ack || 0 !== $at ) {
					return false;
				}
			} else {
				return false;
			}
		}
		return true;
	}

	/**
	 * Bound and consume an exact sealed-source-shaped map as untrusted DATA.
	 * Owner flags are separate claims, never an imported native certificate.
	 *
	 * @param mixed $capture Original valid/facts/raw_returns primitive map.
	 * @param mixed $owner Separate claimed cleanup seal, tick and empty slots.
	 * @return array|false Unmodified historical facts, not native provenance.
	 */
	private static function sealed_facts( $capture, $owner ) {
		if ( ! self::bounded( $capture ) || ! self::keys( $capture, array( 'valid', 'facts', 'raw_returns' ) ) || true !== $capture['valid'] || ! self::sealed_owner( $owner ) || ! self::keys( $capture['raw_returns'], array( 'begin', 'dml', 'commit', 'rollback', 'close' ) ) || ! self::facts( $capture['facts'] ) ) {
			return false;
		}
		$facts = $capture['facts'];
		if ( 0 !== $facts['events']['accepted_at'] || array_fill_keys( array( 'auth', 'intent', 'physical', 'unreentered' ), null ) !== $facts['guards'] || false !== $facts['handle_remaining'] || ! self::sealed_returns( $capture ) ) {
			return false;
		}
		$positions = array_values( array_filter( $facts['events'] ) );
		sort( $positions, SORT_NUMERIC );
		$expected = 0 === $owner['tick'] ? array() : range( 1, $owner['tick'] );
		return $positions === $expected ? $facts : false;
	}
}
