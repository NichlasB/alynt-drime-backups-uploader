<?php
/** Genuine capture-to-builder positives and fixed-row failures, no apply. */
function alynt_registration_native_cases() {
    global $wpdb;
    $values = alynt_registration_values(); alynt_scan_seed( $values ); alynt_core_login(); $form = alynt_registration_form(); $db = alynt_scan_db();
    try {
        // Before nonce-hook/lifetime probes: removing the initial guard must
        // fail on actual unauthorized protected reads, not hook call counts.
        $bad = $form; $bad['nonce'] = 'bad'; alynt_registration_native( $bad, 0 );
        $frame = alynt_registration_frame( $db ); $auth = $wpdb->dbh;
        $facts = $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row();
        $r = alynt_registration_native( $form, 20, true );
        $data = alynt_registration_eligible( $frame );
        alynt_core_assert( is_array( $data ) && $data['dependency_digest'] === $r['proposal']['dependency_digest'] && in_array( wp_verify_nonce( $r['nonce'], $r['action'] ), array( 1, 2 ), true ), 'Genuine twenty-row capture not bound to constructed command.' );
        alynt_core_assert( $facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row() && $frame === alynt_registration_frame( $db ), 'Borrowed auth or protected fixture bytes changed.' );
        alynt_core_done( 'genuine_positive_twenty_native_reads_full_eligibility_command_nonce_no_writes' );

        $issue = 0; $observed = false; $closed = false;
        $probe = function ( $life, $action ) use ( &$issue, &$observed, &$closed, $auth ) {
            $o = $GLOBALS['alynt_registration_native_owner'];
            if ( 0 === strpos( $action, 'alynt_drime_backups_scan_registration_command_v2:' ) ) { $closed = null === alynt_core_property( $o, 'reader' ); return $life; }
            if ( 2 !== ++$issue ) return $life;
            $reader = alynt_core_property( $o, 'reader' );
            alynt_core_assert( $reader instanceof mysqli && $reader !== $auth && $reader->thread_id !== $auth->thread_id && '1' === $reader->query( 'SELECT CAST(@@tx_read_only+0 AS CHAR)' )->fetch_row()[0], 'Private reader not separately owned/server-read-only.' );
            $write = null; $table = $GLOBALS['alynt_core_proof']['prefix'] . 'options';
            try { $write = $reader->query( "UPDATE {$table} SET option_value=option_value WHERE option_name='alynt_drime_backups_upload_queue'" ); }
            catch ( Throwable $error ) { $write = 1792 === $reader->errno ? false : null; }
            alynt_core_assert( false === $write && 1792 === $reader->errno, 'Native server accepted an owned-fixture write on the reader.' ); $observed = true;
            return $life;
        };
        add_filter( 'nonce_life', $probe, 10, 2 );
        try { alynt_registration_native( $form, 20, true ); } finally { remove_filter( 'nonce_life', $probe, 10 ); }
        alynt_core_assert( $observed && $closed, 'Reader not proved read-only and closed before nonce construction.' );
        alynt_core_done( 'separate_server_readonly_reader_rejects_fixture_dml_closed_before_nonce_creation' );

        foreach ( array( 'off', 'auto-off' ) as $autoload ) { alynt_scan_seed( $values, $autoload ); alynt_registration_native( $form, 20, true ); }
        alynt_scan_seed( $values );
        foreach ( $values as $role => $raw ) { alynt_scan_seed( array( $role => $raw ), 'yes' ); alynt_registration_native( $form, 20 ); alynt_scan_seed( array( $role => $raw ) ); }
        $position = 0;
        foreach ( $values as $role => $raw ) { ++$position; alynt_scan_delete( $role ); alynt_registration_native( $form, $position ); alynt_scan_seed( array( $role => $raw ) ); }
        alynt_registration_native( $form, 20, true );
        // Deliberate deletion/reinsertion creates NEW owned row identities.
        // Capture that expected fixture baseline; do not pretend IDs survived.
        $frame = alynt_registration_frame( $db );
        alynt_core_done( 'every_missing_role_exact_read_bound_and_all_autoload_metadata_without_defaults' );

        foreach ( array( 'connection' => array( 3, 131073 ), 'ledger' => array( 4, 131073 ), 'queue' => array( 7, 1048577 ) ) as $role => $case ) {
            alynt_scan_seed( array( $role => str_repeat( 'x', $case[1] ) ) ); alynt_registration_native( $form, $case[0] ); alynt_scan_seed( array( $role => $values[$role] ) );
        }
        foreach ( array( 'safety' => '{}', 'ledger' => '{}', 'settings' => 'a:0:{}', 'connection' => 'O:8:"stdClass":0:{}', 'queue' => 'a:1:{i:0;b:1;}' ) as $role => $raw ) { alynt_scan_seed( array( $role => $raw ) ); alynt_registration_native( $form, 20 ); alynt_scan_seed( array( $role => $values[$role] ) ); }
        $s = json_decode( $values['safety'], true ); $s['writer']['authenticated_grant'] = array( 'active' => true ); alynt_scan_seed( array( 'safety' => json_encode( $s ) ) ); alynt_registration_native( $form, 20 ); alynt_scan_seed( $values );
        alynt_scan_case_name(); try { alynt_registration_native( $form, 7 ); } finally { alynt_scan_case_name( true ); }
        alynt_core_assert( $frame === alynt_registration_frame( $db ), 'Negative storage cases failed exact fixture reconciliation.' );
        alynt_core_done( 'actual_prefetch_caps_binary_names_full_schema8_genesis_and_codecs_fail_closed' );
        $GLOBALS['alynt_registration_native_baseline'] = array( $values, $frame, $form );
    } finally { $db->close(); }
}
