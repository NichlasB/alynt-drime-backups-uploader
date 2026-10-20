<?php
/** Exact confirmed Plugin Tester supervisor/three-table scope only. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) ) exit( 1 );
require_once __DIR__ . '/registration-proof-fixtures.php';
require_once __DIR__ . '/native-scan-ledger-fixtures.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-registration-native-admission.php';
function alynt_admit( $form, $positive, $reads, $writes = 0, $method = 'POST', $joined = false ) {
    $o = Alynt_Drime_Backups_Uploader_Registration_Native_Admission::create(); $GLOBALS['alynt_admission_owner'] = $o;
    $prior = array(); foreach ( array( 'query', 'pre_wp_load_alloptions' ) as $hook ) $prior[$hook] = isset( $GLOBALS['wp_filter'][$hook] ) ? $GLOBALS['wp_filter'][$hook]->callbacks : array();
    $roles = $GLOBALS['wp_roles']; $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { $result = alynt_core_private( $o, $joined ? 'admit_retained' : 'admit', array( $method, $form ) ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; unset( $GLOBALS['alynt_admission_owner'] ); }
    alynt_core_assert( $positive === $result, 'Private admission decision differed; expected=' . (int) $positive . ', actual=' . (int) $result . ', reads=' . alynt_core_property( $o, 'protected_reads' ) . ', writes=' . alynt_core_property( $o, 'writes' ) . ', phase=' . alynt_core_property( $o, 'phase' ) . ', groups=' . count( $GLOBALS['alynt_core_proof']['cases'] ) );
    alynt_core_assert( $reads === alynt_core_property( $o, 'protected_reads' ) && $writes === alynt_core_property( $o, 'writes' ), 'Admission protected reads/writes differed: expected=' . $reads . '/' . $writes . ', actual=' . alynt_core_property( $o, 'protected_reads' ) . '/' . alynt_core_property( $o, 'writes' ) );
    foreach ( array( 'reader', 'physical', 'read_physical', 'scope', 'before', 'proposal', 'context', 'after', 'recorded_at' ) as $key ) alynt_core_assert( null === alynt_core_property( $o, $key ), 'Private admission fact escaped: ' . $key );
    alynt_core_assert( $roles === $GLOBALS['wp_roles'] && alynt_core_property( $o, 'attempted' ) && alynt_core_property( $o, 'consumed' ) && ! alynt_core_property( $o, 'active' ), 'Admission lifetime or role view escaped.' );
    foreach ( $prior as $hook => $callbacks ) alynt_core_assert( $callbacks === ( isset( $GLOBALS['wp_filter'][$hook] ) ? $GLOBALS['wp_filter'][$hook]->callbacks : array() ), 'Admission stack-local query/auth fence leaked.' );
    alynt_core_assert( false === alynt_core_private( $o, 'admit', array( 'POST', $form ) ) && $writes === alynt_core_property( $o, 'writes' ) && $reads === alynt_core_property( $o, 'protected_reads' ), 'Admission owner retried after completion/refusal.' );
    foreach ( array( 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $flag ) alynt_core_assert( false === $o->disposition()[$flag], 'Closed proof advertised execution authority.' );
    return $o; // Test instrumentation only; never an actual API/caller port.
}
function alynt_admission_receipt( $db, $original ) {
    $current = alynt_registration_frame( $db ); $raw = $current['rows']['ledger']['value']; $s = json_decode( $raw, true );
    $data = alynt_registration_eligible( $original ); alynt_core_assert( is_array( $data ) && is_array( $s ) && 1 === count( $s['operations'] ), 'Committed registration or original eligibility missing.' );
    $o = reset( $s['operations'] ); $roles = array( 'wpvivid_snapshot', 'outbox_snapshot', 'queue_addition' ); $effects = array();
    foreach ( $roles as $role ) { $effects[$role] = $o['effects'][$role]['effect_id']; alynt_core_assert( 'reserved' === $o['effects'][$role]['state'] && null === $o['effects'][$role]['receipt'], 'Registration executed an effect.' ); }
    $p = array( 'command' => $o['command'], 'actor_id' => $o['provenance']['actor_id'], 'operation_id' => $o['operation_id'], 'effect_ids' => $effects, 'reservation_command_ids' => $o['reservation_command_ids'], 'terminal_id' => $o['terminal_id'], 'resolution_id' => $o['resolution_id'], 'dependency_digest' => $o['registration_dependency_digest'] );
    $expected = Alynt_Drime_Backups_Uploader_Closed_Registration_Contract::register( $original['rows']['ledger']['value'], $data['scope'], $p, $o['provenance']['recorded_at'] );
    alynt_core_assert( is_string( $expected ) && $expected === $raw && 90000001 === $p['actor_id'] && $data['dependency_digest'] === $p['dependency_digest'], 'Actual stored registration differs from the exact closed successor.' );
    alynt_core_assert( 21 === $s['ledger_revision'] && $data['scope']['authority_generation'] === $s['authority_generation'] && 'registered_closed' === $o['registration_receipt']['classification'] && null === $o['terminal'] && null === $o['reservation_receipt'], 'Closed receipt/generation/phase changed.' );
    $original['rows']['ledger']['value'] = $raw; $original['rows']['ledger']['octets'] = (string) strlen( $raw );
    alynt_core_assert( $original === $current, 'Registration changed something other than exact ledger value/length.' );
    return $p; // Non-secret test-local lookup context, never emitted or persisted externally.
}
/** Storage primitive setup deliberately bypasses native entry; never call it genuine admission proof. */
function alynt_admission_primitive( $original ) {
    global $wpdb;
    $built = alynt_registration_construct( $original, alynt_registration_form(), true );
    $o = Alynt_Drime_Backups_Uploader_Registration_Native_Admission::create();
    alynt_ledger_set( $o, 'attempted', true ); alynt_ledger_set( $o, 'consumed', true ); alynt_ledger_set( $o, 'started', hrtime( true ) );
    $core = alynt_core_private( $o, 'frame' ); $facts = alynt_core_private( $o, 'native_idle', array( $wpdb->dbh ) );
    $context = array( 'blog_id' => $core['blog'], 'prefix' => $core['prefix'], 'database' => DB_NAME, 'actor_id' => get_current_user_id() );
    alynt_core_assert( is_array( $facts ) && alynt_core_private( $o, 'prepare_candidate', array( $original, $built['proposal'], $context, time() ) ) && alynt_core_private( $o, 'open_writer', array( $wpdb->dbh, $facts ) ), 'Owned isolated storage-primitive setup failed.' );
    return array( $o, $core, $built['proposal']['command'] );
}
function alynt_admission_locked( $o, $core ) {
    $writer = alynt_core_property( $o, 'reader' ); alynt_ledger_set( $o, 'phase', 'begin_sent' );
    alynt_core_assert( true === $writer->query( 'START TRANSACTION READ WRITE' ), 'Owned primitive BEGIN did not acknowledge.' );
    alynt_ledger_set( $o, 'phase', 'transaction_owned' );
    foreach ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) as $role ) alynt_core_assert( is_array( alynt_core_private( $o, 'admission_row', array( $core['options'], $role, true ) ) ), 'Owned primitive row lock failed.' );
}
