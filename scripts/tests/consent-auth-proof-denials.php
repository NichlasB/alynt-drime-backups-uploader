<?php
/** Real cookie/user/session/capability and nonce expiry denials, with no private state reads. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_auth_denial_cases( $p, $adapter, $body, &$receipt, $backend, $before ) {
	$check = function ( $request ) use ( $p, $adapter ) { $reads = $p->state_reads; $calls = $p->sink_calls; alynt_lifecycle_check( is_wp_error( $adapter->consume( 'POST', $request ) ) && $reads === $p->state_reads && $calls === $p->sink_calls, 'Core auth denial reached private state or sink.' ); };
	$original = $_COOKIE[LOGGED_IN_COOKIE]; $last = substr( $original, -1 ); $_COOKIE[LOGGED_IN_COOKIE] = substr( $original, 0, -1 ) . ( '0' === $last ? '1' : '0' ); $check( $body ); $_COOKIE[LOGGED_IN_COOKIE] = $original; $receipt['forged_cookie_denied_before_state'] = true;
	alynt_auth_login( 90000003 ); alynt_lifecycle_check( $p->authorize( $p::SITE ), 'Second core admin session failed.' ); $check( $body ); $receipt['real_nonce_user_binding'] = true;
	alynt_auth_login( 90000001 ); alynt_lifecycle_check( $p->authorize( $p::SITE ), 'New same-user core session failed.' ); $check( $body ); $receipt['real_nonce_session_binding'] = true;
	$i = $p->issue( $adapter ); $request = alynt_auth_body( $i ); $cookie = $_COOKIE[LOGGED_IN_COOKIE]; $session = wp_get_session_token();
	WP_Session_Tokens::get_instance( 90000001 )->destroy( $session ); // Keep only the already-authenticated request's cached current user.
	// Core nonce verification alone can still succeed after session revocation. Admission must not.
	$c = $i['envelope']; $action = 'alynt_drime_backups_consent_fixture_command_v1:' . hash( 'sha256', json_encode( $c ) );
	alynt_lifecycle_check( in_array( wp_verify_nonce( $i['nonce'], $action ), array( 1, 2 ), true ), 'Revocation scenario lost its still-valid core nonce.' ); $check( $request ); $receipt['revoked_session_denied_despite_valid_nonce'] = true;
	alynt_auth_login( 90000001 ); $i = $p->issue( $adapter ); $request = alynt_auth_body( $i ); $user = new WP_User( 90000001 ); $user->set_role( 'subscriber' ); $check( $request ); $user->set_role( 'administrator' ); $receipt['capability_loss_after_issue_denied'] = true;
	$p->authorize( $p::SITE ); $i = $p->issue( $adapter ); $request = alynt_auth_body( $i ); $p->now += 300; $check( $request ); $p->now -= 300; $receipt['short_request_deadline_not_extended_by_core_nonce'] = true;
	$short_life = function () { return 2; }; add_filter( 'nonce_life', $short_life );
	try {
		$i = $p->issue( $adapter ); $request = alynt_auth_body( $i ); $tick = wp_nonce_tick(); $limit = microtime( true ) + 5;
		while ( wp_nonce_tick() <= $tick + 1 && microtime( true ) < $limit ) usleep( 100000 );
		alynt_lifecycle_check( wp_nonce_tick() > $tick + 1, 'Core nonce expiry window failed to advance.' ); $check( $request );
	} finally { remove_filter( 'nonce_life', $short_life ); }
	$receipt['real_core_nonce_expiry_denied'] = true;
	alynt_auth_login( 90000001, time() - 7200 ); $check( $body ); $receipt['expired_core_cookie_and_session_denied'] = true;
	alynt_auth_login( 90000001 ); alynt_lifecycle_check( $p->authorize( $p::SITE ), 'Fresh reauthentication failed.' ); alynt_auth_unchanged( $backend, $before ); $receipt['fresh_core_session_reauthenticates_without_permission'] = true;
}
