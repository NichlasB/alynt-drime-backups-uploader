<?php
/**
 * Fixed all-ten ledger checkpoint critical section, no WordPress callbacks.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Unknown commands remain poisoned; no replay, cancellation or resolution. */
trait Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Transaction {
	/**
	 * Native SQL, exact already-validated tuples and original clocks only.
	 *
	 * @param array  $frame Pinned scope.
	 * @param array  $e Original typed intent.
	 * @param array  $rows Complete prefetch.
	 * @param string $raw Bounded ledger-only successor.
	 * @return bool Historical acknowledged commit, not current permission.
	 */
	private function transact( array $frame, array $e, array $rows, $raw ) {
		if ( 'idle' !== $this->phase || ! $this->session( false ) || ! $this->budget( $e ) ) {
			return false;
		}
		$this->phase = 'begin_sent';
		if ( true !== $this->reader->query( 'START TRANSACTION READ WRITE' ) || ! $this->session( true ) ) {
			return false;
		}
		$this->phase = 'transaction_owned';
		foreach ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) as $role ) {
			if ( ! $this->budget( $e ) || $this->checkpoint_row( $frame['options'], $role, true ) !== $rows[ $role ] || ( 'safety' === $role && ! $this->metadata( $frame['options'] ) ) ) {
				return false;
			}
		}
		if ( ! $this->budget( $e ) || ! $this->write_ledger( $frame['options'], $rows['ledger'], $raw ) ) {
			return false;
		}
		$rows['ledger']['value']  = $raw;
		$rows['ledger']['octets'] = (string) strlen( $raw );
		foreach ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) as $role ) {
			if ( ! $this->budget( $e ) || $this->checkpoint_row( $frame['options'], $role, true ) !== $rows[ $role ] ) {
				return false;
			}
		}
		if ( ! $this->session( true ) || ! $this->budget( $e ) ) {
			return false;
		}
		$this->phase = 'commit_sent';
		if ( true !== $this->reader->query( 'COMMIT AND NO CHAIN NO RELEASE' ) || ! $this->session( false ) ) {
			return false;
		}
		$this->phase = 'committed';
		return true;
	}
}
