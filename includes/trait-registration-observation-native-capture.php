<?php
/**
 * Dormant fixed guarded capture to own origin retention; no admission or observer.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Same fixed capture profile as registration, not an atomic storage snapshot. */
trait Alynt_Drime_Backups_Uploader_Registration_Observation_Native_Capture {
	/**
	 * Only this owner opens/closes this read-only connection.
	 *
	 * @var mysqli|null
	 */
	private $reader;
	/**
	 * Fixed twenty-row projection ceiling across both sweeps.
	 *
	 * @var int
	 */
	private $protected_reads = 0;
	/**
	 * Native capture attempt cannot retry or import a prior frame.
	 *
	 * @var bool
	 */
	private $capture_attempted = false;
	/**
	 * Genuine read construction is also once-attempted, including early refusal.
	 *
	 * @var bool
	 */
	private $native_read_attempted = false;
	/**
	 * Original capture-to-retention monotonic start, not reset by inner guards.
	 *
	 * @var int|float|null
	 */
	private $native_started;

	/**
	 * Read a fixed private candidate AFTER auth, then construct and retain OWN origin.
	 * No raw frame, external blueprint, origin object or callback is accepted.
	 *
	 * @param string $method Exact POST.
	 * @param mixed  $body Original small mutation issuance form.
	 * @return bool Private retention only; no attempted write or receipt certificate.
	 */
	private function retain_native_registration( $method, $body ) {
		if ( $this->busy ) {
			$this->interrupted = true;
			return false;
		}
		if ( $this->capture_attempted || $this->retention_attempted || $this->observation_attempted ) {
			return false;
		}
		$this->capture_attempted = true;
		$this->busy              = true;
		$this->native_started    = hrtime( true );
		$ok                      = false;
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Fail closed on warnings, prior handler restored unconditionally.
		set_error_handler(
			static function () {
				throw new RuntimeException( 'Origin capture unavailable.' );
			}
		);
		try {
			$f = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issuance( $body );
			if ( 2 !== func_num_args() || 'POST' !== $method || false === $f || 'register_closed' !== $f['purpose'] ) {
				return false;
			}
			$core = $this->open_window();
			if ( false === $core || ! $this->profile() ) {
				return false;
			}
			$actor = get_current_user_id();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Original cookie remains stack-local and is never exported.
			$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
			$auth   = $core['db']->dbh;
			$before = $this->native_idle( $auth );
			$action = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( $core['blog'], 'register_closed' );
			if ( false === $before || ! $this->guard( $core, $f['nonce'], $action, null ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->auth_same( $core, $auth, $before ) || ! $this->native_capture_budget() ) {
				return false;
			}
			if ( ! $this->open_reader( $auth, $before ) || ! $this->table_supported( $core['options'] ) || ! $this->auth_same( $core, $auth, $before ) ) {
				return false;
			}
			$session = $this->native_idle( $this->reader );
			$sweeps  = array();
			for ( $pass = 0; $pass < 2; ++$pass ) {
				$rows = array();
				foreach ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) as $role ) {
					if ( false === $session || '1' !== $session['tx_ro'] || ! $this->native_capture_budget() || $this->native_idle( $this->reader ) !== $session ) {
						return false;
					}
					$row = $this->scan_row( $core['options'], $role );
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
			if ( $this->native_idle( $this->reader ) !== $session || ! Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::compare( $sweeps[0], $sweeps[1] )['same_candidate'] || ! $this->guard( $core, $f['nonce'], $action, null ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->auth_same( $core, $auth, $before ) || ! $this->native_capture_budget() || true !== $this->reader->close() ) {
				return false;
			}
			$this->reader = null;
			$this->close_window();
			// Fixed same-owner call, not a returned frame or user-selected continuation.
			$retained   = $this->retain_registration( $sweeps[0], $body );
			$this->busy = true;
			$ok         = $retained && ! $this->observation_attempted && null !== $this->origin && $this->same_request( $core, $cookie, $actor ) && $this->auth_same( $core, $auth, $before ) && $this->native_capture_budget();
			return $ok;
		} catch ( Throwable $error ) {
			return false;
		} finally {
			$this->retention_attempted = true;
			$this->consumed            = true;
			$owned                     = $this->reader;
			$this->reader              = null;
			try {
				if ( $owned instanceof mysqli ) {
					$owned->close();
				}
			} catch ( Throwable $error ) {
				$ok = false;
			} finally {
				if ( ! $ok ) {
					$this->clear_origin();
				}
				$this->native_started = null;
				$this->close_window();
				restore_error_handler();
			}
		}
	}

	/**
	 * Original capture-to-retention budget, not a hard bound on blocking IO.
	 *
	 * @return bool
	 */
	private function native_capture_budget() {
		$now = hrtime( true );
		return ! $this->interrupted && null !== $this->native_started && $now >= $this->native_started && $now - $this->native_started < 10000000000 && $this->window( null );
	}

	/**
	 * Guard native authentication-session identity around the existing read builder.
	 * Metadata SELECTs only here; no protected rows, observer transaction or writer.
	 *
	 * @param mixed $body Original small read intent.
	 * @return array|false Private non-authorizing read blueprint.
	 */
	private function construct_native_observation( $body ) {
		if ( $this->busy ) {
			$this->interrupted = true;
			return false;
		}
		if ( $this->native_read_attempted || $this->observation_attempted ) {
			return false;
		}
		$this->native_read_attempted = true;
		$this->busy                  = true;
		$start                       = hrtime( true );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Fail closed on native diagnostics and restore previous handler.
		set_error_handler(
			static function () {
				throw new RuntimeException( 'Observation request unavailable.' );
			}
		);
		try {
			$i = Alynt_Drime_Backups_Uploader_Registration_Observation_Request::intent( $body );
			if ( 1 !== func_num_args() || ! $this->capture_attempted || null === $this->origin || false === $i || $i['observed_command_id'] !== $this->origin_data['mutation']['command_id'] ) {
				return false;
			}
			$core = $this->open_window();
			if ( false === $core || ! $this->profile() || $this->installation( $core ) !== $this->origin_data['installation'] ) {
				return false;
			}
			$actor = get_current_user_id();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact original cookie retained only within this stack.
			$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
			$auth   = $core['db']->dbh;
			$before = $this->native_idle( $auth );
			if ( false === $before || ! $this->within_budget() ) {
				return false;
			}
			$this->close_window();
			$r          = $this->construct_observation( $body );
			$this->busy = true;
			$now        = hrtime( true );
			return is_array( $r ) && ! $this->interrupted && $now >= $start && $now - $start < 10000000000 && $this->same_request( $core, $cookie, $actor ) && $this->auth_same( $core, $auth, $before ) ? $r : false;
		} catch ( Throwable $error ) {
			return false;
		} finally {
			$this->observation_attempted = true;
			$this->clear_origin();
			$this->close_window();
			restore_error_handler();
		}
	}
}
