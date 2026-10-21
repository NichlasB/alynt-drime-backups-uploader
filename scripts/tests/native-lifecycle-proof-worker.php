<?php
/** One private genuine-core worker. Never a site endpoint, relay or loss experiment. */
if ( 'cli' !== PHP_SAPI || 5 !== count( $argv ) ) exit( 1 );
$root = realpath( $argv[1] );
if ( ! $root || $root !== realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' ) || '10004' !== $argv[2] || 1 !== preg_match( '/^wp_alynt_core_probe_[a-f0-9]{12}_$/D', $argv[3] ) || 1 !== preg_match( '/^[a-f0-9]{24}$/D', $argv[4] ) ) exit( 1 );
require __DIR__ . '/native-lifecycle-proof-manifest.php'; if ( ! Alynt_Test_Native_Lifecycle_Manifest::verify() ) exit( 1 );
require __DIR__ . '/fixed-core-proof-storage.php';
$GLOBALS['alynt_core_proof'] = array( 'prefix' => $argv[3], 'queries' => 0, 'fixture_writes' => 0, 'guard_readonly' => false, 'checks' => 0, 'cases' => array(), 'events' => array(), 'protected_reads' => 0, 'denied_io' => 0 );
$GLOBALS['alynt_native_lifecycle_threads'] = array(); $receipt = array( 'passed' => false );
try {
    require __DIR__ . '/native-lifecycle-proof-bootstrap.php'; alynt_native_lifecycle_thread_bootstrap();
    require __DIR__ . '/fixed-core-proof-cases.php'; require __DIR__ . '/fixed-core-proof-denials.php'; alynt_core_cases(); alynt_core_denials();
    $receipt['genuine_guard_prelude'] = array( 'checks' => $GLOBALS['alynt_core_proof']['checks'], 'groups' => count( $GLOBALS['alynt_core_proof']['cases'] ) );
    require __DIR__ . '/native-lifecycle-proof-source.php';
    alynt_core_assert( Alynt_Test_Native_Lifecycle_Manifest::composition(), 'native_lifecycle_exact_source_composition' );
    require __DIR__ . '/native-observer-proof-fixtures.php'; require __DIR__ . '/registration-admission-proof-fixtures.php';
    require __DIR__ . '/native-lifecycle-proof-checks.php'; require __DIR__ . '/native-lifecycle-proof-denials.php'; require __DIR__ . '/native-lifecycle-proof-cases.php';
    alynt_scan_seed( alynt_registration_values() ); $audit = alynt_scan_db(); $before = alynt_registration_frame( $audit );
    try { alynt_native_lifecycle_denials(); alynt_core_assert( $before === alynt_registration_frame( $audit ), 'native_lifecycle_denials_no_ledger_or_manifest_delta' ); } finally { $audit->close(); }
    alynt_native_lifecycle_positive();
    alynt_core_assert( 0 === $GLOBALS['alynt_core_proof']['protected_reads'] && 0 === $GLOBALS['alynt_core_proof']['denied_io'] && array() === headers_list(), 'native_lifecycle_no_protected_wp_io_or_browser_headers' );
    foreach ( get_included_files() as $file ) alynt_core_assert( 0 !== strpos( str_replace( '\\', '/', $file ), str_replace( '\\', '/', WP_CONTENT_DIR ) . '/' ), 'native_lifecycle_no_site_content_loaded' );
    $receipt['passed'] = true;
} catch ( Throwable $error ) {
    $receipt['failed'] = true; $receipt['failure'] = $error instanceof RuntimeException ? $error->getMessage() : 'Unexpected failure; private payload withheld.';
    $receipt['failure_location'] = array( basename( $error->getFile() ), $error->getLine() );
} finally {
    $receipt['owned_threads'] = array_values( $GLOBALS['alynt_native_lifecycle_threads'] );
    if ( isset( $wpdb ) && $wpdb->dbh instanceof mysqli ) { try { if ( true !== $wpdb->close() ) $receipt['passed'] = false; } catch ( Throwable $error ) { $receipt['passed'] = false; } }
    $_COOKIE = array(); unset( $GLOBALS['current_user'] );
    $receipt['checks'] = $GLOBALS['alynt_core_proof']['checks']; $receipt['cases'] = $GLOBALS['alynt_core_proof']['cases'];
    $receipt['native_observation'] = $GLOBALS['alynt_native_lifecycle_diagnostic'] ?? null;
    $receipt['suppression_and_production_wiring'] = false;
}
echo json_encode( $receipt, JSON_PRETTY_PRINT ) . "\n"; exit( $receipt['passed'] ? 0 : 1 );
function alynt_native_lifecycle_thread_bootstrap() { $id = $GLOBALS['wpdb']->dbh->thread_id; $GLOBALS['alynt_native_lifecycle_threads'][$id] = $id; }
