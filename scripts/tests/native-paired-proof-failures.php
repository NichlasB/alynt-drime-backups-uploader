<?php
/** Owned native failure/visibility probes; protocol injections are labeled. */
function alynt_pair_failures( $db ) {
    global $wpdb; $table = $wpdb->options;
    list( , , $form ) = alynt_pair_seed(); $o = alynt_pair_closed( $form ); $pending = alynt_native_begin_rows( $db );
    alynt_core_mutate( function () { WP_Session_Tokens::get_instance( 90000001 )->destroy_all(); } );
    alynt_core_assert( false === alynt_pair_call( $o, 'complete', array() ) && 0 === alynt_core_property( $o, 'protected_reads' ) && $pending === alynt_native_begin_rows( $db ), 'Revoked continuation read or changed target.' ); alynt_pair_clean( $o );
    alynt_core_login(); alynt_core_done( 'genuine_between_transaction_revocation_no_target_effect' );
    list( , , $form ) = alynt_pair_seed(); $o = alynt_pair_closed( $form );
    $pending = alynt_native_begin_rows( $db );
    list( $c, $p, $writer ) = alynt_pair_primitive( $o );
    try {
        alynt_core_assert( true === alynt_core_private( $o, 'write_safety', array( $table, $c['a'], json_encode( $p['state'] ) ) ), 'First paired UPDATE did not execute.' );
        alynt_core_assert( $pending === alynt_native_begin_rows( $db ), 'Uncommitted first UPDATE leaked to independent reader.' );
        $bad = $c['b']; $bad['option_id'] = (string) ( (int) $bad['option_id'] + 1000000 );
        alynt_core_assert( false === alynt_core_private( $o, 'write_settings', array( $table, $bad, $p['target'] ) ), 'Zero-row second UPDATE was accepted.' );
    } finally { alynt_core_private( $o, 'finish' ); }
    alynt_core_assert( 'rolled_back' === alynt_core_property( $o, 'phase' ) && 2 === alynt_core_property( $o, 'writes' ) && $pending === alynt_native_begin_rows( $db ), 'Half-pair survived definite failure.' );
    alynt_pair_resolve( alynt_pair_receipt_form( $form['command_id'] ), 'begin_only_unresolved', 2 ); alynt_pair_clean( $o );
    alynt_core_done( 'actual_first_update_then_zero_affected_second_update_confirmed_rollback_prior_begin_preserved' );

    list( , , $form ) = alynt_pair_seed(); $o = alynt_pair_closed( $form ); $pending = alynt_native_begin_rows( $db );
    list( $c, $p, $writer ) = alynt_pair_primitive( $o ); $failed = false;
    try {
        alynt_core_assert( true === alynt_core_private( $o, 'write_safety', array( $table, $c['a'], json_encode( $p['state'] ) ) ), 'Disconnect probe first UPDATE missing.' );
        $thread = $writer->thread_id;
        alynt_core_assert( $thread > 0 && $thread !== $db->thread_id && $thread !== $wpdb->dbh->thread_id && true === $db->query( 'KILL CONNECTION ' . (int) $thread ), 'Exact owned writer termination not acknowledged.' );
        try { $failed = false === alynt_core_private( $o, 'write_settings', array( $table, $c['b'], $p['target'] ) ); }
        catch ( mysqli_sql_exception $error ) { $failed = true; }
    } finally { alynt_core_private( $o, 'finish' ); }
    alynt_core_assert( $failed && 'poisoned' === alynt_core_property( $o, 'phase' ) && $pending === alynt_native_begin_rows( $db ), 'Owned disconnect leaked half-pair or adopted another session.' ); alynt_pair_clean( $o );
    alynt_core_done( 'genuine_owned_disconnect_after_first_update_server_rollback_no_reconnect' );

    // Real in-flight pair: first barrier attempt cannot observe absence through it.
    foreach ( array( false, true ) as $commit ) {
        list( , , $form ) = alynt_pair_seed(); $o = alynt_pair_closed( $form ); $pending = alynt_native_begin_rows( $db );
        list( $c, $p, $writer ) = alynt_pair_primitive( $o );
        try {
            alynt_core_assert( true === alynt_core_private( $o, 'write_safety', array( $table, $c['a'], json_encode( $p['state'] ) ) ) && true === alynt_core_private( $o, 'write_settings', array( $table, $c['b'], $p['target'] ) ), 'In-flight pair writes failed.' );
            alynt_core_assert( $pending === alynt_native_begin_rows( $db ), 'Uncommitted pair visible outside transaction.' );
            list( $blocked ) = alynt_pair_resolve( alynt_pair_receipt_form( $form['command_id'] ), false, 1 );
            alynt_core_assert( 'rolled_back' === alynt_core_property( $blocked, 'phase' ), 'Blocked resolution did not terminate owned transaction.' );
            // Missing acknowledgement is a protocol simulation, not actual wire loss.
            alynt_native_begin_set( $o, 'phase', 'commit_sent' );
            if ( $commit ) alynt_core_assert( true === $writer->query( 'COMMIT AND NO CHAIN NO RELEASE' ), 'Primitive actual COMMIT failed.' );
        } finally { alynt_core_private( $o, 'finish' ); }
        alynt_core_assert( 'poisoned' === alynt_core_property( $o, 'phase' ) && false === alynt_pair_call( $o, 'complete', array() ), 'Unknown pair outcome allowed replay.' );
        list( , $r ) = alynt_pair_resolve( alynt_pair_receipt_form( $form['command_id'] ), $commit ? 'completed' : 'begin_only_unresolved', 2 );
        alynt_core_assert( $commit ? 'matching_bytes' === $r['target'] : $pending === alynt_native_begin_rows( $db ), 'Late physical outcome resolved incorrectly.' );
        alynt_pair_clean( $o );
    }
    alynt_core_done( 'genuine_inflight_barrier_timeout_then_late_commit_or_close_protocol_uncertainty_no_replay' );

    // Hold settings only: an omitted second locking read must not pass this control.
    $db->query( 'START TRANSACTION' ); $r = $db->query( "SELECT option_id FROM {$table} WHERE option_name='alynt_drime_backups_settings' FOR UPDATE" );
    alynt_core_assert( $r instanceof mysqli_result, 'Independent settings lock failed.' ); $r->free();
    try { list( $blocked ) = alynt_pair_resolve( alynt_pair_receipt_form( $form['command_id'] ), false, 2 ); }
    finally { $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
    alynt_core_assert( 'rolled_back' === alynt_core_property( $blocked, 'phase' ), 'Settings barrier contention was ignored.' );
    alynt_core_done( 'genuine_resolution_safety_first_settings_second_contention' );

    // The continuation does not refresh the original wall or monotonic deadline.
    list( , , $form ) = alynt_pair_seed(); $o = alynt_pair_closed( $form ); $pending = alynt_native_begin_rows( $db );
    alynt_native_begin_set( $o, 'started', hrtime( true ) - 10000000001 );
    alynt_core_assert( false === alynt_pair_call( $o, 'complete', array() ) && $pending === alynt_native_begin_rows( $db ), 'Continuation renewed original budget.' ); alynt_pair_clean( $o );
    alynt_core_done( 'original_monotonic_deadline_consumes_continuation_without_target_effect' );

    // Real nonce callback changes exact post-begin bytes at the final pre-lock guard.
    list( , , $form ) = alynt_pair_seed(); $o = alynt_pair_closed( $form ); $count = 0;
    $change = function ( $life ) use ( &$count ) {
        if ( 2 === ++$count ) {
            $d = alynt_native_begin_db();
            try { $table = $GLOBALS['alynt_core_proof']['prefix'] . 'options'; alynt_core_assert( true === $d->query( "UPDATE {$table} SET option_value=CONCAT(option_value,' ') WHERE option_name='alynt_drime_backups_schedule_safety_state'" ), 'Owned post-begin conflict fixture failed.' ); }
            finally { $d->close(); }
        }
        return $life;
    };
    add_filter( 'nonce_life', $change );
    try { alynt_core_assert( false === alynt_pair_call( $o, 'complete', array() ), 'Changed post-begin tuple passed locked comparison.' ); }
    finally { remove_filter( 'nonce_life', $change ); }
    alynt_core_assert( 0 === alynt_core_property( $o, 'writes' ) && 'rolled_back' === alynt_core_property( $o, 'phase' ), 'Conflict produced a pair write.' ); alynt_pair_clean( $o );
    alynt_core_done( 'genuine_final_prelock_auth_callback_exact_postbegin_conflict_no_pair_write' );

    foreach ( array( 'autoload', 'value' ) as $key ) {
        list( , , $form ) = alynt_pair_seed(); $o = alynt_pair_closed( $form ); $pending = alynt_native_begin_rows( $db );
        list( $c, $p ) = alynt_pair_primitive( $o );
        try {
            alynt_core_assert( true === alynt_core_private( $o, 'write_safety', array( $table, $c['a'], json_encode( $p['state'] ) ) ), 'CAS probe first UPDATE missing.' );
            $bad = $c['b']; $bad[$key] = 'autoload' === $key ? 'NO' : $bad[$key] . ' ';
            alynt_core_assert( false === alynt_core_private( $o, 'write_settings', array( $table, $bad, $p['target'] ) ), 'Settings binary CAS accepted collation-equivalent bytes.' );
        } finally { alynt_core_private( $o, 'finish' ); }
        alynt_core_assert( 'rolled_back' === alynt_core_property( $o, 'phase' ) && $pending === alynt_native_begin_rows( $db ), 'Settings CAS failure left a half-pair.' ); alynt_pair_clean( $o );
    }
    alynt_core_done( 'settings_binary_autoload_and_trailing_space_cas_refusals_atomic_rollback' );

    list( , , $form ) = alynt_pair_seed(); $o = alynt_pair_closed( $form ); $calls = 0;
    $revoke = function ( $life ) use ( &$calls ) {
        if ( 3 === ++$calls ) alynt_core_mutate( function () { WP_Session_Tokens::get_instance( 90000001 )->destroy_all(); } );
        return $life;
    };
    add_filter( 'nonce_life', $revoke );
    try { alynt_core_assert( false === alynt_pair_call( $o, 'complete', array() ), 'Post-commit revocation returned success.' ); }
    finally { remove_filter( 'nonce_life', $revoke ); }
    alynt_core_assert( 'committed' === alynt_core_property( $o, 'phase' ) && 2 === alynt_core_property( $o, 'writes' ), 'Post-commit revocation rewrote historical pair outcome.' ); alynt_pair_clean( $o );
    alynt_core_login(); alynt_pair_resolve( alynt_pair_receipt_form( $form['command_id'] ), 'completed', 2 );
    alynt_core_done( 'genuine_post_pair_commit_revocation_withholds_result_not_historical_effect' );
}
