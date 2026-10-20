<?php
/** Confirmed owned fixtures only. Private reflection is proof instrumentation, not an API. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) ) exit( 1 );
require_once __DIR__ . '/registration-admission-proof-fixtures.php';

/** Inspect actual fixed-source observations; never fill event slots in this proof. */
function alynt_outcome_snapshot( $o, array $ordered, $valid = true ) {
    $s = alynt_core_property( $o, 'native_outcome' );
    alynt_core_assert( is_array( $s ) && array( 'valid', 'facts', 'raw_returns' ) === array_keys( $s ) && $valid === $s['valid'], 'Native outcome capture unavailable or poisoned unexpectedly.' );
    alynt_core_assert( strlen( json_encode( $s ) ) < 4096, 'Native observation map exceeded its bounded primitive scope.' );
    array_walk_recursive( $s, function ( $v ) { alynt_core_assert( is_scalar( $v ) || null === $v, 'Native snapshot exported a handle/object.' ); } );
    $last = 0;
    foreach ( $ordered as $event ) {
        $at = $s['facts']['events'][$event];
        alynt_core_assert( is_int( $at ) && $at > $last && $at <= 32, 'Actual native event order missing: ' . $event ); $last = $at;
    }
    $positions = array_filter( $s['facts']['events'] );
    alynt_core_assert( count( $positions ) === count( array_unique( $positions ) ), 'Actual native observation positions collided.' );
    alynt_core_assert( array_fill_keys( array( 'auth', 'intent', 'physical', 'unreentered' ), null ) === $s['facts']['guards'] && 0 === $s['facts']['events']['accepted_at'], 'Historical native facts were promoted to current acceptance.' );
    return $s;
}
function alynt_outcome_finished( $o, array $ordered, $valid = true ) {
    alynt_core_private( $o, 'finish' ); $s = alynt_outcome_snapshot( $o, $ordered, $valid );
    foreach ( array( 'reader', 'native_outcome_writer', 'physical' ) as $key ) alynt_core_assert( null === alynt_core_property( $o, $key ), 'Owned native handle survived cleanup: ' . $key );
    alynt_core_assert( true === alynt_core_property( $o, 'native_cleanup_done' ) && true === alynt_core_property( $o, 'native_outcome_closed' ) && false === $s['facts']['handle_remaining'], 'Cleanup did not consume/seal/release.' );
    alynt_core_private( $o, 'finish' );
    alynt_core_assert( $s === alynt_core_property( $o, 'native_outcome' ), 'Repeated native cleanup rewrote historical facts or retried close.' );
    return $s;
}
function alynt_outcome_erased( $o ) {
    alynt_core_assert( null === alynt_core_property( $o, 'native_outcome' ) && 0 === alynt_core_property( $o, 'native_outcome_tick' ), 'Outer root retained native history.' );
    alynt_core_assert( null === alynt_core_property( $o, 'native_outcome_writer' ) && true === alynt_core_property( $o, 'native_outcome_started' ) && true === alynt_core_property( $o, 'native_outcome_closed' ), 'Outer cleanup failed to consume capture.' );
    alynt_core_assert( false === alynt_core_private( $o, 'start_native_outcome' ) && null === alynt_core_property( $o, 'native_outcome' ), 'Erased native capture reopened.' );
}
function alynt_outcome_commit_order() {
    return array( 'writer_created', 'begin_sent', 'begin_ack_at', 'transaction_owned', 'attempt_reserved', 'bind_verified', 'dml_sent', 'dml_ack_at', 'readback', 'commit_sent', 'commit_ack_at', 'commit_idle', 'detached', 'close_at' );
}
function alynt_outcome_commit( $s ) {
    foreach ( array( 'begin', 'dml', 'commit', 'close' ) as $name ) alynt_core_assert( true === $s['raw_returns'][$name] && 'yes' === $s['facts']['acks'][$name], 'True native return/ack not preserved: ' . $name );
    alynt_core_assert( 'one' === $s['facts']['affected_rows'] && 'not_called' === $s['raw_returns']['rollback'] && 0 === $s['facts']['events']['rollback_sent'], 'Committed one-row history changed into rollback/no-write.' );
}
