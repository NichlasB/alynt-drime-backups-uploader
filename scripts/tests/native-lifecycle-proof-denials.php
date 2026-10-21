<?php
/** Fresh genuine-core denials; no native transaction/DML in these cases. */
function alynt_native_lifecycle_denials() {
    global $wpdb;
    alynt_core_login(); $form = alynt_registration_form();
    $o = Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle::create(); $q = $GLOBALS['alynt_core_proof']['queries']; $g = 0;
    $count = function ( $life ) use ( &$g ) { ++$g; return $life; };
    alynt_core_assert( false === alynt_native_lifecycle_call( $o, 'POST', $form, $count ) && 0 === $g && $q === $GLOBALS['alynt_core_proof']['queries'], 'native_lifecycle_final_entry_still_closed_before_auth_io' );
    alynt_core_done( 'final_unavailable_entry_no_auth_or_native_io' );
    foreach ( array( 'malformed', 'method', 'bad_nonce', 'domain', 'subscriber', 'expired_nonce', 'session', 'capability', 'role', 'user' ) as $case ) {
        alynt_core_login( 'subscriber' === $case ? 90000002 : 90000001 ); $body = alynt_registration_form(); $restore = null; $method = 'POST';
        if ( 'malformed' === $case ) $body['provider'] = 'forbidden';
        elseif ( 'method' === $case ) $method = 'GET';
        elseif ( 'bad_nonce' === $case ) $body['nonce'] = 'bad';
        elseif ( 'domain' === $case ) $body['nonce'] = wp_create_nonce( Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( get_current_blog_id(), 'reserve_wpvivid_intent' ) );
        elseif ( 'expired_nonce' === $case ) {
            $short = function () { return 2; }; add_filter( 'nonce_life', $short ); $body = alynt_registration_form(); $tick = wp_nonce_tick(); $end = microtime( true ) + 5;
            while ( wp_nonce_tick() <= $tick + 1 && microtime( true ) < $end ) usleep( 50000 );
            alynt_core_assert( wp_nonce_tick() > $tick + 1, 'native_lifecycle_real_nonce_expired' );
        } elseif ( in_array( $case, array( 'session', 'capability', 'role', 'user' ), true ) ) { get_user_meta( 90000001 ); get_option( $wpdb->prefix . 'user_roles' ); $restore = alynt_core_change( $case ); }
        $o = Alynt_Test_Native_Lifecycle_Owner::create();
        try { alynt_core_assert( false === alynt_native_lifecycle_call( $o, $method, $body ), 'native_lifecycle_denial_' . $case ); }
        finally { if ( $restore ) $restore(); if ( 'expired_nonce' === $case ) remove_filter( 'nonce_life', $short ); wp_cache_flush(); $GLOBALS['wp_roles'] = new WP_Roles(); }
        foreach ( array( 'protected_reads', 'writes', 'barrier_reads' ) as $key ) alynt_core_assert( 0 === alynt_core_property( $o, $key ), 'native_lifecycle_denied_before_protected_io_' . $case );
        foreach ( array( 'capture_receipt', 'admission_receipt', 'barrier_receipt' ) as $key ) alynt_core_assert( null === alynt_core_property( $o, $key ), 'native_lifecycle_denied_before_factory_' . $case );
    }
    alynt_core_done( 'genuine_malformed_method_nonce_domain_expiry_subscriber_and_raw_warm_cache_revocations' );
    for ( $gate = 1; $gate <= 10; ++$gate ) {
        alynt_core_login(); $body = alynt_registration_form(); $o = Alynt_Test_Native_Lifecycle_Owner::create(); $n = 0;
        $deny = function ( $life ) use ( &$n, $gate, $o ) {
            ++$n; $reader = alynt_core_property( $o, 'reader' );
            if ( $reader instanceof mysqli ) { alynt_native_lifecycle_thread( $reader ); alynt_core_assert( '0' === $reader->query( 'SELECT CAST(@@in_transaction+0 AS CHAR)' )->fetch_row()[0], 'native_lifecycle_no_core_callback_under_lock' ); }
            return $n === $gate ? 1 : $life; // Genuine nonce lifetime invalidation, not a replacement verifier.
        };
        alynt_core_assert( false === alynt_native_lifecycle_call( $o, 'POST', $body, $deny ) && $gate === $n, 'native_lifecycle_exact_denied_guard_' . $gate );
        alynt_core_assert( ( 1 === $gate ? 0 : 20 ) === alynt_core_property( $o, 'protected_reads' ) && 0 === alynt_core_property( $o, 'writes' ) && 0 === alynt_core_property( $o, 'barrier_reads' ), 'native_lifecycle_guard_before_begin_and_dml_' . $gate );
    }
    alynt_core_done( 'ten_mutation_guards_individually_denied_before_dml_no_retry' );
}
