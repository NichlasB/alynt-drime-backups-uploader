<?php
/** Fixed direct helper boundary. Never loads WordPress or accepts SQL/table/provider input. */
if ( 'cli' !== PHP_SAPI || 5 !== count( $argv ) || ! defined( 'ALYNT_UNCERTAIN_HELPER' ) ||
    $argv[1] !== realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' ) || '10004' !== $argv[2] ||
    ! preg_match( '/^wp_alynt_core_probe_[a-f0-9]{12}_$/D', $argv[3] ) || ! preg_match( '/^[a-f0-9]{24}$/D', $argv[4] ) || ! getenv( 'MYSQL_PWD' ) ) exit( 1 );
require __DIR__ . '/owned-stdio-io.php';
require dirname( __DIR__, 2 ) . '/tests/Support/UncertainBarrierNativeManifest.php';
if ( ! Alynt_Test_Uncertain_Barrier_Native_Manifest::verify() ) exit( 1 );
define( 'ABSPATH', $argv[1] . '/' ); require dirname( __DIR__, 2 ) . '/includes/class-scan-effect-manifest.php';
$helper_bytes = 0; $helper_context = null;
function alynt_uncertain_helper_send( $kind, array $facts ) {
    global $helper_bytes, $helper_context;
    $m = array_merge( array( 'domain' => 'alynt.uncertain-barrier.direct.v1', 'kind' => $kind ), $facts );
    if ( null !== $helper_context ) $m['context'] = $helper_context;
    $line = json_encode( $m, JSON_UNESCAPED_SLASHES );
    if ( ! is_string( $line ) || strlen( $line ) > 4095 || ( $helper_bytes += strlen( $line ) + 1 ) > 32768 ) throw new RuntimeException( 'helper_output' );
    $calls = 0; alynt_stdio_probe_write( STDOUT, $line . "\n", $calls );
}
function alynt_uncertain_helper_receive( $command ) {
    global $helper_bytes, $helper_context;
    $calls = 0; $line = alynt_stdio_probe_read( $calls ); $m = json_decode( $line, true, 8 );
    if ( ! is_array( $m ) || json_encode( $m, JSON_UNESCAPED_SLASHES ) !== $line || ! preg_match( '/^[\x20-\x5b\x5d-\x7e]+$/D', $line ) ||
        ( $helper_bytes += strlen( $line ) + 1 ) > 32768 || array_keys( $m ) !== array( 'domain','command','context' ) ||
        'alynt.uncertain-barrier.direct.v1' !== $m['domain'] || $command !== $m['command'] ) throw new RuntimeException( 'helper_input' );
    $c = $m['context'];
    if ( ! is_array( $c ) || array_keys( $c ) !== array( 'attempt','root','source','roles' ) ||
        ! preg_match( '/^[a-f0-9]{64}$/D', $c['attempt'] ) || ! preg_match( '/^[a-f0-9]{64}$/D', $c['root'] ) || $c['attempt'] === $c['root'] ||
        Alynt_Test_Uncertain_Barrier_Native_Manifest::DIGEST !== $c['source'] || ! is_array( $c['roles'] ) ||
        array_keys( $c['roles'] ) !== array( 'auth','capture','writer','barrier','blocker','monitor' ) || count( array_unique( $c['roles'] ) ) !== 6 ) throw new RuntimeException( 'helper_context' );
    foreach ( $c['roles'] as $id ) if ( ! is_int( $id ) || $id < 1 || $id > 4294967295 ) throw new RuntimeException( 'helper_thread' );
    if ( null !== $helper_context && $c !== $helper_context ) throw new RuntimeException( 'helper_rebind' );
    $helper_context = $c; return $c;
}
function alynt_uncertain_helper_zero( $db ) {
    $r = $db->query( "SHOW SESSION STATUS WHERE Variable_name IN ('Com_insert','Com_insert_select','Com_update','Com_update_multi','Com_delete','Com_delete_multi','Com_replace','Com_replace_select','Com_create_table','Com_alter_table','Com_drop_table','Com_truncate','Handler_write','Handler_update','Handler_delete')" );
    $rows = $r->fetch_all( MYSQLI_NUM ); $r->free();
    if ( 15 !== count( $rows ) ) throw new RuntimeException( 'helper_counters' );
    foreach ( $rows as $row ) if ( '0' !== $row[1] ) throw new RuntimeException( 'helper_dml' );
}
$db = null; $owned_transaction = false; $passed = false;
try {
    mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
    $db = mysqli_init(); $db->options( MYSQLI_OPT_CONNECT_TIMEOUT, 3 ); $db->options( MYSQLI_OPT_READ_TIMEOUT, 5 );
    $db->real_connect( '127.0.0.1', 'root', getenv( 'MYSQL_PWD' ), 'local', 10004 ); $db->set_charset( 'utf8mb4' );
    if ( '10.6.23-MariaDB' !== $db->server_info || $argv[4] !== $db->query( "SELECT option_value FROM {$argv[3]}options WHERE option_name='probe_owner'" )->fetch_row()[0] ) throw new RuntimeException( 'helper_target' );
    alynt_uncertain_helper_send( 'hello', array( 'role' => ALYNT_UNCERTAIN_HELPER, 'thread' => $db->thread_id ) );
    require __DIR__ . '/uncertain-barrier-native-' . ALYNT_UNCERTAIN_HELPER . '-body.php';
    alynt_uncertain_helper_zero( $db );
    if ( true !== $db->close() ) throw new RuntimeException( 'helper_close' ); $db = null;
    alynt_uncertain_helper_send( 'closed', array( 'role' => ALYNT_UNCERTAIN_HELPER, 'zero_dml' => true ) ); $passed = true;
} catch ( Throwable $error ) { alynt_uncertain_helper_send( 'refused', array( 'role' => ALYNT_UNCERTAIN_HELPER ) ); }
finally {
    if ( $db instanceof mysqli ) { try { if ( $owned_transaction ) $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); $db->close(); } catch ( Throwable $ignored ) { $passed = false; } }
}
exit( $passed ? 0 : 1 );
