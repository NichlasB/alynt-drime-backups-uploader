<?php
/**
 * Native owned writer lifecycle, never the application's authentication session.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No generic query/callback/reconnect port or automatic retry. */
trait Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session {
	/**
	 * Reuse the proven fresh read-only factory, then configure only our idle handle.
	 *
	 * @param mysqli $auth Borrowed authentication connection, never configured here.
	 * @param array  $before Original physical facts.
	 * @return bool
	 */
	private function open_writer( $auth, array $before ) {
		if ( ! $this->open_reader( $auth, $before ) ) {
			return false;
		}
		foreach ( array( 'SET SESSION TRANSACTION READ WRITE', 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED', 'SET SESSION innodb_lock_wait_timeout=2', 'SET SESSION lock_wait_timeout=3' ) as $sql ) {
			if ( false === $this->native_idle( $this->reader ) || true !== $this->reader->query( $sql ) ) {
				return false;
			}
		}
		$this->physical = $this->native_idle( $this->reader );
		if ( false === $this->physical || '0' !== $this->physical['tx_ro'] || ! $this->policy() ) {
			return false;
		}
		$this->phase = 'idle';
		return true;
	}

	/**
	 * Verify actual session policy rather than a SET acknowledgement alone.
	 *
	 * @return bool
	 */
	private function policy() {
		$r = $this->reader->query( 'SELECT @@tx_isolation,CAST(@@innodb_lock_wait_timeout AS CHAR),CAST(@@lock_wait_timeout AS CHAR),CURRENT_ROLE(),CAST(@@innodb_flush_log_at_trx_commit AS CHAR)' );
		if ( ! $r instanceof mysqli_result ) {
			return false;
		}
		try {
			return 1 === $r->num_rows && array( 'READ-COMMITTED', '2', '3', null, '1' ) === $r->fetch_row();
		} finally {
			$r->free();
		}
	}

	/**
	 * Same pinned native lifetime, exact transaction state, no implicit repair.
	 *
	 * @param bool $transaction Expected owned transaction.
	 * @return bool
	 */
	private function session( $transaction ) {
		if ( ! $this->reader instanceof mysqli || ! is_array( $this->physical ) || (string) $this->reader->thread_id !== $this->physical['thread'] || 'utf8mb4' !== $this->reader->character_set_name() ) {
			return false;
		}
		$r = $this->reader->query( 'SELECT CAST(CONNECTION_ID() AS CHAR),DATABASE(),@@hostname,CAST(@@port AS CHAR),CAST(@@server_id AS CHAR),VERSION(),CAST(@@autocommit+0 AS CHAR),CAST(@@in_transaction+0 AS CHAR),CAST(@@read_only+0 AS CHAR),CAST(@@tx_read_only+0 AS CHAR)' );
		if ( ! $r instanceof mysqli_result ) {
			return false;
		}
		try {
			$expected    = array_values( $this->physical );
			$expected[7] = $transaction ? '1' : '0';
			return 1 === $r->num_rows && $expected === $r->fetch_row() && $this->policy();
		} finally {
			$r->free();
		}
	}

	/**
	 * Roll back only definite pre-commit ownership; unknown BEGIN/COMMIT poisons.
	 * An idle state alone never promotes an unacknowledged command to success.
	 *
	 * @return void
	 */
	private function finish() {
		try {
			if ( 'transaction_owned' === $this->phase ) {
				if ( ! $this->session( true ) ) {
					$this->phase = 'poisoned';
				} else {
					$this->phase = 'rollback_sent';
					if ( true === $this->reader->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ) && $this->session( false ) ) {
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
			$owned        = $this->reader;
			$this->reader = null;
			try {
				if ( $owned instanceof mysqli && true !== $owned->close() ) {
					$this->phase = 'poisoned';
				}
			} catch ( Throwable $error ) {
				$this->phase = 'poisoned';
			}
			$this->physical = null;
		}
	}
}
