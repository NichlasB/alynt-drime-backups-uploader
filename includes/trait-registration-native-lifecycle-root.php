<?php
/**
 * One closed same-root adapter; final entry is unavailable, API tests isolated.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No imported outcome, actor, origin, clock or native handle. */
trait Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Root {
	/**
	 * Fixed private request lifetime, not a hook/route/worker entry.
	 *
	 * @param string $method Original request method.
	 * @param mixed  $body Original small mutation intent.
	 * @return array|false Non-authorizing observation only.
	 */
	private function lifecycle_root( $method, $body ) {
		if ( $this->busy ) {
			$this->interrupted                = true;
			$this->admission_join_interrupted = true;
			return false;
		}
		if ( $this->root_attempted ) {
			return false;
		}
		$this->root_attempted      = true;
		$this->busy                = true;
		$this->admission_join_busy = true;
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Redacted refusal, restored in finally.
		set_error_handler(
			static function () {
				throw new RuntimeException( 'Native lifecycle unavailable.' );
			}
		);
		try {
			$f = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issuance( $body );
			if ( ! $this->native_entry_available() || 2 !== func_num_args() || 'POST' !== $method || false === $f || 'register_closed' !== $f['purpose'] ) {
				return false;
			}
			$core = $this->open_window();
			if ( false === $core ) {
				return false;
			}
			$this->lifecycle_clock = array(
				'started_ns' => $this->started,
				'now_ns'     => $this->started,
				'utc'        => time(),
			);
			$actor                 = get_current_user_id();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact original cookie remains stack-local.
			$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
			$facts  = $this->lifecycle_profile( $core );
			$own    = false === $facts ? false : $this->prepare_native_lifecycle( $core, $body, $actor, $cookie, $facts );
			if ( false === $own || ! $this->setup_native_writer( $core, $facts ) || ! $this->guard_native_mutation( $core, $body, $own, $actor, $cookie, $facts ) ) {
				return false;
			}
			try {
				$this->admission_transaction( $core, $own[1]['proposal']['command'] );
			} catch ( Throwable $error ) {
				// No manufactured acknowledgement or false-to-commit promotion.
				unset( $error );
			}
			$released  = $this->lifecycle_release();
			$qualified = $this->qualify_admission_outcome( $own[0], array_fill_keys( array( 'auth', 'intent', 'physical', 'unreentered' ), null ) );
			if ( true !== $released || ! is_array( $qualified ) || true !== $qualified['client_release_claimed'] || ! in_array( $qualified['historical_outcome'], array( 'committed', 'uncertain' ), true ) || ! $this->within_budget() ) {
				return false;
			}
			$this->origin_data = $this->admission_origin;
			$this->used        = array_fill_keys( $this->admission_ids, true );
			$this->origin      = Alynt_Drime_Backups_Uploader_Registration_Observation_Origin::prepare( $this->origin_data );
			unset( $own, $qualified );
			$this->erase_lifecycle_writer();
			$this->read_started = hrtime( true );
			return $this->continue_lifecycle( $core, $body, $actor, $cookie, $facts );
		} catch ( Throwable $error ) {
			return false;
		} finally {
			$clean = $this->settle_native_lifecycle();
			$this->erase_lifecycle();
			$this->barrier_auth       = null;
			$this->barrier_auth_facts = null;
			$filters                  = $this->fences;
			$this->fences             = array();
			foreach ( $filters as $hook => $callback ) {
				try {
					remove_filter( $hook, $callback, PHP_INT_MAX );
				} catch ( Throwable $error ) {
					$clean = false;
				}
			}
			$this->scope   = null;
			$this->started = null;
			$this->active  = false;
			$this->busy    = false;
			restore_error_handler();
			if ( ! $clean || $this->interrupted || $this->admission_join_interrupted ) {
				return false;
			}
		}
	}
}
