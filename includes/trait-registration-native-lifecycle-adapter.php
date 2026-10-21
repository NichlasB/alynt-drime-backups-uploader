<?php
/**
 * Fixed native profile, capture and idle phase setup; no callback/query port.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** These helpers are unreachable from the final closed entry. */
trait Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Adapter {
	/**
	 * Pin borrowed metadata only, never protected payloads or owned factories.
	 *
	 * @param array $core Original core.
	 * @return array|false Native physical facts, not permission.
	 */
	private function lifecycle_profile( $core ) {
		if ( ! $this->profile() ) {
			return false;
		}
		$this->barrier_auth       = $core['db']->dbh;
		$this->barrier_auth_facts = $this->native_idle( $this->barrier_auth );
		return $this->barrier_auth_facts;
	}

	/**
	 * Original borrowed identity across both phases, never re-pinned.
	 *
	 * @param array $core Original core.
	 * @param array $facts Original facts.
	 * @return bool Same physical session.
	 */
	private function lifecycle_physical( $core, $facts ) {
		return $facts === $this->barrier_auth_facts && $this->auth_same( $core, $this->barrier_auth, $facts );
	}

	/**
	 * Own read-only capture and acknowledged detach/close BEFORE construction.
	 *
	 * @param array $core Original core.
	 * @return array|false Private equal-sweep frame, not atomicity.
	 */
	private function lifecycle_capture( $core ) {
		if ( null !== $this->capture_receipt || null !== $this->native_resource ) {
			return false;
		}
		$this->capture_receipt = false;
		$this->native_resource = 'capture';
		try {
			if ( ! $this->open_lifecycle_session( false ) ) {
				return false;
			}
			$frame = $this->admission_capture( $core );
			return 20 === $this->protected_reads && $this->budget( null ) ? $frame : false;
		} finally {
			if ( ! $this->release_native_capture() ) {
				return false;
			}
		}
	}

	/**
	 * One owned factory; fixed phase selects read-only vs idle writer policy.
	 *
	 * @param bool $writer Fixed internal factory selection, not a request input.
	 * @return bool Factory result only.
	 */
	private function open_lifecycle_session( $writer ) {
		try {
			return $writer ? $this->open_writer_without_outcome( $this->barrier_auth, $this->barrier_auth_facts ) : $this->open_reader( $this->barrier_auth, $this->barrier_auth_facts );
		} finally {
			$this->native_handle = $this->reader instanceof mysqli && $this->reader !== $this->barrier_auth ? $this->reader : null;
		}
	}

	/**
	 * Idle writer setup only; root MUST run both guards again before transaction.
	 *
	 * @param array $core Original core.
	 * @param array $facts Original facts.
	 * @return bool No BEGIN, DML or authorization claim.
	 */
	private function setup_native_writer( $core, $facts ) {
		if ( true !== $this->capture_receipt || null !== $this->admission_receipt || null !== $this->native_resource || ! $this->lifecycle_physical( $core, $facts ) || ! $this->budget( $this->proposal['command'] ) ) {
			return false;
		}
		$this->admission_receipt = false;
		$this->native_resource   = 'admission';
		return $this->open_writer( $this->barrier_auth, $facts ) && $this->metadata( $core['options'] );
	}

	/**
	 * Original-root plus read-phase budget, no callbacks or supplied clock reset.
	 *
	 * @param int   $outer Own original read start.
	 * @param array $e Own typed read envelope.
	 * @return bool Native-only acceptance budget.
	 */
	private function barrier_budget( $outer, $e ) {
		return $outer === $this->read_started && $this->budget( $e ) && self::clock( $this->lifecycle_read_clock() );
	}

	/**
	 * Do not import the standalone observer's consuming entry into this owner.
	 *
	 * @param mixed $body Ignored; no origin/capture adoption.
	 * @return false Always closed.
	 */
	private function observe_native( $body ) {
		unset( $body );
		return false;
	}
}
