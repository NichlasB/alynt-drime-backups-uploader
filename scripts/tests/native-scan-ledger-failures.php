<?php
/** Native fault primitives; not genuine HTTP entry points or lost-wire-ack proof. */
function alynt_scan_ledger_failures( $db, $original, $form, $raw, $values ) {
    global $wpdb; $table = $wpdb->options; $e = Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Request::parse( $form )['envelope'];
    foreach ( array( 'option_id', 'autoload', 'value' ) as $field ) {
        $o = alynt_ledger_primitive();
        try {
            $rows = alynt_ledger_locked( $o ); $before = $rows['ledger'];
            if ( 'option_id' === $field ) $before[$field] = (string) ( (int) $before[$field] + 100000 );
            elseif ( 'autoload' === $field ) $before[$field] = 'NO'; else $before[$field] .= ' ';
            alynt_core_assert( false === alynt_core_private( $o, 'write_ledger', array( $table, $before, $raw ) ) && 1 === alynt_core_property( $o, 'writes' ), 'Exact ledger CAS accepted conflicting ' . $field );
        } finally { alynt_core_private( $o, 'finish' ); }
        alynt_core_assert( 'rolled_back' === alynt_core_property( $o, 'phase' ) && $original === alynt_ledger_frame( $db ), 'Failed CAS changed durable ledger.' );
    }
    alynt_core_done( 'native_ledger_cas_exact_id_binary_autoload_and_bytes_zero_affected_is_not_success' );

    $o = alynt_ledger_primitive();
    try {
        alynt_ledger_set( $o, 'protected_reads', 20 );
        alynt_core_assert( false === alynt_core_private( $o, 'transact', array( alynt_core_private( $o, 'frame' ), $e, $original['rows'], $raw ) ) && 1 === alynt_core_property( $o, 'writes' ), 'Post-UPDATE readback fence did not reach native UPDATE.' );
    } finally { alynt_core_private( $o, 'finish' ); }
    alynt_core_assert( 'rolled_back' === alynt_core_property( $o, 'phase' ) && $original === alynt_ledger_frame( $db ), 'Unverified readback committed a ledger change.' );
    alynt_core_done( 'real_update_followed_by_readback_budget_failure_acknowledged_rollback_no_durable_delta' );

    foreach ( array( false, true ) as $commit ) {
        $o = alynt_ledger_primitive(); $writer = alynt_core_property( $o, 'reader' );
        try {
            $rows = alynt_ledger_locked( $o ); alynt_core_assert( alynt_core_private( $o, 'write_ledger', array( $table, $rows['ledger'], $raw ) ), 'Unknown-outcome control never updated fixture.' );
            alynt_ledger_set( $o, 'phase', 'commit_sent' );
            if ( $commit ) alynt_core_assert( true === $writer->query( 'COMMIT AND NO CHAIN NO RELEASE' ), 'Physical commit primitive failed.' );
            // Omit the owner's ack transition on two real SQL outcomes. This is
            // a protocol simulation, NOT an actual dropped COMMIT response.
        } finally { alynt_core_private( $o, 'finish' ); }
        $expected = $original;
        if ( $commit ) { $expected['rows']['ledger']['value'] = $raw; $expected['rows']['ledger']['octets'] = (string) strlen( $raw ); }
        alynt_core_assert( 'poisoned' === alynt_core_property( $o, 'phase' ) && $expected === alynt_ledger_frame( $db ), 'Ambiguous COMMIT assumed one outcome or reconnected.' );
        alynt_core_assert( false === alynt_core_private( $o, 'checkpoint', array( 'POST', $form ) ) && 1 === alynt_core_property( $o, 'writes' ), 'Ambiguous owner replayed.' ); alynt_scan_seed( $values );
    }
    alynt_core_done( 'protocol_ack_omission_simulation_real_committed_and_uncommitted_sessions_remain_poisoned_no_replay' );

    $o = alynt_ledger_primitive(); $writer = alynt_core_property( $o, 'reader' );
    try {
        $rows = alynt_ledger_locked( $o ); alynt_core_assert( alynt_core_private( $o, 'write_ledger', array( $table, $rows['ledger'], $raw ) ), 'Disconnect primitive did not reach UPDATE.' );
        $thread = $writer->thread_id; alynt_core_assert( $thread !== $db->thread_id && $thread !== $wpdb->dbh->thread_id, 'Would kill a borrowed connection.' );
        alynt_core_assert( true === $db->query( 'KILL CONNECTION ' . (int) $thread ), 'Owned native disconnect failed.' );
    } finally { alynt_core_private( $o, 'finish' ); }
    alynt_core_assert( 'poisoned' === alynt_core_property( $o, 'phase' ) && $original === alynt_ledger_frame( $db ), 'Killed owned precommit transaction changed durable state.' );
    alynt_core_done( 'real_owned_connection_kill_after_update_no_reconnect_no_commit_unchanged_application_rows' );

    $o = alynt_ledger_primitive();
    try {
        alynt_core_assert( alynt_core_private( $o, 'metadata', array( $table ) ), 'Supported fixture metadata refused.' );
        alynt_core_assert( false === alynt_core_private( $o, 'metadata', array( $wpdb->users ) ) && false === alynt_core_private( $o, 'metadata', array( $table . '`' ) ), 'Wrong schema/identifier was accepted.' );
    } finally { alynt_core_private( $o, 'finish' ); }
    foreach ( array( 'ledger' => 131073, 'queue' => 1048577 ) as $role => $bytes ) {
        $form = alynt_ledger_form( $db ); alynt_scan_seed( array( $role => str_repeat( 'x', $bytes ) ) );
        $index = array_search( $role, array_keys( $values ), true ); $o = alynt_ledger_run( $form, false, $index + 1 );
        alynt_core_assert( 0 === alynt_core_property( $o, 'writes' ), 'Oversized projection reached ledger UPDATE.' ); alynt_scan_seed( $values );
    }
    alynt_core_done( 'native_metadata_wrong_schema_identifier_and_prefetch_payload_caps_refuse_without_update' );

    alynt_scan_seed( array( 'ledger' => '{}' ) ); $bad = alynt_ledger_form( $db ); $o = alynt_ledger_run( $bad, false, 10 );
    alynt_core_assert( 0 === alynt_core_property( $o, 'writes' ), 'Malformed closed ledger reached UPDATE.' ); alynt_scan_seed( $values );
    alynt_ledger_run( alynt_ledger_form( $db ), true, 30 ); alynt_scan_seed( $values );
    alynt_core_assert( $original === alynt_ledger_frame( $db ), 'Final proof reconciliation failed.' );
    alynt_core_done( 'malformed_ledger_refuses_before_begin_final_genuine_positive_recovery_and_reconciliation' );
}
