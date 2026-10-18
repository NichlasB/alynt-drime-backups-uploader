<?php
/** Owned native SQL helpers; never loaded by WordPress/plugin bootstrap. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) || ! defined( 'SHORTINIT' ) || SHORTINIT ) exit( 1 );
require dirname( __DIR__, 2 ) . '/includes/class-native-closed-begin.php';

function alynt_native_begin_form( array $s, $settings, $offset = 0 ) {
    list( $form ) = alynt_core_form( 'begin_disable', $offset );
    $context = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::context( $s, $settings, $form['site_uuid'], 1, 'no' );
    alynt_core_assert( is_array( $context ), 'Closed begin fixture context failed.' );
    $form['expected_generation'] = (string) $s['lifecycle']['permission_generation'];
    $form['enrollment_binding'] = $s['enrollment_binding'];
    foreach ( array( 'context_digest', 'before_hash', 'after_hash' ) as $key ) $form[$key] = $context[$key];
    $typed = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::parse( $form );
    $form['nonce'] = wp_create_nonce( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::action( $typed['envelope'] ) );
    return $form;
}
function alynt_native_begin_db() {
    global $wpdb;
    $prefix = $GLOBALS['alynt_core_proof']['prefix'];
    alynt_core_check( $wpdb->options === $prefix . 'options' && 1 === preg_match( '/^wp_alynt_core_probe_[a-f0-9]{12}_$/D', $prefix ), 'Closed begin fixture target mismatch.' );
    $db = new mysqli( '127.0.0.1', 'root', getenv( 'MYSQL_PWD' ), 'local', (int) $GLOBALS['argv'][2] ); $db->set_charset( 'utf8mb4' );
    alynt_core_check( $GLOBALS['argv'][4] === $db->query( "SELECT option_value FROM {$prefix}options WHERE option_name='probe_owner'" )->fetch_row()[0], 'Closed begin owner marker mismatch.' );
    return $db;
}
function alynt_native_begin_rows( $db ) {
    $table = $GLOBALS['alynt_core_proof']['prefix'] . 'options';
    $rows = $db->query( "SELECT option_name,CAST(option_id AS CHAR) AS option_id,BINARY autoload AS autoload,BINARY option_value AS value FROM {$table} WHERE option_name IN ('alynt_drime_backups_schedule_safety_state','alynt_drime_backups_settings') ORDER BY option_name" )->fetch_all( MYSQLI_ASSOC );
    $out = array(); foreach ( $rows as $row ) $out[$row['option_name']] = $row;
    return $out;
}
function alynt_native_begin_run( $form, $positive = false, $reads = null ) {
    $owner = Alynt_Drime_Backups_Uploader_Native_Closed_Begin::create();
    $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { $value = alynt_core_private( $owner, 'begin', array( 'POST', $form ) ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; }
    alynt_core_assert( $positive === $value, 'Native closed begin private decision differed.' );
    if ( null !== $reads ) alynt_core_assert( $reads === alynt_core_property( $owner, 'protected_reads' ), 'Native closed begin protected-read ordering differed.' );
    alynt_core_assert( null === alynt_core_property( $owner, 'reader' ) && null === alynt_core_property( $owner, 'physical' ), 'Native writer connection escaped.' );
    $writes = alynt_core_property( $owner, 'writes' );
    alynt_core_assert( false === alynt_core_private( $owner, 'begin', array( 'POST', $form ) ) && $writes === alynt_core_property( $owner, 'writes' ), 'One-shot writer reentered or replayed.' );
    return $owner;
}
function alynt_native_begin_set( $owner, $name, $value ) {
    $p = new ReflectionProperty( $owner, $name ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $p->setValue( $owner, $value );
}
