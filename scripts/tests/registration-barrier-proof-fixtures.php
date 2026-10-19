<?php
/** Exact confirmed local owned fixture scope; never loaded by a site endpoint. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) ) exit( 1 );
require_once __DIR__ . '/registration-handoff-proof-fixtures.php';
function alynt_barrier_fixture( $phase = 'registered' ) {
    list( $o, $frame, $i, $d ) = alynt_handoff_fixture( $phase ); $values = array();
    foreach ( $frame['rows'] as $role => $row ) $values[$role] = $row['value'];
    alynt_scan_seed( $values ); // Synthetic current history in owned rows, not a real admission.
    return array( $o, $i, $d );
}
function alynt_barrier_run( $o, $i, $classification = false, $reads = 10 ) {
    $hooks = alynt_origin_hooks(); $roles = $GLOBALS['wp_roles']; $handler = alynt_handoff_handler(); $writes = $GLOBALS['alynt_core_proof']['fixture_writes']; $controls = $GLOBALS['alynt_origin_control_writes'] ?? 0;
    $db = alynt_scan_db(); $before = alynt_barrier_frame( $db );
    $GLOBALS['alynt_origin_owner'] = $o; $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { $r = alynt_core_private( $o, 'observe_native', array( $i ) ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; unset( $GLOBALS['alynt_origin_owner'] ); }
    try { alynt_core_assert( $before === alynt_barrier_frame( $db ), 'Native observer changed protected owned rows.' ); } finally { $db->close(); }
    alynt_core_assert( false === $classification ? false === $r : is_array( $r ) && $classification === $r['classification'], 'Native barrier decision differed; phase=' . alynt_core_property( $o, 'phase' ) . ', reads=' . alynt_core_property( $o, 'barrier_reads' ) . ', zero=' . alynt_core_property( $o, 'zero_dml_checks' ) );
    alynt_core_assert( $reads === alynt_core_property( $o, 'barrier_reads' ), 'Native protected read ordering/count differed.' );
    if ( false !== $classification ) {
        alynt_core_assert( 9 === count( $r ) && false === $r['candidate_only'] && true === $r['barrier_verified'] && 'closed' === alynt_core_property( $o, 'phase' ), 'Result lacks acknowledged native close qualification.' );
        alynt_core_assert( 2 === alynt_core_property( $o, 'zero_dml_checks' ), 'Native session did not prove zero DML before/after transaction.' );
        alynt_core_assert( 2 === alynt_core_property( $o, 'barrier_metadata_checks' ), 'Exact native metadata not rechecked under safety MDL.' );
        foreach ( array( 'authenticated', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $k ) alynt_core_assert( false === $r[$k], 'Native result granted authority: ' . $k );
        array_walk_recursive( $r, function ( $v ) { alynt_core_assert( is_scalar( $v ) || null === $v, 'Native handle escaped result.' ); } );
    }
    alynt_origin_closed( $o );
    foreach ( array( 'staged', 'inflight', 'physical', 'barrier_auth', 'barrier_auth_facts' ) as $k ) alynt_core_assert( null === alynt_core_property( $o, $k ), 'Private barrier fact survived: ' . $k );
    alynt_core_assert( $hooks === alynt_origin_hooks() && $roles === $GLOBALS['wp_roles'] && $handler === alynt_handoff_handler(), 'Native observer leaked hooks/roles/error handler.' );
    alynt_core_assert( $GLOBALS['alynt_core_proof']['fixture_writes'] - $writes === ( $GLOBALS['alynt_origin_control_writes'] ?? 0 ) - $controls, 'Native observer issued WordPress writes outside explicit fixture controls.' );
    alynt_core_assert( false === alynt_core_private( $o, 'observe_native', array( $i ) ), 'Native observer retried.' );
    return $r;
}
/** Independent original owned-table snapshot even when a fault poisons wpdb metadata. */
function alynt_barrier_frame( mysqli $db ) {
    $table = $GLOBALS['alynt_core_proof']['prefix'] . 'options'; $out = array();
    alynt_core_assert( 1 === preg_match( '/^wp_alynt_core_probe_[a-f0-9]{12}_options$/D', $table ), 'Non-owned audit target.' );
    foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $role => $def ) {
        $stmt = $db->prepare( "SELECT CAST(option_id AS CHAR),BINARY option_name,BINARY autoload,BINARY option_value FROM {$table} WHERE option_name=?" ); $stmt->bind_param( 's', $def[0] ); $stmt->execute();
        $r = $stmt->get_result(); alynt_core_assert( $r->num_rows <= 1, 'Owned audit duplicate row.' ); $out[$role] = $r->fetch_row(); $r->free(); $stmt->close();
    }
    return $out;
}
