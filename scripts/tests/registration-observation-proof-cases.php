<?php
/** Non-vacuous genuine own-origin/read positives, not registration/observer writes. */
function alynt_origin_cases() {
    global $wpdb;
    foreach ( array( 'wp_generate_uuid4', 'wp_create_nonce', 'wp_verify_nonce', 'wp_validate_auth_cookie', 'user_can' ) as $fn ) alynt_core_assert( 0 === strpos( realpath( ( new ReflectionFunction( $fn ) )->getFileName() ), realpath( ABSPATH . 'wp-includes' ) . DIRECTORY_SEPARATOR ), 'Proof requires genuine core APIs.' );
    $values = alynt_registration_values(); alynt_scan_seed( $values ); alynt_core_login(); $form = alynt_registration_form();
    $bad = $form; $bad['nonce'] = 'bad'; alynt_origin_retain( $bad, 0 ); // Before positives: removed initial auth must expose unauthorized native reads.
    $db = alynt_scan_db();
    try {
        $frame = alynt_registration_frame( $db ); $auth = $wpdb->dbh;
        $facts = $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row();
        $o = alynt_origin_retain( $form, 20, true ); $d = alynt_core_property( $o, 'origin_data' );
        $expected = alynt_registration_eligible( $frame );
        alynt_core_assert( 90000001 === $d['actor_id'] && $expected['scope'] === $d['scope'] && $frame['prefix'] === $d['installation']['prefix'] && $d['mutation']['context_digest'] === $d['context_digest'], 'Own original context did not match genuine captured construction.' );
        alynt_core_assert( alynt_core_property( $o, 'origin' )->matches( $d ) && count( alynt_core_property( $o, 'used' ) ) >= 12, 'Own origin/issued roles not retained.' );
        $i = alynt_origin_intent( $o ); $r = alynt_origin_read( $o, $i, true );
        alynt_core_assert( $r['envelope']['observed_command_id'] === $d['mutation']['command_id'] && $r['envelope']['site_uuid'] === $d['scope']['site_uuid'] && $r['envelope']['command_id'] !== $d['mutation']['command_id'], 'Genuine fresh read not bound to own original identity.' );
        alynt_core_assert( in_array( wp_verify_nonce( $r['nonce'], $r['action'] ), array( 1, 2 ), true ) && false === wp_verify_nonce( $i['nonce'], $r['action'] ), 'Genuine full read nonce missing or issuance crossed domain.' );
        foreach ( array( 'storage_supported', 'dispatch_supported', 'execution_supported', 'barrier_verified', 'closure_proven' ) as $key ) alynt_core_assert( false === $r[$key], 'Blueprint promoted to a storage/closure capability.' );
        array_walk_recursive( $r, function ( $v ) { alynt_core_assert( is_scalar( $v ) || null === $v, 'Private native object escaped.' ); } );
        alynt_core_assert( $facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row() && $frame === alynt_registration_frame( $db ), 'Protected fixture or borrowed auth session changed.' );
        alynt_core_done( 'genuine_twenty_read_own_retention_fresh_independent_receipt_nonce_no_storage_capability' );

        $o = alynt_origin_retain( $form, 20, true ); $d = alynt_core_property( $o, 'origin_data' );
        alynt_core_login( 90000003 ); $i = alynt_origin_intent( $o ); alynt_origin_read( $o, $i, true );
        alynt_core_assert( 90000001 === $d['actor_id'], 'Observer replaced the historical mutation actor.' );
        alynt_core_done( 'different_current_authorized_administrator_preserves_original_actor' );

        alynt_core_login(); $form = alynt_registration_form(); $seen = array();
        $inspect = function ( $life, $action ) use ( &$seen, $auth ) {
            $o = $GLOBALS['alynt_origin_owner']; $reader = alynt_core_property( $o, 'reader' );
            if ( $reader instanceof mysqli ) $seen['reader'] = $reader !== $auth && $reader->thread_id !== $auth->thread_id && '1' === $reader->query( 'SELECT CAST(@@tx_read_only+0 AS CHAR)' )->fetch_row()[0];
            if ( 0 === strpos( $action, 'alynt_drime_backups_scan_registration_command_v2:' ) ) $seen['closed_before_command_nonce'] = null === $reader;
            return $life;
        };
        add_filter( 'nonce_life', $inspect, 10, 2 );
        try { $o = alynt_origin_retain( $form, 20, true ); } finally { remove_filter( 'nonce_life', $inspect, 10 ); }
        alynt_core_assert( array( 'reader' => true, 'closed_before_command_nonce' => true ) === $seen, 'Owned reader not genuinely distinct/read-only/closed before construction.' );
        alynt_origin_read( $o, alynt_origin_intent( $o ), true );
        alynt_core_done( 'genuine_distinct_server_readonly_capture_session_closed_before_nonce_construction' );

        // Real nonce_life changes exercise the genuine verifier; no fabricated verification result.
        foreach ( array( 'command', 'receipt' ) as $purpose ) {
            alynt_core_login(); $form = alynt_registration_form(); $o = null;
            if ( 'receipt' === $purpose ) { $o = alynt_origin_retain( $form, 20, true ); $i = alynt_origin_intent( $o ); }
            $calls = 0; $prefix = 'alynt_drime_backups_scan_registration_' . $purpose . '_v2:';
            $expire = function ( $life, $action ) use ( &$calls, $prefix ) { if ( 0 === strpos( $action, $prefix ) && ++$calls >= 2 ) return 1; return $life; };
            add_filter( 'nonce_life', $expire, 10, 2 );
            try { if ( 'command' === $purpose ) alynt_origin_retain( $form, 20 ); else alynt_origin_read( $o, $i ); }
            finally { remove_filter( 'nonce_life', $expire, 10 ); }
            alynt_core_assert( $calls >= 2, 'Independent genuine full nonce verifier not reached.' );
        }
        alynt_core_done( 'genuine_full_mutation_and_receipt_nonce_expiry_independent_of_valid_issuance' );
        $GLOBALS['alynt_origin_baseline'] = array( $values, $frame );
    } finally { $db->close(); }
}
