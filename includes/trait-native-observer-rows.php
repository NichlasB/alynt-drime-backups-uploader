<?php
/**
 * Fixed bounded binary row projection on the private read-only connection.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No generic execute/update port; identifiers are from the pinned core frame. */
trait Alynt_Drime_Backups_Uploader_Native_Observer_Rows {
	/**
	 * Require a real InnoDB table and full single-column unique option-name index.
	 * This is read-only planning, NOT writer schema/trigger/metadata-lock admission.
	 *
	 * @param string $table Pinned options table.
	 * @return bool
	 */
	private function table_supported( $table ) {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]{1,71}$/D', $table ) ) {
			return false;
		}
		$stmt = $this->reader->prepare( 'SELECT ENGINE,TABLE_TYPE FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 2' );
		if ( false === $stmt ) {
			return false;
		}
		try {
			$stmt->bind_param( 's', $table );
			$stmt->execute();
			$result = $stmt->get_result();
			if ( ! $result instanceof mysqli_result ) {
				return false;
			}
			try {
				$row = $result->fetch_row();
				if ( 1 !== $result->num_rows || array( 'InnoDB', 'BASE TABLE' ) !== $row ) {
					return false;
				}
			} finally {
				$result->free();
			}
		} finally {
			$stmt->close();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Validated fixed core identifier, never request input.
		$result = $this->reader->query( "SHOW INDEX FROM `{$table}` WHERE Key_name='option_name'" );
		if ( ! $result instanceof mysqli_result ) {
			return false;
		}
		try {
			$row = $result->fetch_assoc();
			return 1 === $result->num_rows && '0' === (string) $row['Non_unique'] && '1' === (string) $row['Seq_in_index'] && 'option_name' === $row['Column_name'] && null === $row['Sub_part'] && 'BTREE' === $row['Index_type'];
		} finally {
			$result->free();
		}
	}

	/**
	 * Project byte bounds in SQL BEFORE fetching raw values; never fetch then trim.
	 * LIMIT 2 and full unique indexing bound row count; exact byte-name check follows.
	 *
	 * @param string $table Pinned core options table.
	 * @param bool   $safety Fixed order selector, not caller-provided option names.
	 * @return array|false Private projected row.
	 */
	private function raw_row( $table, $safety ) {
		if ( $this->protected_reads >= 4 || 1 !== preg_match( '/^[A-Za-z0-9_]{1,71}$/D', $table ) || ! is_bool( $safety ) ) {
			return false;
		}
		$name = $safety ? 'alynt_drime_backups_schedule_safety_state' : 'alynt_drime_backups_settings';
		$cap  = $safety ? 131072 : 1048576;
		// Bounds also cover malformed metadata; nulls refuse rather than truncate.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed validated core identifier and literal cap; name bound separately with mysqli.
		$stmt = $this->reader->prepare( "SELECT CASE WHEN OCTET_LENGTH(CAST(option_id AS CHAR))<=20 THEN CAST(option_id AS CHAR) ELSE NULL END AS option_id, CASE WHEN OCTET_LENGTH(option_name)<=64 THEN BINARY option_name ELSE NULL END AS option_name, CASE WHEN OCTET_LENGTH(autoload)<=20 THEN BINARY autoload ELSE NULL END AS autoload, CAST(OCTET_LENGTH(option_value) AS CHAR) AS octets, CASE WHEN OCTET_LENGTH(option_value) BETWEEN 1 AND {$cap} THEN BINARY option_value ELSE NULL END AS value FROM `{$table}` FORCE INDEX (option_name) WHERE option_name=? LIMIT 2" );
		if ( false === $stmt ) {
			return false;
		}
		try {
			++$this->protected_reads;
			$stmt->bind_param( 's', $name );
			$stmt->execute();
			$result = $stmt->get_result();
			if ( ! $result instanceof mysqli_result ) {
				return false;
			}
			try {
				$row = $result->fetch_assoc();
				return 1 === $result->num_rows && is_array( $row ) && $name === $row['option_name'] && is_string( $row['value'] ) ? $row : false;
			} finally {
				$result->free();
			}
		} finally {
			$stmt->close();
		}
	}
}
