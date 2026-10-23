<?php
/**
 * Separate fixed Tools GET authentication. Never calls the POST-only guard.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No public issuer, method selector or modification of the legacy guard. */
trait Alynt_Drime_Backups_Uploader_Registration_Closed_Entry_Get_Auth {
	/**
	 * Context callbacks cannot replace the original session/identity/transport.
	 *
	 * @return bool
	 */
	private function get_scope() {
		return $this->original_scope() && $this->tools_context() && $this->original_scope();
	}

	/**
	 * Fresh current session/role capability on both sides of small nonce creation.
	 *
	 * Required core user/session reads are not protected settings capture. This
	 * branch is isolated-tested only; genuine core/server evidence is still gated.
	 *
	 * @return bool
	 */
	private function get_authenticated() {
		if ( ! $this->get_scope() ) {
			return false;
		}
		$core   = $this->state['core'];
		$actor  = $this->state['actor'];
		$cookie = $this->state['cookie'];
		$parts  = wp_parse_auth_cookie( $cookie, 'logged_in' );
		$max    = (string) ( PHP_INT_MAX - 3600 );
		$end    = is_array( $parts ) ? ( $parts['expiration'] ?? null ) : null;
		if ( ! $this->get_scope() || ! is_string( $end ) || 1 !== preg_match( '/\A[1-9][0-9]*\z/D', $end ) || strlen( $end ) > strlen( $max ) || ( strlen( $end ) === strlen( $max ) && strcmp( $end, $max ) > 0 ) || (int) $end <= time() || ! is_string( $parts['username'] ?? null ) || '' === $parts['username'] || ! is_string( $parts['token'] ?? null ) || '' === $parts['token'] ) {
			return false;
		}
		clean_user_cache( $actor );
		wp_cache_delete( $actor, 'user_meta' );
		if ( ! $this->get_scope() || wp_validate_auth_cookie( $cookie, 'logged_in' ) !== $actor || ! $this->get_scope() ) {
			return false;
		}
		$had_roles = array_key_exists( 'wp_roles', $GLOBALS );
		$previous  = $GLOBALS['wp_roles'] ?? null;
		try {
			foreach ( array( $core['prefix'] . 'user_roles', 'alloptions', 'notoptions' ) as $key ) {
				wp_cache_delete( $key, 'options' );
			}
			if ( ! $this->get_scope() ) {
				return false;
			}
			$roles = new WP_Roles( $core['blog'] );
			if ( ! $this->get_scope() || true !== $roles->use_db || $roles->role_key !== $core['prefix'] . 'user_roles' || $roles->get_site_id() !== $core['blog'] ) {
				return false;
			}
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporary exact same-blog role view; original restored below on every outcome.
			$GLOBALS['wp_roles'] = $roles;
			$user                = new WP_User( $actor, '', $core['blog'] );
			if ( ! $this->get_scope() || ! $user->exists() || $user->ID !== $actor || $user->user_login !== $parts['username'] ) {
				return false;
			}
			return true === user_can( $user, 'manage_options' ) && true === is_user_logged_in() && $this->get_scope();
		} finally {
			if ( $had_roles ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore only our temporary role view.
				$GLOBALS['wp_roles'] = $previous;
			} else {
				unset( $GLOBALS['wp_roles'] );
			}
		}
	}
}
