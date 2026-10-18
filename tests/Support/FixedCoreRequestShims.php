<?php
/** Called only inside isolated unit processes; never loads WordPress or a database. */
function alynt_test_fixed_core_request_shims() {
	function alynt_test_core_call( $name, $args = array() ) {
		$s =& $GLOBALS['alynt_core_request_test']; $s['calls'][] = array( $name, $args );
		if ( isset( $s['on'][$name] ) ) { $fn = $s['on'][$name]; unset( $s['on'][$name] ); $fn(); }
		if ( ( $s['throw'] ?? '' ) === $name ) throw new RuntimeException( 'Synthetic secret: do not export.' );
	}
	class wpdb {
		public $prefix = 'wp_'; public $base_prefix = 'wp_'; public $options = 'wp_options'; public $users = 'wp_users'; public $usermeta = 'wp_usermeta';
		public function get_blog_prefix( $blog ) { alynt_test_core_call( 'prefix', array( $blog ) ); return $this->prefix; }
		public function query( $sql ) { alynt_test_core_call( 'query' ); throw new RuntimeException( 'No storage is supported.' ); }
		public function __call( $name, $args ) { alynt_test_core_call( 'protected_io', array( $name ) ); throw new RuntimeException( 'No database method is supported.' ); }
	}
	// These traps record attempted IO even when the owner catches the exception.
	function get_option( $name, $default = false ) { alynt_test_core_call( 'protected_io', array( 'get_option', $name ) ); throw new RuntimeException( 'No options reader is supported.' ); }
	function update_option( $name, $value, $autoload = null ) { alynt_test_core_call( 'protected_io', array( 'update_option', $name ) ); throw new RuntimeException( 'No options writer is supported.' ); }
	function add_option( $name, $value = '', $deprecated = '', $autoload = 'yes' ) { alynt_test_core_call( 'protected_io', array( 'add_option', $name ) ); throw new RuntimeException( 'No options writer is supported.' ); }
	function delete_option( $name ) { alynt_test_core_call( 'protected_io', array( 'delete_option', $name ) ); throw new RuntimeException( 'No options writer is supported.' ); }
	class WP_Roles {
		public $use_db = true; public $role_key = 'wp_user_roles'; public $allowed; private $site;
		public function __construct( $site = null ) { alynt_test_core_call( 'roles', array( $site ) ); $this->site = $site; $s = $GLOBALS['alynt_core_request_test']; $this->allowed = $s['role_cache'] ?? $s['role_now']; }
		public function get_site_id() { return $this->site; }
	}
	class WP_User {
		public $ID; public $user_login; public $allowed;
		public function __construct( $id, $name = '', $site = 0 ) { alynt_test_core_call( 'user', array( $id, $site ) ); $s = $GLOBALS['alynt_core_request_test']; $this->ID = $id; $this->user_login = 'local-' . $id; $this->allowed = $GLOBALS['wp_roles']->allowed && ( $s['user_cache'] ?? $s['user_now'] ); }
		public function exists() { return true; }
	}
	function is_multisite() { alynt_test_core_call( 'multisite' ); return $GLOBALS['alynt_core_request_test']['multisite']; }
	function get_current_blog_id() { alynt_test_core_call( 'blog' ); return $GLOBALS['alynt_core_request_test']['blog']; }
	function wp_using_ext_object_cache() { alynt_test_core_call( 'external_cache' ); return $GLOBALS['alynt_core_request_test']['external_cache']; }
	function get_current_user_id() { alynt_test_core_call( 'actor' ); return $GLOBALS['alynt_core_request_test']['actor']; }
	function is_user_logged_in() { alynt_test_core_call( 'logged_in' ); return $GLOBALS['alynt_core_request_test']['logged_in']; }
	function clean_user_cache( $id ) { alynt_test_core_call( 'clean_user', array( $id ) ); }
	function wp_cache_delete( $key, $group = '' ) {
		alynt_test_core_call( 'cache_delete', array( $key, $group ) ); $s =& $GLOBALS['alynt_core_request_test'];
		if ( 'user_meta' === $group ) { $s['session_cache'] = null; $s['user_cache'] = null; }
		if ( 'options' === $group && 'wp_user_roles' === $key ) $s['role_cache'] = null;
		return false; // A missing cache entry is not an eviction failure.
	}
	function wp_parse_auth_cookie( $cookie, $scheme ) {
		alynt_test_core_call( 'parse_cookie', array( $cookie, $scheme ) ); $s = $GLOBALS['alynt_core_request_test'];
		return array( 'username' => $s['username'] ?? 'local-' . $s['cookie_actor'], 'expiration' => $s['expiration'] ?? (string) ( time() + 900 ), 'token' => 'synthetic-session', 'hmac' => 'synthetic-hmac', 'scheme' => $scheme );
	}
	function wp_validate_auth_cookie( $cookie, $scheme ) { alynt_test_core_call( 'validate_cookie', array( $cookie, $scheme ) ); $s = $GLOBALS['alynt_core_request_test']; return ( $s['session_cache'] ?? $s['session_now'] ) ? $s['cookie_actor'] : false; }
	function user_can( $user, $capability ) { alynt_test_core_call( 'capability', array( $user->ID, $capability ) ); return $user->allowed && $GLOBALS['alynt_core_request_test']['filter_allows']; }
	function wp_verify_nonce( $nonce, $action ) { alynt_test_core_call( 'verify_nonce', array( $nonce, $action ) ); $s = $GLOBALS['alynt_core_request_test']; return 'unit-nonce' === $nonce && ( ! isset( $s['expected_action'] ) || $s['expected_action'] === $action ) ? $s['nonce_result'] : false; }
}
