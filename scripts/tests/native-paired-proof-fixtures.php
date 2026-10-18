<?php
/** Fixed owned fixture helpers; never loaded by application bootstrap. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) ) exit( 1 );
require __DIR__ . '/native-closed-begin-proof-fixtures.php';

function alynt_pair_seed() {
    $site = '11111111-1111-4111-8111-111111111111'; $six = alynt_native_six(); $now = time();
    $target = serialize( array( 'site_uuid' => $site, 'auto_scan_enabled' => true, 'unknown' => array( "ø\0b:1;", 'private-fixture' ) ) );
    $e = array( 'contract' => 'closed_writer_request_v1', 'purpose' => 'upgrade_closed', 'command_id' => '88888888-8888-4888-8888-000000000011', 'site_uuid' => $site, 'enrollment_binding' => $six['enrollment_binding'], 'expected_generation' => $six['lifecycle']['permission_generation'], 'issued_at' => $now, 'expires_at' => $now + 300, 'confirm' => 'close', 'origin_digest' => Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::hash( $six ) );
    $p = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::plan( $six, $e, $target, $now, 1, 'no', null, true );
    alynt_core_assert( is_array( $p ), 'Synthetic origin failed.' );
    alynt_native_seed_rows( array( 'alynt_drime_backups_schedule_safety_state' => json_encode( $p['state'] ), 'alynt_drime_backups_settings' => $target ) );
    alynt_core_login(); return array( $p['state'], $target, alynt_native_begin_form( $p['state'], $target ) );
}
function alynt_pair_call( $owner, $method, array $args ) {
    $prior = $GLOBALS['alynt_core_proof']['guard_readonly']; $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { return alynt_core_private( $owner, $method, $args ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = $prior; }
}
function alynt_pair_closed( $form ) {
    $owner = Alynt_Drime_Backups_Uploader_Native_Closed_Begin::create();
    alynt_core_assert( true === alynt_pair_call( $owner, 'begin', array( 'POST', $form ) ), 'Confirmed begin prerequisite missing.' );
    alynt_core_assert( is_array( alynt_core_property( $owner, 'continuation' ) ) && null === alynt_core_property( $owner, 'reader' ), 'Private confirmed continuation missing or handle escaped.' );
    return $owner;
}
function alynt_pair_clean( $owner ) {
    alynt_core_assert( null === alynt_core_property( $owner, 'reader' ) && null === alynt_core_property( $owner, 'physical' ) && null === alynt_core_property( $owner, 'continuation' ), 'Pair/resolution retained private resources.' );
    alynt_core_assert( false === $owner->disposition()['storage_supported'], 'Public admission was enabled.' );
}
function alynt_pair_receipt_form( $id, $offset = 0 ) {
    list( $f ) = alynt_core_form( 'receipt_read', $offset ); $f['observed_command_id'] = $id;
    $e = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::parse( $f )['envelope'];
    $f['nonce'] = wp_create_nonce( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::action( $e ) ); return $f;
}
function alynt_pair_resolve( $f, $expected, $reads = null ) {
    $o = Alynt_Drime_Backups_Uploader_Native_Closed_Begin::create();
    $r = alynt_pair_call( $o, 'resolve', array( 'POST', $f ) );
    alynt_core_assert( false === $expected ? false === $r : is_array( $r ) && $expected === $r['historical'], 'Locked receipt decision differed.' );
    if ( null !== $reads ) alynt_core_assert( $reads === alynt_core_property( $o, 'protected_reads' ), 'Receipt authorization/barrier ordering differed.' );
    alynt_core_assert( 0 === alynt_core_property( $o, 'writes' ) && false === alynt_pair_call( $o, 'resolve', array( 'POST', $f ) ), 'Receipt wrote or replayed.' );
    alynt_pair_clean( $o ); return array( $o, $r );
}
// Separately labeled protocol primitive: real owned IO, not request admission.
function alynt_pair_primitive( $owner ) {
    $c = alynt_core_property( $owner, 'continuation' );
    alynt_native_begin_set( $owner, 'continuation', null );
    alynt_native_begin_set( $owner, 'protected_reads', 0 ); alynt_native_begin_set( $owner, 'writes', 0 );
    alynt_core_assert( true === alynt_core_private( $owner, 'open_writer', array( $c['auth'], $c['before'] ) ), 'Owned pair primitive factory failed.' );
    $s = Alynt_Drime_Backups_Uploader_Closed_Writer_Json_Contract::decode( $c['a']['value'] );
    $p = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::completion( $s, $c['e'], $c['b']['value'], time(), 1, 'no', true );
    alynt_core_assert( is_array( $p ), 'Pair candidate failed.' );
    $db = alynt_core_property( $owner, 'reader' );
    alynt_native_begin_set( $owner, 'phase', 'begin_sent' ); alynt_core_assert( true === $db->query( 'START TRANSACTION READ WRITE' ), 'Primitive BEGIN failed.' );
    alynt_native_begin_set( $owner, 'phase', 'transaction_owned' );
    alynt_core_assert( $c['a'] === alynt_core_private( $owner, 'row', array( $c['frame']['options'], true, true ) ) && $c['b'] === alynt_core_private( $owner, 'row', array( $c['frame']['options'], false, true ) ), 'Primitive lock tuples differed.' );
    return array( $c, $p, $db );
}
