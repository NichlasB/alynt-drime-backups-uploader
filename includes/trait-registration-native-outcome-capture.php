<?php
/**
 * Private passive native observation slots; never admission or read authority.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-registration-admission-outcome.php';

/** Fixed source calls only; no provider, callback, native handle or origin import. */
trait Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Capture {
	/**
	 * Bounded primitive observations only, erased at the outer root exit.
	 *
	 * @var array|null
	 */
	private $native_outcome;
	/**
	 * Capture cannot be reopened after refusal or erasure.
	 *
	 * @var bool
	 */
	private $native_outcome_started = false;
	/**
	 * First cleanup consumes the owned writer, before any close operation.
	 *
	 * @var bool
	 */
	private $native_cleanup_done = false;
	/**
	 * No event or acknowledgement can promote a completed cleanup snapshot.
	 *
	 * @var bool
	 */
	private $native_outcome_closed = false;
	/**
	 * At most 32 ordered primitive observations, no event history allocation.
	 *
	 * @var int
	 */
	private $native_outcome_tick = 0;

	/**
	 * Start once before the fixed owned writer factory; no authentication claims.
	 *
	 * @return bool Capture availability only, never permission to execute.
	 */
	private function start_native_outcome() {
		if ( $this->native_outcome_started ) {
			$this->invalidate_native_outcome();
			return false;
		}
		$this->native_outcome_started = true;
		$commands                     = array( 'begin', 'dml', 'commit', 'rollback', 'close' );
		$this->native_outcome         = array(
			'valid'       => true,
			'facts'       => array(
				'events'           => array_fill_keys( Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::EVENTS, 0 ),
				'acks'             => array_fill_keys( $commands, 'not_sent' ),
				'affected_rows'    => 'not_observed',
				'handle_remaining' => null,
				'guards'           => array_fill_keys( array( 'auth', 'intent', 'physical', 'unreentered' ), null ),
			),
			'raw_returns' => array_fill_keys( $commands, 'not_called' ),
		);
		return true;
	}

	/**
	 * One fixed observation position; unknown/duplicate calls poison, never grow.
	 *
	 * @param string $event Compiled private source label.
	 * @return void
	 */
	private function native_outcome_event( $event ) {
		if ( null === $this->native_outcome ) {
			return;
		}
		if ( $this->native_outcome_closed || ! $this->native_outcome['valid'] || ! is_string( $event ) || ! array_key_exists( $event, $this->native_outcome['facts']['events'] ) || $this->native_outcome['facts']['events'][ $event ] || $this->native_outcome_tick >= 32 ) {
			$this->invalidate_native_outcome();
			return;
		}
		$this->native_outcome['facts']['events'][ $event ] = ++$this->native_outcome_tick;
	}

	/**
	 * Mark may-have-been-sent before IO, even if the following call throws.
	 *
	 * @param string $command Compiled operation name.
	 * @return void
	 */
	private function native_outcome_send( $command ) {
		if ( null === $this->native_outcome ) {
			return;
		}
		if ( $this->native_outcome_closed || ! $this->native_outcome['valid'] || ! is_string( $command ) || ! array_key_exists( $command, $this->native_outcome['raw_returns'] ) || 'not_called' !== $this->native_outcome['raw_returns'][ $command ] ) {
			$this->invalidate_native_outcome();
			return;
		}
		$this->native_outcome_event( 'close' === $command ? 'detached' : $command . '_sent' );
		$this->native_outcome['raw_returns'][ $command ]   = 'unknown';
		$this->native_outcome['facts']['acks'][ $command ] = 'unknown';
		if ( 'dml' === $command ) {
			$this->native_outcome['facts']['affected_rows'] = 'unknown';
		}
	}

	/**
	 * Preserve a raw return separately: false is NOT a definite server rejection.
	 *
	 * @param string $command Fixed operation name.
	 * @param mixed  $ack Raw API return, never converted to a favorable boolean.
	 * @return void
	 */
	private function native_outcome_ack( $command, $ack ) {
		if ( null === $this->native_outcome ) {
			return;
		}
		if ( $this->native_outcome_closed || ! $this->native_outcome['valid'] || ! is_string( $command ) || ! array_key_exists( $command, $this->native_outcome['raw_returns'] ) || 'unknown' !== $this->native_outcome['raw_returns'][ $command ] || ! is_bool( $ack ) ) {
			$this->invalidate_native_outcome();
			return;
		}
		$this->native_outcome['raw_returns'][ $command ] = $ack;
		if ( true === $ack ) {
			$this->native_outcome_event( 'close' === $command ? 'close_at' : $command . '_ack_at' );
			$this->native_outcome['facts']['acks'][ $command ] = 'yes';
		}
	}

	/** Permanently refuse this capture without rewriting its historical facts. */
	private function invalidate_native_outcome() {
		if ( null !== $this->native_outcome ) {
			$this->native_outcome['valid'] = false;
		}
	}

	/** Erase all primitive observations; the once-started latch never resets. */
	private function clear_native_outcome() {
		$this->native_outcome         = null;
		$this->native_outcome_tick    = 0;
		$this->native_outcome_started = true;
		$this->native_outcome_closed  = true;
	}
}
