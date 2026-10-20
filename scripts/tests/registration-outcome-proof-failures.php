<?php
/** Actual fixed native transaction paths; candidate/cursor faults below are explicit instrumentation. */
function alynt_outcome_failures() {
    global $wpdb;
    alynt_core_login(); $values = alynt_registration_values(); alynt_scan_seed( $values ); $db = alynt_scan_db(); $original = alynt_registration_frame( $db );
    try {
        // No imported event labels, manually started transaction or synthetic phase ownership.
        foreach ( array_keys( $values ) as $role ) {
            list( $o, $core, $e ) = alynt_admission_primitive( $original );
            try {
                alynt_scan_seed( array( $role => $values[$role] ), 'off' );
                alynt_core_assert( false === alynt_core_private( $o, 'admission_transaction', array( $core, $e ) ), 'Stale native tuple committed.' );
            } finally { alynt_core_private( $o, 'finish' ); alynt_scan_seed( $values ); }
            $s = alynt_outcome_finished( $o, array( 'writer_created', 'begin_sent', 'begin_ack_at', 'transaction_owned', 'rollback_sent', 'rollback_ack_at', 'rollback_idle', 'detached', 'close_at' ) );
            alynt_core_assert( true === $s['raw_returns']['begin'] && true === $s['raw_returns']['rollback'] && 'not_called' === $s['raw_returns']['dml'] && 0 === $s['facts']['events']['attempt_reserved'], 'Pre-DML stale refusal fabricated a send/attempt or lost rollback ack.' );
            alynt_core_assert( $original === alynt_registration_frame( $db ), 'Stale native transaction changed durable frame.' );
        }
        alynt_core_done( 'primitive_all_ten_real_begin_locked_stale_refusals_acknowledged_rollback_close' );

        list( $o, $core, $e ) = alynt_admission_primitive( $original );
        try {
            // Fault injection forces readback refusal AFTER ten genuine lock reads and real DML.
            // It is not a claim of thirty earlier real captures or full authenticated admission.
            alynt_ledger_set( $o, 'protected_reads', 30 );
            alynt_core_assert( false === alynt_core_private( $o, 'admission_transaction', array( $core, $e ) ), 'Instrumented read ceiling allowed commit.' );
        } finally { alynt_core_private( $o, 'finish' ); }
        $s = alynt_outcome_finished( $o, array( 'writer_created', 'begin_sent', 'begin_ack_at', 'transaction_owned', 'attempt_reserved', 'bind_verified', 'dml_sent', 'dml_ack_at', 'rollback_sent', 'rollback_ack_at', 'rollback_idle', 'detached', 'close_at' ) );
        alynt_core_assert( true === $s['raw_returns']['dml'] && 'one' === $s['facts']['affected_rows'] && 0 === $s['facts']['events']['readback'] && 'not_called' === $s['raw_returns']['commit'], 'Real DML/readback refusal observations conflated.' );
        alynt_core_assert( $original === alynt_registration_frame( $db ), 'Acknowledged rollback retained a ledger write.' );
        alynt_core_done( 'primitive_real_update_instrumented_readback_ceiling_native_acknowledged_rollback' );

        list( $o, $core, $e ) = alynt_admission_primitive( $original ); $timed_out = false;
        $db->query( 'START TRANSACTION' ); $name = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows()['failed'][0];
        $stmt = $db->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name=? FOR UPDATE" ); $stmt->bind_param( 's', $name ); $stmt->execute(); $stmt->get_result()->free(); $stmt->close();
        try {
            try { $r = alynt_core_private( $o, 'admission_transaction', array( $core, $e ) ); $timed_out = false === $r; }
            catch ( mysqli_sql_exception $error ) { $timed_out = 1205 === $error->getCode(); }
        } finally { alynt_core_private( $o, 'finish' ); $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
        $s = alynt_outcome_finished( $o, array( 'begin_sent', 'begin_ack_at', 'transaction_owned', 'rollback_sent', 'rollback_ack_at', 'rollback_idle', 'detached', 'close_at' ) );
        alynt_core_assert( $timed_out && 'not_called' === $s['raw_returns']['dml'] && $original === alynt_registration_frame( $db ), 'Real owned contention did not refuse before DML/clean rollback.' );
        alynt_core_done( 'primitive_actual_last_row_lock_timeout_fixed_cleanup_no_retry' );

        list( $o, $core, $e ) = alynt_admission_primitive( $original );
        try {
            alynt_core_assert( true === alynt_core_private( $o, 'admission_transaction', array( $core, $e ) ), 'Primitive positive commit unavailable.' );
            $writer = alynt_core_property( $o, 'reader' ); alynt_core_assert( true === $writer->close(), 'Owned client-close fault did not occur.' );
        } finally { alynt_core_private( $o, 'finish' ); }
        $s = alynt_outcome_finished( $o, array_slice( alynt_outcome_commit_order(), 0, -1 ) );
        alynt_core_assert( true === $s['raw_returns']['commit'] && $s['facts']['events']['commit_idle'] > 0 && 'unknown' === $s['raw_returns']['close'] && 'unknown' === $s['facts']['acks']['close'] && 0 === $s['facts']['events']['close_at'] && 'poisoned' === alynt_core_property( $o, 'phase' ), 'Close exception promoted release or erased committed history.' );
        alynt_admission_receipt( $db, $original ); alynt_scan_seed( $values );
        alynt_core_done( 'primitive_real_commit_then_owned_double_close_exception_preserves_history_without_release' );

        list( $o ) = alynt_admission_primitive( $original ); $auth = $wpdb->dbh; $owned = alynt_core_property( $o, 'reader' );
        $facts = $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row();
        alynt_ledger_set( $o, 'reader', $auth ); // Explicit foreign-slot corruption, never a supported handle import.
        $s = alynt_outcome_finished( $o, array( 'writer_created' ), false );
        $closed = false; try { $owned->query( 'SELECT 1' ); } catch ( Error $error ) { $closed = true; }
        // Invalid capture refuses further acknowledgements: do not invent a
        // positive close certificate even though the exact owned client closed.
        alynt_core_assert( $closed && 'not_called' === $s['raw_returns']['close'] && 0 === $s['facts']['events']['close_at'], 'Poisoned ownership promoted close evidence or leaked exact owned client.' );
        alynt_core_assert( $facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(), 'Borrowed auth session changed or was closed by corrupted cleanup.' );
        alynt_core_assert( $original === alynt_registration_frame( $db ), 'Failure proof left protected fixture changes.' );
        alynt_core_done( 'primitive_foreign_reader_slot_poison_exact_owned_close_borrowed_session_preserved' );
    } finally { $db->close(); }
}
