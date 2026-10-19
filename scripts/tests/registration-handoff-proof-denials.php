<?php
/** Actual owned auth revocation/callback faults; no native observer execution. */
function alynt_handoff_denials() {
    foreach ( array( 'session', 'capability', 'role', 'user' ) as $change ) {
        list( $o, $frame, $i ) = alynt_handoff_fixture(); get_user_meta( 90000001 ); get_option( $GLOBALS['wpdb']->prefix . 'user_roles' );
        $restore = alynt_core_change( $change );
        try { alynt_handoff_run( $o, $frame, $i ); }
        finally { $restore(); wp_cache_flush(); $GLOBALS['wp_roles'] = new WP_Roles(); }
    }
    alynt_core_done( 'genuine_warm_cache_session_role_capability_user_revocation_denied' );
    foreach ( array( 4, 6 ) as $phase ) foreach ( array( 'session', 'capability', 'cookie', 'actor', 'method', 'table', 'budget', 'reset_window', 'throw', 'reentry' ) as $change ) {
        list( $o, $frame, $i ) = alynt_handoff_fixture(); $facts = array(); $restore = null;
        $probe = alynt_handoff_probe( $facts, function ( $n, $life, $owner ) use ( $phase, $change, &$restore, $frame, $i ) {
            if ( $n !== $phase ) return $life;
            if ( 'throw' === $change ) throw new RuntimeException( 'Owned handoff callback fault.' );
            elseif ( 'budget' === $change ) alynt_origin_set( $owner, 'started', hrtime( true ) - 10000000001 );
            elseif ( 'reset_window' === $change ) alynt_origin_set( $owner, 'started', hrtime( true ) );
            elseif ( 'reentry' === $change ) alynt_core_private( $owner, 'observe_candidate', array( $frame, $i ) );
            else $restore = alynt_origin_change( $change );
            return $life;
        } );
        add_filter( 'nonce_life', $probe, 10, 2 );
        try { alynt_handoff_run( $o, $frame, $i ); }
        finally { remove_filter( 'nonce_life', $probe, 10 ); if ( $restore ) $restore(); wp_cache_flush(); $GLOBALS['wp_roles'] = new WP_Roles(); }
        alynt_core_assert( count( $facts ) >= $phase, 'Required late genuine guard fault not reached.' );
    }
    alynt_core_done( 'pre_claim_and_post_finish_genuine_authority_request_budget_reentry_throw_losses' );
    foreach ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) as $role ) {
        list( $o, $frame, $i ) = alynt_handoff_fixture(); $frame['rows'][$role]['autoload'] = 'yes';
        alynt_handoff_run( $o, $frame, $i );
    }
    alynt_core_done( 'all_ten_candidate_autoload_guards_refuse_without_protected_io_or_repairs' );
    list( $o, $frame, $i ) = alynt_handoff_fixture(); alynt_handoff_run( $o, $frame, $i, 'registered_closed' );
    $public = array(); foreach ( ( new ReflectionClass( $o ) )->getMethods( ReflectionMethod::IS_PUBLIC ) as $m ) $public[] = $m->getName();
    sort( $public ); $expected = array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ); sort( $expected );
    alynt_core_assert( $expected === $public, 'Public continuation/handle export introduced.' );
    alynt_core_done( 'final_genuine_positive_recovery_private_surface_only_no_observer_or_admission' );
}
