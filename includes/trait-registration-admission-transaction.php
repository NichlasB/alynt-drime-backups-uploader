<?php
/**
 * Fixed native capture and registration critical section; never WordPress hooks.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Candidate preparation/authentication happen outside all owned row locks. */
trait Alynt_Drime_Backups_Uploader_Registration_Admission_Transaction {
	/**
	 * Twenty fixed read-only projections. Equal sweeps are not atomic admission.
	 *
	 * @param array $core Original core frame.
	 * @return array|false Private full candidate, never exported.
	 */
	private function admission_capture( array $core ) {
		$this->phase         = 'capturing';
		$this->read_physical = $this->native_idle( $this->reader );
		if ( false === $this->read_physical || '1' !== $this->read_physical['tx_ro'] || ! $this->metadata( $core['options'] ) ) {
			return false;
		}
		$sweeps = array();
		for ( $pass = 0; $pass < 2; ++$pass ) {
			$rows = array();
			foreach ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) as $role ) {
				if ( ! $this->budget( null ) ) {
					return false;
				}
				$row = $this->admission_row( $core['options'], $role, false );
				if ( false === $row ) {
					return false;
				}
				$rows[ $role ] = $row;
			}
			$sweeps[] = array(
				'blog_id'  => $core['blog'],
				'prefix'   => $core['prefix'],
				'database' => DB_NAME,
				'rows'     => $rows,
			);
		}
		return $this->native_idle( $this->reader ) === $this->read_physical && Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::compare( $sweeps[0], $sweeps[1] )['same_candidate'] && $this->budget( null ) ? $sweeps[0] : false;
	}

	/**
	 * All ten locked before the sole ledger delta, then all ten read back.
	 *
	 * @param array $core Original core frame.
	 * @param array $e Original once-constructed typed command.
	 * @return bool Historical acknowledged closed registration commit only.
	 */
	private function admission_transaction( array $core, array $e ) {
		if ( 'idle' !== $this->phase || ! $this->session( false ) || ! $this->budget( $e ) ) {
			return false;
		}
		$this->phase = 'begin_sent';
		if ( true !== $this->reader->query( 'START TRANSACTION READ WRITE' ) || ! $this->session( true ) ) {
			return false;
		}
		$this->phase = 'transaction_owned';
		foreach ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) as $role ) {
			if ( ! $this->budget( $e ) || $this->admission_row( $core['options'], $role, true ) !== $this->before['rows'][ $role ] || ( 'safety' === $role && ! $this->metadata( $core['options'] ) ) ) {
				return false;
			}
		}
		if ( ! $this->budget( $e ) || ! $this->admission_write( $core['options'] ) ) {
			return false;
		}
		$expected                     = $this->before['rows'];
		$expected['ledger']['value']  = $this->after;
		$expected['ledger']['octets'] = (string) strlen( $this->after );
		foreach ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) as $role ) {
			if ( ! $this->budget( $e ) || $this->admission_row( $core['options'], $role, true ) !== $expected[ $role ] ) {
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
