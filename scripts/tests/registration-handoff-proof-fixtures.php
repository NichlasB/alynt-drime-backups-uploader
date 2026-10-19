<?php
/** Genuine core against owned tables; candidate frames are not native barriers. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) ) exit( 1 );
require_once __DIR__ . '/registration-observation-proof-fixtures.php';

function alynt_handoff_fixture( $phase = 'registered' ) {
    alynt_scan_seed( alynt_registration_values() ); alynt_core_login();
    $db = alynt_scan_db(); try { $frame = alynt_registration_frame( $db ); } finally { $db->close(); }
    $o = alynt_origin_retain( alynt_registration_form(), 20, true );
    $d = alynt_core_property( $o, 'origin_data' ); $ids = array_slice( array_keys( alynt_core_property( $o, 'used' ) ), -10 );
    $roles = array( 'wpvivid_snapshot', 'outbox_snapshot', 'queue_addition' ); $c = 'Alynt_Drime_Backups_Uploader_Closed_Registration_Contract';
    $p = array( 'command' => $d['mutation'], 'actor_id' => $d['actor_id'], 'operation_id' => $d['operation_id'], 'effect_ids' => array_combine( $roles, array_slice( $ids, 4, 3 ) ), 'reservation_command_ids' => array_combine( $roles, array_slice( $ids, 7, 3 ) ), 'terminal_id' => $ids[2], 'resolution_id' => $ids[3], 'dependency_digest' => hash( 'sha256', 'registration_full_frame_v2:' . json_encode( $frame ) ) );
    alynt_core_assert( $d['context_digest'] === $c::registration_context( $d['scope'], $p ), 'Candidate must use same owner original proposal, not reconstruct expected context from receipt.' );
    if ( 'empty' !== $phase ) {
        if ( 'conflict' === $phase ) { $p['actor_id']++; $p['command']['context_digest'] = $c::registration_context( $d['scope'], $p ); }
        $raw = $c::register( $frame['rows']['ledger']['value'], $d['scope'], $p, $d['mutation']['issued_at'] );
        alynt_core_assert( is_string( $raw ), 'Synthetic candidate registration failed.' );
        if ( 'reserved' === $phase ) {
            $e = $p['command']; $e['purpose'] = 'reserve_wpvivid_intent'; $e['command_id'] = $p['reservation_command_ids']['wpvivid_snapshot']; $e['expected_ledger_revision']++; $e['confirm'] = 'reserve'; $e['operation_id'] = $p['operation_id'];
            $r = array( 'command' => $e, 'actor_id' => $d['actor_id'], 'dependency_digest' => str_repeat( 'c', 64 ), 'before' => 'a:0:{}', 'replacement' => serialize( array( str_repeat( 'd', 64 ) => array( 'size' => 12, 'mtime' => 101 ) ) ) );
            $r['command']['context_digest'] = $c::reservation_context( $d['context_digest'], $r ); $raw = $c::reserve( $raw, $d['scope'], $r, $d['mutation']['issued_at'] + 1 );
        }
        alynt_core_assert( is_string( $raw ) && is_array( $c::inspect( $raw, $d['scope'] ) ), 'Current complete synthetic history invalid.' );
        $frame = alynt_registration_row( $frame, 'ledger', $raw );
    }
    return array( $o, $frame, alynt_origin_intent( $o ), $d );
}
function alynt_handoff_handler() {
    $probe = static function () { return false; }; $prior = set_error_handler( $probe ); restore_error_handler(); return $prior;
}
function alynt_handoff_run( $o, $frame, $intent, $classification = false ) {
    $hooks = alynt_origin_hooks(); $roles = $GLOBALS['wp_roles']; $handler = alynt_handoff_handler();
    $writes = $GLOBALS['alynt_core_proof']['fixture_writes']; $controls = $GLOBALS['alynt_origin_control_writes'] ?? 0; $reads = alynt_core_property( $o, 'protected_reads' );
    $GLOBALS['alynt_origin_owner'] = $o; $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { $r = alynt_core_private( $o, 'observe_candidate', array( $frame, $intent ) ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; unset( $GLOBALS['alynt_origin_owner'] ); }
    alynt_core_assert( false === $classification ? false === $r : is_array( $r ) && $classification === $r['classification'], 'Genuine guarded handoff decision/classification differed.' );
    if ( false !== $classification ) {
        alynt_core_assert( 9 === count( $r ) && true === $r['candidate_only'], 'Private candidate result shape changed.' );
        foreach ( array( 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $k ) alynt_core_assert( false === $r[$k], 'Candidate promoted to authority: ' . $k );
        array_walk_recursive( $r, function ( $v ) { alynt_core_assert( is_scalar( $v ) || null === $v, 'Write-capable handle escaped candidate.' ); } );
    }
    alynt_origin_closed( $o );
    foreach ( array( 'staged', 'inflight' ) as $k ) alynt_core_assert( null === alynt_core_property( $o, $k ), 'Private handoff payload survived: ' . $k );
    foreach ( array( 'attempted', 'claimed', 'finished', 'observation_attempted' ) as $k ) alynt_core_assert( true === alynt_core_property( $o, $k ), 'Consumed handoff latch missing: ' . $k );
    alynt_core_assert( false === alynt_core_property( $o, 'native_read_attempted' ) && $reads === alynt_core_property( $o, 'protected_reads' ), 'Candidate invoked native observer/capture.' );
    alynt_core_assert( $hooks === alynt_origin_hooks() && $roles === $GLOBALS['wp_roles'] && $handler === alynt_handoff_handler(), 'Owned hooks/roles/error handler leaked.' );
    alynt_core_assert( $GLOBALS['alynt_core_proof']['fixture_writes'] - $writes === ( $GLOBALS['alynt_origin_control_writes'] ?? 0 ) - $controls, 'Handoff wrote outside deliberate owned revocation control.' );
    alynt_core_assert( false === alynt_core_private( $o, 'observe_candidate', array( $frame, $intent ) ), 'Consumed handoff retried.' );
    return $r;
}
/** Observe genuine verifier calls, not nonce creation; never fabricate a verifier result. */
function alynt_handoff_probe( &$facts, $fault = null ) {
    return function ( $life, $action ) use ( &$facts, $fault ) {
        $stack = array_column( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 8 ), 'function' );
        if ( ! in_array( 'wp_verify_nonce', $stack, true ) ) return $life;
        $o = $GLOBALS['alynt_origin_owner']; $fences = alynt_core_property( $o, 'fences' ); $blocked = false;
        try { $fences['query']( 'SELECT option_value FROM ' . $GLOBALS['wpdb']->options . ' LIMIT 1' ); } catch ( RuntimeException $error ) { $blocked = true; }
        $installed = PHP_INT_MAX === has_filter( 'query', $fences['query'] ) && PHP_INT_MAX === has_filter( 'pre_wp_load_alloptions', $fences['pre_wp_load_alloptions'] );
        $facts[] = array( 'action' => $action, 'origin' => null !== alynt_core_property( $o, 'origin' ), 'staged' => null !== alynt_core_property( $o, 'staged' ), 'finished' => alynt_core_property( $o, 'finished' ), 'fenced' => $installed && $blocked && array() === apply_filters( 'pre_wp_load_alloptions', null, false ), 'window' => alynt_core_property( $o, 'started' ) );
        return null === $fault ? $life : $fault( count( $facts ), $life, $o );
    };
}
