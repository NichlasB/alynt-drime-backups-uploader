<?php
/**
 * Registration-specific cleanup observations, not a shared-session policy change.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Reuse fixed writer creation/session checks; no injected handle or SQL port. */
trait Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Session {
	/**
	 * Exact factory-created writer identity, never copied into primitive DATA.
	 *
	 * @var mysqli|null
	 */
	private $native_outcome_writer;
	/**
	 * Observe the owned factory's resulting handle, including failed configuration.
	 *
	 * @param mysqli $auth Borrowed auth handle, never retained in observations.
	 * @param array  $before Original physical facts.
	 * @return bool Unchanged fixed factory decision.
	 */
	private function open_writer( $auth, array $before ) {
		if ( ! $this->start_native_outcome() ) {
			return false;
		}
		try {
			return $this->open_writer_without_outcome( $auth, $before );
		} finally {
			if ( $this->reader instanceof mysqli && $this->reader !== $auth ) {
				$this->native_outcome_writer = $this->reader;
				$this->native_outcome_event( 'writer_created' );
			} elseif ( null !== $this->reader ) {
				$this->invalidate_native_outcome();
			}
		}
	}

	/**
	 * Preserve raw rollback/close facts independently of later poisoned phase.
	 * Read-only pre-writer cleanup still uses the unchanged shared implementation.
	 *
	 * @return void
	 */
	private function finish() {
		if ( null === $this->native_outcome ) {
			$this->finish_without_outcome();
			return;
		}
		if ( $this->native_cleanup_done ) {
			if ( ! $this->native_outcome_closed || null !== $this->reader || null !== $this->native_outcome_writer ) {
				$this->invalidate_native_outcome();
			}
			return;
		}
		$this->native_cleanup_done = true;
		try {
			if ( $this->reader !== $this->native_outcome_writer ) {
				$this->invalidate_native_outcome();
				$this->phase = 'poisoned';
			}
			if ( 'transaction_owned' === $this->phase ) {
				if ( ! $this->session( true ) ) {
					$this->phase = 'poisoned';
				} else {
					$this->phase = 'rollback_sent';
					$this->native_outcome_send( 'rollback' );
					$ack = $this->reader->query( 'ROLLBACK AND NO CHAIN NO RELEASE' );
					$this->native_outcome_ack( 'rollback', $ack );
					if ( true === $ack && $this->session( false ) ) {
						$this->native_outcome_event( 'rollback_idle' );
						$this->phase = 'rolled_back';
					}
				}
			}
		} catch ( Throwable $error ) {
			$this->phase = 'poisoned';
		} finally {
			if ( in_array( $this->phase, array( 'begin_sent', 'commit_sent', 'rollback_sent' ), true ) ) {
				$this->phase = 'poisoned';
			}
			$owned                       = $this->native_outcome_writer;
			$this->native_outcome_writer = null;
			if ( $this->reader !== $owned ) {
				$this->invalidate_native_outcome();
			}
			$this->reader = null;
			try {
				if ( $owned instanceof mysqli ) {
					$this->native_outcome_send( 'close' );
					$ack = $owned->close();
					$this->native_outcome_ack( 'close', $ack );
					if ( true !== $ack ) {
						$this->phase = 'poisoned';
					}
				} elseif ( $this->native_outcome['facts']['events']['writer_created'] ) {
					$this->invalidate_native_outcome();
				}
			} catch ( Throwable $error ) {
				$this->phase = 'poisoned';
			}
			$this->native_outcome['facts']['handle_remaining'] = null !== $this->reader;
			$this->physical                                    = null;
			$this->native_outcome_closed                       = true;
		}
	}
}
