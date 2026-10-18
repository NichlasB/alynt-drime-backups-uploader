<?php
/**
 * Fixed ten-row conditional SQL projection, not a generic selector or writer.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Private methods used only by the dormant scan observer. */
trait Alynt_Drime_Backups_Uploader_Scan_Observation_Rows {
	/**
	 * Bound payloads in SQL before fetching; select only the compiled manifest.
	 *
	 * @param string $table Pinned genuine-core table identifier.
	 * @param string $role Internally iterated manifest role, never posted.
	 * @return array|false Private tuple, never exported by public inspect.
	 */
	private function scan_row( $table, $role ) {
		$manifest = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows();
		if ( $this->protected_reads >= 20 || 1 !== preg_match( '/^[A-Za-z0-9_]{1,71}$/D', $table ) || ! is_string( $role ) || ! isset( $manifest[ $role ] ) ) {
			return false;
		}
		list( $name, $cap ) = $manifest[ $role ];
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Pinned validated core table and compiled integer cap only; option name bound separately.
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
