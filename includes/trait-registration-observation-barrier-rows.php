<?php
/**
 * Fixed ten-role locking SELECT and native zero-DML policy, no write port.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Compiled identifiers/caps and exact session counters only. */
trait Alynt_Drime_Backups_Uploader_Registration_Observation_Barrier_Rows {
	/**
	 * Bound bytes before fetch; no alternate query, capture sweep or retry.
	 *
	 * @param string $table Pinned validated core table.
	 * @param string $role Next compiled role in safety-first order.
	 * @return array|false Private tuple; full codecs parse only after close.
	 */
	private function barrier_row( $table, $role ) {
		$manifest = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows();
		if ( 'transaction_owned' !== $this->phase || $this->barrier_reads >= 10 || ( array_keys( $manifest )[ $this->barrier_reads ] ?? null ) !== $role || 1 !== preg_match( '/^[A-Za-z0-9_]{1,71}$/D', $table ) ) {
			return false;
		}
		list( $name, $cap ) = $manifest[ $role ];
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Pinned ASCII table and compiled integer cap only; exact manifest name bound separately.
		$stmt = $this->reader->prepare( "SELECT CASE WHEN OCTET_LENGTH(CAST(option_id AS CHAR))<=20 THEN CAST(option_id AS CHAR) ELSE NULL END AS option_id, CASE WHEN OCTET_LENGTH(option_name)<=64 THEN BINARY option_name ELSE NULL END AS option_name, CASE WHEN OCTET_LENGTH(autoload)<=20 THEN BINARY autoload ELSE NULL END AS autoload, CAST(OCTET_LENGTH(option_value) AS CHAR) AS octets, CASE WHEN OCTET_LENGTH(option_value) BETWEEN 1 AND {$cap} THEN BINARY option_value ELSE NULL END AS value FROM `{$table}` FORCE INDEX (option_name) WHERE option_name=? LIMIT 2 FOR UPDATE" );
		if ( false === $stmt ) {
			return false;
		}
		try {
			++$this->barrier_reads;
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
	 * Fresh owned connection only: every fixed DML/DDL and row-write counter zero.
	 * Not permission to run DML, and no counter reset or session repair occurs.
	 *
	 * @return bool Native execution evidence, not a caller-supplied assertion.
	 */
	private function barrier_zero_dml() {
		$r = $this->reader->query( "SHOW SESSION STATUS WHERE Variable_name IN ('Com_insert','Com_insert_select','Com_update','Com_update_multi','Com_delete','Com_delete_multi','Com_replace','Com_replace_select','Com_create_table','Com_alter_table','Com_drop_table','Com_truncate','Handler_write','Handler_update','Handler_delete')" );
		if ( ! $r instanceof mysqli_result ) {
			return false;
		}
		try {
			if ( 15 !== $r->num_rows ) {
				return false;
			}
			foreach ( $r->fetch_all( MYSQLI_NUM ) as $row ) {
				if ( '0' !== $row[1] ) {
					return false;
				}
			}
			++$this->zero_dml_checks;
			return true;
		} finally {
			$r->free();
		}
	}
}
