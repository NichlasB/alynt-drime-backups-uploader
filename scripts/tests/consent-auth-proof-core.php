<?php
/** Load genuine core auth APIs only after all three database pointers are isolated. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
foreach ( array( 'options', 'users', 'usermeta' ) as $key ) alynt_lifecycle_check( 1 === preg_match( '/^wp_alynt_auth_probe_[a-f0-9]{12}_' . $key . '$/D', $wpdb->$key ), 'Core auth storage is not isolated.' );
foreach ( array( 'l10n.php', 'kses.php', 'blocks.php', 'rest-api.php', 'class-wp-role.php', 'class-wp-roles.php', 'class-wp-user.php', 'capabilities.php', 'user.php', 'class-wp-session-tokens.php', 'class-wp-user-meta-session-tokens.php', 'pluggable.php' ) as $file ) require_once ABSPATH . WPINC . '/' . $file;
foreach ( array( 'set_current_user', 'auth_cookie_malformed', 'auth_cookie_expired', 'auth_cookie_bad_username', 'auth_cookie_bad_hash', 'auth_cookie_valid' ) as $hook ) {
	foreach ( isset( $GLOBALS['wp_filter'][$hook] ) ? $GLOBALS['wp_filter'][$hook]->callbacks : array() as $callbacks ) foreach ( $callbacks as $callback ) alynt_lifecycle_check( is_callable( $callback['function'] ), 'Required core auth hook dependency unavailable.' );
}
foreach ( array( 'siteurl', 'home' ) as $name ) if ( false === get_option( $name ) ) alynt_lifecycle_check( add_option( $name, 'http://plugin-tester.local', '', false ), 'Public fixture origin seed failed.' );
wp_plugin_directory_constants(); wp_cookie_constants(); $_SERVER['REQUEST_METHOD'] = 'POST';
$repo = dirname( __DIR__, 2 );
foreach ( array( 'class-schedule-safety-option-cas', 'trait-schedule-safety-state-validation', 'trait-schedule-safety-state-transitions', 'class-schedule-safety-store', 'trait-cadence-authority-validation', 'trait-cadence-authority-mutations', 'trait-cadence-authority-dispatch', 'class-cadence-authority-store', 'trait-cadence-authority-lifecycle-preparation', 'class-cadence-authority-lifecycle-store', 'trait-consent-ledger-validation', 'trait-consent-ledger-commands', 'trait-consent-ledger-migration', 'trait-consent-ledger-api-boundary', 'class-cadence-consent-ledger', 'trait-closed-consent-provenance-validation', 'class-closed-consent-provenance-store', 'trait-consent-request-validation', 'class-consent-request-adapter' ) as $part ) require_once $repo . '/includes/' . $part . '.php';
require_once __DIR__ . '/lifecycle-proof-processes.php'; require_once __DIR__ . '/consent-ledger-proof-processes.php'; require_once __DIR__ . '/closed-provenance-proof-processes.php';
function alynt_auth_seed_users( array $owned ) {
	global $wpdb; $roles = array( 'administrator' => array( 'name' => 'Fixture administrator', 'capabilities' => array( 'read' => true, 'manage_options' => true ) ), 'subscriber' => array( 'name' => 'Fixture subscriber', 'capabilities' => array( 'read' => true ) ) );
	alynt_lifecycle_check( add_option( $wpdb->get_blog_prefix() . 'user_roles', $roles, '', false ), 'Fixture role seed failed.' ); $GLOBALS['wp_roles'] = new WP_Roles();
	foreach ( array( 90000001 => 'administrator', 90000002 => 'subscriber', 90000003 => 'administrator' ) as $id => $role ) {
		alynt_lifecycle_check( 1 === $wpdb->insert( $owned['users'], array( 'ID' => $id, 'user_login' => 'alynt-auth-fixture-' . $id, 'user_pass' => wp_hash_password( bin2hex( random_bytes( 24 ) ) ), 'user_nicename' => 'alynt-auth-fixture-' . $id, 'user_email' => 'fixture-' . $id . '@example.invalid', 'display_name' => 'Disposable auth fixture', 'user_registered' => gmdate( 'Y-m-d H:i:s' ) ) ), 'Fixture user seed failed.' );
		$user = new WP_User( $id ); $user->set_role( $role ); alynt_lifecycle_check( $user->exists(), 'Fixture user not readable.' );
	}
}
function alynt_auth_login( $id, $expiration = null ) {
	alynt_lifecycle_check( in_array( $id, array( 90000001, 90000002, 90000003 ), true ), 'Non-fixture user refused.' );
	$expires = null === $expiration ? time() + 900 : $expiration; $token = WP_Session_Tokens::get_instance( $id )->create( $expires );
	$_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie( $id, $expires, 'logged_in', $token ); wp_set_current_user( 0 );
	return $token; // Ephemeral in-process only; never output/store raw cookie or token.
}
