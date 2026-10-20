<?php
/**
 * Shared fixed standalone/joined root; never nested consuming owners.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Only fixed private entries select retention; no public strategy/provider port. */
trait Alynt_Drime_Backups_Uploader_Registration_Admission_Root {
	/**
	 * Original small issuance -> private fresh construction -> locked ledger commit.
	 * An unavailable result may follow an acknowledged historical commit.
	 *
	 * @param string $method Exact original POST.
	 * @param mixed  $body Original small issuance form, never a supplied blueprint.
	 * @param bool   $retained Fixed private entry choice, never a public mode input.
	 * @return bool Private historical decision; no native handle or raw receipt.
	 */
	private function admission_root( $method, $body, $retained ) {
		if ( $this->attempted ) {
			if ( $this->admission_join_busy ) {
				$this->admission_join_interrupted = true;
				$this->clear_admission_origin();
			}
			return false;
		}
		$this->attempted           = true;
		$this->active              = true;
		$this->started             = hrtime( true );
		$fences                    = array();
		$root_start                = $this->started;
		$origin_pin                = null;
		$this->admission_join_busy = $retained;
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Stack-local warning-to-refusal policy, always restored; no logging.
		set_error_handler(
			static function () {
				throw new RuntimeException( 'Closed registration admission unavailable.' );
			}
		);
		try {
			$f = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issuance( $body );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact unchanged method only.
			if ( 3 !== func_num_args() || ! is_bool( $retained ) || 'POST' !== $method || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? null ) || false === $f || 'register_closed' !== $f['purpose'] ) {
				return false;
			}
			$core = $this->frame();
			if ( false === $core || ! $this->pin( $core ) || ! $this->profile() ) {
				return false;
			}
			// Role-only core fallback and independent query fence across ALL callbacks.
			$fences['pre_wp_load_alloptions'] = static function () {
				return array();
			};
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Pinned core table; fixed role name bound separately.
			$role_sql        = $core['db']->prepare( "SELECT option_value FROM {$core['options']} WHERE option_name = %s LIMIT 1", $core['prefix'] . 'user_roles' );
			$table           = preg_quote( $core['options'], '/' );
			$fences['query'] = static function ( $sql ) use ( $role_sql, $table ) {
				if ( 1 === preg_match( '/\b(?:FROM|JOIN|UPDATE|INTO|TABLE)\s+`?' . $table . '`?(?=\s|$)/i', $sql ) && $sql !== $role_sql ) {
					throw new RuntimeException( 'Registration option access unavailable.' );
				}
				return $sql;
			};
			foreach ( $fences as $hook => $callback ) {
				add_filter( $hook, $callback, PHP_INT_MAX );
			}
			$actor = get_current_user_id();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Private original cookie compared only, never persisted or exported.
			$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
			$auth   = $core['db']->dbh;
			$facts  = $this->native_idle( $auth );
			$issue  = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( $core['blog'], 'register_closed' );
			if ( false === $facts || ! $this->guard( $core, $f['nonce'], $issue, null ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->auth_same( $core, $auth, $facts ) || ! $this->budget( null ) || ! $this->open_reader( $auth, $facts ) ) {
				return false;
			}
			$captured = $this->admission_capture( $core );
			if ( false === $captured || ! $this->guard( $core, $f['nonce'], $issue, null ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->auth_same( $core, $auth, $facts ) || ! $this->budget( null ) || true !== $this->reader->close() ) {
				return false;
			}
			$this->reader        = null;
			$this->read_physical = null;
			// No callbacks in this latch transfer; the outer attempted flag stays set.
			$this->active = false;
			$built        = $this->construct_registration( $captured, $body );
			if ( ! is_array( $built ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->auth_same( $core, $auth, $facts ) || ! $this->budget( null ) ) {
				return false;
			}
			$form          = array_map( 'strval', $built['proposal']['command'] );
			$form['nonce'] = $built['nonce'];
			$parsed        = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $form );
			$context       = array(
				'blog_id'  => $core['blog'],
				'prefix'   => $core['prefix'],
				'database' => DB_NAME,
				'actor_id' => $actor,
			);
			if ( $retained && ( false === $parsed || ! $this->guard( $core, $parsed['nonce'], $parsed['action'], $parsed['envelope'] ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->auth_same( $core, $auth, $facts ) || ! $this->budget( $parsed['envelope'] ) ) ) {
				return false;
			}
			if ( false === $parsed || $parsed['envelope'] !== $built['proposal']['command'] || $parsed['action'] !== $built['action'] || $parsed['nonce'] !== $built['nonce'] || ! $this->prepare_candidate( $captured, $built['proposal'], $context, time() ) ) {
				return false;
			}
			if ( $retained ) {
				$origin_pin = $this->retain_admission_origin( $core, $captured, $actor, $root_start );
				if ( false === $origin_pin ) {
					return false;
				}
			}
			if ( ! $this->open_writer( $auth, $facts ) || ! $this->metadata( $core['options'] ) ) {
				return false;
			}
			$e = $parsed['envelope'];
			if ( ! $this->auth_same( $core, $auth, $facts ) || ! $this->guard( $core, $f['nonce'], $issue, $e ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->auth_same( $core, $auth, $facts ) || ! $this->guard( $core, $parsed['nonce'], $parsed['action'], $e ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->auth_same( $core, $auth, $facts ) || ! $this->budget( $e ) || ( $retained && ! $this->admission_origin_same( $origin_pin ) ) || ! $this->admission_transaction( $core, $e ) ) {
				return false;
			}
			$this->finish(); // All locks/owned handles released before WordPress callbacks.
			return ( ! $retained || $this->admission_origin_same( $origin_pin ) ) && 'committed' === $this->phase && $this->budget( $e ) && $this->auth_same( $core, $auth, $facts ) && $this->guard( $core, $f['nonce'], $issue, $e ) && $this->same_request( $core, $cookie, $actor ) && $this->auth_same( $core, $auth, $facts ) && $this->guard( $core, $parsed['nonce'], $parsed['action'], $e ) && $this->same_request( $core, $cookie, $actor ) && $this->auth_same( $core, $auth, $facts ) && $this->budget( $e ) && ( ! $retained || $this->admission_origin_same( $origin_pin ) );
		} catch ( Throwable $error ) {
			return false;
		} finally {
			$this->finish();
			$this->clear_native_outcome();
			$this->clear_admission_origin();
			$this->admission_join_busy = false;
			$this->active              = false;
			$this->consumed            = true;
			$this->scope               = null;
			$this->read_physical       = null;
			$this->before              = null;
			$this->proposal            = null;
			$this->context             = null;
			$this->after               = null;
			$this->recorded_at         = null;
			foreach ( $fences as $hook => $callback ) {
				remove_filter( $hook, $callback, PHP_INT_MAX );
			}
			restore_error_handler();
		}
	}

	/**
	 * Original ten-second acceptance budget, not hard IO cancellation.
	 *
	 * @param array|null $e Original typed command or initial issuance.
	 * @return bool Original monotonic and UTC deadline still valid.
	 */
	private function budget( $e ) {
		$tick = hrtime( true );
		return ! $this->admission_join_interrupted && $tick >= $this->started && $tick - $this->started < 10000000000 && $this->window( $e );
	}
}
