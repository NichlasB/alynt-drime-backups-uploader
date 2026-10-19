<?php
/** Extension-free API exercise only. No WordPress bootstrap, credentials or IO. */
if ( realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) !== __FILE__ ) return;
if ( 2 !== $argc || ! in_array( $argv[1], array( 'commit', 'ambiguous', 'write_failures', 'rollback', 'cleanup', 'readback', 'lifetime', 'ownership' ), true ) ) exit( 1 );
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require_once __DIR__ . '/RegistrationOutcomeApiDoubles.php';
alynt_outcome_api_install_doubles();
require_once __DIR__ . '/RegistrationOutcomeApiHarness.php';
$checks = 0;
function alynt_outcome_api_check( $ok, $label ) {
    $GLOBALS['checks']++; if ( ! $ok ) throw new LogicException( 'Outcome API assertion: ' . $label );
}
function alynt_outcome_snapshot( $o ) {
    $capture = $o->state( 'native_outcome' ); alynt_outcome_api_check( is_array( $capture ) && true === $capture['valid'], 'valid capture' );
    $m = new ReflectionMethod( Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::class, 'facts' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true );
    alynt_outcome_api_check( true === $m->invoke( null, $capture['facts'] ), 'DATA fact dependencies' );
    $m = new ReflectionMethod( Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::class, 'history' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true );
    $capture['history'] = $m->invoke( null, $capture['facts'] );
    alynt_outcome_api_check( 0 === $capture['facts']['events']['accepted_at'] && array_fill_keys( array( 'auth', 'intent', 'physical', 'unreentered' ), null ) === $capture['facts']['guards'], 'no auth/acceptance invented' );
    alynt_outcome_api_check( array() === $o->auth->calls, 'borrowed handle untouched' );
    return $capture;
}
function alynt_outcome_run( $fault = '', $returns = array(), $throws = array() ) {
    $o = new Alynt_Outcome_Api_Harness( $fault ); $o->db->returns = $returns; $o->db->throws = $throws;
    $o->db->inspect = function( $point ) use ( $o ) {
        $c = $o->state( 'native_outcome' ); $e = $c['facts']['events'];
        if ( in_array( $point, array( 'begin', 'execute', 'commit', 'rollback', 'close' ), true ) ) {
            $key = 'execute' === $point ? 'dml' : $point; $marker = 'close' === $key ? 'detached' : $key . '_sent';
            alynt_outcome_api_check( $e[$marker] > 0 && 'unknown' === $c['raw_returns'][$key] && 'unknown' === $c['facts']['acks'][$key], $point . ' marker before API' );
        }
        if ( 'prepare' === $point ) alynt_outcome_api_check( 1 === $o->state( 'writes' ) && $e['attempt_reserved'] > 0 && 0 === $e['dml_sent'], 'attempt before prepare, not send' );
        if ( 'execute' === $point ) alynt_outcome_api_check( $e['bind_verified'] > 0, 'bind before execute' );
        if ( 'commit' === $point ) alynt_outcome_api_check( $e['readback'] > 0 && 20 === count( $o->row_calls ), 'all ten readbacks before commit' );
        if ( 'close' === $point ) alynt_outcome_api_check( null === $o->state( 'reader' ), 'detach before close' );
    };
    try { $opened = $o->call( 'open_writer', $o->auth, array( 'original' => true ) ); $ok = $opened && $o->call( 'admission_transaction', array( 'options' => 'wp_options' ), array( 'expires_at' => 500 ) ); }
    catch ( RuntimeException $e ) { $ok = false; }
    finally { $o->call( 'finish' ); }
    alynt_outcome_api_check( null === $o->state( 'reader' ) && null === $o->state( 'physical' ) && null === $o->state( 'native_outcome_writer' ), 'owned cleanup' );
    return array( $o, $ok );
}
try {
    require __DIR__ . '/RegistrationOutcomeApiCases.php';
    alynt_outcome_api_cases( $argv[1] );
    echo json_encode( array( 'ok' => true, 'group' => $argv[1], 'checks' => $checks, 'native_extension' => false, 'authentication' => false, 'storage' => false, 'execution' => false ) );
} catch ( Throwable $error ) {
    $message = 0 === strpos( $error->getMessage(), 'Outcome API assertion: ' ) ? $error->getMessage() : 'Unexpected API worker error: ' . get_class( $error );
    echo json_encode( array( 'ok' => false, 'checks' => $checks, 'failure' => $message ) ); exit( 1 );
}
