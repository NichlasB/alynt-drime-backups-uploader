<?php
/** Same approved Plugin Tester supervisor/three-table scope; never production. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) ) exit( 1 );
require_once __DIR__ . '/registration-proof-fixtures.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-registration-native-reader.php';
function alynt_registration_native( $form, $reads, $positive = false, $method = 'POST' ) {
    $o = Alynt_Drime_Backups_Uploader_Registration_Native_Reader::create(); $GLOBALS['alynt_registration_native_owner'] = $o;
    $hooks = array(); foreach ( array( 'query', 'pre_wp_load_alloptions' ) as $hook ) $hooks[$hook] = isset( $GLOBALS['wp_filter'][$hook] ) ? $GLOBALS['wp_filter'][$hook]->callbacks : array();
    $roles = $GLOBALS['wp_roles']; $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { $r = alynt_core_private( $o, 'read_registration', array( $method, $form ) ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; unset( $GLOBALS['alynt_registration_native_owner'] ); }
    foreach ( $hooks as $hook => $prior ) alynt_core_assert( $prior === ( isset( $GLOBALS['wp_filter'][$hook] ) ? $GLOBALS['wp_filter'][$hook]->callbacks : array() ), 'Stack-local authorization hook was not restored.' );
    alynt_core_assert( $positive ? is_array( $r ) : false === $r, 'Native registration capture decision differed: expected=' . (int) $positive . ', actual=' . (int) is_array( $r ) . ', reads=' . alynt_core_property( $o, 'protected_reads' ) . ', completed_groups=' . count( $GLOBALS['alynt_core_proof']['cases'] ) );
    alynt_core_assert( $reads === alynt_core_property( $o, 'protected_reads' ), 'Native protected-read ordering/count differed: expected=' . $reads . ', actual=' . alynt_core_property( $o, 'protected_reads' ) );
    alynt_core_assert( null === alynt_core_property( $o, 'reader' ) && ! alynt_core_property( $o, 'active' ) && alynt_core_property( $o, 'consumed' ) && alynt_core_property( $o, 'read_attempted' ) && $roles === $GLOBALS['wp_roles'], 'Native reader, retry lifetime or roles escaped.' );
    alynt_core_assert( false === alynt_core_private( $o, 'read_registration', array( 'POST', $form ) ) && $reads === alynt_core_property( $o, 'protected_reads' ), 'Native capture retried or reminted.' );
    if ( $positive ) {
        foreach ( array( 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $key ) alynt_core_assert( false === $r[$key], 'Private blueprint became authority.' );
        array_walk_recursive( $r, function ( $v ) { alynt_core_assert( is_scalar( $v ) || null === $v, 'Native handle escaped in blueprint.' ); } );
    }
    return $r;
}
function alynt_registration_native_set( $o, $key, $value ) {
    $p = new ReflectionProperty( $o, $key ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $p->setValue( $o, $value );
}
