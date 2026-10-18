<?php
/**
 * Dormant paired completion, reachable only through the owner's private begin.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No caller bytes, callback, clock, handle or Boolean admission port. */
trait Alynt_Drime_Backups_Uploader_Native_Paired_Completion {
	/**
	 * Execute the private two-transaction proof flow, never a registered handler.
	 *
	 * @param string $method Original method.
	 * @param mixed  $body Original form.
	 * @return bool Non-authorizing private decision only.
	 */
	private function disable( $method, $body ) {
		try {
			return $this->begin( $method, $body ) && $this->complete();
		} finally {
			$this->continuation = null;
		}
	}

	/**
	 * Consume facts before IO, retaining the original monotonic/UTC budget.
	 *
	 * @return bool False may include a durable pair commit; never replay.
	 */
	private function complete() {
		$c                  = $this->continuation;
		$this->continuation = null;
		if ( ! is_array( $c ) || 'committed' !== $this->phase || ! $this->budget( $c['e'] ) ) {
			return false;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Local warning policy restored in finally; no private diagnostics escape.
		set_error_handler(
			static function () {
				throw new RuntimeException( 'Native pair unavailable.' );
			}
		);
		try {
			// Per-transaction cap; at most eight begin plus eight pair reads total.
			$this->protected_reads = 0;
			$this->writes          = 0;
			if ( ! $this->continuation_auth( $c ) ) {
				return false;
			}
			if ( ! $this->open_writer( $c['auth'], $c['before'] ) || ! $this->metadata( $c['frame']['options'] ) ) {
				return false;
			}
			$a = $this->row( $c['frame']['options'], true, false );
			$b = $this->row( $c['frame']['options'], false, false );
			// Compare the confirmed POST-begin tuples, never the original state hash.
			if ( $a !== $c['a'] || $b !== $c['b'] ) {
				return false;
			}
			$s   = Alynt_Drime_Backups_Uploader_Closed_Writer_Json_Contract::decode( $a['value'] );
			$p   = false === $s ? false : Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::completion( $s, $c['e'], $b['value'], time(), $c['frame']['blog'], $b['autoload'], true );
			$raw = false === $p ? false : json_encode( $p['state'] );
			if ( ! is_string( $raw ) || false === Alynt_Drime_Backups_Uploader_Closed_Writer_Json_Contract::decode( $raw ) || ! is_string( $p['target'] ) || $raw === $a['value'] || $p['target'] === $b['value'] || ! $this->continuation_auth( $c ) ) {
				return false;
			}
			if ( ! $this->pair_transaction( $c['frame'], $c['e'], $a, $b, $raw, $p['target'] ) || ! $this->continuation_auth( $c ) ) {
				return false;
			}
			$this->finish();
			return 'committed' === $this->phase && $this->budget( $c['e'] );
		} catch ( Throwable $error ) {
			return false;
		} finally {
			$this->finish();
			restore_error_handler();
		}
	}

	/**
	 * Passive borrowed-session fences surround every genuine guard, outside locks.
	 *
	 * @param array $c Internally minted continuation only.
	 * @return bool
	 */
	private function continuation_auth( array $c ) {
		return $this->auth_same( $c['frame'], $c['auth'], $c['before'] ) && $this->guard( $c['frame'], $c['f']['nonce'], $c['action'], $c['e'] ) && $this->auth_same( $c['frame'], $c['auth'], $c['before'] ) && $this->same_request( $c['frame'], $c['cookie'], $c['actor'] ) && $this->budget( $c['e'] );
	}

	/**
	 * Fixed critical section: no domain validators, WordPress or callbacks.
	 *
	 * @param array  $frame Fixed frame.
	 * @param array  $e Original begin.
	 * @param array  $a Validated post-begin safety.
	 * @param array  $b Validated target.
	 * @param string $raw Prepared completion.
	 * @param string $target Prepared one-token patch.
	 * @return bool
	 */
	private function pair_transaction( array $frame, array $e, array $a, array $b, $raw, $target ) {
		if ( 'idle' !== $this->phase || ! $this->session( false ) || ! $this->budget( $e ) ) {
			return false;
		}
		$this->phase = 'begin_sent';
		if ( true !== $this->reader->query( 'START TRANSACTION READ WRITE' ) || ! $this->session( true ) ) {
			return false;
		}
		$this->phase = 'transaction_owned';
		if ( $a !== $this->row( $frame['options'], true, true ) || ! $this->metadata( $frame['options'] ) || $b !== $this->row( $frame['options'], false, true ) || ! $this->budget( $e ) || ! $this->write_safety( $frame['options'], $a, $raw ) || ! $this->write_settings( $frame['options'], $b, $target ) ) {
			return false;
		}
		$expected_a           = $a;
		$expected_b           = $b;
		$expected_a['value']  = $raw;
		$expected_b['value']  = $target;
		$expected_a['octets'] = (string) strlen( $raw );
		$expected_b['octets'] = (string) strlen( $target );
		if ( $expected_a !== $this->row( $frame['options'], true, true ) || $expected_b !== $this->row( $frame['options'], false, true ) || ! $this->session( true ) || ! $this->budget( $e ) ) {
			return false;
		}
		$this->phase = 'commit_sent';
		if ( true !== $this->reader->query( 'COMMIT AND NO CHAIN NO RELEASE' ) || ! $this->session( false ) ) {
			return false;
		}
		$this->phase = 'committed';
		return $expected_a === $this->row( $frame['options'], true, false ) && $expected_b === $this->row( $frame['options'], false, false ) && $this->session( false ) && $this->budget( $e );
	}

	/**
	 * Second and last UPDATE only, exact fixed settings row and binary predicates.
	 *
	 * @param string $table Pinned table.
	 * @param array  $before Exact original tuple.
	 * @param string $raw Prepared primitive token patch.
	 * @return bool
	 */
	private function write_settings( $table, array $before, $raw ) {
		if ( 'transaction_owned' !== $this->phase || 1 !== $this->writes || ! $this->session( true ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed table from validated private frame, all values bound.
		$stmt = $this->reader->prepare( "UPDATE `{$table}` SET option_value=? WHERE option_id=? AND BINARY option_name=? AND BINARY autoload=? AND BINARY option_value=? LIMIT 1" );
		if ( false === $stmt ) {
			return false;
		}
		try {
			++$this->writes;
			$name = 'alynt_drime_backups_settings';
			$stmt->bind_param( 'sssss', $raw, $before['option_id'], $name, $before['autoload'], $before['value'] );
			return true === $stmt->execute() && 1 === $stmt->affected_rows;
		} finally {
			$stmt->close();
		}
	}
}
