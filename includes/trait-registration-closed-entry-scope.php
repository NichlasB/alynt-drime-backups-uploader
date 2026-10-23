<?php
/**
 * Original root/identity, role-only fences and owned cleanup for dormant entry.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Private stack scope only; no persisted continuation or native capability. */
trait Alynt_Drime_Backups_Uploader_Registration_Closed_Entry_Scope {
	/**
	 * Original monotonic root precedes all input acquisition/dependencies.
	 *
	 * @return bool
	 */
	private function start() {
		$this->state         = array(
			'start' => hrtime( true ),
			'last'  => time(),
		);
		$this->error_handler = static function () {
			throw new RuntimeException( 'Closed entry unavailable.' );
		};
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Owned fail-closed IO warning boundary, not logging/debugging; restored below.
		set_error_handler( $this->error_handler );
		return $this->within_root();
	}

	/**
	 * The initial phase remains under both its 10s and the original 20s root.
	 *
	 * @return bool
	 */
	private function within_root() {
		$mono = hrtime( true );
		$utc  = time();
		if ( self::$poisoned || ! is_int( $mono ) || ! is_int( $this->state['start'] ?? null ) || $mono < ( $this->state['last_mono'] ?? $this->state['start'] ) || $mono - $this->state['start'] >= 20000000000 || $mono - $this->state['start'] >= 10000000000 || ! is_int( $utc ) || $utc < 1 || $utc > PHP_INT_MAX - 3900 || $utc < ( $this->state['last'] ?? PHP_INT_MAX ) ) {
			return false;
		}
		$this->state['last']      = $utc;
		$this->state['last_mono'] = $mono;
		return true;
	}

	/**
	 * Acquire once; subsequent guards compare, never repin a changed valid actor.
	 *
	 * @return bool
	 */
	private function pin_original() {
		$core  = $this->frame();
		$actor = false === $core ? null : get_current_user_id();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Preserve original bounded cookie for core validation, never normalize/export.
		$cookie = defined( 'LOGGED_IN_COOKIE' ) ? ( $_COOKIE[ LOGGED_IN_COOKIE ] ?? null ) : null;
		if ( isset( $this->state['core'] ) || false === $core || ! is_int( $actor ) || $actor < 1 || ! is_string( $cookie ) || '' === $cookie || strlen( $cookie ) > 4096 ) {
			return false;
		}
		$this->state['core']   = $core;
		$this->state['actor']  = $actor;
		$this->state['cookie'] = $cookie;
		return $this->original_scope();
	}

	/**
	 * Method-independent original identity only, not a replacement POST guard.
	 *
	 * @return bool
	 */
	private function original_scope() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Byte equality, no authorization from caller-supplied values.
		return $this->within_root() && $this->frame() === ( $this->state['core'] ?? null ) && get_current_user_id() === ( $this->state['actor'] ?? null ) && ( $_COOKIE[ LOGGED_IN_COOKIE ] ?? null ) === ( $this->state['cookie'] ?? null ) && $this->within_root();
	}

	/**
	 * Separate fixed POST scope and original transport after callbacks.
	 *
	 * @return bool
	 */
	private function post_scope() {
		return $this->reader_scope() && $this->original_scope() && $this->reader_scope();
	}

	/**
	 * Existing POST authentication, with the outer original pin on both sides.
	 *
	 * @return bool
	 */
	private function post_authenticated() {
		return $this->post_scope() && $this->authenticate( $this->state['core'], $this->state['cookie'], $this->state['actor'] ) && $this->post_scope();
	}

	/**
	 * Install only owned fixed read fences before callback-bearing authentication.
	 *
	 * @return bool
	 */
	private function open_fences() {
		if ( ! function_exists( 'add_filter' ) || ! function_exists( 'remove_filter' ) || ! $this->original_scope() ) {
			return false;
		}
		$core = $this->state['core'];
		$role = "SELECT option_value FROM {$core['options']} WHERE option_name = '{$core['prefix']}user_roles' LIMIT 1";
		// Deliberately narrow core read shapes; genuine compatibility is unproved.
		$users = preg_quote( $core['users'], '/' );
		$meta  = preg_quote( $core['usermeta'], '/' );
		$actor = $this->state['actor'];
		// Cookie syntax narrows read allowance only; it cannot authenticate anyone.
		$parts        = explode( '|', $this->state['cookie'] );
		$login        = 4 === count( $parts ) && 1 === preg_match( '/\A[A-Za-z0-9_.@-]{1,60}\z/D', $parts[0] ) ? preg_quote( $parts[0], '/' ) : '(?!)';
		$reads        = '/\ASELECT (?:\* FROM ' . $users . " WHERE (?:ID = '?" . $actor . "'?|user_login = '" . $login . "') LIMIT 1|user_id, meta_key, meta_value FROM " . $meta . ' WHERE user_id IN \(' . $actor . '\) ORDER BY umeta_id ASC)\z/D';
		$this->fences = array(
			'pre_wp_load_alloptions' => static function () {
				return array();
			},
			'query'                  => static function ( $sql ) use ( $role, $reads ) {
				if ( ! is_string( $sql ) || strlen( $sql ) > 512 || ( $sql !== $role && 1 !== preg_match( $reads, $sql ) ) ) {
					throw new RuntimeException( 'Closed entry unavailable.' );
				}
				return $sql;
			},
		);
		foreach ( $this->fences as $hook => $callback ) {
			add_filter( $hook, $callback, PHP_INT_MAX );
		}
		return $this->original_scope();
	}

	/**
	 * Unknown cleanup overrides the checkpoint; preserve foreign callbacks.
	 *
	 * @return bool
	 */
	private function close() {
		$clean = ! self::$poisoned;
		foreach ( $this->fences as $hook => $callback ) {
			try {
				if ( true !== remove_filter( $hook, $callback, PHP_INT_MAX ) ) {
					$clean = false;
				}
			} catch ( Throwable $error ) {
				$clean = false;
			}
		}
		if ( null !== $this->error_handler ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Inspect/restore only our handler; never remove a foreign replacement.
			$current = set_error_handler( $this->error_handler );
			restore_error_handler();
			if ( $current !== $this->error_handler ) {
				$clean = false;
			} else {
				restore_error_handler();
			}
		}
		$clean               = $clean && ! self::$poisoned && ( array() === $this->state || $this->within_root() );
		$this->state         = array();
		$this->fences        = array();
		$this->error_handler = null;
		$this->last_clock    = 0;
		return $clean;
	}
}
