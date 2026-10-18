<?php
/** CLI-only captured fixture data. No production reader, writer or success port. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) || ! defined( 'SHORTINIT' ) || SHORTINIT ) exit( 1 );
require_once __DIR__ . '/native-scan-proof-fixtures.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-registration-request-owner.php';
add_filter( 'query', function ( $sql ) {
    foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $def ) {
        if ( false !== strpos( $sql, $def[0] ) ) { $GLOBALS['alynt_core_proof']['denied_io']++; throw new RuntimeException( 'Protected registration SQL attempted through WordPress.' ); }
    }
    return $sql;
}, PHP_INT_MAX );

function alynt_registration_values() {
    $values = alynt_scan_values(); $six = json_decode( $values['safety'], true ); $now = time();
    $site = '11111111-1111-4111-8111-111111111111';
    $e = array( 'contract' => 'closed_writer_request_v1', 'purpose' => 'upgrade_closed', 'command_id' => '88888888-8888-4888-8888-000000000071', 'site_uuid' => $site, 'enrollment_binding' => $six['enrollment_binding'], 'expected_generation' => $six['lifecycle']['permission_generation'], 'issued_at' => $now, 'expires_at' => $now + 300, 'confirm' => 'close', 'origin_digest' => Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::hash( $six ) );
    $plan = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::plan( $six, $e, $values['settings'], $now, get_current_blog_id(), 'no', null, true );
    alynt_core_assert( is_array( $plan ) && Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::valid( $plan['state'], $site ), 'Synthetic retained schema-eight fixture invalid.' );
    $s = $plan['state']; $values['safety'] = json_encode( $s );
    $values['settings'] = serialize( array( 'site_uuid' => $site, 'auto_scan_enabled' => false ) );
    $values['ledger'] = json_encode( array( 'schema_version' => 2, 'domain' => 'scan_registration_closed_v2', 'site_uuid' => $site, 'enrollment_binding' => $s['enrollment_binding'], 'authority_generation' => $s['lifecycle']['permission_generation'], 'ledger_revision' => 20, 'participant_digest' => Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::digest(), 'closed' => true, 'origin' => array( 'kind' => 'owned_closed_fixture_v2', 'ledger_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'created_at' => 1 ), 'operations' => array() ) );
    return $values; // Synthetic history, NOT an authenticated prior admission.
}
function alynt_registration_frame( mysqli $db ) {
    global $wpdb; $rows = array();
    // Test capture before invocation; this is NOT the future authorizing reader.
    foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $role => $def ) {
        $stmt = $db->prepare( "SELECT CAST(option_id AS CHAR) AS option_id,BINARY option_name AS option_name,BINARY autoload AS autoload,CAST(OCTET_LENGTH(option_value) AS CHAR) AS octets,BINARY option_value AS value FROM {$wpdb->options} WHERE option_name=?" );
        $stmt->bind_param( 's', $def[0] ); $stmt->execute(); $r = $stmt->get_result();
        alynt_core_assert( 1 === $r->num_rows, 'Registration owned frame missing role.' ); $rows[$role] = $r->fetch_assoc(); $r->free(); $stmt->close();
    }
    return array( 'blog_id' => get_current_blog_id(), 'prefix' => $wpdb->prefix, 'database' => DB_NAME, 'rows' => $rows );
}
function alynt_registration_form() {
    return array( 'purpose' => 'register_closed', 'confirm' => 'register', 'nonce' => wp_create_nonce( Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( get_current_blog_id(), 'register_closed' ) ) );
}
function alynt_registration_construct( $frame, $form, $positive = false ) {
    $o = Alynt_Drime_Backups_Uploader_Registration_Request_Owner::create(); $roles = $GLOBALS['wp_roles']; $writes = $GLOBALS['alynt_core_proof']['fixture_writes'];
    $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { $r = alynt_core_private( $o, 'construct_registration', array( $frame, $form ) ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; }
    alynt_core_assert( $positive ? is_array( $r ) : false === $r, 'Genuine registration private decision differed.' );
    alynt_core_assert( $roles === $GLOBALS['wp_roles'] && ! alynt_core_property( $o, 'active' ) && alynt_core_property( $o, 'consumed' ), 'Registration lifetime or roles escaped.' );
    alynt_core_assert( $writes === $GLOBALS['alynt_core_proof']['fixture_writes'], 'Private construction wrote via WordPress.' );
    alynt_core_assert( false === alynt_core_private( $o, 'construct_registration', array( $frame, $form ) ), 'Registration owner retried.' );
    if ( $positive ) {
        foreach ( array( 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $key ) alynt_core_assert( false === $r[$key], 'Private data became authority.' );
        alynt_core_assert( array( 'candidate_only', 'storage_supported', 'dispatch_supported', 'execution_supported', 'proposal', 'nonce', 'action' ) === array_keys( $r ), 'Unexpected handle or private export.' );
        array_walk_recursive( $r, function ( $value ) { alynt_core_assert( is_scalar( $value ) || null === $value, 'Blueprint contains an object or write-capable handle.' ); } );
    }
    return $r;
}
function alynt_registration_eligible( $frame ) {
    $o = Alynt_Drime_Backups_Uploader_Registration_Request_Owner::create();
    return alynt_core_private( $o, 'eligible_registration', array( $frame, alynt_core_private( $o, 'frame' ) ) );
}
function alynt_registration_row( array $frame, $role, $raw ) {
    $frame['rows'][$role]['value'] = $raw; $frame['rows'][$role]['octets'] = (string) strlen( $raw ); return $frame;
}
