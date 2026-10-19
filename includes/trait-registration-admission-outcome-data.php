<?php
/**
 * Synthetic admission facts, not native event certificates.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Fixed fact dependencies, not another executable phase machine. */
trait Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome_Data {
	/**
	 * Exact observation positions. Zero explicitly means not observed.
	 *
	 * @var array
	 */
	const EVENTS = array( 'writer_created', 'attempt_reserved', 'begin_sent', 'begin_ack_at', 'transaction_owned', 'bind_verified', 'dml_sent', 'dml_ack_at', 'readback', 'commit_sent', 'commit_ack_at', 'commit_idle', 'rollback_sent', 'rollback_ack_at', 'rollback_idle', 'detached', 'close_at', 'accepted_at' );

	/**
	 * Validate independent raw acknowledgements and their observation positions.
	 *
	 * @param array $facts Bounded exact DATA.
	 * @return bool
	 */
	private static function acknowledgements( $facts ) {
		$events = $facts['events'];
		foreach ( array( 'begin', 'dml', 'commit', 'rollback', 'close' ) as $command ) {
			$sent = 'close' === $command ? $events['detached'] : $events[ $command . '_sent' ];
			$at   = 'close' === $command ? $events['close_at'] : $events[ $command . '_ack_at' ];
			$ack  = $facts['acks'][ $command ];
			if ( 0 === $sent ) {
				if ( 'not_sent' !== $ack || 0 !== $at ) {
					return false;
				}
			} elseif ( 'unknown' === $ack ) {
				if ( 0 !== $at ) {
					return false;
				}
			} elseif ( ! in_array( $ack, array( 'yes', 'no' ), true ) || $at <= $sent ) {
				return false;
			}
		}
		if ( 0 === $events['dml_sent'] ) {
			return 'not_observed' === $facts['affected_rows'];
		}
		return 'yes' === $facts['acks']['dml'] ? in_array( $facts['affected_rows'], array( 'zero', 'one', 'other' ), true ) : 'unknown' === $facts['affected_rows'];
	}

	/**
	 * Strict primitive graph, unique positions and direct causal prerequisites.
	 *
	 * @param mixed $facts Untrusted synthetic claims.
	 * @return bool
	 */
	private static function facts( $facts ) {
		if ( ! self::bounded( $facts ) || ! self::keys( $facts, array( 'events', 'acks', 'affected_rows', 'handle_remaining', 'guards' ) ) || ! self::keys( $facts['events'], self::EVENTS ) || ! self::keys( $facts['acks'], array( 'begin', 'dml', 'commit', 'rollback', 'close' ) ) || ! self::keys( $facts['guards'], array( 'auth', 'intent', 'physical', 'unreentered' ) ) || ( null !== $facts['handle_remaining'] && ! is_bool( $facts['handle_remaining'] ) ) ) {
			return false;
		}
		$events = $facts['events'];
		$seen   = array();
		foreach ( $events as $position ) {
			if ( ! is_int( $position ) || $position < 0 || $position > 32 || ( 0 !== $position && isset( $seen[ $position ] ) ) ) {
				return false;
			}
			if ( 0 !== $position ) {
				$seen[ $position ] = true;
			}
		}
		foreach ( $facts['guards'] as $guard ) {
			if ( null !== $guard && ! is_bool( $guard ) ) {
				return false;
			}
		}
		$dependencies = array(
			'attempt_reserved'  => array( 'writer_created' ),
			'begin_sent'        => array( 'writer_created' ),
			'transaction_owned' => array( 'begin_ack_at' ),
			'bind_verified'     => array( 'attempt_reserved' ),
			'dml_sent'          => array( 'bind_verified', 'transaction_owned' ),
			'readback'          => array( 'dml_ack_at' ),
			'commit_sent'       => array( 'readback' ),
			'commit_idle'       => array( 'commit_ack_at' ),
			'rollback_sent'     => array( 'transaction_owned' ),
			'rollback_idle'     => array( 'rollback_ack_at' ),
			'detached'          => array( 'writer_created' ),
			'accepted_at'       => array( 'close_at' ),
		);
		foreach ( $dependencies as $event => $prior ) {
			foreach ( $prior as $required ) {
				if ( 0 !== $events[ $event ] && ( 0 === $events[ $required ] || $events[ $required ] >= $events[ $event ] ) ) {
					return false;
				}
			}
		}
		if ( ! self::acknowledgements( $facts ) || ( $events['commit_sent'] && $events['rollback_sent'] ) || ( $events['transaction_owned'] && 'yes' !== $facts['acks']['begin'] ) || ( $events['readback'] && ( 'yes' !== $facts['acks']['dml'] || 'one' !== $facts['affected_rows'] ) ) || ( $events['commit_idle'] && 'yes' !== $facts['acks']['commit'] ) || ( $events['rollback_idle'] && 'yes' !== $facts['acks']['rollback'] ) ) {
			return false;
		}
		foreach ( array_slice( self::EVENTS, 0, 15 ) as $event ) {
			if ( $events['detached'] && $events[ $event ] >= $events['detached'] ) {
				return false;
			}
			if ( $events['rollback_sent'] && ! in_array( $event, array( 'rollback_sent', 'rollback_ack_at', 'rollback_idle' ), true ) && $events[ $event ] >= $events['rollback_sent'] ) {
				return false;
			}
		}
		return ! $events['accepted_at'] || ( self::released( $facts ) && ! in_array( false, $facts['guards'], true ) && ! in_array( null, $facts['guards'], true ) );
	}

	/**
	 * Preserve history independently of late close/current guard failures.
	 *
	 * @param array $facts Validated synthetic facts.
	 * @return string
	 */
	private static function history( $facts ) {
		$events = $facts['events'];
		if ( $events['commit_idle'] ) {
			return 'committed';
		}
		if ( ( $events['begin_sent'] && 'no' !== $facts['acks']['begin'] && ! $events['transaction_owned'] ) || ( $events['rollback_sent'] && ! $events['rollback_idle'] ) ) {
			return 'unsupported';
		}
		if ( $events['rollback_idle'] ) {
			return $events['dml_sent'] ? 'rolled_back' : 'not_written';
		}
		if ( $events['dml_sent'] || $events['commit_sent'] ) {
			return 'uncertain';
		}
		return $events['transaction_owned'] ? 'unsupported' : 'not_written';
	}

	/**
	 * Client-only release; neither server quiescence nor authorization.
	 *
	 * @param array $facts Validated synthetic facts.
	 * @return bool
	 */
	private static function released( $facts ) {
		return false === $facts['handle_remaining'] && ( 0 === $facts['events']['writer_created'] || ( $facts['events']['detached'] > 0 && 'yes' === $facts['acks']['close'] ) );
	}
}
