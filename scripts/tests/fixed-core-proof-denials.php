<?php
/** Genuine revoked/stale authority and changes during core nonce verification. */
function alynt_core_denials() {
    global $wpdb;
    alynt_core_login(); list( $form, $e, $action ) = alynt_core_form();
    $original = $_COOKIE[LOGGED_IN_COOKIE]; $_COOKIE[LOGGED_IN_COOKIE] = substr( $original, 0, -1 ) . ( '0' === substr( $original, -1 ) ? '1' : '0' );
    alynt_core_assert( false === alynt_core_decision( $form['nonce'], $action, $e ), 'Forged cookie admitted.' ); $_COOKIE[LOGGED_IN_COOKIE] = $original;
    alynt_core_login( 90000003 ); alynt_core_assert( false === alynt_core_decision( $form['nonce'], $action, $e ), 'Other administrator nonce admitted.' );
    alynt_core_login(); alynt_core_assert( false === alynt_core_decision( $form['nonce'], $action, $e ), 'Other same-user session nonce admitted.' );
    alynt_core_login( 90000002 ); $subscriber_nonce = wp_create_nonce( $action );
    alynt_core_assert( false === alynt_core_decision( $subscriber_nonce, $action, $e ), 'Subscriber admitted with genuine own nonce.' );
    alynt_core_login( 90000001, time() - 7200 );
    alynt_core_assert( false === alynt_core_decision( wp_create_nonce( $action ), $action ), 'Expired genuine cookie admitted.' );
    alynt_core_done( 'forged_expired_wrong_actor_session_and_capability_denied' );
    foreach ( array( 'session', 'capability', 'role', 'user' ) as $change ) {
        alynt_core_login(); list( $body, $intent, $bound ) = alynt_core_form();
        alynt_core_assert( true === alynt_core_decision( $body['nonce'], $bound, $intent ), 'Freshness case lacks positive control.' );
        // Warm native caches/current_user before a raw fixture-only SQL change.
        get_user_meta( 90000001 ); get_option( $wpdb->prefix . 'user_roles' );
        $restore = alynt_core_change( $change );
        try {
            alynt_core_assert( in_array( wp_verify_nonce( $body['nonce'], $bound ), array( 1, 2 ), true ), 'Revocation proof needs a still-valid nonce and stale current user.' );
            alynt_core_assert( false === alynt_core_decision( $body['nonce'], $bound, $intent ), 'Stale native ' . $change . ' authority admitted.' );
        } finally { $restore(); wp_cache_flush(); $GLOBALS['wp_roles'] = new WP_Roles(); }
    }
    alynt_core_done( 'raw_session_user_capability_role_revocation_beats_warm_core_caches' );
    foreach ( array( 'session', 'capability', 'role', 'user', 'actor', 'cookie', 'method', 'table', 'blog' ) as $change ) {
        alynt_core_login(); list( $body, $intent, $bound ) = alynt_core_form();
        alynt_core_assert( true === alynt_core_decision( $body['nonce'], $bound, $intent ), 'Post-nonce case lacks positive control.' );
        $restore = null; $fired = 0;
        $during_nonce = function ( $life ) use ( $change, &$restore, &$fired ) { $fired++; if ( 1 === $fired ) $restore = alynt_core_change( $change ); return $life; };
        add_filter( 'nonce_life', $during_nonce );
        try { alynt_core_assert( false === alynt_core_decision( $body['nonce'], $bound, $intent ), 'Post-nonce ' . $change . ' loss admitted.' ); alynt_core_assert( 1 === $fired, 'Exact core nonce boundary not reached.' ); }
        finally { remove_filter( 'nonce_life', $during_nonce ); if ( $restore ) $restore(); wp_cache_flush(); $GLOBALS['wp_roles'] = new WP_Roles(); }
    }
    alynt_core_done( 'authority_actor_cookie_method_table_blog_loss_inside_real_nonce_denied' );
    alynt_core_login(); list( $body, $intent, $bound ) = alynt_core_form();
    $deny = function ( $all ) { $all['manage_options'] = false; return $all; }; add_filter( 'user_has_cap', $deny, PHP_INT_MAX );
    try { alynt_core_assert( false === alynt_core_decision( $body['nonce'], $bound, $intent ), 'Native capability filter denial bypassed.' ); }
    finally { remove_filter( 'user_has_cap', $deny, PHP_INT_MAX ); }
    alynt_core_assert( true === alynt_core_decision( $body['nonce'], $bound, $intent ), 'Capability filter removal did not restore the positive control.' );
    alynt_core_done( 'genuine_capability_filter_denial_and_recovery' );
}
function alynt_core_change( $change ) {
    global $wpdb;
    if ( in_array( $change, array( 'session', 'capability' ), true ) ) {
        $key = 'session' === $change ? 'session_tokens' : $wpdb->prefix . 'capabilities';
        $raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id=90000001 AND meta_key=%s", $key ) );
        alynt_core_assert( is_string( $raw ), 'Owned metadata must exist.' );
        alynt_core_mutate( function () use ( $wpdb, $key ) { alynt_core_assert( 1 === $wpdb->update( $wpdb->usermeta, array( 'meta_value' => 'a:0:{}' ), array( 'user_id' => 90000001, 'meta_key' => $key ) ), 'Owned metadata revocation failed.' ); } );
        return function () use ( $wpdb, $key, $raw ) { alynt_core_mutate( function () use ( $wpdb, $key, $raw ) { alynt_core_assert( 1 === $wpdb->update( $wpdb->usermeta, array( 'meta_value' => $raw ), array( 'user_id' => 90000001, 'meta_key' => $key ) ), 'Owned metadata restore failed.' ); } ); };
    }
    if ( 'role' === $change ) {
        $name = $wpdb->prefix . 'user_roles'; $raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $name ) );
        $roles = unserialize( $raw, array( 'allowed_classes' => false ) ); unset( $roles['administrator']['capabilities']['manage_options'] );
        alynt_core_mutate( function () use ( $wpdb, $name, $roles ) { alynt_core_assert( 1 === $wpdb->update( $wpdb->options, array( 'option_value' => serialize( $roles ) ), array( 'option_name' => $name ) ), 'Owned role revocation failed.' ); } );
        return function () use ( $wpdb, $name, $raw ) { alynt_core_mutate( function () use ( $wpdb, $name, $raw ) { alynt_core_assert( 1 === $wpdb->update( $wpdb->options, array( 'option_value' => $raw ), array( 'option_name' => $name ) ), 'Owned role restore failed.' ); } ); };
    }
    if ( 'user' === $change ) {
        alynt_core_mutate( function () use ( $wpdb ) { alynt_core_assert( 1 === $wpdb->update( $wpdb->users, array( 'user_login' => 'alynt-core-revoked' ), array( 'ID' => 90000001 ) ), 'Owned user revocation failed.' ); } );
        return function () use ( $wpdb ) { alynt_core_mutate( function () use ( $wpdb ) { alynt_core_assert( 1 === $wpdb->update( $wpdb->users, array( 'user_login' => 'alynt-core-fixture-90000001' ), array( 'ID' => 90000001 ) ), 'Owned user restore failed.' ); } ); };
    }
    if ( 'actor' === $change ) { $old = $GLOBALS['current_user']; wp_set_current_user( 90000003 ); return function () use ( $old ) { $GLOBALS['current_user'] = $old; }; }
    if ( 'cookie' === $change ) { $old = $_COOKIE[LOGGED_IN_COOKIE]; $_COOKIE[LOGGED_IN_COOKIE] = 'changed'; return function () use ( $old ) { $_COOKIE[LOGGED_IN_COOKIE] = $old; }; }
    if ( 'method' === $change ) { $_SERVER['REQUEST_METHOD'] = 'GET'; return function () { $_SERVER['REQUEST_METHOD'] = 'POST'; }; }
    if ( 'table' === $change ) { $old = $wpdb->options; $wpdb->options = 'refused_options'; return function () use ( $wpdb, $old ) { $wpdb->options = $old; }; }
    alynt_core_assert( 'blog' === $change, 'Unknown fixture mutation.' ); $old = $GLOBALS['blog_id']; $GLOBALS['blog_id'] = 2;
    return function () use ( $old ) { $GLOBALS['blog_id'] = $old; };
}
