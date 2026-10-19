<?php
/** Owned physical primitives and genuine real-clock refusal, not lost-wire/crash proof. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) ) exit( 1 );
function alynt_contention_failures() {
    global $wpdb;
    foreach ( array( 'begin_sent', 'commit_sent' ) as $phase ) {
        $o = Alynt_Drime_Backups_Uploader_Registration_Observation_Construction::create(); $auth = $wpdb->dbh;
        $facts = alynt_core_private( $o, 'native_idle', array( $auth ) ); $audit = alynt_scan_db(); $before = alynt_barrier_frame( $audit );
        try {
            alynt_core_assert( alynt_core_private( $o, 'open_writer', array( $auth, $facts ) ) && alynt_core_private( $o, 'barrier_zero_dml' ), 'Unread-ack physical primitive setup failed.' );
            $reader = alynt_core_property( $o, 'reader' );
            if ( 'commit_sent' === $phase ) alynt_core_assert( true === $reader->query( 'START TRANSACTION READ WRITE' ) && alynt_core_private( $o, 'session', array( true ) ), 'Unread COMMIT primitive has no owned transaction.' );
            alynt_origin_set( $o, 'phase', $phase );
            $sql = 'begin_sent' === $phase ? 'START TRANSACTION READ WRITE' : 'COMMIT AND NO CHAIN NO RELEASE';
            // Native async send, deliberately NEVER reap the acknowledgement. This
            // is not packet loss, server crash or an uncertain admission write.
            alynt_core_assert( true === $reader->query( $sql, MYSQLI_ASYNC ), 'Owned async command was not queued.' );
            alynt_core_private( $o, 'close_barrier' );
            alynt_core_assert( 'poisoned' === alynt_core_property( $o, 'phase' ) && null === alynt_core_property( $o, 'reader' ) && null === alynt_core_property( $o, 'physical' ), 'Unread native acknowledgement became definite success or an owned retry.' );
            alynt_core_assert( $before === alynt_barrier_frame( $audit ) && $facts === alynt_core_private( $o, 'native_idle', array( $auth ) ) && $auth === $wpdb->dbh, 'Unread-ack primitive changed owned rows/authentication.' );
        } finally { alynt_core_private( $o, 'close_barrier' ); $audit->close(); }
        alynt_core_done( 'physical_' . $phase . '_unreaped_native_ack_poisoned_not_wire_loss_or_auth_coordinator_proof' );
    }
    list( $o, $i ) = alynt_barrier_fixture(); $facts = array(); $delayed = false;
    $probe = alynt_handoff_probe( $facts, function ( $n, $life ) use ( &$delayed, $o ) {
        if ( 7 === $n ) {
            alynt_core_assert( 'idle' === alynt_core_property( $o, 'phase' ) && 0 === alynt_core_property( $o, 'barrier_reads' ), 'Real deadline test must run before BEGIN/payload.' );
            $at = hrtime( true ); time_nanosleep( 10, 100000000 ); $delayed = hrtime( true ) - $at >= 10000000000;
        }
        return $life;
    } );
    add_filter( 'nonce_life', $probe, 10, 2 ); $at = hrtime( true );
    try { alynt_barrier_run( $o, $i, false, 0 ); } finally { remove_filter( 'nonce_life', $probe, 10 ); }
    alynt_core_assert( $delayed && 7 === count( $facts ) && hrtime( true ) - $at < 15000000000 && 'idle' === alynt_core_property( $o, 'phase' ) && 0 === alynt_core_property( $o, 'zero_dml_checks' ), 'Original real acceptance budget was reset or reached native payload I/O.' );
    alynt_core_done( 'genuine_original_real_clock_budget_overrun_before_begin_refuses_without_payload_or_retry' );
    $audit = alynt_scan_db(); $before = alynt_barrier_frame( $audit ); $raw = $before['ledger'][3]; $changed = json_decode( $raw, true ); $changed['ledger_revision']++; $peer = null;
    try {
        $peer = new Alynt_Registration_Contention_Process( 'timeout', $raw, json_encode( $changed ) ); $ready = $peer->event( 'ready' );
        alynt_core_assert( $ready['thread'] !== $wpdb->dbh->thread_id, 'Cleanup peer borrowed authentication.' ); $peer->abort();
        $stmt = $audit->prepare( 'SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID=?' ); $stmt->bind_param( 'i', $ready['thread'] ); $stmt->execute(); $r = $stmt->get_result();
        alynt_core_assert( 0 === (int) $r->fetch_row()[0], 'Aborted owned peer session survived cleanup.' ); $r->free(); $stmt->close();
        alynt_core_assert( $before === alynt_barrier_frame( $audit ), 'Peer EOF/abort did not roll back its owned uncommitted CAS.' );
    } finally { if ( $peer ) $peer->abort(); $audit->close(); }
    alynt_core_done( 'physical_peer_parent_channel_eof_rolls_back_owned_fixture_update_no_orphan_session' );
}
