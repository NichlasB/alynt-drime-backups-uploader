<?php
/** Genuine authentication/request/profile losses, never application mutations. */
function alynt_admission_denials( $db, $values, $original ) {
    global $wpdb;
    alynt_core_login(); $form = alynt_registration_form();
    foreach ( array( array(), array_merge( $form, array( 'actor_id' => '90000001' ) ), array_merge( $form, array( 'provider' => 'external' ) ), array_merge( $form, array( 'nonce' => 'bad' ) ) ) as $bad ) alynt_admit( $bad, false, 0 );
    alynt_admit( $form, false, 0, 0, 'GET' ); $_SERVER['REQUEST_METHOD'] = 'GET'; try { alynt_admit( $form, false, 0 ); } finally { $_SERVER['REQUEST_METHOD'] = 'POST'; }
    $bad = $form; $bad['nonce'] = wp_create_nonce( Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( get_current_blog_id(), 'reserve_wpvivid_intent' ) ); alynt_admit( $bad, false, 0 );
    alynt_scan_seed( array( 'connection' => $values['connection'] ), 'yes' ); alynt_core_login( 90000002 ); $form = alynt_registration_form(); $bulk = 0;
    $watch = function ( $sql ) use ( &$bulk, $wpdb ) { if ( false !== strpos( $sql, 'SELECT option_name, option_value FROM ' . $wpdb->options ) ) ++$bulk; return $sql; };
    add_filter( 'query', $watch, PHP_INT_MAX );
    try { alynt_admit( $form, false, 0 ); } finally { remove_filter( 'query', $watch, PHP_INT_MAX ); alynt_scan_seed( $values ); wp_cache_flush(); }
    alynt_core_assert( 0 === $bulk, 'Unauthorized role lookup fetched protected bulk autoload data.' );
    foreach ( array( 'session', 'capability', 'role', 'user' ) as $change ) {
        alynt_core_login(); $form = alynt_registration_form(); $restore = alynt_core_change( $change );
        try { alynt_admit( $form, false, 0 ); } finally { $restore(); wp_cache_flush(); $GLOBALS['wp_roles'] = new WP_Roles(); }
    }
    alynt_core_done( 'malformed_method_nonce_domain_subscriber_bulk_autoload_and_warm_cache_revocations_zero_protected_reads' );

    alynt_core_login(); $form = alynt_registration_form(); $removed = false; $late_bulk = 0;
    $late_watch = function ( $sql ) use ( &$late_bulk, $wpdb ) { if ( false !== strpos( $sql, 'SELECT option_name, option_value FROM ' . $wpdb->options ) ) ++$late_bulk; return $sql; };
    $missing_bypass = function ( $life ) use ( &$removed, $late_watch ) {
        if ( $removed ) return $life;
        foreach ( $GLOBALS['wp_filter']['pre_wp_load_alloptions']->callbacks as $priority => $callbacks ) foreach ( $callbacks as $callback ) {
            $fn = $callback['function'];
            if ( $fn instanceof Closure && 'class-registration-native-admission.php' === basename( ( new ReflectionFunction( $fn ) )->getFileName() ) ) { remove_filter( 'pre_wp_load_alloptions', $fn, $priority ); $removed = true; }
        }
        remove_filter( 'query', $late_watch, PHP_INT_MAX );
        add_filter( 'query', $late_watch, PHP_INT_MAX ); // Move AFTER the source fence, before any actual SQL execution.
        return $life;
    };
    add_filter( 'query', $late_watch, PHP_INT_MAX ); add_filter( 'nonce_life', $missing_bypass );
    try { alynt_admit( $form, false, 0 ); } finally { remove_filter( 'nonce_life', $missing_bypass ); remove_filter( 'query', $late_watch, PHP_INT_MAX ); }
    alynt_core_assert( $removed && 0 === $late_bulk, 'Independent query fence did not reject a missing role-only bypass before bulk SQL.' );
    alynt_core_done( 'independent_query_fence_rejects_missing_core_autoload_short_circuit_before_sql' );

    alynt_core_login(); $form = alynt_registration_form(); $auth = $wpdb->dbh; $swapped = false;
    $swap = function ( $life ) use ( &$swapped, $wpdb ) { if ( ! $swapped ) { $swapped = true; $wpdb->db_connect( false ); } return $life; };
    add_filter( 'nonce_life', $swap ); try { alynt_admit( $form, false, 0 ); } finally { remove_filter( 'nonce_life', $swap ); }
    alynt_core_assert( $swapped && $wpdb->dbh !== $auth, 'Physical auth swap control did not actually run.' ); $auth = $wpdb->dbh;
    $auth->query( 'START TRANSACTION READ ONLY' );
    try { alynt_admit( $form, false, 0 ); alynt_core_assert( '1' === $auth->query( 'SELECT CAST(@@in_transaction+0 AS CHAR)' )->fetch_row()[0], 'Borrowed transaction was repaired.' ); } finally { $auth->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
    alynt_core_done( 'original_physical_auth_swap_and_ambient_transaction_refuse_without_adoption_or_repair' );

    foreach ( array( 'session', 'capability', 'actor', 'cookie', 'method', 'table', 'blog', 'throw', 'reenter', 'budget', 'utc' ) as $change ) {
        alynt_core_login(); $form = alynt_registration_form(); $calls = 0; $restore = null; $nested = null;
        $loss = function ( $life ) use ( $change, &$calls, &$restore, &$nested, $form ) {
            if ( 2 !== ++$calls ) return $life; $owner = $GLOBALS['alynt_admission_owner'];
            if ( 'throw' === $change ) throw new RuntimeException( 'Owned post-capture throw.' );
            if ( 'reenter' === $change ) { $nested = alynt_core_private( $owner, 'admit', array( 'POST', $form ) ); throw new RuntimeException( 'Owned nested throw.' ); }
            if ( 'budget' === $change ) { alynt_ledger_set( $owner, 'started', hrtime( true ) - 10000000001 ); return $life; }
            if ( 'utc' === $change ) { alynt_ledger_set( $owner, 'last_clock', time() + 1 ); return $life; }
            $restore = alynt_core_change( $change ); return $life;
        };
        add_filter( 'nonce_life', $loss ); try { alynt_admit( $form, false, 20 ); } finally { remove_filter( 'nonce_life', $loss ); if ( $restore ) $restore(); wp_cache_flush(); $GLOBALS['wp_roles'] = new WP_Roles(); }
        if ( 'reenter' === $change ) alynt_core_assert( false === $nested, 'Nested private native admission succeeded.' );
    }
    alynt_core_done( 'post_capture_request_scope_revocation_throw_reentry_and_original_budget_losses_refuse' );

    alynt_core_login(); $form = alynt_registration_form(); $closed = false; $restore = null;
    $nonce_loss = function ( $life, $action ) use ( &$closed, &$restore ) {
        if ( 0 === strpos( $action, 'alynt_drime_backups_scan_registration_command_v2:' ) ) { $closed = null === alynt_core_property( $GLOBALS['alynt_admission_owner'], 'reader' ); $restore = alynt_core_change( 'session' ); }
        return $life;
    };
    add_filter( 'nonce_life', $nonce_loss, 10, 2 );
    try { alynt_admit( $form, false, 20 ); } finally { remove_filter( 'nonce_life', $nonce_loss, 10 ); if ( $restore ) $restore(); wp_cache_flush(); }
    alynt_core_assert( $closed, 'Genuine UUID/nonce construction used an open native capture handle.' );
    foreach ( array( false, true ) as $post_commit ) {
        $token = alynt_core_login(); $form = alynt_registration_form(); $revoked = false;
        $revoke = function ( $life, $action ) use ( $post_commit, $token, &$revoked ) {
            $owner = $GLOBALS['alynt_admission_owner']; $phase = alynt_core_property( $owner, 'phase' );
            if ( ! $revoked && ( $post_commit ? 'committed' === $phase : 'idle' === $phase && 0 === strpos( $action, 'alynt_drime_backups_scan_registration_command_v2:' ) ) ) { $revoked = true; alynt_core_mutate( function () use ( $token ) { WP_Session_Tokens::get_instance( 90000001 )->destroy( $token ); } ); }
            return $life;
        };
        add_filter( 'nonce_life', $revoke, 10, 2 );
        try { $o = alynt_admit( $form, false, $post_commit ? 40 : 20, $post_commit ? 1 : 0 ); } finally { remove_filter( 'nonce_life', $revoke, 10 ); }
        alynt_core_assert( $revoked && ( $post_commit ? 'committed' : 'idle' ) === alynt_core_property( $o, 'phase' ), 'Revocation entered BEGIN or erased a historical commit.' );
        if ( $post_commit ) alynt_admission_receipt( $db, $original ); else alynt_core_assert( $original === alynt_registration_frame( $db ), 'Pre-BEGIN revocation changed protected rows.' );
        alynt_scan_seed( $values );
    }
    alynt_core_done( 'nonce_creation_after_capture_close_prebegin_and_postcommit_genuine_session_revocation_distinguish_history' );
    alynt_core_login(); alynt_core_assert( $original === alynt_registration_frame( $db ), 'Request-denial cases left protected fixture changes.' );
}
