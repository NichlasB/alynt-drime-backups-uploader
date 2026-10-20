<?php
/** Genuine fixed root only; negative reflection faults are explicitly instrumentation. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) ) exit( 1 );

function alynt_join_qualification_cases() {
    global $wpdb;
    $values = alynt_registration_values(); $db = alynt_scan_db(); $auth = $wpdb->dbh;
    try {
        foreach ( array( 'positive', 'stale9', 'stale10', 'revoke', 'invalid_capture', 'unsealed', 'remaining', 'writer_bytes', 'reentry', 'throw' ) as $case ) {
            alynt_scan_seed( $values ); $token = alynt_core_login(); $form = alynt_registration_form(); $before = alynt_registration_frame( $db );
            $pin = null; $seal = null; $gates = array(); $creates = 0; $fired = false; $actual_owner = null;
            $auth_facts = $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row();
            $probe = function ( $life, $action, $verify, $o ) use ( &$pin, &$seal, &$gates, &$creates, &$fired, &$actual_owner, $case, $token, $db, $auth, $form ) {
                if ( ! $verify ) { ++$creates; return $life; }
                $actual_owner = $o; $gates[] = $action; $n = count( $gates );
                alynt_core_assert( false === alynt_core_property( $o, 'admission_qualification_spent' ), 'Qualification consumed before the final genuine guards completed.' );
                if ( 1 === $n ) alynt_core_assert( 0 === alynt_core_property( $o, 'protected_reads' ) && 0 === alynt_core_property( $o, 'writes' ), 'Protected IO preceded initial authorization.' );
                if ( $n < 9 ) return $life;
                foreach ( array( 'reader', 'native_outcome_writer', 'physical', 'read_physical' ) as $key ) alynt_core_assert( null === alynt_core_property( $o, $key ), 'Final genuine guard preceded empty owned slots.' );
                alynt_core_assert( $GLOBALS['wpdb']->dbh === $auth && 'committed' === alynt_core_property( $o, 'phase' ) && true === alynt_core_property( $o, 'native_cleanup_done' ) && true === alynt_core_property( $o, 'native_outcome_closed' ), 'Final guard preceded native commit/cleanup/seal.' );
                $snapshot = alynt_outcome_snapshot( $o, alynt_outcome_commit_order() ); alynt_outcome_commit( $snapshot );
                if ( null === $seal ) { $seal = $snapshot; $pin = alynt_join_pin( $o ); }
                alynt_core_assert( $seal === $snapshot && $pin === alynt_join_pin( $o ), 'Native history or full original pin changed between postrelease guards.' );
                foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $def ) alynt_ledger_nowait( $db, $def[0], false );
                if ( ( 'stale9' === $case && 9 === $n ) || ( 'stale10' === $case && 10 === $n ) ) { $fired = true; return 1; }
                if ( 10 !== $n || 'positive' === $case ) return $life;
                $fired = true;
                if ( 'revoke' === $case ) alynt_core_mutate( function () use ( $token ) { WP_Session_Tokens::get_instance( 90000001 )->destroy( $token ); } );
                elseif ( 'invalid_capture' === $case ) { $s = alynt_core_property( $o, 'native_outcome' ); $s['valid'] = false; alynt_ledger_set( $o, 'native_outcome', $s ); }
                elseif ( 'unsealed' === $case ) alynt_ledger_set( $o, 'native_outcome_closed', false );
                elseif ( 'remaining' === $case ) alynt_ledger_set( $o, 'read_physical', array( 'instrumented_remaining' => true ) );
                elseif ( 'writer_bytes' === $case ) { $p = alynt_core_property( $o, 'before' ); $p['rows']['queue']['value'] .= ' '; alynt_ledger_set( $o, 'before', $p ); }
                elseif ( 'reentry' === $case ) alynt_core_assert( false === alynt_core_private( $o, 'admit', array( 'POST', $form ) ), 'Joined postrelease reentry succeeded.' );
                elseif ( 'throw' === $case ) throw new RuntimeException( 'Owned qualification intentional callback failure.' );
                return $life;
            };
            $thrown = false;
            try { $o = alynt_join_run( $form, 'positive' === $case, 40, 1, $probe ); }
            catch ( RuntimeException $error ) {
                if ( 'throw' !== $case || 'Owned qualification intentional callback failure.' !== $error->getMessage() ) throw $error;
                $thrown = true; $o = $actual_owner; alynt_join_erased( $o, $form );
            }
            alynt_core_assert( ( 'throw' === $case ) === $thrown && ( 'positive' === $case || $fired ) && is_array( $seal ) && is_array( $pin ), 'Intended owned qualification path was not reached.' );
            alynt_core_assert( ( 'stale9' === $case ? 9 : 10 ) === count( $gates ) && 1 === $creates, 'Original nonce verification/creation count changed.' );
            $small = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( get_current_blog_id(), 'register_closed' );
            $command = array_map( 'strval', $pin[4]['command'] ); $command['nonce'] = 'pending';
            $parsed = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $command );
            alynt_core_assert( is_array( $parsed ) && $small !== $parsed['action'], 'Independent original nonce domains lost.' ); $full = $parsed['action'];
            $expected = array( $small, $small, $small, $small, $small, $full, $small, $full, $small, $full );
            alynt_core_assert( $gates === array_slice( $expected, 0, count( $gates ) ), 'Original guard order changed.' );
            alynt_core_assert( $pin[4] === alynt_admission_receipt( $db, $before ), 'Late refusal rewrote acknowledged committed receipt/original identities.' );
            alynt_core_assert( 'committed' === alynt_core_property( $o, 'phase' ) && true === alynt_core_property( $o, 'admission_qualification_spent' ), 'Historical phase or closed qualification lifetime changed.' );
            $counts = array( alynt_core_property( $o, 'protected_reads' ), alynt_core_property( $o, 'writes' ) );
            // Private test-only replay after actual erasure, never an imported-origin runtime call.
            alynt_core_assert( false === alynt_core_private( $o, 'qualify_admission_outcome', array( $pin, array_fill_keys( array( 'auth', 'intent', 'physical', 'unreentered' ), true ) ) ) && $counts === array( alynt_core_property( $o, 'protected_reads' ), alynt_core_property( $o, 'writes' ) ), 'Erased qualification replayed or performed native IO.' );
            alynt_core_assert( $auth_facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(), 'Borrowed authentication client was changed/closed.' );
            alynt_scan_seed( $values ); alynt_core_assert( $before === alynt_registration_frame( $db ), 'Exact owned fixture reconciliation failed.' );
            $kind = in_array( $case, array( 'invalid_capture', 'unsealed', 'remaining', 'writer_bytes', 'reentry', 'throw' ), true ) ? 'instrumented' : 'genuine';
            alynt_core_done( $kind . '_qualification_' . $case . '_native_commit_seal_current_guard_closed_erase' );
        }
    } finally { $db->close(); }
}
