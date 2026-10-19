<?php
/** Genuine auth and physical native transaction; synthetic closed fixture history. */
function alynt_barrier_cases() {
    global $wpdb;
    foreach ( array( 'registered' => 'registered_closed', 'reserved' => 'registered_closed', 'empty' => 'not_observed_at_barrier', 'conflict' => 'domain_conflict' ) as $phase => $classification ) {
        list( $o, $i, $d ) = alynt_barrier_fixture( $phase ); $facts = array(); $states = array(); $probe = alynt_handoff_probe( $facts );
        $auth = $wpdb->dbh; $before = alynt_core_private( $o, 'native_idle', array( $auth ) );
        $inspect = function ( $life, $action ) use ( &$states, $o, $probe ) {
            if ( ! in_array( 'wp_verify_nonce', array_column( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 8 ), 'function' ), true ) ) return $life;
            $reader = alynt_core_property( $o, 'reader' ); $physical = null === $reader ? null : alynt_core_private( $o, 'native_idle', array( $reader ) );
            $states[] = array( 'phase' => alynt_core_property( $o, 'phase' ), 'reader' => null !== $reader, 'idle' => null === $reader || is_array( $physical ) && '0' === $physical['tx'], 'distinct' => null === $reader || $reader !== $GLOBALS['wpdb']->dbh );
            return $probe( $life, $action );
        };
        add_filter( 'nonce_life', $inspect, 10, 2 );
        try { alynt_barrier_run( $o, $i, $classification ); } finally { remove_filter( 'nonce_life', $inspect, 10 ); }
        alynt_core_assert( 9 === count( $facts ), 'Nine independent genuine native nonce guards required.' );
        foreach ( $facts as $n => $f ) {
            alynt_core_assert( $f['fenced'] && $f['window'] === $facts[0]['window'] && ( $n < 3 ) === $f['origin'], 'Native guard fence/origin/original clock ordering differed.' );
            alynt_core_assert( $states[$n]['idle'] && $states[$n]['distinct'], 'WordPress nonce callback ran inside native locks or borrowed auth handle.' );
        }
        alynt_core_assert( 'idle' === $states[5]['phase'] && $states[5]['reader'] && 'closed' === $states[7]['phase'] && ! $states[7]['reader'], 'Pre-BEGIN/post-close guard ordering differs.' );
        alynt_core_assert( $wpdb->dbh === $auth && $before === alynt_core_private( $o, 'native_idle', array( $auth ) ), 'Borrowed auth session changed.' );
    }
    alynt_core_done( 'genuine_four_classifications_ten_locked_rows_zero_dml_nine_guards_acknowledged_close' );
    list( $o, $i, $d ) = alynt_barrier_fixture(); alynt_core_login( 90000003 ); $i = alynt_origin_intent( $o );
    alynt_barrier_run( $o, $i, 'registered_closed' ); alynt_core_assert( 90000001 === $d['actor_id'], 'Observer adopted historical actor.' );
    alynt_core_done( 'different_authorized_observer_exact_original_provenance' );
    foreach ( array( 'bad_nonce', 'wrong_id', 'extra', 'subscriber', 'stale' ) as $fault ) {
        list( $o, $i ) = alynt_barrier_fixture();
        if ( 'bad_nonce' === $fault ) $i['nonce'] = 'bad';
        elseif ( 'wrong_id' === $fault ) $i['observed_command_id'] = wp_generate_uuid4();
        elseif ( 'extra' === $fault ) $i['provider'] = 'caller';
        elseif ( 'subscriber' === $fault ) { alynt_core_login( 90000002 ); $i = alynt_origin_intent( $o ); }
        else alynt_core_login();
        alynt_barrier_run( $o, $i, false, 0 );
    }
    alynt_core_done( 'malformed_stale_unauthorized_request_zero_native_payload_reads' );
    foreach ( range( 1, 9 ) as $deny ) {
        list( $o, $i ) = alynt_barrier_fixture(); $facts = array(); $probe = alynt_handoff_probe( $facts, function ( $n, $life ) use ( $deny ) { return $n === $deny ? 1 : $life; } );
        add_filter( 'nonce_life', $probe, 10, 2 );
        try { alynt_barrier_run( $o, $i, false, $deny >= 8 ? 10 : 0 ); } finally { remove_filter( 'nonce_life', $probe, 10 ); }
        alynt_core_assert( $deny === count( $facts ), 'Required native nonce guard bypassed.' );
    }
    alynt_core_done( 'every_native_nonce_guard_independently_denies_no_early_payload_reads_or_retry' );
}
