<?php
/**
 * Separate fixed ten-row locking selector and ledger-only binary CAS.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No caller-selected row, SQL, callback or fallback to the old two-row owner. */
trait Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Storage {
	/**
	 * Bound projection before fetch; three fixed passes, no unbounded traversal.
	 *
	 * @param string $table Pinned genuine-core identifier.
	 * @param string $role Internally compiled role.
	 * @param bool   $lock Only in the verified owned transaction.
	 * @return array|false Private exact tuple.
	 */
	private function checkpoint_row( $table, $role, $lock ) {
		$manifest = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows();
		if ( $this->protected_reads >= 30 || ! is_bool( $lock ) || 1 !== preg_match( '/^[A-Za-z0-9_]{1,71}$/D', $table ) || ! is_string( $role ) || ! isset( $manifest[ $role ] ) || ! $this->session( $lock ) || ( $lock && 'transaction_owned' !== $this->phase ) ) {
			return false;
		}
		list( $name, $cap ) = $manifest[ $role ];
		$suffix             = $lock ? ' FOR UPDATE' : '';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Pinned ASCII identifier and compiled cap/suffix; fixed name bound below.
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
	 * Exactly one attempted UPDATE of the existing ledger; no effect-row writer.
	 *
	 * @param string $table Pinned table.
	 * @param array  $before Validated locked original tuple.
	 * @param string $raw Prepared bounded closed successor.
	 * @return bool Acknowledged exactly-one-row CAS only.
	 */
	private function write_ledger( $table, array $before, $raw ) {
		if ( 'transaction_owned' !== $this->phase || 0 !== $this->writes || ! $this->session( true ) || 1 !== preg_match( '/^[A-Za-z0-9_]{1,71}$/D', $table ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Pinned ASCII core identifier; every compared value bound.
		$stmt = $this->reader->prepare( "UPDATE `{$table}` SET option_value=? WHERE option_id=? AND BINARY option_name=? AND BINARY autoload=? AND BINARY option_value=? LIMIT 1" );
		if ( false === $stmt ) {
			return false;
		}
		try {
			++$this->writes;
			$name = 'alynt_drime_backups_scan_effect_state';
			$stmt->bind_param( 'sssss', $raw, $before['option_id'], $name, $before['autoload'], $before['value'] );
			return true === $stmt->execute() && 1 === $stmt->affected_rows;
		} finally {
			$stmt->close();
		}
	}
}
