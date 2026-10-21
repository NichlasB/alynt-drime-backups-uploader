<?php
/** php -n only: no socket, actual WordPress or native extension can execute. */
if ( realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) !== __FILE__ ) return;
if ( 2 !== $argc ) exit( 1 );
require_once __DIR__ . '/NativeLifecycleApiBootstrap.php'; alynt_native_api_bootstrap();
try {
    $frame = json_decode( stream_get_contents( STDIN ), true );
    if ( ! is_array( $frame ) ) throw new LogicException( 'Missing synthetic fixture.' );
    require_once __DIR__ . '/NativeLifecycleApiHarness.php';
    require_once __DIR__ . '/NativeLifecycleApiCases.php';
    require_once __DIR__ . '/NativeLifecycleApiControls.php';
    if ( 0 === strpos( $argv[1], 'control-' ) ) alynt_native_control( $frame, substr( $argv[1], 8 ) );
    else alynt_native_case( $frame, $argv[1] );
    if ( $GLOBALS['native_failures'] ) throw new LogicException( $GLOBALS['native_failures'][0] );
    echo json_encode( array( 'ok' => true, 'case' => $argv[1], 'checks' => $GLOBALS['native_checks'], 'native_extension' => false, 'authentication' => false, 'storage' => false, 'native_execution' => false, 'wiring' => false ) );
} catch ( Throwable $error ) {
    echo json_encode( array( 'ok' => false, 'checks' => $GLOBALS['native_checks'], 'failure' => $GLOBALS['native_failures'][0] ?? ( get_class( $error ) . ':' . basename( $error->getFile() ) . ':' . $error->getLine() ), 'context' => $GLOBALS['native_context'] ?? null ) ); exit( 1 );
}
