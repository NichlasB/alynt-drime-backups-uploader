<?php
/**
 * Dormant fixed guarded construction-to-candidate integration, no native IO.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Same-owner private transfer only; actual native barrier remains disabled. */
trait Alynt_Drime_Backups_Uploader_Registration_Observation_Guarded_Handoff {
	/**
	 * Private test-only candidate bytes are not a protected native observation.
	 * No origin, provider, clock, blueprint or handle can be supplied by a caller.
	 *
	 * @param mixed $captured Complete current synthetic frame in isolated API tests.
	 * @param mixed $body Original small read intent, not a full imported command.
	 * @return array|false Candidate only; every native/execution flag stays false.
	 */
	private function observe_candidate( $captured, $body ) {
		if ( $this->busy ) {
			$this->interrupted = true;
			return false;
		}
		if ( $this->observation_attempted ) {
			return false;
		}
		$this->observation_attempted = true;
		$this->busy                  = true;
		$outer                       = hrtime( true );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Fail closed on diagnostics, restoring the previous handler in finally.
		set_error_handler(
			static function () {
				throw new RuntimeException( 'Observation handoff unavailable.' );
			}
		);
		try {
			if ( 2 !== func_num_args() ) {
				return false;
			}
			$built = $this->build_observation( $body );
			if ( false === $built ) {
				return false;
			}
			// Derive exclusively from this owner's retained facts, before cleanup.
			$data = $this->origin_data;
			$ids  = array_keys( $this->used );
			if ( ! $this->stage( $data, $body, $built['form'], $this->handoff_clock( $outer ), $ids ) ) {
				return false;
			}
			$this->clear_origin();
			if ( ! $this->renew_observation( $built, $outer ) || ! $this->claim( $data, $body, $built['form'], $this->handoff_clock( $outer ), $ids ) ) {
				return false;
			}
			$result = $this->finish( $captured, $body, $built['form'], $this->handoff_clock( $outer ) );
			// No positive result survives fresh guards after consumed candidate parsing.
			return is_array( $result ) && $this->renew_observation( $built, $outer ) ? $result : false;
		} catch ( Throwable $error ) {
			return false;
		} finally {
			$this->attempted = true;
			$this->claimed   = true;
			$this->finished  = true;
			$this->staged    = null;
			$this->inflight  = null;
			$this->clear_origin();
			$this->close_window();
			restore_error_handler();
		}
	}

	/**
	 * Both nonce domains and original core/actor/cookie are checked independently.
	 * The borrowed core frame is stack-local; it is never handed to the DATA model.
	 *
	 * @param array $built This invocation's private helper result, never imported.
	 * @param int   $outer Original outer monotonic start, never reset by inner work.
	 * @return bool No auth-at-commit or physical-session claim.
	 */
	private function renew_observation( $built, $outer ) {
		$core = $built['core'];
		$e    = $built['blueprint']['envelope'];
		return $this->guard( $core, $built['intent']['nonce'], $built['issue_action'], $e ) && $this->same_request( $core, $built['cookie'], $built['actor'] ) && $this->guard( $core, $built['blueprint']['nonce'], $built['blueprint']['action'], $e ) && $this->same_request( $core, $built['cookie'], $built['actor'] ) && $this->started === $built['window_started'] && $this->within_budget() && self::clock( $this->handoff_clock( $outer ) );
	}

	/**
	 * Real observations for this fixed private path; no injectable clock port.
	 *
	 * @param int $outer Original stack-local monotonic start.
	 * @return array Primitive DATA only, not a lease or native session.
	 */
	private function handoff_clock( $outer ) {
		return array(
			'started_ns' => $outer,
			'now_ns'     => hrtime( true ),
			'utc'        => time(),
		);
	}
}
