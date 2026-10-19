<?php
/**
 * Shared fixed native options metadata policy; no payload reader or writer.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Native writers reuse exactly the same conservative supported schema. */
trait Alynt_Drime_Backups_Uploader_Native_Options_Metadata {
	/**
	 * Conservative proof profile: global metadata visibility, normal WP schema,
	 * no triggers or inbound/outbound FKs. Repeat after acquiring table MDL.
	 * This does not certify a hostile administrator or a future deployment.
	 *
	 * @param string $table Pinned installation identifier.
	 * @return bool
	 */
	private function metadata( $table ) {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]{1,71}$/D', $table ) ) {
			return false;
		}
		// Explicit global SELECT/TRIGGER/REFERENCES avoids hidden-empty metadata.
		$r = $this->reader->query( "SELECT CAST(COUNT(DISTINCT PRIVILEGE_TYPE) AS CHAR) FROM information_schema.USER_PRIVILEGES WHERE GRANTEE=CONCAT(QUOTE(SUBSTRING_INDEX(CURRENT_USER(),'@',1)),'@',QUOTE(SUBSTRING_INDEX(CURRENT_USER(),'@',-1))) AND PRIVILEGE_TYPE IN ('SELECT','TRIGGER','REFERENCES')" );
		if ( ! $r instanceof mysqli_result ) {
			return false;
		}
		try {
			if ( 1 !== $r->num_rows || array( '3' ) !== $r->fetch_row() ) {
				return false;
			}
		} finally {
			$r->free();
		}
		$checks = array(
			'SELECT ENGINE,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 2' => array( array( 'InnoDB', 'BASE TABLE' ) ),
			'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,EXTRA,GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION LIMIT 5' => array(
				array( 'option_id', 'bigint(20) unsigned', 'NO', 'auto_increment', null ),
				array( 'option_name', 'varchar(191)', 'NO', '', null ),
				array( 'option_value', 'longtext', 'NO', '', null ),
				array( 'autoload', 'varchar(20)', 'NO', '', null ),
			),
			'SELECT CAST(COUNT(*) AS CHAR) FROM information_schema.TRIGGERS WHERE EVENT_OBJECT_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE=?' => array( array( '0' ) ),
			'SELECT CONSTRAINT_NAME,CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY CONSTRAINT_NAME LIMIT 4' => array( array( 'option_name', 'UNIQUE' ), array( 'PRIMARY', 'PRIMARY KEY' ) ),
		);
		foreach ( $checks as $sql => $expected ) {
			$stmt = $this->reader->prepare( $sql );
			if ( false === $stmt ) {
				return false;
			}
			try {
				$stmt->bind_param( 's', $table );
				if ( true !== $stmt->execute() ) {
					return false;
				}
				$r = $stmt->get_result();
				if ( ! $r instanceof mysqli_result ) {
					return false;
				}
				try {
					if ( $expected !== $r->fetch_all( MYSQLI_NUM ) ) {
						return false;
					}
				} finally {
					$r->free();
				}
			} finally {
				$stmt->close();
			}
		}
		$stmt = $this->reader->prepare( 'SELECT CAST(COUNT(*) AS CHAR) FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME IS NOT NULL AND ((TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?) OR (REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME=?))' );
		if ( false === $stmt ) {
			return false;
		}
		try {
			$stmt->bind_param( 'ss', $table, $table );
			if ( true !== $stmt->execute() ) {
				return false;
			}
			$r = $stmt->get_result();
			if ( ! $r instanceof mysqli_result ) {
				return false;
			}
			try {
				if ( array( array( '0' ) ) !== $r->fetch_all( MYSQLI_NUM ) ) {
					return false;
				}
			} finally {
				$r->free();
			}
		} finally {
			$stmt->close();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed ASCII core identifier, never request input.
		$r = $this->reader->query( "SHOW INDEX FROM `{$table}`" );
		if ( ! $r instanceof mysqli_result ) {
			return false;
		}
		try {
			if ( $r->num_rows < 2 || $r->num_rows > 3 ) {
				return false;
			}
			$keys = array();
			foreach ( $r->fetch_all( MYSQLI_ASSOC ) as $row ) {
				$name = $row['Key_name'];
				if ( isset( $keys[ $name ] ) || ! in_array( $name, array( 'PRIMARY', 'option_name', 'autoload' ), true ) || '1' !== (string) $row['Seq_in_index'] || null !== $row['Sub_part'] || 'BTREE' !== $row['Index_type'] || 'NO' !== ( $row['Ignored'] ?? null ) || ( 'autoload' === $name ? '1' : '0' ) !== (string) $row['Non_unique'] || ( 'PRIMARY' === $name ? 'option_id' : $name ) !== $row['Column_name'] ) {
					return false;
				}
				$keys[ $name ] = true;
			}
			return isset( $keys['PRIMARY'], $keys['option_name'] );
		} finally {
			$r->free();
		}
	}
}
