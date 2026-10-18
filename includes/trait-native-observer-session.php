<?php
/**
 * Fixed loopback MariaDB candidate profile; no writer or transaction authority.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Private native operations only, with no caller-selected endpoint or query port. */
trait Alynt_Drime_Backups_Uploader_Native_Observer_Session {
	/**
	 * Narrow initial profile, not a new plugin-wide compatibility floor.
	 *
	 * @return bool
	 */
	private function profile() {
		foreach ( array( 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_CHARSET', 'DB_COLLATE' ) as $name ) {
			if ( ! defined( $name ) || ! is_string( constant( $name ) ) ) {
				return false;
			}
		}
		if ( PHP_INT_SIZE < 8 || ! extension_loaded( 'mysqli' ) || ! extension_loaded( 'mysqlnd' ) || ( defined( 'MYSQL_CLIENT_FLAGS' ) && 0 !== MYSQL_CLIENT_FLAGS ) || 'utf8mb4' !== DB_CHARSET || '' !== DB_COLLATE || 1 !== preg_match( '/^127\.0\.0\.1:([1-9][0-9]{0,4})$/D', DB_HOST, $m ) ) {
			return false;
		}
		return (int) $m[1] <= 65535 && 1 === preg_match( '/^[A-Za-z0-9_-]{1,64}$/D', DB_NAME ) && '' !== DB_USER;
	}

	/**
	 * Direct fixed metadata SELECT: no wpdb query, reconnect, ping or session repair.
	 *
	 * @param mixed $db Exact native handle.
	 * @return array|false Idle physical facts, private and short-lived.
	 */
	private function native_idle( $db ) {
		if ( ! $db instanceof mysqli || 'mysqli' !== get_class( $db ) || false === strpos( $db->host_info, 'TCP/IP' ) || 'utf8mb4' !== $db->character_set_name() ) {
			return false;
		}
		// MariaDB CAST(boolean AS CHAR) can yield ON/OFF; arithmetic fixes the wire grammar.
		$result = $db->query( 'SELECT CAST(CONNECTION_ID() AS CHAR) AS thread, DATABASE() AS db, @@hostname AS host, CAST(@@port AS CHAR) AS port, CAST(@@server_id AS CHAR) AS server, VERSION() AS version, CAST(@@autocommit+0 AS CHAR) AS auto, CAST(@@in_transaction+0 AS CHAR) AS tx, CAST(@@read_only+0 AS CHAR) AS ro, CAST(@@tx_read_only+0 AS CHAR) AS tx_ro' );
		if ( ! $result instanceof mysqli_result ) {
			return false;
		}
		try {
			$r = $result->fetch_assoc();
			if ( ! is_array( $r ) || 1 !== $result->num_rows || (string) $db->thread_id !== $r['thread'] || DB_NAME !== $r['db'] || '1' !== $r['auto'] || '0' !== $r['tx'] || '0' !== $r['ro'] || 1 !== preg_match( '/^10\.6\.[0-9]+-MariaDB(?:[- ].*)?$/D', $r['version'] ) ) {
				return false;
			}
			return $r;
		} finally {
			$result->free();
		}
	}

	/**
	 * Detect authentication handle/session swaps, including reconnect on same wpdb.
	 *
	 * @param array  $frame Original core metadata.
	 * @param mysqli $auth Borrowed, never closed/configured here.
	 * @param array  $before Original native facts.
	 * @return bool
	 */
	private function auth_same( array $frame, $auth, array $before ) {
		return $this->frame() === $frame && $frame['db']->dbh === $auth && $this->native_idle( $auth ) === $before;
	}

	/**
	 * Own a new nonpersistent read-only session. No adoption of caller handles.
	 * Trusted loopback installation/endpoint is an explicit deployment assumption.
	 *
	 * @param mysqli $auth Borrowed authentication handle.
	 * @param array  $before Authentication metadata.
	 * @return bool
	 */
	private function open_reader( $auth, array $before ) {
		// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- A private owned native session is required; wpdb can reconnect or borrow ambient transactions.
		$this->reader = mysqli_init();
		if ( ! $this->reader instanceof mysqli || $this->reader === $auth || ! $this->reader->options( MYSQLI_OPT_CONNECT_TIMEOUT, 3 ) || ! $this->reader->options( MYSQLI_OPT_READ_TIMEOUT, 5 ) || ! $this->reader->options( MYSQLI_OPT_LOCAL_INFILE, false ) || ! $this->reader->real_connect( '127.0.0.1', DB_USER, DB_PASSWORD, DB_NAME, (int) substr( DB_HOST, 10 ), null, 0 ) || ! $this->reader->set_charset( 'utf8mb4' ) ) {
			return false;
		}
		$own = $this->native_idle( $this->reader );
		if ( false === $own || $own['thread'] === $before['thread'] ) {
			return false;
		}
		foreach ( array( 'db', 'host', 'port', 'server', 'version', 'ro' ) as $key ) {
			if ( $own[ $key ] !== $before[ $key ] ) {
				return false;
			}
		}
		if ( true !== $this->reader->query( 'SET SESSION TRANSACTION READ ONLY' ) ) {
			return false;
		}
		$own = $this->native_idle( $this->reader );
		return false !== $own && '1' === $own['tx_ro'];
	}
}
