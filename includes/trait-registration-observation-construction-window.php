<?php
/**
 * Stack-local role-only fences and real construction lifetime, no native IO.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Private owner helpers; not a defense against hostile same-process PHP. */
trait Alynt_Drime_Backups_Uploader_Registration_Observation_Construction_Window {
	/**
	 * Install own fixed fences before any authentication callback.
	 *
	 * @return array|false Fixed core frame, not a native connection proof.
	 */
	private function open_window() {
		$this->started = hrtime( true );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact method only.
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? null ) || ! function_exists( 'add_filter' ) || ! function_exists( 'remove_filter' ) ) {
			return false;
		}
		$core = $this->frame();
		if ( false === $core || ! $this->pin( $core ) ) {
			return false;
		}
		// frame restricts the interpolated prefix to ASCII letters, digits and '_'.
		$role_sql     = "SELECT option_value FROM {$core['options']} WHERE option_name = '{$core['prefix']}user_roles' LIMIT 1";
		$table        = preg_quote( $core['options'], '/' );
		$this->fences = array(
			'pre_wp_load_alloptions' => static function () {
				return array(); },
			'query'                  => static function ( $sql ) use ( $role_sql, $table ) {
				if ( ! is_string( $sql ) || ( 1 === preg_match( '/(?<![A-Za-z0-9_])`?' . $table . '`?(?![A-Za-z0-9_])/i', $sql ) && $sql !== $role_sql ) ) {
					throw new RuntimeException( 'Observation construction unavailable.' );
				}
				return $sql;
			},
		);
		foreach ( $this->fences as $hook => $callback ) {
			add_filter( $hook, $callback, PHP_INT_MAX );
		}
		return $core;
	}

	/**
	 * Do not replace the pinned object/table frame during callback phases.
	 *
	 * @param array $core Fresh core metadata.
	 * @return bool
	 */
	private function pin( $core ) {
		if ( null === $this->scope ) {
			$this->scope = $core;
		}
		return $this->scope === $core;
	}

	/**
	 * Minimal installation DATA, never retains a write-capable core object.
	 *
	 * @param array $core Guarded metadata.
	 * @return array
	 */
	private function installation( $core ) {
		return array(
			'blog_id'  => $core['blog'],
			'prefix'   => $core['prefix'],
			'database' => DB_NAME,
		);
	}

	/**
	 * Fixed ten-second phase budget; monotonic clock cannot be supplied by callers.
	 *
	 * @return bool
	 */
	private function within_budget() {
		$now = hrtime( true );
		return ! $this->interrupted && null !== $this->started && $now >= $this->started && $now - $this->started < 10000000000;
	}

	/** Restore only own filters; no global removal and no native continuation. */
	private function close_window() {
		foreach ( $this->fences as $hook => $callback ) {
			remove_filter( $hook, $callback, PHP_INT_MAX );
		}
		$this->fences  = array();
		$this->scope   = null;
		$this->started = null;
		$this->active  = false;
		$this->busy    = false;
	}

	/** Erase original private DATA on every consumed read outcome. */
	private function clear_origin() {
		$this->origin      = null;
		$this->origin_data = null;
		$this->used        = array();
	}
}
