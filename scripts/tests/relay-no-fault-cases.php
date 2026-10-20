<?php
/** Positive genuine joined admission plus pre-I/O denials; no faults or suppression. */
function alynt_relay_no_fault_cases() {
    global $wpdb;
    $values = alynt_registration_values(); $db = alynt_scan_db(); $auth = $wpdb->dbh;
    $roles = array( 'auth' => $auth->thread_id, 'capture' => null, 'writer' => null );
    try {
        alynt_scan_seed( $values ); alynt_core_login(); $form = alynt_registration_form(); $before = alynt_registration_frame( $db );
        foreach ( array( array(), array_merge( $form, array( 'nonce' => 'bad' ) ) ) as $bad ) alynt_join_erased( alynt_admit( $bad, false, 0, 0, 'POST', true ), $bad );
        alynt_core_assert( $before === alynt_registration_frame( $db ), 'Malformed relay intent changed ledger.' );
        alynt_core_done( 'relay_genuine_malformed_intents_zero_protected_io' );
        $gates = array(); $creates = 0; $pin = null; $capture = null; $writer = null; $observed = null;
        $facts = $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row();
        $probe = function ( $life, $action, $verify, $o ) use ( &$roles, &$gates, &$creates, &$pin, &$capture, &$writer, &$observed, $auth ) {
            if ( ! $verify ) { ++$creates; return $life; }
            $gates[] = $action; $n = count( $gates ); $reader = alynt_core_property( $o, 'reader' );
            alynt_core_assert( $GLOBALS['wpdb']->dbh === $auth, 'Borrowed auth replaced.' );
            if ( 1 === $n ) alynt_core_assert( 0 === alynt_core_property( $o, 'protected_reads' ), 'Authorization followed protected IO.' );
            if ( 2 === $n ) { $capture = $reader; $roles['capture'] = $reader->thread_id; }
            if ( 7 === $n ) { $writer = $reader; $roles['writer'] = $reader->thread_id; $pin = alynt_join_pin( $o ); alynt_core_assert( 'idle' === alynt_core_property( $o, 'phase' ) && 0 === alynt_core_property( $o, 'writes' ), 'Writer binding followed BEGIN/DML.' ); }
            if ( 9 === $n ) $observed = alynt_outcome_snapshot( $o, alynt_outcome_commit_order() );
            return $life;
        };
        alynt_join_run( $form, true, 40, 1, $probe );
        alynt_core_assert( 10 === count( $gates ) && 1 === $creates && is_array( $observed ) && is_array( $pin ), 'Genuine positive relay path did not complete.' );
        alynt_outcome_commit( $observed );
        $small = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( get_current_blog_id(), 'register_closed' );
        $command_form = array_map( 'strval', $pin[4]['command'] ); $command_form['nonce'] = 'pending'; $parsed = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $command_form );
        alynt_core_assert( is_array( $parsed ) && $small !== $parsed['action'] && array( $small, $small, $small, $small, $small, $parsed['action'], $small, $parsed['action'], $small, $parsed['action'] ) === $gates, 'Separate nonce domains/guard order changed.' );
        alynt_core_assert( $pin[4] === alynt_admission_receipt( $db, $before ) && 3 === count( array_unique( $roles ) ), 'Stored proposal or distinct genuine session identity mismatch.' );
        foreach ( array( $capture, $writer ) as $client ) { $closed = false; try { $client->query( 'SELECT 1' ); } catch ( Error $error ) { $closed = true; } alynt_core_assert( $closed, 'Owned native session survived.' ); }
        alynt_core_assert( $facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(), 'Borrowed auth session altered.' );
        foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $def ) alynt_ledger_nowait( $db, $def[0], false );
        alynt_scan_seed( $values ); alynt_core_assert( $before === alynt_registration_frame( $db ), 'Owned frame reconciliation failed.' );
        alynt_core_done( 'relay_genuine_positive_original_proposal_guard_order_closed_clients_no_locks' );
        return $roles; // Inherited private stdout to relay supervisor only; removed before operator receipt.
    } finally { $db->close(); }
}
