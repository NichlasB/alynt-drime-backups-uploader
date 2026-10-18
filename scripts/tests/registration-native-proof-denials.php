<?php
/** Actual core authority/connection/lifetime losses; owned fixtures only. */
function alynt_registration_native_denials() {
    global $wpdb;
    list( $values, $frame ) = $GLOBALS['alynt_registration_native_baseline'];
    // Core capability lookup must not bulk-fetch protected autoload payloads,
    // even when a malformed fixture has marked one of them autoload=yes.
    alynt_scan_seed( array( 'connection' => $values['connection'] ), 'yes' ); alynt_core_login( 90000002 ); $denied = alynt_registration_form(); $bulk = 0;
    $observe_bulk = function ( $sql ) use ( &$bulk, $wpdb ) { if ( false !== strpos( $sql, 'SELECT option_name, option_value FROM ' . $wpdb->options ) ) ++$bulk; return $sql; };
    add_filter( 'query', $observe_bulk, PHP_INT_MAX );
    try { alynt_registration_native( $denied, 0 ); } finally { remove_filter( 'query', $observe_bulk, PHP_INT_MAX ); alynt_scan_seed( $values ); wp_cache_flush(); }
    alynt_core_assert( 0 === $bulk, 'Unauthorized core role lookup fetched protected autoload payloads.' );
    alynt_core_done( 'genuine_core_authorization_does_not_bulk_fetch_protected_autoload_payloads' );
    alynt_core_login(); $form = alynt_registration_form(); $removed = false; $prior_hook = isset( $GLOBALS['wp_filter']['pre_wp_load_alloptions'] ) ? clone $GLOBALS['wp_filter']['pre_wp_load_alloptions'] : null;
    $remove_bypass = function ( $life ) use ( &$removed ) { if ( ! $removed ) { $removed = true; remove_all_filters( 'pre_wp_load_alloptions' ); } return $life; };
    add_filter( 'nonce_life', $remove_bypass );
    // Simulate an unsupported/missing core short-circuit. Keep any prior hooks.
    // The independent query fence must refuse before aggregate SQL executes.
    $o = Alynt_Drime_Backups_Uploader_Registration_Native_Reader::create(); $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { $r = alynt_core_private( $o, 'read_registration', array( 'POST', $form ) ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; remove_filter( 'nonce_life', $remove_bypass ); if ( $prior_hook ) $GLOBALS['wp_filter']['pre_wp_load_alloptions'] = $prior_hook; else unset( $GLOBALS['wp_filter']['pre_wp_load_alloptions'] ); }
    alynt_core_assert( $removed && false === $r && 0 === alynt_core_property( $o, 'protected_reads' ) && null === alynt_core_property( $o, 'reader' ), 'Unsupported/missing autoload bypass reached aggregate payload SQL or protected capture.' );
    alynt_core_done( 'query_fence_independently_refuses_missing_autoload_short_circuit_without_payload_read' );
    alynt_core_login(); $form = alynt_registration_form();
    foreach ( array( array(), array_merge( $form, array( 'actor_id' => '90000001' ) ), array_merge( $form, array( 'provider' => 'caller' ) ), array_merge( $form, array( 'nonce' => 'bad' ) ) ) as $bad ) alynt_registration_native( $bad, 0 );
    alynt_registration_native( $form, 0, false, 'GET' ); $_SERVER['REQUEST_METHOD'] = 'GET'; alynt_registration_native( $form, 0 ); $_SERVER['REQUEST_METHOD'] = 'POST';
    $bad = $form; $bad['nonce'] = wp_create_nonce( Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( get_current_blog_id(), 'reserve_wpvivid_intent' ) ); alynt_registration_native( $bad, 0 );
    alynt_core_login( 90000002 ); alynt_registration_native( alynt_registration_form(), 0 );
    foreach ( array( 'session', 'capability', 'role', 'user' ) as $change ) {
        alynt_core_login(); $form = alynt_registration_form(); $restore = alynt_core_change( $change );
        try { alynt_registration_native( $form, 0 ); } finally { $restore(); wp_cache_flush(); $GLOBALS['wp_roles'] = new WP_Roles(); }
    }
    alynt_core_done( 'malformed_method_domain_subscriber_and_warm_cache_revocation_before_any_protected_read' );

    alynt_core_login(); $form = alynt_registration_form(); $auth = $wpdb->dbh; $swapped = false;
    $swap = function ( $life ) use ( &$swapped, $wpdb ) { if ( ! $swapped ) { $swapped = true; $wpdb->db_connect( false ); } return $life; };
    add_filter( 'nonce_life', $swap );
    try { alynt_registration_native( $form, 0 ); } finally { remove_filter( 'nonce_life', $swap ); }
    alynt_core_assert( $swapped && $wpdb->dbh !== $auth, 'Initial nonce physical-handle swap did not occur.' );
    $auth = $wpdb->dbh; $auth->query( 'START TRANSACTION READ ONLY' );
    try { alynt_registration_native( $form, 0 ); alynt_core_assert( '1' === $auth->query( 'SELECT CAST(@@in_transaction+0 AS CHAR)' )->fetch_row()[0], 'Borrowed transaction was repaired.' ); }
    finally { $auth->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
    alynt_core_done( 'physical_auth_handle_swap_and_ambient_transaction_refused_without_repair' );

    foreach ( array( 'session', 'capability', 'actor', 'cookie', 'method', 'table', 'blog', 'throw', 'reenter', 'budget' ) as $change ) {
        alynt_core_login(); $form = alynt_registration_form(); $calls = 0; $restore = null; $nested = null;
        $loss = function ( $life ) use ( $change, &$calls, &$restore, &$nested, $form ) {
            if ( 2 !== ++$calls ) return $life;
            $o = $GLOBALS['alynt_registration_native_owner'];
            if ( 'throw' === $change ) throw new RuntimeException( 'Owned post-read failure.' );
            if ( 'reenter' === $change ) { $nested = alynt_core_private( $o, 'read_registration', array( 'POST', $form ) ); throw new RuntimeException( 'Owned nested failure.' ); }
            if ( 'budget' === $change ) { alynt_registration_native_set( $o, 'started', hrtime( true ) - 10000000001 ); return $life; }
            $restore = alynt_core_change( $change ); return $life;
        };
        add_filter( 'nonce_life', $loss );
        try { alynt_registration_native( $form, 20 ); } finally { remove_filter( 'nonce_life', $loss ); if ( $restore ) $restore(); wp_cache_flush(); $GLOBALS['wp_roles'] = new WP_Roles(); }
        if ( 'reenter' === $change ) alynt_core_assert( false === $nested, 'Nested native attempt admitted.' );
    }
    alynt_core_done( 'post_capture_revocation_scope_loss_throw_reentry_and_original_budget_refused' );

    alynt_core_login(); $form = alynt_registration_form(); $closed = false; $restore = null;
    $post_nonce = function ( $life, $action ) use ( &$closed, &$restore ) {
        if ( 0 === strpos( $action, 'alynt_drime_backups_scan_registration_command_v2:' ) ) { $closed = null === alynt_core_property( $GLOBALS['alynt_registration_native_owner'], 'reader' ); $restore = alynt_core_change( 'session' ); }
        return $life;
    };
    add_filter( 'nonce_life', $post_nonce, 10, 2 );
    try { alynt_registration_native( $form, 20 ); } finally { remove_filter( 'nonce_life', $post_nonce, 10 ); if ( $restore ) $restore(); wp_cache_flush(); }
    alynt_core_assert( $closed, 'Constructor ran with an open native reader.' );
    alynt_core_done( 'session_loss_at_real_nonce_creation_after_reader_close_never_exports_blueprint' );
    // Deliberately mutate an owned dependency AFTER both sweeps. A blueprint
    // describes captured bytes, not current storage or permission to commit.
    alynt_core_login(); $form = alynt_registration_form(); $calls = 0;
    $after_capture = function ( $life ) use ( &$calls, $values ) { if ( 2 === ++$calls ) alynt_scan_seed( array( 'queue' => $values['queue'] ), 'off' ); return $life; };
    add_filter( 'nonce_life', $after_capture );
    try { $candidate = alynt_registration_native( $form, 20, true ); } finally { remove_filter( 'nonce_life', $after_capture ); }
    $db = alynt_scan_db();
    try { $current = alynt_registration_eligible( alynt_registration_frame( $db ) ); alynt_core_assert( is_array( $current ) && $candidate['proposal']['dependency_digest'] !== $current['dependency_digest'], 'Captured-versus-current storage discriminator failed.' ); }
    finally { $db->close(); alynt_scan_seed( $values ); }
    alynt_core_done( 'post_sweep_dependency_change_proves_blueprint_is_nonatomic_captured_data_not_commit_authority' );
    $db = alynt_scan_db(); try { alynt_core_assert( $frame === alynt_registration_frame( $db ), 'Denials left protected fixture changes.' ); } finally { $db->close(); }
    unset( $GLOBALS['alynt_registration_native_baseline'] );
}
