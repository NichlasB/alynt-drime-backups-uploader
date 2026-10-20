<?php
/** Extension-free composed source proof; no WordPress bootstrap, socket or SQL. */
if ( realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) !== __FILE__ ) return;
if ( 2 !== $argc ) exit( 1 );
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' ); define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'DB_NAME', 'synthetic' ); define( 'LOGGED_IN_COOKIE', 'alynt_unit_logged_in' );
require_once __DIR__ . '/RegistrationOutcomeApiDoubles.php'; alynt_outcome_api_install_doubles();
require_once __DIR__ . '/FixedCoreRequestShims.php'; alynt_test_fixed_core_request_shims();
$checks = 0; $failures = array(); $injections = array();
function alynt_failure_check( $ok, $label ) { ++$GLOBALS['checks']; if ( ! $ok ) $GLOBALS['failures'][] = $label; } // Deferred assertions cannot be swallowed by runtime catches.
function alynt_failure_inject( $point ) { $GLOBALS['injections'][] = $point; throw new RuntimeException( 'Expected isolated API interruption.' ); }
function alynt_failure_install_core_doubles() {
function add_filter( $hook, $callback, $priority = 10 ) { $GLOBALS['owned'][$hook][] = array( $callback, $priority ); }
function remove_filter( $hook, $callback, $priority = 10 ) {
    $f =& $GLOBALS['owned']; $key = array_search( array( $callback, $priority ), $f[$hook] ?? array(), true );
    alynt_failure_check( false !== $key, 'owned exact filter removal' ); if ( false !== $key ) unset( $f[$hook][$key] ); if ( ! $f[$hook] ) unset( $f[$hook] ); return true;
}
function wp_generate_uuid4() { return sprintf( '99999999-9999-4999-8999-%012d', ++$GLOBALS['uuid'] ); }
function wp_create_nonce( $action ) {
    alynt_test_core_call( 'create_nonce', array( $action ) ); $n = substr( hash( 'sha256', $action ), 0, 10 );
    $GLOBALS['alynt_core_request_test']['nonce_actions'][$n] = $action; return $n;
}
function wp_json_encode( $value ) { return json_encode( $value ); }
class WP_Error { public function __construct( $code = '', $message = '' ) {} }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
}
alynt_failure_install_core_doubles();
$GLOBALS['alynt_core_request_test'] = array( 'calls' => array(), 'on' => array(), 'throw' => '', 'multisite' => false, 'external_cache' => false, 'blog' => 1, 'actor' => 7, 'cookie_actor' => 7, 'logged_in' => true, 'session_now' => true, 'session_cache' => true, 'user_now' => true, 'user_cache' => true, 'role_now' => true, 'role_cache' => true, 'filter_allows' => true, 'nonce_result' => 1 );
$GLOBALS['owned'] = array(); $GLOBALS['uuid'] = 0; $GLOBALS['wp_roles'] = new WP_Roles( 1 );
$_COOKIE[LOGGED_IN_COOKIE] = 'unit-cookie'; $_SERVER['REQUEST_METHOD'] = 'POST';
try {
    $frame = json_decode( stream_get_contents( STDIN ), true ); if ( ! is_array( $frame ) ) throw new LogicException( 'Missing synthetic fixture.' );
    // Definitions only, same prerequisite order as the established owned proof;
    // never load its operational worker, instantiate a CAS or bootstrap WordPress.
    foreach ( array( 'class-schedule-safety-option-cas', 'trait-schedule-safety-state-validation', 'trait-schedule-safety-state-transitions', 'class-schedule-safety-store', 'trait-cadence-authority-validation', 'trait-cadence-authority-mutations', 'trait-cadence-authority-dispatch', 'class-cadence-authority-store', 'trait-cadence-authority-lifecycle-preparation', 'class-cadence-authority-lifecycle-store', 'trait-consent-ledger-validation', 'trait-consent-ledger-commands', 'trait-consent-ledger-migration', 'trait-consent-ledger-api-boundary', 'class-cadence-consent-ledger', 'trait-closed-consent-provenance-validation', 'class-closed-consent-provenance-store', 'trait-closed-writer-barrier-records', 'trait-closed-writer-barrier-validation', 'trait-closed-writer-barrier-transitions', 'trait-closed-writer-barrier-commands', 'class-closed-writer-barrier-store', 'class-closed-writer-admission-contract', 'class-closed-writer-admission-snapshot', 'class-closed-writer-admission-projection' ) as $part ) require_once ABSPATH . 'includes/' . $part . '.php';
    require_once __DIR__ . '/RegistrationFailureApiHarness.php'; require_once __DIR__ . '/RegistrationFailureApiCases.php';
    alynt_failure_case( $frame, $argv[1] );
    if ( $failures ) throw new LogicException( $failures[0] );
    echo json_encode( array( 'ok' => true, 'case' => $argv[1], 'checks' => $checks, 'native_extension' => false, 'authentication' => false, 'storage' => false, 'execution' => false ) );
} catch ( Throwable $error ) {
    echo json_encode( array( 'ok' => false, 'checks' => $checks, 'failure' => $failures[0] ?? ( 'Unexpected worker error: ' . get_class( $error ) . ':' . basename( $error->getFile() ) . ':' . $error->getLine() ), 'context' => $GLOBALS['failure_context'] ?? null ) ); exit( 1 );
}
