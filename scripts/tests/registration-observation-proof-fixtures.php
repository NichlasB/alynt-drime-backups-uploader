<?php
/** Genuine core, owned tables only; no observer/admission/write implementation. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) ) exit( 1 );
require_once __DIR__ . '/registration-proof-fixtures.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-registration-observation-construction.php';

function alynt_origin_hooks() {
    $out = array(); foreach ( array( 'query', 'pre_wp_load_alloptions' ) as $hook ) $out[$hook] = isset( $GLOBALS['wp_filter'][$hook] ) ? $GLOBALS['wp_filter'][$hook]->callbacks : array(); return $out;
}
function alynt_origin_closed( $o, $origin = false ) {
    foreach ( array( 'reader', 'scope', 'started', 'native_started' ) as $key ) alynt_core_assert( null === alynt_core_property( $o, $key ), 'Private native/frame lifetime survived: ' . $key );
    alynt_core_assert( false === alynt_core_property( $o, 'busy' ) && false === alynt_core_property( $o, 'active' ) && array() === alynt_core_property( $o, 'fences' ), 'Owned phase not closed.' );
    if ( ! $origin ) foreach ( array( 'origin', 'origin_data' ) as $key ) alynt_core_assert( null === alynt_core_property( $o, $key ), 'Refused/read origin survived.' );
    if ( ! $origin ) alynt_core_assert( array() === alynt_core_property( $o, 'used' ), 'Issued identities survived consumed read.' );
}
function alynt_origin_retain( $form, $reads, $positive = false, $method = 'POST' ) {
    $o = Alynt_Drime_Backups_Uploader_Registration_Observation_Construction::create(); $GLOBALS['alynt_origin_owner'] = $o;
    $hooks = alynt_origin_hooks(); $roles = $GLOBALS['wp_roles']; $writes = $GLOBALS['alynt_core_proof']['fixture_writes']; $controls = $GLOBALS['alynt_origin_control_writes'] ?? 0;
    $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { $r = alynt_core_private( $o, 'retain_native_registration', array( $method, $form ) ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; unset( $GLOBALS['alynt_origin_owner'] ); }
    alynt_core_assert( $positive === $r, 'Own native retention decision differed; expected=' . (int) $positive . ', reads=' . alynt_core_property( $o, 'protected_reads' ) );
    alynt_core_assert( $reads === alynt_core_property( $o, 'protected_reads' ), 'Own native capture read ordering/count differed.' );
    alynt_core_assert( $GLOBALS['alynt_core_proof']['fixture_writes'] - $writes === ( $GLOBALS['alynt_origin_control_writes'] ?? 0 ) - $controls, 'Retention wrote outside deliberate owned revocation controls.' );
    alynt_core_assert( $roles === $GLOBALS['wp_roles'], 'Retention did not restore roles.' );
    alynt_core_assert( $hooks === alynt_origin_hooks(), 'Retention did not restore owned hooks; positive=' . (int) $positive . ', groups=' . count( $GLOBALS['alynt_core_proof']['cases'] ) );
    alynt_origin_closed( $o, $positive );
    alynt_core_assert( false === alynt_core_private( $o, 'retain_native_registration', array( 'POST', $form ) ) && $reads === alynt_core_property( $o, 'protected_reads' ), 'Native origin capture retried.' );
    return $o;
}
function alynt_origin_intent( $o ) {
    $d = alynt_core_property( $o, 'origin_data' ); alynt_core_assert( is_array( $d ), 'Own retained context required.' );
    return array( 'purpose' => 'observe_registration', 'confirm' => 'observe', 'observed_command_id' => $d['mutation']['command_id'], 'nonce' => wp_create_nonce( Alynt_Drime_Backups_Uploader_Registration_Observation_Request::issue_action( get_current_blog_id(), $d['mutation']['command_id'] ) ) );
}
function alynt_origin_read( $o, $intent, $positive = false ) {
    $hooks = alynt_origin_hooks(); $roles = $GLOBALS['wp_roles']; $writes = $GLOBALS['alynt_core_proof']['fixture_writes']; $reads = alynt_core_property( $o, 'protected_reads' ); $controls = $GLOBALS['alynt_origin_control_writes'] ?? 0;
    $GLOBALS['alynt_origin_owner'] = $o; $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { $r = alynt_core_private( $o, 'construct_native_observation', array( $intent ) ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; unset( $GLOBALS['alynt_origin_owner'] ); }
    alynt_core_assert( $positive ? is_array( $r ) : false === $r, 'Genuine read construction decision differed.' );
    alynt_core_assert( $reads === alynt_core_property( $o, 'protected_reads' ) && $GLOBALS['alynt_core_proof']['fixture_writes'] - $writes === ( $GLOBALS['alynt_origin_control_writes'] ?? 0 ) - $controls && $roles === $GLOBALS['wp_roles'] && $hooks === alynt_origin_hooks(), 'Read issued unplanned storage IO or leaked hooks/roles.' );
    alynt_origin_closed( $o );
    alynt_core_assert( false === alynt_core_private( $o, 'construct_native_observation', array( $intent ) ), 'Read lifetime reopened.' );
    return $r;
}
function alynt_origin_set( $o, $key, $value ) {
    $p = new ReflectionProperty( $o, $key ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $p->setValue( $o, $value );
}
/** Account ONLY for the explicitly selected owned revocation control's writes. */
function alynt_origin_change( $change ) {
    $before = $GLOBALS['alynt_core_proof']['fixture_writes'];
    $restore = alynt_core_change( $change );
    $GLOBALS['alynt_origin_control_writes'] = ( $GLOBALS['alynt_origin_control_writes'] ?? 0 ) + $GLOBALS['alynt_core_proof']['fixture_writes'] - $before;
    return $restore;
}
