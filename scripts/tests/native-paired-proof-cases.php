<?php
/** Genuine fixed-owner positive controls plus owned physical atomicity probes. */
function alynt_native_pair_cases() {
    global $wpdb;
    $db = alynt_native_begin_db(); $table = $wpdb->options; $auth = $wpdb->dbh;
    $facts = $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row();
    try {
        list( $eight, $target, $form ) = alynt_pair_seed(); $original = alynt_native_begin_rows( $db );
        $o = Alynt_Drime_Backups_Uploader_Native_Closed_Begin::create();
        alynt_core_assert( false === alynt_pair_call( $o, 'complete', array() ) && 0 === alynt_core_property( $o, 'protected_reads' ), 'Completion without private begin was admitted.' );
        $callbacks = 0;
        $trap = function () use ( $db, $table, &$callbacks ) {
            $db->query( 'START TRANSACTION' );
            try {
                foreach ( array( 'alynt_drime_backups_schedule_safety_state', 'alynt_drime_backups_settings' ) as $name ) {
                    $r = $db->query( "SELECT option_id FROM {$table} WHERE option_name='{$name}' FOR UPDATE NOWAIT" );
                    alynt_core_assert( $r instanceof mysqli_result && 1 === $r->num_rows, 'Core callback ran under native pair/resolution locks.' ); $r->free();
                }
                ++$callbacks;
            } finally { $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
        };
        add_action( 'auth_cookie_valid', $trap );
        try {
            alynt_core_assert( true === alynt_pair_call( $o, 'disable', array( 'POST', $form ) ), 'Genuine paired completion failed.' );
            $cross_domain = alynt_pair_receipt_form( $form['command_id'] ); $cross_domain['nonce'] = $form['nonce'];
            alynt_pair_resolve( $cross_domain, false, 0 );
            list( , $r ) = alynt_pair_resolve( alynt_pair_receipt_form( $form['command_id'] ), 'completed', 2 );
        } finally { remove_action( 'auth_cookie_valid', $trap ); }
        alynt_pair_clean( $o );
        $rows = alynt_native_begin_rows( $db ); $s = Alynt_Drime_Backups_Uploader_Closed_Writer_Json_Contract::decode( $rows['alynt_drime_backups_schedule_safety_state']['value'] );
        $patched = Alynt_Drime_Backups_Uploader_Serialized_Settings_Target::inspect( $target, $form['site_uuid'], 'no' )['after'];
        alynt_core_assert( $patched === $rows['alynt_drime_backups_settings']['value'] && $original['alynt_drime_backups_settings']['option_id'] === $rows['alynt_drime_backups_settings']['option_id'] && 'no' === $rows['alynt_drime_backups_settings']['autoload'], 'Exact one-token target/metadata changed incorrectly.' );
        alynt_core_assert( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::supported( $s, $form['site_uuid'] ) && null === $s['writer']['pending'] && null === $s['writer']['authenticated_grant'] && $s['writer']['closure']['completion_id'] === $s['writer']['completion']['command']['command_id'] && $s['lifecycle']['permission_generation'] === $eight['lifecycle']['permission_generation'] + 2, 'Persisted pair provenance invalid.' );
        alynt_core_assert( 'matching_bytes' === $r['target'] && false === $r['admission_proven'] && false === $r['execution_supported'] && $callbacks >= 12, 'Positive resolution/callback control missing.' );
        alynt_core_assert( false === alynt_pair_call( $o, 'complete', array() ) && false === alynt_pair_call( $o, 'disable', array( 'POST', $form ) ) && $rows === alynt_native_begin_rows( $db ), 'Consumed continuation replayed.' );
        alynt_core_done( 'genuine_core_begin_private_continuation_atomic_pair_locked_completed_receipt_no_hooks_under_locks' );

        // Historical completion is not current effective state; restore only owned target.
        alynt_native_seed_rows( array( 'alynt_drime_backups_settings' => $target ) );
        list( , $r ) = alynt_pair_resolve( alynt_pair_receipt_form( $form['command_id'] ), 'completed', 2 );
        alynt_core_assert( 'superseded_bytes' === $r['target'], 'Historical event was confused with current target.' );
        alynt_pair_resolve( alynt_pair_receipt_form( '99999999-9999-4999-8999-999999999999' ), 'unresolved', 2 );
        alynt_pair_resolve( alynt_pair_receipt_form( $s['writer']['completion']['command']['command_id'] ), 'domain_conflict', 2 );
        alynt_pair_resolve( alynt_pair_receipt_form( $s['writer']['upgrade']['command']['command_id'] ), 'domain_conflict', 2 );
        alynt_pair_resolve( $form, false, 0 );
        alynt_pair_resolve( alynt_pair_receipt_form( $form['command_id'], -301 ), false, 0 );
        $bad = alynt_pair_receipt_form( $form['command_id'] ); $bad['nonce'] = 'bad'; alynt_pair_resolve( $bad, false, 0 );
        alynt_core_login( 90000002 ); alynt_pair_resolve( alynt_pair_receipt_form( $form['command_id'] ), false, 0 ); alynt_core_login();
        alynt_core_done( 'fresh_receipt_nonce_domain_stale_malformed_subscriber_absence_and_superseded_bytes' );

        require __DIR__ . '/native-paired-proof-failures.php'; alynt_pair_failures( $db );
        alynt_core_assert( $facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(), 'Application authentication connection changed.' );
    } finally { $db->close(); }
}
