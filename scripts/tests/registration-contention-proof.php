<?php
/** Genuine coordinator plus real participating native fixture writers; not admission recovery. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) ) exit( 1 );
require_once __DIR__ . '/registration-barrier-proof-fixtures.php';
require_once __DIR__ . '/registration-contention-process.php';
function alynt_contention_cases() {
    global $wpdb;
    foreach ( array( 'commit', 'rollback', 'timeout', 'disconnect', 'late' ) as $mode ) {
        list( $o, $i, $d ) = alynt_barrier_fixture(); $audit = alynt_scan_db(); $registered = alynt_barrier_frame( $audit )['ledger'][3];
        $empty = json_decode( $registered, true ); $empty['ledger_revision'] = 20; $empty['operations'] = array(); $empty = json_encode( $empty );
        alynt_core_assert( is_array( Alynt_Drime_Backups_Uploader_Closed_Registration_Contract::inspect( $empty, $d['scope'] ) ), 'Empty synthetic ledger invalid.' );
        alynt_scan_seed( array( 'ledger' => $empty ) ); $before = alynt_barrier_frame( $audit );
        $auth = $wpdb->dbh; $auth_facts = alynt_core_private( $o, 'native_idle', array( $auth ) );
        $hooks = alynt_origin_hooks(); $roles = $GLOBALS['wp_roles']; $handler = alynt_handoff_handler(); $writes = $GLOBALS['alynt_core_proof']['fixture_writes'];
        $peer = null; $facts = array(); $observer_thread = null; $peer_thread = null; $late_finished = false;
        $probe = alynt_handoff_probe( $facts, function ( $n, $life ) use ( &$peer, &$observer_thread, &$peer_thread, &$late_finished, $mode, $o, $empty, $registered, $auth ) {
            if ( 7 === $n ) {
                $reader = alynt_core_property( $o, 'reader' );
                alynt_core_assert( 'idle' === alynt_core_property( $o, 'phase' ) && $reader instanceof mysqli && $reader !== $auth && alynt_core_private( $o, 'session', array( false ) ), 'Peer must start before BEGIN on a distinct idle owned session.' );
                $observer_thread = $reader->thread_id; $peer = new Alynt_Registration_Contention_Process( $mode, $empty, $registered ); $ready = $peer->event( 'ready' ); $peer_thread = $ready['thread'];
                alynt_core_assert( $peer_thread !== $observer_thread && $peer_thread !== $auth->thread_id, 'Peer reused observer/auth connection.' );
                if ( 'late' !== $mode ) $peer->send( (string) $observer_thread );
            }
            if ( 8 === $n && 'late' === $mode ) {
                alynt_core_assert( 'closed' === alynt_core_property( $o, 'phase' ) && null === alynt_core_property( $o, 'reader' ), 'Late writer started before actual observer close.' );
                $peer->send( 'later' ); $done = $peer->event( 'finished' ); alynt_core_assert( true === $done['committed'] && 1 === $done['updates'], 'Late fixture writer did not commit exactly one ledger update.' ); $peer->finish(); $late_finished = true;
            }
            return $life;
        } );
        $GLOBALS['alynt_origin_owner'] = $o; add_filter( 'nonce_life', $probe, 10, 2 ); $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
        $start = hrtime( true );
        try {
            $r = alynt_core_private( $o, 'observe_native', array( $i ) ); $elapsed = hrtime( true ) - $start;
            alynt_core_assert( $peer instanceof Alynt_Registration_Contention_Process, 'Genuine pre-BEGIN guard never reached peer.' );
            if ( 'late' !== $mode ) {
                try { $peer->event( 'contended' ); } catch ( RuntimeException $error ) { throw new RuntimeException( $error->getMessage() . ' / observer phase=' . alynt_core_property( $o, 'phase' ) . ', reads=' . alynt_core_property( $o, 'barrier_reads' ) . ', guards=' . count( $facts ) ); }
                if ( 'timeout' === $mode ) $peer->send( 'release' );
                if ( 'disconnect' === $mode ) $peer->event( 'disconnected' );
                $done = $peer->event( 'finished' );
                alynt_core_assert( ( 'commit' === $mode ) === $done['committed'] && ( 'disconnect' === $mode ? 0 : 1 ) === $done['updates'], 'Contending fixture writer disposition/accounting differs.' ); $peer->finish();
            }
            $positive = in_array( $mode, array( 'commit', 'rollback', 'late' ), true );
            if ( $positive ) {
                $expected = 'commit' === $mode ? 'registered_closed' : 'not_observed_at_barrier';
                alynt_core_assert( is_array( $r ) && $expected === $r['classification'] && 9 === count( $r ) && true === $r['barrier_verified'] && false === $r['candidate_only'], 'Real contention classification differs.' );
                foreach ( array( 'authenticated', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $k ) alynt_core_assert( false === $r[$k], 'Contention result granted authority.' );
                alynt_core_assert( 9 === count( $facts ) && 10 === alynt_core_property( $o, 'barrier_reads' ) && 2 === alynt_core_property( $o, 'zero_dml_checks' ) && 2 === alynt_core_property( $o, 'barrier_metadata_checks' ) && 'closed' === alynt_core_property( $o, 'phase' ), 'Contention skipped guards/zero-DML/complete native barrier.' );
            } else {
                alynt_core_assert( false === $r && 7 === count( $facts ) && 1 === alynt_core_property( $o, 'barrier_reads' ) && 1 === alynt_core_property( $o, 'zero_dml_checks' ), 'Native failure admitted a result, retried or skipped the actual blocking projection.' );
                alynt_core_assert( ( 'timeout' === $mode ? 'rolled_back' : 'poisoned' ) === alynt_core_property( $o, 'phase' ), 'Native failure lifecycle differs.' );
                if ( 'timeout' === $mode ) alynt_core_assert( $elapsed >= 1500000000 && $elapsed < 10000000000, 'Real bounded row timeout was not exercised.' );
            }
            if ( 'late' === $mode ) alynt_core_assert( $late_finished, 'Late commit was not acknowledged before the observer returned historical absence.' );
        } finally {
            $GLOBALS['alynt_core_proof']['guard_readonly'] = false; remove_filter( 'nonce_life', $probe, 10 ); unset( $GLOBALS['alynt_origin_owner'] ); if ( $peer ) $peer->abort();
            $after = alynt_barrier_frame( $audit ); $audit->close();
        }
        $expected_frame = $before; if ( in_array( $mode, array( 'commit', 'late' ), true ) ) $expected_frame['ledger'][3] = $registered;
        alynt_core_assert( $expected_frame === $after, 'Unexpected fixture mutation beyond the exact peer ledger CAS.' );
        alynt_core_assert( $auth === $wpdb->dbh && $auth_facts === alynt_core_private( $o, 'native_idle', array( $auth ) ), 'Contention changed borrowed authentication session.' );
        alynt_core_assert( $hooks === alynt_origin_hooks() && $roles === $GLOBALS['wp_roles'] && $handler === alynt_handoff_handler() && $writes === $GLOBALS['alynt_core_proof']['fixture_writes'], 'Contention leaked hooks/roles/handler or issued WordPress writes.' );
        alynt_origin_closed( $o ); foreach ( array( 'staged', 'inflight', 'physical', 'barrier_auth', 'barrier_auth_facts' ) as $k ) alynt_core_assert( null === alynt_core_property( $o, $k ), 'Contention retained private payload.' );
        alynt_core_assert( false === alynt_core_private( $o, 'observe_native', array( $i ) ), 'Contention/failure retried consumed owner.' );
        alynt_core_done( 'genuine_native_contention_' . $mode . '_owned_peer_no_authority_no_retry' );
    }
}
