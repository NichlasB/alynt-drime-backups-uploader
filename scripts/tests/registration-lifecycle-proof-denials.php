<?php
/** Real owned authority loss; reflection controls change private state, not core results. */
function alynt_lifecycle_denials() {
    foreach ( array( 'session', 'capability', 'role', 'user' ) as $change ) {
        list( $o, $frame, $i ) = alynt_lifecycle_fixture(); get_user_meta( 90000001 ); get_option( $GLOBALS['wpdb']->prefix . 'user_roles' );
        $restore = alynt_core_change( $change );
        try { alynt_lifecycle_run( $o, $frame, $i ); }
        finally { $restore(); wp_cache_flush(); $GLOBALS['wp_roles'] = new WP_Roles(); }
    }
    alynt_core_done( 'raw_owned_session_capability_role_user_revocation_beats_warm_core_caches' );
    foreach ( array( 5, 6 ) as $gate ) foreach ( array( 'session', 'capability', 'role', 'user', 'actor', 'cookie', 'method', 'table', 'blog', 'budget', 'restart', 'clock_regression', 'origin', 'ids', 'throw', 'warning', 'reentry' ) as $change ) {
        list( $o, $frame, $i ) = alynt_lifecycle_fixture(); $facts = array(); $created = array(); $restore = null; $completed = false; $nested = null;
        $probe = alynt_lifecycle_probe( $facts, $created, function ( $n, $life, $owner ) use ( $gate, $change, &$restore, &$completed, &$nested, $frame, $i ) {
            if ( $gate !== $n ) return $life;
            alynt_core_assert( is_array( alynt_core_property( $owner, 'origin_data' ) ), 'Late fault lacks original retained origin.' );
            if ( 'throw' === $change ) { $completed = true; throw new RuntimeException( 'Owned lifecycle callback failure.' ); }
            elseif ( 'warning' === $change ) { $completed = true; trigger_error( 'Owned lifecycle callback warning.', E_USER_WARNING ); }
            elseif ( 'budget' === $change ) alynt_lifecycle_set( $owner, 'started', hrtime( true ) - 10000000001 );
            elseif ( 'restart' === $change ) alynt_lifecycle_set( $owner, 'started', hrtime( true ) );
            elseif ( 'clock_regression' === $change ) { $clock = alynt_core_property( $owner, 'root_clock' ); $clock['now_ns'] = hrtime( true ) + 1000000000; alynt_lifecycle_set( $owner, 'root_clock', $clock ); }
            elseif ( 'origin' === $change ) { $d = alynt_core_property( $owner, 'origin_data' ); $d['actor_id']++; alynt_lifecycle_set( $owner, 'origin_data', $d ); }
            elseif ( 'ids' === $change ) { $ids = alynt_core_property( $owner, 'used' ); array_pop( $ids ); alynt_lifecycle_set( $owner, 'used', $ids ); }
            elseif ( 'reentry' === $change ) $nested = alynt_core_private( $owner, 'prepare_admission', array( $frame, $i ) );
            else $restore = alynt_lifecycle_change( $change );
            $completed = true;
            return $life;
        } );
        add_filter( 'nonce_life', $probe, 10, 2 );
        try { alynt_lifecycle_run( $o, $frame, $i ); }
        finally { remove_filter( 'nonce_life', $probe, 10 ); if ( $restore ) $restore(); wp_cache_flush(); $GLOBALS['wp_roles'] = new WP_Roles(); }
        alynt_core_assert( count( $facts ) >= $gate && 1 === count( $created ) && is_array( $facts[$gate - 1]['origin'] ), 'Late genuine guard fault not exercised with original proposal.' );
        alynt_core_assert( $completed && ( 'reentry' !== $change || false === $nested ), 'Fault control did not complete or reentry admitted; caught assertion must not look like intended denial.' );
    }
    alynt_core_done( 'both_retained_guards_revoke_auth_identity_clock_provenance_reentry_throw_warning_and_cleanup' );
    list( $o, $frame, $i ) = alynt_lifecycle_fixture(); $public = array();
    foreach ( ( new ReflectionClass( $o ) )->getMethods( ReflectionMethod::IS_PUBLIC ) as $m ) $public[] = $m->name; sort( $public );
    alynt_core_assert( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ) === $public, 'Public continuation or write-capable handle export introduced.' );
    foreach ( array( 'admit', 'admission_write', 'open_writer', 'read', 'observe_native', 'stage', 'claim' ) as $method ) alynt_core_assert( ! method_exists( $o, $method ), 'Native transaction/outcome import method introduced.' );
    $facts = array(); $created = array(); $probe = alynt_lifecycle_probe( $facts, $created, function ( $n, $life, $owner ) {
        if ( 5 !== $n ) return $life;
        try { serialize( $owner ); alynt_core_assert( false, 'Retained owner serialized.' ); } catch ( LogicException $error ) { alynt_core_assert( 'Closed contract data cannot be transferred.' === $error->getMessage(), 'Unexpected privacy denial.' ); }
        alynt_core_assert( $owner->disposition() === $owner->__debugInfo(), 'Debug disclosure differs from closed disposition.' ); return $life;
    } );
    add_filter( 'nonce_life', $probe, 10, 2 ); try { alynt_lifecycle_run( $o, $frame, $i, true ); } finally { remove_filter( 'nonce_life', $probe, 10 ); }
    alynt_core_done( 'retained_privacy_no_public_native_handles_and_final_positive_recovery' );
}
