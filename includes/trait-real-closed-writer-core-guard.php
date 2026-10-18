<?php
/**
 * Fixed core request guards outside locks; no settings/safety reader or writer.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Private owner methods only; no injectable authorization or context ports. */
trait Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Core_Guard {
	/**
	 * Check dependencies without autoloading classes or installing anything.
	 *
	 * @return bool
	 */
	private function dependencies() {
		foreach ( array( 'is_multisite', 'get_current_blog_id', 'wp_using_ext_object_cache', 'get_current_user_id', 'is_user_logged_in', 'clean_user_cache', 'wp_cache_delete', 'wp_parse_auth_cookie', 'wp_validate_auth_cookie', 'user_can', 'wp_verify_nonce' ) as $name ) {
			if ( ! function_exists( $name ) ) {
				return false;
			}
		}
		return class_exists( 'wpdb', false ) && class_exists( 'WP_User', false ) && class_exists( 'WP_Roles', false ) && defined( 'WP_CONTENT_DIR' ) && defined( 'LOGGED_IN_COOKIE' ) && is_string( LOGGED_IN_COOKIE ) && '' !== LOGGED_IN_COOKIE;
	}

	/**
	 * Fixed single-site object/table metadata, not a native connection proof.
	 *
	 * @return array|false
	 */
	private function frame() {
		global $wpdb;
		if ( ! $this->dependencies() || false !== is_multisite() || false !== wp_using_ext_object_cache() || ! empty( $GLOBALS['switched'] ) || ! empty( $GLOBALS['_wp_switched_stack'] ) || ! empty( $GLOBALS['wp_user_roles'] ) || ( defined( 'SHORTINIT' ) && SHORTINIT ) || ( defined( 'WP_CLI' ) && WP_CLI ) || file_exists( WP_CONTENT_DIR . '/db.php' ) || file_exists( WP_CONTENT_DIR . '/object-cache.php' ) || ! $wpdb instanceof wpdb || 'wpdb' !== get_class( $wpdb ) ) {
			return false;
		}
		foreach ( array(
			'wp_roles'     => 'WP_Roles',
			'current_user' => 'WP_User',
		) as $name => $class ) {
			if ( isset( $GLOBALS[ $name ] ) && ( ! is_object( $GLOBALS[ $name ] ) || get_class( $GLOBALS[ $name ] ) !== $class ) ) {
				return false;
			}
		}
		$blog = get_current_blog_id();
		if ( ! is_int( $blog ) || $blog < 1 || $blog >= PHP_INT_MAX || ! is_string( $wpdb->prefix ) || strlen( $wpdb->prefix ) > 64 || 1 !== preg_match( '/^[A-Za-z0-9_]+$/D', $wpdb->prefix ) || $wpdb->base_prefix !== $wpdb->prefix || $wpdb->get_blog_prefix( $blog ) !== $wpdb->prefix || $wpdb->options !== $wpdb->prefix . 'options' || $wpdb->users !== $wpdb->prefix . 'users' || $wpdb->usermeta !== $wpdb->prefix . 'usermeta' || get_current_blog_id() !== $blog || $GLOBALS['wpdb'] !== $wpdb ) {
			return false;
		}
		return array(
			'db'              => $wpdb,
			'blog'            => $blog,
			'prefix'          => $wpdb->prefix,
			'options'         => $wpdb->options,
			'users'           => $wpdb->users,
			'usermeta'        => $wpdb->usermeta,
			'settings_option' => 'alynt_drime_backups_settings',
			'safety_option'   => 'alynt_drime_backups_schedule_safety_state',
		);
	}

	/**
	 * Do not adopt another actor, session, blog or database object mid-request.
	 *
	 * @param array  $frame Fixed metadata.
	 * @param string $cookie Original private cookie, never retained/exported.
	 * @param int    $actor Original local actor.
	 * @return bool
	 */
	private function same_request( array $frame, $cookie, $actor ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Compare exact original cookie/method bytes; no normalization or coercion.
		return $this->frame() === $frame && get_current_user_id() === $actor && ( $_COOKIE[ LOGGED_IN_COOKIE ] ?? null ) === $cookie && 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? null );
	}

	/**
	 * Fresh core session/capability semantics; no identity or permission writes.
	 *
	 * @param array  $frame Current metadata.
	 * @param string $cookie Original private cookie.
	 * @param int    $actor Same local actor, not a posted ID.
	 * @return bool
	 */
	private function authenticate( array $frame, $cookie, $actor ) {
		if ( ! $this->same_request( $frame, $cookie, $actor ) ) {
			return false;
		}
		$parts = wp_parse_auth_cookie( $cookie, 'logged_in' );
		$max   = (string) ( PHP_INT_MAX - 3600 );
		$end   = is_array( $parts ) ? ( $parts['expiration'] ?? null ) : null;
		if ( ! is_string( $end ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $end ) || strlen( $end ) > strlen( $max ) || ( strlen( $end ) === strlen( $max ) && strcmp( $end, $max ) > 0 ) || (int) $end <= time() || ! is_string( $parts['username'] ?? null ) || '' === $parts['username'] || ! is_string( $parts['token'] ?? null ) || '' === $parts['token'] ) {
			return false;
		}
		clean_user_cache( $actor );
		// WordPress 6.0 does not clear user metadata in clean_user_cache().
		wp_cache_delete( $actor, 'user_meta' );
		if ( ! $this->same_request( $frame, $cookie, $actor ) || wp_validate_auth_cookie( $cookie, 'logged_in' ) !== $actor || ! $this->same_request( $frame, $cookie, $actor ) ) {
			return false;
		}
		$had_roles = array_key_exists( 'wp_roles', $GLOBALS );
		$previous  = $GLOBALS['wp_roles'] ?? null;
		try {
			foreach ( array( $frame['prefix'] . 'user_roles', 'alloptions', 'notoptions' ) as $key ) {
				wp_cache_delete( $key, 'options' );
			}
			if ( ! $this->same_request( $frame, $cookie, $actor ) ) {
				return false;
			}
			$roles = new WP_Roles( $frame['blog'] );
			if ( ! $this->same_request( $frame, $cookie, $actor ) || true !== $roles->use_db || $roles->role_key !== $frame['prefix'] . 'user_roles' || $roles->get_site_id() !== $frame['blog'] ) {
				return false;
			}
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Fresh same-site core role view for user_can; restored unconditionally below, no role writes.
			$GLOBALS['wp_roles'] = $roles;
			$user                = new WP_User( $actor, '', $frame['blog'] );
			if ( ! $this->same_request( $frame, $cookie, $actor ) || ! $user->exists() || $user->ID !== $actor || $user->user_login !== $parts['username'] ) {
				return false;
			}
			return true === user_can( $user, 'manage_options' ) && true === is_user_logged_in() && $this->same_request( $frame, $cookie, $actor );
		} finally {
			if ( $had_roles ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the original process-local role view even on denial or throw.
				$GLOBALS['wp_roles'] = $previous;
			} else {
				unset( $GLOBALS['wp_roles'] );
			}
		}
	}

	/**
	 * Real clock only, original independent deadline, never nonce-only admission.
	 *
	 * @param array|null $e Original command, or small issuance inspection.
	 * @return bool
	 */
	private function window( $e ) {
		$now = time();
		if ( $now < 1 || $now > PHP_INT_MAX - ( null === $e ? 3900 : 3600 ) || $now < $this->last_clock ) {
			return false;
		}
		$this->last_clock = $now;
		return null === $e || ( $now >= $e['issued_at'] && $now < $e['expires_at'] );
	}

	/**
	 * Session and current capability checked on both sides of exact core nonce.
	 *
	 * @param array      $frame Latched metadata.
	 * @param string     $nonce Original nonce.
	 * @param string     $action Fixed exact action.
	 * @param array|null $e Original typed envelope.
	 * @return bool
	 */
	private function guard( array $frame, $nonce, $action, $e ) {
		$actor = get_current_user_id();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Pass unchanged bounded cookie bytes to the genuine core parser/validator, as core itself does.
		$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
		if ( ! is_int( $actor ) || $actor < 1 || ! is_string( $cookie ) || '' === $cookie || strlen( $cookie ) > 4096 || ! $this->window( $e ) || ! $this->authenticate( $frame, $cookie, $actor ) || ! in_array( wp_verify_nonce( $nonce, $action ), array( 1, 2 ), true ) ) {
			return false;
		}
		return $this->authenticate( $frame, $cookie, $actor ) && $this->same_request( $frame, $cookie, $actor ) && $this->window( $e );
	}
}
