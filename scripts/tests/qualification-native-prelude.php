<?php
/** Fixed direct-backend genuine guards only. Never opens the qualification channel/relay. */
if ( 'cli' !== PHP_SAPI || 5 !== count( $argv ) ) exit( 1 );
$root = realpath( $argv[1] );
if ( $root !== realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' ) || '10004' !== $argv[2] || ! preg_match( '/^wp_alynt_core_probe_[a-f0-9]{12}_$/D', $argv[3] ) || ! preg_match( '/^[a-f0-9]{24}$/D', $argv[4] ) ) exit( 1 );
$qualification_thread = null;
try {
    $qualification_bootstrap_port = 10004; require __DIR__ . '/qualification-native-bootstrap.php';
    $qualification_thread = $wpdb->dbh->thread_id;
    require __DIR__ . '/fixed-core-proof-cases.php'; require __DIR__ . '/fixed-core-proof-denials.php'; alynt_core_cases(); alynt_core_denials();
    $checks = $GLOBALS['alynt_core_proof']['checks']; $cases = $GLOBALS['alynt_core_proof']['cases'];
    // Seed the ten synthetic ledger rows solely for the unchanged independent cleanup/lock audit.
    require __DIR__ . '/native-observer-proof-fixtures.php'; require __DIR__ . '/registration-join-proof-fixtures.php';
    alynt_scan_seed( alynt_registration_values() );
    alynt_core_check( 0 === $GLOBALS['alynt_core_proof']['protected_reads'] && 0 === $GLOBALS['alynt_core_proof']['denied_io'] && array() === headers_list(), 'qualification_escape' );
    foreach ( get_included_files() as $file ) alynt_core_check( 0 !== strpos( str_replace( '\\', '/', $file ), str_replace( '\\', '/', WP_CONTENT_DIR ) . '/' ), 'qualification_site_content' );
    alynt_core_check( true === $wpdb->close(), 'qualification_prelude_close' );
    $_COOKIE = array(); unset( $GLOBALS['current_user'] );
    echo json_encode( array( 'passed' => true, 'checks' => $checks, 'cases' => $cases, 'auth_thread' => $qualification_thread, 'protected_option_api_reads' => 0, 'public_storage_dispatch_execution' => false, 'browser_headers_emitted' => false, 'qualification_attempted' => false ) ) . "\n";
    exit( 0 );
} catch ( Throwable $error ) {
    // Thread is private cleanup correlation only; no raw error/SQL or authentication material.
    echo json_encode( array( 'passed' => false, 'auth_thread' => $qualification_thread ) ) . "\n";
    exit( 1 );
}
