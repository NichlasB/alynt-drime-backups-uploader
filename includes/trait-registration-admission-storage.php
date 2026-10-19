<?php
/**
 * Separate fixed forty-read projection and one-ledger-row CAS, not a SQL port.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Only the dormant native registration coordinator owns these operations. */
trait Alynt_Drime_Backups_Uploader_Registration_Admission_Storage {
	/**
	 * Bound fixed payloads before fetch; capture or already-owned lock phases only.
	 *
	 * @param string $table Pinned genuine-core identifier.
	 * @param string $role Compiled manifest role.
	 * @param bool   $lock Exact lock phase.
	 * @return array|false Private tuple.
	 */
	private function admission_row( $table, $role, $lock ) {
		$manifest = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows();
		if ( $this->protected_reads >= 40 || ! is_bool( $lock ) || ! is_string( $role ) || ! isset( $manifest[ $role ] ) || 1 !== preg_match( '/^[A-Za-z0-9_]{1,71}$/D', $table ) || ( $lock ? 'transaction_owned' !== $this->phase || ! $this->session( true ) : 'capturing' !== $this->phase || $this->native_idle( $this->reader ) !== $this->read_physical ) ) {
			return false;
		}
		list( $name, $cap ) = $manifest[ $role ];
		$suffix             = $lock ? ' FOR UPDATE' : '';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed cap/suffix and pinned ASCII core identifier; compiled name bound below.
		$stmt = $this->reader->prepare( "SELECT CASE WHEN OCTET_LENGTH(CAST(option_id AS CHAR))<=20 THEN CAST(option_id AS CHAR) ELSE NULL END AS option_id, CASE WHEN OCTET_LENGTH(option_name)<=64 THEN BINARY option_name ELSE NULL END AS option_name, CASE WHEN OCTET_LENGTH(autoload)<=20 THEN BINARY autoload ELSE NULL END AS autoload, CAST(OCTET_LENGTH(option_value) AS CHAR) AS octets, CASE WHEN OCTET_LENGTH(option_value) BETWEEN 1 AND {$cap} THEN BINARY option_value ELSE NULL END AS value FROM `{$table}` FORCE INDEX (option_name) WHERE option_name=? LIMIT 2{$suffix}" );
		if ( false === $stmt ) {
			return false;
		}
		try {
			++$this->protected_reads;
			$stmt->bind_param( 's', $name );
			if ( true !== $stmt->execute() ) {
				return false;
			}
			$r = $stmt->get_result();
			if ( ! $r instanceof mysqli_result ) {
				return false;
			}
			try {
				$row = $r->fetch_assoc();
				return 1 === $r->num_rows && is_array( $row ) && $name === $row['option_name'] && is_string( $row['value'] ) ? $row : false;
			} finally {
				$r->free();
			}
		} finally {
			$stmt->close();
		}
	}

	/**
	 * One ledger UPDATE attempt; no other row, insertion, producer or repair.
	 *
	 * @param string $table Pinned table identifier.
	 * @return bool Acknowledged exact one-row CAS only.
	 */
	private function admission_write( $table ) {
		if ( 'transaction_owned' !== $this->phase || 0 !== $this->writes || ! $this->session( true ) || 1 !== preg_match( '/^[A-Za-z0-9_]{1,71}$/D', $table ) ) {
			return false;
		}
		$before = $this->before['rows']['ledger'];
		++$this->writes;
		$this->native_outcome_event( 'attempt_reserved' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Pinned ASCII core identifier; exact original row predicates are bound.
		$stmt = $this->reader->prepare( "UPDATE `{$table}` SET option_value=? WHERE option_id=? AND BINARY option_name=? AND BINARY autoload=? AND BINARY option_value=? LIMIT 1" );
		if ( false === $stmt ) {
			return false;
		}
		try {
			$name = 'alynt_drime_backups_scan_effect_state';
			if ( true !== $stmt->bind_param( 'sssss', $this->after, $before['option_id'], $name, $before['autoload'], $before['value'] ) ) {
				return false;
			}
			$this->native_outcome_event( 'bind_verified' );
			if ( ! $this->session( true ) || ! $this->budget( $this->proposal['command'] ) ) {
				return false;
			}
			$this->native_outcome_send( 'dml' );
			$ack = $stmt->execute();
			$this->native_outcome_ack( 'dml', $ack );
			if ( true !== $ack ) {
				return false;
			}
			$rows = $stmt->affected_rows;
			if ( null !== $this->native_outcome ) {
				$this->native_outcome['facts']['affected_rows'] = 1 === $rows ? 'one' : ( 0 === $rows ? 'zero' : 'other' );
			}
			return 1 === $rows;
		} finally {
			$stmt->close();
		}
	}
}
