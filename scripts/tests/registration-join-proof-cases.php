<?php
/** Actual final-class joined entry, original retained identities, native commit and late refusal. */
function alynt_join_cases() {
    global $wpdb;
    $values = alynt_registration_values(); $db = alynt_scan_db(); $auth = $wpdb->dbh;
    try {
        foreach ( array( 'positive', 'revoke_after_commit', 'late_origin_loss' ) as $case ) {
            alynt_scan_seed( $values ); $token = alynt_core_login(); $form = alynt_registration_form(); $before = alynt_registration_frame( $db );
            $eligible = alynt_registration_eligible( $before ); $pin = null; $start = null; $gates = array(); $creates = 0; $observed = null; $fired = false; $capture = null; $writer = null;
            $facts = $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row();
            $probe = function ( $life, $action, $verify, $o ) use ( &$pin, &$start, &$gates, &$creates, &$observed, &$fired, &$capture, &$writer, $eligible, $before, $auth, $db, $case, $token ) {
                if ( ! $verify ) { ++$creates; return $life; }
                $n = count( $gates ) + 1; $gates[] = $action;
                if ( null === $start ) $start = alynt_core_property( $o, 'started' );
                alynt_core_assert( $start === alynt_core_property( $o, 'started' ) && $GLOBALS['wpdb']->dbh === $auth, 'Original root/auth identity was replaced.' );
                $p = alynt_join_pin( $o ); $reader = alynt_core_property( $o, 'reader' );
                if ( $n <= 6 ) alynt_core_assert( null === $p[0] && array() === $p[1] && null === $p[2], 'Origin retained before independent command verification.' );
                if ( 1 === $n ) alynt_core_assert( 0 === alynt_core_property( $o, 'protected_reads' ) && 0 === alynt_core_property( $o, 'writes' ), 'Initial authorization followed protected I/O.' );
                if ( 2 === $n ) { $capture = $reader; alynt_core_assert( $reader instanceof mysqli && $reader !== $auth && '1' === $reader->query( 'SELECT CAST(@@tx_read_only+0 AS CHAR)' )->fetch_row()[0], 'Capture did not use separate server-read-only client.' ); }
                if ( 6 === $n ) alynt_core_assert( null === $reader && null === $p[4], 'Command authorization followed retention or used an open capture client.' );
                if ( $n >= 7 && ! ( 'late_origin_loss' === $case && $fired ) ) {
                    if ( null === $pin ) $pin = $p;
                    alynt_core_assert( $pin === $p && $p[2]['started_ns'] === $start && $p[3] === $before, 'Retained same-root origin/writer pin changed.' );
                    $proposal = $p[4]; $ids = array_merge( array_keys( $eligible['used'] ), array( $proposal['command']['command_id'], $proposal['operation_id'], $proposal['terminal_id'], $proposal['resolution_id'] ), array_values( $proposal['effect_ids'] ), array_values( $proposal['reservation_command_ids'] ) );
                    alynt_core_assert( $ids === $p[1] && count( array_unique( $ids ) ) === count( $ids ) && $proposal['command'] === $p[0]['mutation'] && $proposal['operation_id'] === $p[0]['operation_id'], 'Complete collision set/all ten original IDs were reminted or lost.' );
                    alynt_core_assert( strlen( json_encode( array_slice( $p, 0, 3 ) ) ) <= 16384, 'Minimal retention exceeded its aggregate bound.' );
                    $minimal = array_slice( $p, 0, 3 );
                    array_walk_recursive( $minimal, function ( $v ) { alynt_core_assert( is_scalar( $v ) || null === $v, 'Minimal origin exported a write-capable handle.' ); } );
                    if ( $n <= 8 ) {
                        $writer = $reader;
                        alynt_core_assert( $reader instanceof mysqli && $reader !== $capture && $reader !== $auth && 'idle' === alynt_core_property( $o, 'phase' ) && 20 === alynt_core_property( $o, 'protected_reads' ) && 0 === alynt_core_property( $o, 'writes' ), 'Retained origin was not established before BEGIN/DML.' );
                        $history = alynt_core_property( $o, 'native_outcome' );
                        alynt_core_assert( 0 === $history['facts']['events']['begin_sent'] && 0 === $history['facts']['events']['dml_sent'], 'Native send preceded original retention.' );
                    } else {
                        alynt_core_assert( null === $reader && 'committed' === alynt_core_property( $o, 'phase' ), 'Postrelease callback preceded owned cleanup.' );
                        $observed = alynt_outcome_snapshot( $o, alynt_outcome_commit_order() );
                        if ( ! $fired && 'positive' !== $case ) {
                            $fired = true;
                            if ( 'late_origin_loss' === $case ) alynt_ledger_set( $o, 'admission_origin', null );
                            else alynt_core_mutate( function () use ( $token ) { WP_Session_Tokens::get_instance( 90000001 )->destroy( $token ); } );
                        }
                    }
                }
                // No WordPress nonce callback may execute while native row locks are held.
                foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $def ) alynt_ledger_nowait( $db, $def[0], false );
                return $life;
            };
            $o = alynt_join_run( $form, 'positive' === $case, 40, 1, $probe );
            alynt_core_assert( is_array( $pin ) && is_array( $observed ) && 1 === $creates && ( 'positive' === $case || $fired ), 'Real joined positive/late fault was not reached.' ); alynt_outcome_commit( $observed );
            $small = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( get_current_blog_id(), 'register_closed' );
            $command_form = array_map( 'strval', $pin[4]['command'] ); $command_form['nonce'] = 'pending';
            $parsed = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $command_form );
            alynt_core_assert( is_array( $parsed ), 'Original command no longer parses after native release.' ); $full = $parsed['action'];
            $expected = array( $small, $small, $small, $small, $small, $full, $small, $full, $small, $full );
            alynt_core_assert( $small !== $full && $expected === ( 'revoke_after_commit' === $case ? array_merge( $gates, array( $full ) ) : $gates ), 'Separate nonce domains/ten genuine guard ordering changed.' );
            alynt_core_assert( $pin[4] === alynt_admission_receipt( $db, $before ), 'Committed receipt differs from the original retained full proposal/all ten IDs.' );
            foreach ( array( $capture, $writer ) as $client ) { $closed = false; try { $client->query( 'SELECT 1' ); } catch ( Error $error ) { $closed = true; } alynt_core_assert( $closed, 'Owned native client survived return.' ); }
            alynt_core_assert( $facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(), 'Borrowed authentication session was changed or closed.' );
            alynt_scan_seed( $values ); alynt_core_assert( $before === alynt_registration_frame( $db ), 'Exact owned frame reconciliation failed.' );
            alynt_core_done( 'genuine_join_' . $case . '_original_pin_native_commit_closed_clients_erased_root' );
        }
    } finally { $db->close(); }
}
