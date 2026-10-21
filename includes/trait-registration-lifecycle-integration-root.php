<?php
/**
 * Fixed same-stack admission preparation and sealed-outcome-to-read handoff.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** IO boundaries are permanently closed in the final owner, doubled only in tests. */
trait Alynt_Drime_Backups_Uploader_Registration_Lifecycle_Integration_Root {
	/**
	 * No imported candidate, origin, outcome, clock, actor or callback arguments.
	 *
	 * @param string $method Original request method.
	 * @param mixed  $body Original small mutation intent.
	 * @return array|false Private candidate only; never native admission success.
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
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Redacted refusal, previous handler restored in finally.
		set_error_handler(
			static function () {
				throw new RuntimeException( 'Closed lifecycle unavailable.' );
			}
		);
		try {
			$f = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issuance( $body );
			if ( 2 !== func_num_args() || 'POST' !== $method || false === $f || 'register_closed' !== $f['purpose'] ) {
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
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact original cookie stays stack-local, never exported.
			$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
			$facts  = $this->lifecycle_profile( $core );
			$pin    = false === $facts ? false : $this->prepare_lifecycle( $core, $body, $actor, $cookie, $facts );
			if ( false === $pin ) {
				return false;
			}
			try {
				// Return value is NOT history; sealed original capture decides that.
				$this->lifecycle_attempt( $core, $this->proposal['command'] );
			} catch ( Throwable $error ) {
				// Unknown attempted outcome may still qualify after actual release.
				// Do not manufacture acknowledgements, guards or successful history.
				unset( $error );
			}
			$released  = $this->lifecycle_release();
			$qualified = $this->qualify_admission_outcome( $pin, array_fill_keys( array( 'auth', 'intent', 'physical', 'unreentered' ), null ) );
			if ( true !== $released || ! is_array( $qualified ) || true !== $qualified['client_release_claimed'] || ! in_array( $qualified['historical_outcome'], array( 'committed', 'uncertain' ), true ) || ! $this->within_budget() ) {
				return false;
			}
			$this->origin_data = $this->admission_origin;
			$this->used        = array_fill_keys( $this->admission_ids, true );
			$this->origin      = Alynt_Drime_Backups_Uploader_Registration_Observation_Origin::prepare( $this->origin_data );
			// No full pin/proposal/capture/seal remains across read-auth callbacks.
			unset( $pin, $qualified );
			$this->erase_lifecycle_writer();
			$this->read_started = hrtime( true );
			return $this->continue_lifecycle( $core, $body, $actor, $cookie, $facts );
		} catch ( Throwable $error ) {
			return false;
		} finally {
			try {
				$clean = true === $this->lifecycle_release();
				$clean = $clean && $this->within_budget();
			} catch ( Throwable $error ) {
				$clean = false;
			}
			$this->erase_lifecycle();
			$filters      = $this->fences;
			$this->fences = array();
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

	/**
	 * Guard before the fixed capture, construct OWN proposal and retain before DML.
	 *
	 * @param array  $core Original frame.
	 * @param array  $body Small mutation intent.
	 * @param int    $actor Original actor.
	 * @param string $cookie Original cookie.
	 * @param mixed  $facts Original physical facts.
	 * @return array|false Own full pin, consumed before read callbacks.
	 */
	private function prepare_lifecycle( $core, $body, $actor, $cookie, $facts ) {
		$f     = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issuance( $body );
		$issue = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( $core['blog'], 'register_closed' );
		if ( ! $this->guard( $core, $f['nonce'], $issue, null ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->lifecycle_physical( $core, $facts ) || ! $this->within_budget() ) {
			return false;
		}
		$captured = $this->lifecycle_capture( $core );
		if ( false === $captured || ! $this->guard( $core, $f['nonce'], $issue, null ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->lifecycle_physical( $core, $facts ) || ! $this->within_budget() ) {
			return false;
		}
		$built = $this->construct_registration( $captured, $body );
		if ( false === $built || ! $this->same_request( $core, $cookie, $actor ) || ! $this->lifecycle_physical( $core, $facts ) || ! $this->within_budget() ) {
			return false;
		}
		$p = $built['proposal'];
		$c = array(
			'blog_id'  => $core['blog'],
			'prefix'   => $core['prefix'],
			'database' => DB_NAME,
			'actor_id' => $actor,
		);
		if ( ! $this->guard( $core, $built['nonce'], $built['action'], $p['command'] ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->prepare_candidate( $captured, $p, $c, time() ) ) {
			return false;
		}
		$pin = $this->retain_admission_origin( $core, $captured, $actor, $this->started );
		if ( false === $pin || ! $this->guard( $core, $f['nonce'], $issue, $p['command'] ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->guard( $core, $built['nonce'], $built['action'], $p['command'] ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->lifecycle_physical( $core, $facts ) || ! $this->within_budget() || ! $this->admission_origin_same( $pin ) ) {
			return false;
		}
		return $pin;
	}
}
