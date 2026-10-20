<?php
/** Genuine fixed entry only; callbacks observe private DATA, never supply runtime success. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) ) exit( 1 );
require_once __DIR__ . '/registration-outcome-proof-fixtures.php';

function alynt_join_erased( $o, $form ) {
    foreach ( array( 'admission_origin', 'admission_clock' ) as $key ) alynt_core_assert( null === alynt_core_property( $o, $key ), 'Joined root retained private origin/clock.' );
    alynt_core_assert( array() === alynt_core_property( $o, 'admission_ids' ) && false === alynt_core_property( $o, 'admission_join_busy' ), 'Joined root retained IDs/busy latch.' );
    alynt_outcome_erased( $o );
    $reads = alynt_core_property( $o, 'protected_reads' ); $writes = alynt_core_property( $o, 'writes' );
    alynt_core_assert( false === alynt_core_private( $o, 'admit_retained', array( 'POST', $form ) ) && $reads === alynt_core_property( $o, 'protected_reads' ) && $writes === alynt_core_property( $o, 'writes' ), 'Joined entry replayed after final erasure.' );
    $public = array_map( function ( $m ) { return $m->name; }, ( new ReflectionClass( $o ) )->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $public );
    alynt_core_assert( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ) === $public, 'Native join exposed a new public operation/handle port.' );
}

/** Catch assertion failures outside the swallowing production refusal boundary too. */
function alynt_join_run( $form, $expected, $reads, $writes, callable $probe ) {
    $callback_error = null; $call_error = null;
    $filter = function ( $life, $action ) use ( $probe, &$callback_error ) {
        $stack = array_column( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 14 ), 'function' );
        $verify = in_array( 'wp_verify_nonce', $stack, true ); $create = in_array( 'wp_create_nonce', $stack, true );
        if ( ! $verify && ! $create ) return $life;
        try { return $probe( $life, $action, $verify, $GLOBALS['alynt_admission_owner'] ); }
        catch ( Throwable $error ) { $callback_error = $error; throw $error; }
    };
    add_filter( 'nonce_life', $filter, 10, 2 );
    try { $o = alynt_admit( $form, $expected, $reads, $writes, 'POST', true ); }
    catch ( Throwable $error ) { $call_error = $error; }
    finally { remove_filter( 'nonce_life', $filter, 10 ); }
    if ( null !== $callback_error ) throw $callback_error;
    if ( null !== $call_error ) throw $call_error;
    alynt_join_erased( $o, $form );
    return $o;
}

/** Exact private root-local pin. Never returned by production or printed by tests. */
function alynt_join_pin( $o ) {
    return array_map( function ( $key ) use ( $o ) { return alynt_core_property( $o, $key ); }, array( 'admission_origin', 'admission_ids', 'admission_clock', 'before', 'proposal', 'context', 'after', 'recorded_at' ) );
}
