<?php
/** One actual same-root commit and zero-DML barrier, no imported origin/native doubles. */
function alynt_native_lifecycle_positive() {
    global $wpdb;
    alynt_core_login(); $body = alynt_registration_form(); $o = Alynt_Test_Native_Lifecycle_Owner::create(); $audit = alynt_scan_db();
    $before = alynt_registration_frame( $audit ); $auth = $wpdb->dbh;
    $facts = $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row();
    $gates = array(); $start = null; $proposal = null; $writer = null; $barrier = null;
    $probe = function ( $life, $action ) use ( $o, &$gates, &$start, &$proposal, &$writer, &$barrier, $auth ) {
        $gates[] = $action; $n = count( $gates ); $now = alynt_core_property( $o, 'started' );
        if ( null === $start ) $start = $now;
        alynt_core_assert( is_int( $start ) && $start === $now && $GLOBALS['wpdb']->dbh === $auth, 'native_lifecycle_original_clock_and_borrowed_handle' );
        $reader = alynt_core_property( $o, 'reader' );
        if ( $reader instanceof mysqli ) alynt_core_assert( $reader !== $auth && '0' === $reader->query( 'SELECT CAST(@@in_transaction+0 AS CHAR)' )->fetch_row()[0], 'native_lifecycle_core_callbacks_only_while_owned_session_idle' );
        if ( 1 === $n ) alynt_core_assert( null === $reader && 0 === alynt_core_property( $o, 'protected_reads' ) && null === alynt_core_property( $o, 'capture_receipt' ), 'native_lifecycle_authorized_before_capture_or_factory' );
        if ( 2 === $n ) alynt_core_assert( null === $reader && true === alynt_core_property( $o, 'capture_receipt' ) && 20 === alynt_core_property( $o, 'protected_reads' ), 'native_lifecycle_capture_confirmed_closed_before_construction' );
        if ( 9 === $n || 10 === $n ) {
            alynt_core_assert( $reader instanceof mysqli && 'idle' === alynt_core_property( $o, 'phase' ) && 0 === alynt_core_property( $o, 'writes' ), 'native_lifecycle_mutation_revalidated_after_writer_setup' );
            if ( 9 === $n ) { $writer = $reader; alynt_native_lifecycle_thread( $writer ); $proposal = alynt_core_property( $o, 'proposal' ); }
            alynt_core_assert( $writer === $reader && $proposal === alynt_core_property( $o, 'proposal' ) && is_array( alynt_core_property( $o, 'admission_origin' ) ) && count( alynt_core_property( $o, 'admission_ids' ) ) >= 10, 'native_lifecycle_retained_original_proposal_before_dml' );
            $outcome = alynt_core_property( $o, 'native_outcome' );
            alynt_core_assert( 0 === $outcome['facts']['events']['begin_sent'] && 0 === $outcome['facts']['events']['dml_sent'], 'native_lifecycle_no_native_send_before_final_mutation_guards' );
        }
        if ( $n >= 11 ) {
            foreach ( array( 'before', 'proposal', 'context', 'after', 'recorded_at', 'native_outcome', 'native_outcome_writer', 'admission_origin', 'admission_clock' ) as $k ) alynt_core_assert( null === alynt_core_property( $o, $k ), 'native_lifecycle_writer_erased_before_read_guard_' . $k );
            alynt_core_assert( true === alynt_core_property( $o, 'admission_receipt' ) && 1 === alynt_core_property( $o, 'writes' ), 'native_lifecycle_confirmed_writer_close_before_read' );
            if ( 11 === $n ) { $closed = false; try { $writer->query( 'SELECT 1' ); } catch ( Error $error ) { $closed = true; } alynt_core_assert( $closed && null === $reader, 'native_lifecycle_writer_client_actually_closed' ); }
        }
        if ( 21 === $n || 22 === $n ) {
            alynt_core_assert( $reader instanceof mysqli && $reader !== $writer && 'idle' === alynt_core_property( $o, 'phase' ) && 0 === alynt_core_property( $o, 'barrier_reads' ) && 1 === alynt_core_property( $o, 'barrier_metadata_checks' ), 'native_lifecycle_read_revalidated_after_barrier_setup_before_begin' );
            if ( 21 === $n ) { $barrier = $reader; alynt_native_lifecycle_thread( $barrier ); }
            alynt_core_assert( $barrier === $reader, 'native_lifecycle_barrier_not_replaced' );
        }
        if ( $n >= 23 ) {
            alynt_core_assert( null === $reader && true === alynt_core_property( $o, 'barrier_receipt' ) && true === alynt_core_property( $o, 'finished' ) && 'barrier_released' === alynt_core_property( $o, 'phase' ), 'native_lifecycle_barrier_closed_and_data_consumed_before_postclose_guards' );
            if ( 23 === $n ) { $closed = false; try { $barrier->query( 'SELECT 1' ); } catch ( Error $error ) { $closed = true; } alynt_core_assert( $closed, 'native_lifecycle_barrier_client_actually_closed' ); }
        }
        return $life;
    };
    try {
        $r = alynt_native_lifecycle_call( $o, 'POST', $body, $probe );
        $GLOBALS['alynt_native_lifecycle_diagnostic'] = array( 'guards' => count( $gates ), 'phase' => alynt_core_property( $o, 'phase' ), 'admission_reads' => alynt_core_property( $o, 'protected_reads' ), 'writes' => alynt_core_property( $o, 'writes' ), 'barrier_reads' => alynt_core_property( $o, 'barrier_reads' ), 'capture_closed' => alynt_core_property( $o, 'capture_receipt' ), 'admission_closed' => alynt_core_property( $o, 'admission_receipt' ), 'barrier_closed' => alynt_core_property( $o, 'barrier_receipt' ) );
        alynt_core_assert( is_array( $r ) && 9 === count( $r ) && 'registered_closed' === $r['classification'] && true === $r['barrier_verified'] && false === $r['candidate_only'], 'native_lifecycle_nonvacuous_native_result' );
        foreach ( array( 'authenticated', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $key ) alynt_core_assert( false === $r[$key], 'native_lifecycle_result_never_authority_' . $key );
        array_walk_recursive( $r, function ( $v ) { alynt_core_assert( is_scalar( $v ) || null === $v, 'native_lifecycle_result_no_handle' ); } );
        alynt_core_assert( 24 === count( $gates ) && 4 === count( array_unique( $gates ) ), 'native_lifecycle_twentyfour_guards_four_domains' );
        $m = $gates[0]; $mf = $gates[5]; $s = $gates[11]; $f = $gates[13];
        alynt_core_assert( array( $m,$m,$m,$m,$m,$mf,$m,$mf,$m,$mf,$m,$s,$s,$f,$s,$f,$s,$f,$s,$f,$s,$f,$s,$f ) === $gates, 'native_lifecycle_exact_guard_order' );
        alynt_core_assert( 40 === alynt_core_property( $o, 'protected_reads' ) && 1 === alynt_core_property( $o, 'writes' ) && 10 === alynt_core_property( $o, 'barrier_reads' ) && 2 === alynt_core_property( $o, 'barrier_metadata_checks' ) && 2 === alynt_core_property( $o, 'zero_dml_checks' ), 'native_lifecycle_actual_bounded_counts' );
        alynt_core_assert( $proposal === alynt_admission_receipt( $audit, $before ), 'native_lifecycle_only_exact_original_ledger_successor' );
        alynt_core_assert( $facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(), 'native_lifecycle_borrowed_session_unchanged' );
        alynt_core_done( 'same_root_real_commit_original_ids_native_zero_dml_barrier_closed_erased_no_authority' );
    } finally { $audit->close(); }
}
