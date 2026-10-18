<?php
/** Confirmed owned three-table scope only. Never loaded by production. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) ) exit( 1 );
require_once dirname( __DIR__, 2 ) . '/includes/class-scan-native-observer.php';
foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $definition ) {
    if ( in_array( $definition[0], array( 'alynt_drime_backups_settings', 'alynt_drime_backups_schedule_safety_state' ), true ) ) continue;
    add_filter( 'pre_option_' . $definition[0], function () { $GLOBALS['alynt_core_proof']['protected_reads']++; throw new RuntimeException( 'Protected scan option API attempted.' ); }, PHP_INT_MAX );
}

/** Synthetic bytes; no claim of authenticated scan/ledger history. */
function alynt_scan_values() {
    $site = '11111111-1111-4111-8111-111111111111'; $s = alynt_native_six();
    $ledger = array( 'schema_version' => 1, 'domain' => 'scan_effect_storage_closed_v1', 'site_uuid' => $site, 'enrollment_binding' => $s['enrollment_binding'], 'authority_generation' => $s['lifecycle']['permission_generation'], 'ledger_revision' => 1, 'participant_digest' => Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::digest(), 'closed' => true, 'operations' => array() );
    $out = array_fill_keys( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ), 'a:0:{}' );
    $out['safety'] = json_encode( $s ); $out['ledger'] = json_encode( $ledger );
    $out['settings'] = serialize( array( 'site_uuid' => $site, 'auto_scan_enabled' => true, 'private_fixture' => "秘密\0not-a-secret" ) );
    return $out;
}
/** A fresh native helper owns only already-created marker-verified fixture storage. */
function alynt_scan_db() {
    global $wpdb;
    $prefix = $GLOBALS['alynt_core_proof']['prefix'];
    alynt_core_check( 1 === preg_match( '/^wp_alynt_core_probe_[a-f0-9]{12}_$/D', $prefix ) && $wpdb->options === $prefix . 'options', 'Scan fixture target mismatch.' );
    $db = new mysqli( '127.0.0.1', 'root', getenv( 'MYSQL_PWD' ), 'local', (int) $GLOBALS['argv'][2] ); $db->set_charset( 'utf8mb4' );
    alynt_core_check( $GLOBALS['argv'][4] === $db->query( "SELECT option_value FROM {$prefix}options WHERE option_name='probe_owner'" )->fetch_row()[0], 'Scan fixture marker mismatch.' );
    return $db;
}
function alynt_scan_seed( array $values, $autoload = 'no' ) {
    $db = alynt_scan_db(); $table = $GLOBALS['alynt_core_proof']['prefix'] . 'options';
    try {
        alynt_core_check( in_array( $autoload, array( 'no', 'off', 'auto-off', 'yes' ), true ), 'Unknown fixture metadata.' );
        $stmt = $db->prepare( "INSERT INTO {$table}(option_name,option_value,autoload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE option_value=VALUES(option_value),autoload=VALUES(autoload)" );
        foreach ( $values as $role => $value ) {
            $manifest = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows();
            alynt_core_check( isset( $manifest[$role] ) && is_string( $value ), 'Unowned scan fixture role refused.' ); $name = $manifest[$role][0];
            $stmt->bind_param( 'sss', $name, $value, $autoload ); $stmt->execute();
        }
        $stmt->close();
    } finally { $db->close(); }
}
function alynt_scan_form( $offset = 0 ) {
    $now = time() + $offset;
    $form = array( 'contract' => 'scan_effect_observation_v1', 'purpose' => 'observe_closed_manifest', 'command_id' => '88888888-8888-4888-8888-000000000055', 'site_uuid' => '11111111-1111-4111-8111-111111111111', 'issued_at' => (string) $now, 'expires_at' => (string) ( $now + 300 ), 'confirm' => 'observe', 'nonce' => 'pending' );
    $parsed = Alynt_Drime_Backups_Uploader_Scan_Observation_Request::parse( $form ); alynt_core_assert( is_array( $parsed ), 'Scan proof form must parse.' );
    $form['nonce'] = wp_create_nonce( $parsed['action'] ); return $form;
}
function alynt_scan_observe( $form, $reads, $positive = false ) {
    $o = Alynt_Drime_Backups_Uploader_Scan_Native_Observer::create(); $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { $result = alynt_core_private( $o, 'observe', array( 'POST', $form ) ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; }
    alynt_core_assert( $positive === $result, 'Actual private scan observation decision differed: expected=' . (int) $positive . ', actual=' . (int) $result . ', reads=' . alynt_core_property( $o, 'protected_reads' ) . ', completed_groups=' . count( $GLOBALS['alynt_core_proof']['cases'] ) );
    alynt_core_assert( $reads === alynt_core_property( $o, 'protected_reads' ), 'Scan protected-read ordering/count differed.' );
    alynt_core_assert( null === alynt_core_property( $o, 'reader' ) && false === alynt_core_property( $o, 'active' ), 'Scan owned lifetime escaped.' );
    return $o;
}
function alynt_scan_delete( $role ) {
    $manifest = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows(); alynt_core_check( isset( $manifest[$role] ), 'Unknown deletion target.' );
    $db = alynt_scan_db(); $table = $GLOBALS['alynt_core_proof']['prefix'] . 'options'; $name = $manifest[$role][0];
    try {
        $stmt = $db->prepare( "SELECT COUNT(*) FROM {$table} WHERE option_name=?" ); $stmt->bind_param( 's', $name ); $stmt->execute();
        alynt_core_assert( 1 === (int) $stmt->get_result()->fetch_row()[0], 'Owned row deletion preview must be exactly one.' ); $stmt->close();
        $stmt = $db->prepare( "DELETE FROM {$table} WHERE option_name=?" ); $stmt->bind_param( 's', $name ); $stmt->execute(); $stmt->close();
    } finally { $db->close(); }
}
/** Only the fixed owned queue row's binary option name; no generic mutation API. */
function alynt_scan_case_name( $restore = false ) {
    $db = alynt_scan_db(); $table = $GLOBALS['alynt_core_proof']['prefix'] . 'options';
    $original = 'alynt_drime_backups_upload_queue'; $variant = strtoupper( $original );
    $from = $restore ? $variant : $original; $to = $restore ? $original : $variant;
    try { $stmt = $db->prepare( "UPDATE {$table} SET option_name=? WHERE BINARY option_name=?" ); $stmt->bind_param( 'ss', $to, $from ); $stmt->execute(); alynt_core_assert( 1 === $stmt->affected_rows, 'Owned binary-name control did not affect exactly one fixture.' ); $stmt->close(); }
    finally { $db->close(); }
}
