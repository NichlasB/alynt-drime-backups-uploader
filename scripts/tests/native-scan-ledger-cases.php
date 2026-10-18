<?php
/** Genuine authenticated ledger-only integration and separately labeled primitives. */
function alynt_scan_ledger_cases() {
    global $wpdb;
    $values = alynt_scan_values(); alynt_scan_seed( $values ); alynt_core_login(); $db = alynt_scan_db();
    try {
        $original = alynt_ledger_frame( $db ); $form = alynt_ledger_form( $db ); $auth = $wpdb->dbh;
        // Put authorization negatives BEFORE the positive/callback-count check,
        // so bypass controls fail on protected reads or writes, not a call count.
        alynt_ledger_run( array_merge( $form, array( 'nonce' => 'bad' ) ), false, 0 );
        foreach ( array( 2, 3 ) as $which ) {
            $token = alynt_core_login(); $form = alynt_ledger_form( $db ); $calls = 0;
            $early_revoke = function ( $life ) use ( &$calls, $token, $which ) { if ( $which === ++$calls ) alynt_core_mutate( function () use ( $token ) { WP_Session_Tokens::get_instance( 90000001 )->destroy( $token ); } ); return $life; };
            add_filter( 'nonce_life', $early_revoke );
            try { $early = alynt_ledger_run( $form, false, 2 === $which ? 10 : 30 ); } finally { remove_filter( 'nonce_life', $early_revoke ); }
            alynt_core_assert( ( 2 === $which ? 0 : 1 ) === alynt_core_property( $early, 'writes' ) && ( 2 === $which ? 'idle' : 'committed' ) === alynt_core_property( $early, 'phase' ), 'Revoked request entered locks or erased committed history.' );
            alynt_scan_seed( $values ); alynt_core_assert( $original === alynt_ledger_frame( $db ), 'Early revocation fixture reconciliation failed.' );
        }
        alynt_core_login(); $form = alynt_ledger_form( $db );
        alynt_core_done( 'genuine_bad_nonce_before_reads_and_renewed_auth_before_locks_and_after_historical_commit' );
        $facts = $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(); $events = 0;
        $trap = function () use ( $db, &$events ) { foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $def ) alynt_ledger_nowait( $db, $def[0], false ); ++$events; };
        add_action( 'auth_cookie_valid', $trap );
        try { $o = alynt_ledger_run( $form, true, 30 ); } finally { remove_action( 'auth_cookie_valid', $trap ); }
        $expected = $original; $s = json_decode( $values['ledger'], true ); ++$s['ledger_revision']; $raw = json_encode( $s );
        $expected['rows']['ledger']['value'] = $raw; $expected['rows']['ledger']['octets'] = (string) strlen( $raw );
        alynt_core_assert( $expected === alynt_ledger_frame( $db ) && 'committed' === alynt_core_property( $o, 'phase' ) && 1 === alynt_core_property( $o, 'writes' ) && $events >= 6, 'Actual ledger-only acknowledged commit/guard barrier missing.' );
        alynt_core_assert( $facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(), 'Borrowed auth session changed.' );
        alynt_ledger_run( $form, false, 10 ); alynt_core_assert( $expected === alynt_ledger_frame( $db ), 'Stale full-context replay changed ledger.' );
        alynt_scan_seed( $values ); alynt_core_done( 'genuine_guard_ledger_only_commit_all_other_rows_exact_no_wp_hooks_under_locks_stale_replay_denied' );

        foreach ( array( array(), array_merge( $form, array( 'nonce' => 'bad' ) ), array_merge( $form, array( 'extra' => 'provider' ) ), array_merge( $form, array( 'purpose' => 'observe_closed_manifest' ) ), alynt_ledger_form( $db, -301 ) ) as $bad ) alynt_ledger_run( $bad, false, 0 );
        $parsed = Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Request::parse( $form );
        foreach ( array( 'alynt_drime_backups_scan_observation_v1:', 'alynt_drime_backups_real_closed_writer_v1:' ) as $prefix ) { $bad = $form; $bad['nonce'] = wp_create_nonce( $prefix . hash( 'sha256', json_encode( $parsed['envelope'] ) ) ); alynt_ledger_run( $bad, false, 0 ); }
        alynt_core_login( 90000002 ); alynt_ledger_run( alynt_ledger_form( $db ), false, 0 );
        $token = alynt_core_login(); $bad = alynt_ledger_form( $db ); WP_Session_Tokens::get_instance( 90000001 )->destroy( $token ); alynt_ledger_run( $bad, false, 0 );
        alynt_core_login(); $form = alynt_ledger_form( $db ); $auth->query( 'START TRANSACTION READ ONLY' );
        try { alynt_ledger_run( $form, false, 0 ); alynt_core_assert( '1' === $auth->query( 'SELECT CAST(@@in_transaction+0 AS CHAR)' )->fetch_row()[0], 'Borrowed transaction was ended.' ); }
        finally { $auth->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
        alynt_core_assert( $original === alynt_ledger_frame( $db ), 'Preauthorization denials mutated protected rows.' );
        alynt_core_done( 'malformed_stale_separate_nonce_domains_subscriber_revocation_and_ambient_tx_before_protected_reads' );

        $i = 0;
        foreach ( array_keys( $values ) as $role ) {
            ++$i; $calls = 0;
            $change = function ( $life ) use ( &$calls, $role, $values ) { if ( 2 === ++$calls ) alynt_scan_seed( array( $role => $values[$role] ), 'off' ); return $life; };
            add_filter( 'nonce_life', $change );
            try { $o = alynt_ledger_run( $form, false, 10 + $i ); } finally { remove_filter( 'nonce_life', $change ); alynt_scan_seed( $values ); }
            alynt_core_assert( 'rolled_back' === alynt_core_property( $o, 'phase' ) && 0 === alynt_core_property( $o, 'writes' ), 'A stale manifest role escaped exact locked comparison.' );
        }
        alynt_core_assert( $original === alynt_ledger_frame( $db ), 'Stale-role controls left changes.' );
        alynt_core_done( 'each_of_ten_prelock_dependency_metadata_changes_refuses_at_fixed_lock_order_before_update' );

        foreach ( array( 2, 3 ) as $which ) {
            $token = alynt_core_login(); $form = alynt_ledger_form( $db ); $calls = 0;
            $revoke = function ( $life ) use ( &$calls, $which, $token ) { if ( $which === ++$calls ) alynt_core_mutate( function () use ( $token ) { WP_Session_Tokens::get_instance( 90000001 )->destroy( $token ); } ); return $life; };
            add_filter( 'nonce_life', $revoke );
            try { $o = alynt_ledger_run( $form, false, 2 === $which ? 10 : 30 ); } finally { remove_filter( 'nonce_life', $revoke ); }
            alynt_core_assert( ( 2 === $which ? 'idle' : 'committed' ) === alynt_core_property( $o, 'phase' ), 'Revocation erased historical commit or entered locks without auth.' );
            alynt_core_assert( ( 2 === $which ? $original : $expected ) === alynt_ledger_frame( $db ), 'Revocation durable outcome differs.' ); alynt_scan_seed( $values );
        }
        alynt_core_login(); $form = alynt_ledger_form( $db );
        alynt_core_done( 'genuine_prelock_and_postcommit_revocation_refuse_without_erasing_acknowledged_history' );

        $o = alynt_ledger_primitive();
        try { alynt_ledger_locked( $o ); foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $def ) alynt_ledger_nowait( $db, $def[0], true ); }
        finally { alynt_core_private( $o, 'finish' ); }
        alynt_core_assert( 'rolled_back' === alynt_core_property( $o, 'phase' ) && $original === alynt_ledger_frame( $db ), 'Ten-lock primitive changed durable rows.' );
        alynt_core_done( 'real_all_ten_native_locks_exclude_independent_nowait_and_confirm_owned_rollback' );

        // Real contention against the LAST required dependency, not merely safety.
        $db->query( 'START TRANSACTION' ); $name = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows()['failed'][0];
        $db->query( "SELECT option_id FROM {$wpdb->options} WHERE option_name='{$name}' FOR UPDATE" );
        try { $o = alynt_ledger_run( $form, false, 20 ); } finally { $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
        alynt_core_assert( 'rolled_back' === alynt_core_property( $o, 'phase' ) && 0 === alynt_core_property( $o, 'writes' ) && $original === alynt_ledger_frame( $db ), 'Last-lock timeout retried or committed.' );
        alynt_core_done( 'genuine_last_dependency_lock_timeout_no_update_no_retry_definite_rollback' );

        require __DIR__ . '/native-scan-ledger-failures.php'; alynt_scan_ledger_failures( $db, $original, $form, $raw, $values );
        alynt_core_assert( $facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(), 'Final borrowed auth state changed.' );
    } finally { $db->close(); }
}
