<?php
/** Genuine authorization, domain, lifecycle and no-IO controls on owned tables. */
function alynt_origin_denials() {
    global $wpdb;
    list( $values, $frame ) = $GLOBALS['alynt_origin_baseline'];
    alynt_scan_seed( array( 'connection' => $values['connection'] ), 'yes' ); alynt_core_login( 90000002 ); $form = alynt_registration_form(); $bulk = 0;
    $probe = function ( $sql ) use ( &$bulk, $wpdb ) { if ( false !== strpos( $sql, 'SELECT option_name, option_value FROM ' . $wpdb->options ) ) ++$bulk; return $sql; };
    add_filter( 'query', $probe, 11 );
    try { alynt_origin_retain( $form, 0 ); }
    finally { remove_filter( 'query', $probe, 11 ); alynt_scan_seed( $values ); wp_cache_flush(); }
    alynt_core_assert( 0 === $bulk, 'Unauthorized role lookup bulk-fetched protected autoload payloads.' );
    alynt_core_done( 'genuine_subscriber_denial_zero_native_reads_and_zero_protected_bulk_autoload' );

    alynt_core_login(); $form = alynt_registration_form(); $removed = false;
    $prior = isset( $GLOBALS['wp_filter']['pre_wp_load_alloptions'] ) ? clone $GLOBALS['wp_filter']['pre_wp_load_alloptions'] : null;
    $remove = function ( $life ) use ( &$removed ) { if ( ! $removed ) { $removed = true; remove_all_filters( 'pre_wp_load_alloptions' ); } return $life; };
    add_filter( 'nonce_life', $remove );
    try { alynt_origin_retain( $form, 0 ); }
    finally { remove_filter( 'nonce_life', $remove ); if ( $prior ) $GLOBALS['wp_filter']['pre_wp_load_alloptions'] = $prior; else unset( $GLOBALS['wp_filter']['pre_wp_load_alloptions'] ); }
    alynt_core_assert( $removed, 'Independent options-query fence control did not execute.' );
    alynt_core_done( 'independent_query_fence_refuses_missing_core_autoload_bypass_before_capture' );

    foreach ( array( 'session', 'capability', 'role', 'user' ) as $change ) {
        alynt_core_login(); $form = alynt_registration_form(); get_user_meta( 90000001 ); get_option( $wpdb->prefix . 'user_roles' );
        $restore = alynt_core_change( $change );
        try { alynt_origin_retain( $form, 0 ); }
        finally { $restore(); wp_cache_flush(); $GLOBALS['wp_roles'] = new WP_Roles(); }
    }
    alynt_core_done( 'genuine_warm_cache_session_capability_role_user_revocation_before_capture' );

    alynt_core_login(); $form = alynt_registration_form();
    foreach ( array( array(), array_merge( $form, array( 'actor_id' => '90000001' ) ), array_merge( $form, array( 'provider' => 'caller' ) ) ) as $bad ) alynt_origin_retain( $bad, 0 );
    alynt_origin_retain( $form, 0, false, 'GET' );
    $auth = $wpdb->dbh; $swap = function ( $life ) use ( $wpdb ) { $wpdb->db_connect( false ); return $life; }; add_filter( 'nonce_life', $swap );
    try { alynt_origin_retain( $form, 0 ); } finally { remove_filter( 'nonce_life', $swap ); }
    alynt_core_assert( $wpdb->dbh !== $auth, 'Physical-handle swap control did not occur.' );
    alynt_core_done( 'exact_request_shape_method_and_physical_auth_swap_refused_before_native_rows' );

    foreach ( array( 'session', 'capability', 'cookie', 'actor', 'method', 'table', 'budget', 'throw', 'reentry' ) as $change ) {
        alynt_core_login(); $form = alynt_registration_form(); $restore = null; $calls = 0;
        $loss = function ( $life, $action ) use ( $change, &$restore, &$calls, $form ) {
            if ( 0 !== strpos( $action, 'alynt_drime_backups_scan_registration_issue_v2:' ) || 2 !== ++$calls ) return $life;
            $o = $GLOBALS['alynt_origin_owner'];
            if ( 'throw' === $change ) throw new RuntimeException( 'Owned callback failure.' );
            elseif ( 'budget' === $change ) alynt_origin_set( $o, 'native_started', hrtime( true ) - 10000000001 );
            elseif ( 'reentry' === $change ) alynt_core_private( $o, 'retain_native_registration', array( 'POST', $form ) );
            else $restore = alynt_origin_change( $change );
            return $life;
        };
        add_filter( 'nonce_life', $loss, 10, 2 );
        try { alynt_origin_retain( $form, 20 ); }
        finally { remove_filter( 'nonce_life', $loss, 10 ); if ( $restore ) $restore(); wp_cache_flush(); $GLOBALS['wp_roles'] = new WP_Roles(); }
        alynt_core_assert( $calls >= 2, 'Post-capture callback was not reached.' );
    }
    alynt_core_done( 'post_capture_auth_scope_throw_reentry_original_budget_losses_clear_origin' );

    foreach ( array( 'bad_nonce', 'wrong_id', 'cross_domain', 'subscriber', 'session', 'capability', 'cookie', 'method', 'table', 'physical', 'throw', 'reentry' ) as $change ) {
        alynt_core_login(); $form = alynt_registration_form(); $o = alynt_origin_retain( $form, 20, true ); $i = alynt_origin_intent( $o ); $restore = null; $probe = null;
        if ( 'bad_nonce' === $change ) $i['nonce'] = 'bad';
        elseif ( 'wrong_id' === $change ) $i['observed_command_id'] = wp_generate_uuid4();
        elseif ( 'cross_domain' === $change ) $i['nonce'] = $form['nonce'];
        elseif ( 'subscriber' === $change ) { alynt_core_login( 90000002 ); $i = alynt_origin_intent( $o ); }
        elseif ( in_array( $change, array( 'session', 'capability' ), true ) ) $restore = alynt_core_change( $change );
        else {
            $probe = function ( $life, $action ) use ( $change, &$restore, $o, $i ) {
                if ( 0 !== strpos( $action, 'alynt_drime_backups_scan_registration_receipt_v2:' ) ) return $life;
                if ( 'throw' === $change ) throw new RuntimeException( 'Owned read callback failure.' );
                elseif ( 'physical' === $change ) { if ( null === $restore ) { $GLOBALS['wpdb']->db_connect( false ); $restore = static function () {}; } }
                elseif ( 'reentry' === $change ) alynt_core_private( $o, 'construct_native_observation', array( $i ) );
                elseif ( null === $restore ) $restore = alynt_origin_change( $change );
                return $life;
            };
            add_filter( 'nonce_life', $probe, 10, 2 );
        }
        try { alynt_origin_read( $o, $i ); }
        finally { if ( $probe ) remove_filter( 'nonce_life', $probe, 10 ); if ( $restore ) $restore(); wp_cache_flush(); $GLOBALS['wp_roles'] = new WP_Roles(); }
    }
    alynt_core_done( 'fresh_read_domain_original_id_authority_callback_loss_and_once_only_clearing' );
    alynt_core_login(); $form = alynt_registration_form();
    foreach ( $values as $role => $raw ) {
        alynt_scan_seed( array( $role => $raw ), 'yes' );
        try { alynt_origin_retain( $form, 20 ); } finally { alynt_scan_seed( array( $role => $raw ) ); }
    }
    alynt_core_done( 'all_ten_captured_tuple_autoload_guards_remain_closed_without_storage_repairs' );
    alynt_core_login(); $form = alynt_registration_form(); $o = alynt_origin_retain( $form, 20, true ); alynt_origin_read( $o, alynt_origin_intent( $o ), true );
    $db = alynt_scan_db(); try { alynt_core_assert( $frame === alynt_registration_frame( $db ), 'Protected fixture rows changed during auth proof.' ); } finally { $db->close(); }
    unset( $GLOBALS['alynt_origin_baseline'] );
    alynt_core_done( 'positive_recovery_all_ten_tuple_bytes_unchanged_no_admission_or_observer' );
}
