<?php
/** Isolated actual-body diagnostics with API doubles only; no WordPress/native/SQL operations. */
if ( realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) !== __FILE__ ) return;
if ( 'cli' !== PHP_SAPI || 3 !== count( $argv ) || ! in_array( $argv[1], array( 'blocker', 'monitor' ), true ) || ! preg_match( '/^[a-z_]{0,32}$/D', $argv[2] ) ) exit( 1 );
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
if ( ! defined( 'MYSQLI_NUM' ) ) define( 'MYSQLI_NUM', 2 );
require ABSPATH . 'includes/class-scan-effect-manifest.php';
require ABSPATH . 'scripts/tests/uncertain-barrier-helper-diagnostics.php';
$role = $argv[1]; $fault = $argv[2]; $argv = array( 'fixed', 'unused', '10004', 'wp_alynt_core_probe_aaaaaaaaaaaa_' );
$events = array(); $helper_stage = 'body'; $owned_transaction = false;
function alynt_diag_trip() {
    global $fault, $helper_stage, $events; $events[] = $helper_stage;
    if ( $fault === $helper_stage && ! in_array( $fault, array( 'lock_rows', 'watch_identity' ), true ) ) throw new RuntimeException( 'RAW_PRIVATE_SQL_MARKER' );
}
function alynt_uncertain_helper_receive( $command ) { alynt_diag_trip(); return array( 'roles' => array( 'blocker' => 105, 'monitor' => 106, 'barrier' => 104 ) ); }
function alynt_uncertain_helper_zero( $db ) { alynt_diag_trip(); }
function alynt_uncertain_helper_send( $kind, $facts ) { alynt_diag_trip(); }
final class Alynt_Diagnostic_Rows {
    public $num_rows = 1;
    public function __construct() { global $fault; if ( 'lock_rows' === $fault ) $this->num_rows = 0; }
    public function free() {}
    public function fetch_row() { return array( '1', '0' ); }
    public function fetch_all( $mode ) { global $fault; return array( array( '104', '105', '`local`.`wp_alynt_core_probe_aaaaaaaaaaaa_options`', 'watch_identity' === $fault ? 'PRIMARY' : 'option_name' ) ); }
}
final class Alynt_Diagnostic_Statement {
    public function bind_param( $types, &...$values ) { alynt_diag_trip(); }
    public function execute() { alynt_diag_trip(); }
    public function get_result() { return new Alynt_Diagnostic_Rows(); }
    public function close() {}
}
final class Alynt_Diagnostic_Database {
    public $thread_id;
    public function query( $sql ) { alynt_diag_trip(); return 0 === strpos( $sql, 'SELECT CAST' ) ? new Alynt_Diagnostic_Rows() : true; }
    public function prepare( $sql ) { alynt_diag_trip(); return new Alynt_Diagnostic_Statement(); }
}
$db = new Alynt_Diagnostic_Database(); $db->thread_id = 'blocker' === $role ? 105 : 106;
try {
    require ABSPATH . 'scripts/tests/uncertain-barrier-native-' . $role . '-body.php';
    echo json_encode( array( 'body_completed' => true, 'failure' => null, 'events' => $events ) );
} catch ( Throwable $error ) {
    echo json_encode( array( 'body_completed' => false, 'failure' => alynt_uncertain_helper_failure( $role, $helper_stage, $error ), 'events' => $events ) );
}
