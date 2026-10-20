<?php
/** Genuine client failure, never a forged native return or artificial exception. */
function alynt_selected_refusal_shape( $guards, $creates, $pin, $phase ) {
    return 8 === $guards && 1 === $creates && is_array( $pin ) && 'poisoned' === $phase;
}
function alynt_selected_cases() {
    global $wpdb;
    $db = alynt_scan_db(); $auth = $wpdb->dbh; $roles = array( 'auth' => $auth->thread_id, 'capture' => null, 'writer' => null );
    try {
        alynt_scan_seed( alynt_registration_values() ); alynt_core_login(); $form = alynt_registration_form(); $before = alynt_registration_frame( $db );
        foreach ( array( array(), array_merge( $form, array( 'nonce' => 'bad' ) ) ) as $bad ) alynt_join_erased( alynt_admit( $bad, false, 0, 0, 'POST', true ), $bad );
        alynt_core_assert( $before === alynt_registration_frame( $db ), 'Malformed intent changed owned ledger.' );
        alynt_core_done( 'selected_malformed_genuine_intents_zero_io' );
        $gates = array(); $creates = 0; $pin = null; $capture = null; $writer = null;
        $facts = $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row();
        $probe = function ( $life, $action, $verify, $o ) use ( &$gates, &$creates, &$roles, &$pin, &$capture, &$writer, $auth ) {
            if ( ! $verify ) { ++$creates; return $life; }
            $gates[] = $action; $n = count( $gates ); $reader = alynt_core_property( $o, 'reader' );
            alynt_core_assert( $GLOBALS['wpdb']->dbh === $auth && $n <= 8, 'Failure refreshed a guard or replaced auth.' );
            if ( 1 === $n ) alynt_core_assert( 0 === alynt_core_property( $o, 'protected_reads' ), 'Protected IO preceded authorization.' );
            if ( 2 === $n ) { $capture = $reader; $roles['capture'] = $reader->thread_id; }
            if ( 7 === $n ) { $writer = $reader; $roles['writer'] = $reader->thread_id; $pin = alynt_join_pin( $o ); alynt_selected_bind( $o, $roles, $auth, $capture ); }
            if ( 8 === $n ) alynt_observation_recheck( $o );
            return $life;
        };
        alynt_selected_root();
        $o = alynt_join_run( $form, false, 40, 1, $probe );
        alynt_core_assert( alynt_selected_refusal_shape( count( $gates ), $creates, $pin, alynt_core_property( $o, 'phase' ) ), 'Real unknown-COMMIT refusal path was not observed.' );
        $small = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( get_current_blog_id(), 'register_closed' );
        $command = array_map( 'strval', $pin[4]['command'] ); $command['nonce'] = 'pending'; $parsed = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $command );
        alynt_core_assert( is_array( $parsed ) && $small !== $parsed['action'] && array( $small, $small, $small, $small, $small, $parsed['action'], $small, $parsed['action'] ) === $gates, 'Nonce domains/guard order changed.' );
        // Independent direct-backend audit: do not reset or repair the ledger first.
        alynt_core_assert( $pin[4] === alynt_admission_receipt( $db, $before ), 'Stored proposal differs from original pre-BEGIN pin.' );
        foreach ( array( $capture, $writer ) as $handle ) { $closed = false; try { $handle->query( 'SELECT 1' ); } catch ( Error $e ) { $closed = true; } alynt_core_assert( $closed, 'Owned native handle survived.' ); }
        alynt_core_assert( $facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(), 'Borrowed auth state changed.' );
        foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $def ) alynt_ledger_nowait( $db, $def[0], false );
        alynt_core_done( 'selected_actual_client_refusal_original_proposal_other_nine_rows_locks_erasure' );
        return count( $gates );
    } finally { $db->close(); }
}
