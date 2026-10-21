<?php
/** Extension-free API test process; NOT the private protocol or a native worker. */
if ( realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) !== __FILE__ ) return;
if ( 'cli' !== PHP_SAPI || extension_loaded( 'mysqli' ) || 2 !== count( $argv ) ) exit( 1 );
require_once __DIR__ . '/NativeQualificationManifest.php';
function alynt_qualification_compile( $name, array $changes ) {
    if ( ! in_array( $name, array( 'RegistrationOutcomeApiDoubles.php', 'NativeLifecycleApiBootstrap.php', 'NativeQualificationObservation.php', 'NativeLifecycleApiHarness.php' ), true ) ) throw new LogicException( 'Fixed API source name refused.' );
    $path = __DIR__ . '/' . $name; if ( ! is_readable( $path ) || filesize( $path ) > 262144 ) throw new LogicException( 'Fixed API source unavailable.' );
    $code = file_get_contents( $path );
    foreach ( $changes as $from => $to ) { $code = str_replace( $from, $to, $code, $count ); if ( 1 !== $count ) throw new LogicException( 'Unique fixed API seam unavailable.' ); }
    return substr( $code, 5 );
}
try {
    if ( ! Alynt_Test_Native_Qualification_Manifest::verify() ) throw new LogicException( 'Source identity refused.' );
    $case = $argv[1]; $control = 0 === strpos( $case, 'control-' ) ? substr( $case, 8 ) : null;
    if ( $control && ! in_array( $control, array( 'bypass', 'factory', 'return', 'take' ), true ) ) throw new LogicException( 'Unknown fixed control.' );
    $base = $control || 'take-handle' === $case ? 'commit' : $case;
    // Reuse the immutable API doubles, declaring one primitive test property. No native class is available.
    eval( alynt_qualification_compile( 'RegistrationOutcomeApiDoubles.php', array( 'function alynt_outcome_api_install_doubles()' => 'function alynt_native_qualification_install_doubles()', 'public $calls = array();' => 'public $thread_id = 0; public $calls = array();' ) ) );
    eval( alynt_qualification_compile( 'NativeLifecycleApiBootstrap.php', array( 'function alynt_native_api_bootstrap()' => 'function alynt_native_qualification_api_bootstrap()', "require_once __DIR__ . '/RegistrationOutcomeApiDoubles.php'; alynt_outcome_api_install_doubles();" => 'alynt_native_qualification_install_doubles();' ) ) );
    alynt_native_qualification_api_bootstrap();
    require_once __DIR__ . '/NativeQualificationOwner.php';
    if ( ! Alynt_Test_Native_Qualification_Manifest::composition() ) throw new LogicException( 'Fixed composition refused.' );
    $trait = 'Alynt_Test_Native_Qualification_Observation';
    if ( 'take' === $control ) {
        $code = file_get_contents( __DIR__ . '/NativeQualificationObservation.php' );
        $line = null; foreach ( explode( "\n", $code ) as $row ) if ( false !== strpos( $row, "foreach ( array( 'native_resource'" ) ) $line = $row;
        if ( null === $line ) throw new LogicException( 'Fixed erasure control unavailable.' );
        $trait .= '_Control';
        eval( alynt_qualification_compile( 'NativeQualificationObservation.php', array( 'trait Alynt_Test_Native_Qualification_Observation {' => 'trait ' . $trait . ' {', $line => '' ) ) );
    }
    $code = alynt_qualification_compile( 'NativeLifecycleApiHarness.php', array(
        'final class Alynt_Test_Native_Lifecycle_Api {' => 'final class Alynt_Test_Native_Qualification_Api {',
        'use Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Methods { qualify_admission_outcome as private source_qualification; }' => 'use ' . $trait . ' { qualify_admission_outcome as private observed_qualification; }',
        'public $frame_data;' => 'public $observed_result; public $frame_data;',
        'public function run( ...$args ) { return $this->lifecycle_root( ...($args ?: array( \'POST\', array( \'purpose\' => \'register_closed\', \'confirm\' => \'register\', \'nonce\' => \'unit-nonce\' ) )) ); }' => 'public function run( ...$args ) { $first = ! $this->root_attempted; $r = $this->lifecycle_root( ...($args ?: array( \'POST\', array( \'purpose\' => \'register_closed\', \'confirm\' => \'register\', \'nonce\' => \'unit-nonce\' ) )) ); if ( $first ) $this->observed_result = $r; return $r; }',
        '$this->source_qualification( $pin, $guards )' => 'bypass' === $control ? '$this->qualification_source( $pin, $guards )' : '$this->observed_qualification( $pin, $guards )',
        '$this->reader = new mysqli();' => '$this->reader = new mysqli(); $this->reader->thread_id = spl_object_id( $this->reader );',
    ) );
    if ( 'factory' === $control ) $code = substr_replace( $code, 'private function open_lifecycle_session( $writer ) { return $this->session_source( $writer ); }', strrpos( $code, '}' ), 0 );
    if ( 'return' === $control ) { $code = str_replace( 'return $this->qualified;', 'return false;', $code, $n ); if ( 1 !== $n ) throw new LogicException( 'Return control unavailable.' ); }
    eval( $code );
    require_once __DIR__ . '/NativeLifecycleApiCases.php';
    $bytes = stream_get_contents( STDIN, 131073 ); if ( strlen( $bytes ) > 131072 ) throw new LogicException( 'Synthetic frame cap.' );
    $frame = json_decode( $bytes, true, 32 ); if ( ! is_array( $frame ) ) throw new LogicException( 'Synthetic frame refused.' );
    $o = alynt_native_case( $frame, $base, 'Alynt_Test_Native_Qualification_Api' );
    $positive = in_array( $base, array( 'commit', 'uncertain', 'uncertain_throw', 'absence' ), true );
    if ( 'take' === $control || 'take-handle' === $case ) $o->state( 'reader', new mysqli() );
    $m = new ReflectionMethod( $o, 'take_native_qualification_observation' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true );
    $receipt = $m->invoke( $o );
    if ( 'take' === $control || 'take-handle' === $case ) alynt_native_check( false === $receipt, 'retained handle refuses extraction' );
    elseif ( $positive ) {
        alynt_native_check( is_array( $o->observed_result ), 'observed root remains genuinely positive' );
        alynt_native_check( is_array( $receipt ) && true === $receipt['observation']['valid'] && $o->qualified === $receipt['observation']['result'], 'actual alias observation required' );
        alynt_native_check( is_array( $receipt ) && true === $receipt['factory_observation_valid'] && array( $o->dbs['capture']->thread_id, $o->dbs['barrier']->thread_id ) === array_values( $receipt['sessions'] ), 'actual factory observations required' );
    }
    alynt_native_check( false === $m->invoke( $o ), 'observation extraction one-shot' );
    alynt_native_check( null === $o->state( 'qualification_observation' ) && array() === $o->state( 'observed_sessions' ), 'observation erased after extraction' );
    if ( $control ) {
        $labels = array( 'bypass' => 'actual alias observation required', 'factory' => 'actual factory observations required', 'return' => 'observed root remains genuinely positive', 'take' => 'retained handle refuses extraction' );
        $killed = in_array( $labels[$control], $GLOBALS['native_failures'], true ); $GLOBALS['native_failures'] = array();
        alynt_native_check( $killed, 'semantic control assertion-killed, not bootstrap failure' );
    }
    alynt_native_check( Alynt_Test_Native_Qualification_Manifest::verify(), 'all source hashes unchanged after API test/control' );
    echo json_encode( array( 'ok' => ! $GLOBALS['native_failures'], 'case' => $case, 'checks' => $GLOBALS['native_checks'], 'failures' => $GLOBALS['native_failures'], 'native_extension' => false, 'authentication' => false, 'storage' => false, 'native_execution' => false, 'private_protocol_ipc' => false, 'wiring' => false ) ) . "\n";
    exit( $GLOBALS['native_failures'] ? 1 : 0 );
} catch ( Throwable $error ) { echo json_encode( array( 'ok' => false, 'error' => get_class( $error ), 'line' => $error->getLine() ) ) . "\n"; exit( 1 ); }
