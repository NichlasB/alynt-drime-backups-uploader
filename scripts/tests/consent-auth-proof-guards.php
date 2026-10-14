<?php
/** Actual core permission/session changes at a scoped request guard; no effect or consent sink. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_auth_guard_cases( $p, $adapter, &$receipt, $backend, $before, $closed, $seed ) {
	global $wpdb; $p->mode = 'guard_only';
	foreach ( array( 'revoke_session', 'capability_during_context', 'deadline', 'changed_epoch' ) as $case ) {
		alynt_auth_login( 90000001 ); $p->authorize( $p::SITE ); $body = alynt_auth_body( $p->issue( $adapter ) ); $original_time = $p->now;
		if ( 'revoke_session' === $case ) $p->before_guard = function () { WP_Session_Tokens::get_instance( 90000001 )->destroy( wp_get_session_token() ); };
		elseif ( 'capability_during_context' === $case ) $p->on_context = function () { $user = new WP_User( 90000001 ); $user->set_role( 'subscriber' ); };
		elseif ( 'deadline' === $case ) $p->before_guard = function () use ( $p ) { $p->now += 300; };
		else $p->on_context = function () use ( $p, $seed ) {
			$old = $seed; $old['generation'] += 10; $old['schedule_revision'] += 10; $old['lifecycle']['permission_generation'] = 10; alynt_auth_replace_state( $old );
			alynt_lifecycle_check( is_array( $p->model->prepare_closed( str_repeat( 'e', 64 ), 10, function () { return true; } ) ), 'Closed fixture epoch rotation failed.' );
		};
		alynt_lifecycle_check( alynt_lifecycle_error( $adapter->consume( 'POST', $body ), 'consent_request_unresolved' ), 'Guard loss acknowledged a command.' );
		$guard = $p->retained_guard; $queries = $wpdb->num_queries; alynt_lifecycle_check( false === $guard() && $queries === $wpdb->num_queries, 'Retained guard remained usable after request.' );
		if ( 'capability_during_context' === $case ) { $user = new WP_User( 90000001 ); $user->set_role( 'administrator' ); }
		if ( 'changed_epoch' === $case ) alynt_auth_replace_state( $closed );
		$p->now = $original_time; alynt_auth_unchanged( $backend, $before ); $receipt['scoped_guard_' . $case . '_closed'] = true;
	}
	$p->mode = 'closed'; alynt_auth_login( 90000001 ); $p->authorize( $p::SITE );
}
