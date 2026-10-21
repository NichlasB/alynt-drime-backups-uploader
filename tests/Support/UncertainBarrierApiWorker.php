<?php
/** php -n API process only. No native mysqli, WordPress, DB, listener or proof IPC. */
if ( realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) !== __FILE__ ) return;
if ( 'cli' !== PHP_SAPI || extension_loaded( 'mysqli' ) || 2 !== count( $argv ) ) exit( 1 );
require_once __DIR__ . '/NativeQualificationManifest.php';
function alynt_uncertain_compile( $name, array $changes ) {
    if ( ! in_array( $name, array( 'RegistrationOutcomeApiDoubles.php', 'NativeLifecycleApiBootstrap.php', 'NativeLifecycleApiHarness.php', 'NativeLifecycleApiCases.php', 'UncertainBarrierObservation.php' ), true ) ) throw new LogicException( 'Fixed API source refused.' );
    $code = file_get_contents( __DIR__ . '/' . $name );
    foreach ( $changes as $from => $to ) { $code = str_replace( $from, $to, $code, $n ); if ( 1 !== $n ) throw new LogicException( 'Fixed API seam absent: ' . $name ); }
    return substr( $code, 5 );
}
try {
    if ( ! Alynt_Test_Native_Qualification_Manifest::verify() ) throw new LogicException( 'Predecessor source identity refused.' );
    $case = $argv[1]; $controls = array( 'alias', 'return', 'guard' );
    $control = 0 === strpos( $case, 'control-' ) ? substr( $case, 8 ) : null;
    if ( $control && ! in_array( $control, $controls, true ) ) throw new LogicException( 'Fixed control refused.' );
    $fault = $control ? 'barrier-row' : ( in_array( $case, array( 'uncertain-row', 'uncertain-row-throw' ), true ) ? 'barrier-row' : ( 'retained-handle' === $case ? 'commit' : $case ) );
    eval( alynt_uncertain_compile( 'RegistrationOutcomeApiDoubles.php', array( 'function alynt_outcome_api_install_doubles()' => 'function alynt_uncertain_install_doubles()', 'public $calls = array();' => 'public $thread_id = 0; public $calls = array();' ) ) );
    eval( alynt_uncertain_compile( 'NativeLifecycleApiBootstrap.php', array( 'function alynt_native_api_bootstrap()' => 'function alynt_uncertain_bootstrap()', "require_once __DIR__ . '/RegistrationOutcomeApiDoubles.php'; alynt_outcome_api_install_doubles();" => 'alynt_uncertain_install_doubles();' ) ) );
    alynt_uncertain_bootstrap(); require_once __DIR__ . '/UncertainBarrierManifest.php';
    if ( ! Alynt_Test_Uncertain_Barrier_Manifest::verify() ) throw new LogicException( 'Source identity refused.' );
    require_once __DIR__ . '/UncertainBarrierOwner.php';
    if ( ! Alynt_Test_Uncertain_Barrier_Manifest::composition() ) throw new LogicException( 'Composition refused.' );
    $trait = 'Alynt_Test_Uncertain_Barrier_Observation';
    if ( 'alias' === $control ) {
        $trait .= '_Control';
        eval( alynt_uncertain_compile( 'UncertainBarrierObservation.php', array( 'trait Alynt_Test_Uncertain_Barrier_Observation {' => 'trait ' . $trait . ' {', '$this->terminal_source( ...func_get_args() )' => 'false' ) ) );
    }
    $harness = alynt_uncertain_compile( 'NativeLifecycleApiHarness.php', array(
        'final class Alynt_Test_Native_Lifecycle_Api {' => 'final class Alynt_Test_Uncertain_Barrier_Api {',
        'use Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Methods { qualify_admission_outcome as private source_qualification; }' => 'use ' . $trait . ' { qualify_admission_outcome as private source_qualification; }',
        '$this->reader = new mysqli();' => '$this->reader = new mysqli(); $this->reader->thread_id = spl_object_id( $this->reader );',
        "array( 'uncertain', 'absence' )" => "array( 'uncertain', 'absence', 'barrier-row', 'barrier-commit-false', 'barrier-close-false' )",
        "if ( 'barrier-row' === \$this->fault ) return false;" => "if ( 'barrier-row' === \$this->fault ) { " . ( 'uncertain-row-throw' === $case ? "throw new RuntimeException( 'Isolated row fault.' );" : 'return false;' ) . ' }',
    ) );
    eval( $harness );
    // Existing integrity assertions run BEFORE replay; extraction itself is once-only.
    $cases = file_get_contents( __DIR__ . '/NativeLifecycleApiCases.php' );
    $from = '$old = $o->events; alynt_native_check( false === $o->run() && $old === $o->events, \'root replay has no IO\' );';
    if ( 1 !== substr_count( $cases, $from ) ) throw new LogicException( 'Replay boundary absent.' );
    eval( substr( str_replace( $from, '', $cases ), 5 ) );
    $bytes = stream_get_contents( STDIN, 131073 ); if ( strlen( $bytes ) > 131072 ) throw new LogicException( 'Frame cap.' );
    $frame = json_decode( $bytes, true, 32 ); if ( ! is_array( $frame ) ) throw new LogicException( 'Frame refused.' );
    $o = alynt_native_case( $frame, $fault, 'Alynt_Test_Uncertain_Barrier_Api' );
    $m = new ReflectionMethod( $o, 'take_uncertain_barrier_observation' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true );
    if ( 'retained-handle' === $case ) $o->state( 'reader', new mysqli() );
    $event = $m->invoke( $o ); $guards = $o->guards;
    $domains = array(); foreach ( $guards as $i => $action ) { if ( ! in_array( $action, $domains, true ) ) $domains[] = $action; $guards[$i] = array( 'M', 'MF', 'S', 'F' )[array_search( $action, $domains, true )]; }
    if ( 'guard' === $control ) array_pop( $guards );
    if ( 'return' === $control && is_array( $event ) ) $event['terminal']['root_result'] = array( 'classification' => 'registered_closed' );
    $terminal = Alynt_Test_Uncertain_Barrier_Projection::terminal( $event, $guards );
    $supported = in_array( $fault, array( 'commit', 'uncertain', 'uncertain_throw', 'barrier-row' ), true ) && 'retained-handle' !== $case;
    alynt_native_check( $supported ? is_array( $terminal ) : false === $terminal, 'exact terminal and reached guard prefix required' );
    if ( is_array( $terminal ) && 'barrier-row' === $fault ) {
        alynt_native_check( 'refused' === $terminal['terminal']['outcome'] && false === $terminal['terminal']['barrier_receipt'] && 'rolled_back' === $terminal['terminal']['phase'], 'false barrier receipt stays false after rollback' );
        alynt_native_check( 'uncertain' === $terminal['qualification']['result']['historical_outcome'] && 22 === count( $guards ), 'same-root uncertain refusal non-vacuous' );
    }
    alynt_native_check( false === $m->invoke( $o ) && null === $o->state( 'terminal_event' ), 'terminal consumed and erased' );
    $before = $o->events; alynt_native_check( false === $o->run() && $before === $o->events, 'root replay has no IO' );
    if ( $control ) {
        $killed = in_array( 'exact terminal and reached guard prefix required', $GLOBALS['native_failures'], true );
        $GLOBALS['native_failures'] = array(); alynt_native_check( $killed, 'semantic control assertion-killed' );
    }
    alynt_native_check( Alynt_Test_Uncertain_Barrier_Manifest::verify(), 'source unchanged after API/control' );
    echo json_encode( array( 'ok' => ! $GLOBALS['native_failures'], 'checks' => $GLOBALS['native_checks'], 'failures' => $GLOBALS['native_failures'], 'case' => $case, 'terminal' => $terminal, 'native_execution' => false, 'wiring' => false ) );
    exit( $GLOBALS['native_failures'] ? 1 : 0 );
} catch ( Throwable $error ) { echo json_encode( array( 'ok' => false, 'error' => get_class( $error ), 'file' => basename( $error->getFile() ), 'line' => $error->getLine() ) ); exit( 1 ); }
