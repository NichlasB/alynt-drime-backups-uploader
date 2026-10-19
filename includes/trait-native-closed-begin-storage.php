<?php
/**
 * Fixed native metadata, bounded rows and exact safety-row-only CAS.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/trait-native-options-metadata.php';

/** Private operations require the fixed pinned core table, never posted SQL. */
trait Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Storage {
	use Alynt_Drime_Backups_Uploader_Native_Options_Metadata;

	/**
	 * Bounded SQL projection before materializing bytes, fixed indexed row only.
	 *
	 * @param string $table Pinned core table.
	 * @param bool   $safety Fixed row selector.
	 * @param bool   $lock Lock only inside the verified owned transaction.
	 * @return array|false
	 */
	private function row( $table, $safety, $lock ) {
		if ( $this->protected_reads >= 8 || ! is_bool( $safety ) || ! is_bool( $lock ) || 1 !== preg_match( '/^[A-Za-z0-9_]{1,71}$/D', $table ) || ! $this->session( $lock ) || ( $lock && 'transaction_owned' !== $this->phase ) ) {
			return false;
		}
		$name   = $safety ? 'alynt_drime_backups_schedule_safety_state' : 'alynt_drime_backups_settings';
		$cap    = $safety ? 131072 : 1048576;
		$suffix = $lock ? ' FOR UPDATE' : '';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed validated identifier, cap and lock suffix; value parameter bound below.
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
				$id  = is_array( $row ) ? $row['option_id'] : null;
				$max = (string) ( PHP_INT_MAX - 1 );
				return 1 === $r->num_rows && is_string( $id ) && 1 === preg_match( '/^[1-9][0-9]*$/D', $id ) && ( strlen( $id ) < strlen( $max ) || ( strlen( $id ) === strlen( $max ) && strcmp( $id, $max ) <= 0 ) ) && $name === $row['option_name'] && in_array( $row['autoload'], array( 'no', 'off', 'auto-off' ), true ) && is_string( $row['value'] ) && '' !== $row['value'] && (string) strlen( $row['value'] ) === $row['octets'] ? $row : false;
			} finally {
				$r->free();
			}
		} finally {
			$stmt->close();
		}
	}

	/**
	 * Only the fixed existing safety row, exact binary bytes/ID/name/autoload.
	 *
	 * @param string $table Pinned table.
	 * @param array  $before Exact locked original.
	 * @param string $raw Strict bounded closed successor.
	 * @return bool
	 */
	private function write_safety( $table, array $before, $raw ) {
		if ( 'transaction_owned' !== $this->phase || 0 !== $this->writes || ! $this->session( true ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifier fixed/validated by the private transaction path; every value bound.
		$stmt = $this->reader->prepare( "UPDATE `{$table}` SET option_value=? WHERE option_id=? AND BINARY option_name=? AND BINARY autoload=? AND BINARY option_value=? LIMIT 1" );
		if ( false === $stmt ) {
			return false;
		}
		try {
			++$this->writes;
			$name = 'alynt_drime_backups_schedule_safety_state';
			$stmt->bind_param( 'sssss', $raw, $before['option_id'], $name, $before['autoload'], $before['value'] );
			return true === $stmt->execute() && 1 === $stmt->affected_rows;
		} finally {
			$stmt->close();
		}
	}
}
