<?php
/** Owned direct helpers only; fixed SQL and target, separate successor source seal. */
if ( 'cli' !== PHP_SAPI || 5 !== count( $argv ) || ! defined( 'ALYNT_CACHE_HELPER' ) ||
    $argv[1] !== realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' ) || '10004' !== $argv[2] ||
    ! preg_match( '/^wp_alynt_core_probe_[a-f0-9]{12}_$/D', $argv[3] ) || ! preg_match( '/^[a-f0-9]{24}$/D', $argv[4] ) || ! getenv( 'MYSQL_PWD' ) ) exit( 1 );
define( 'ABSPATH', $argv[1] . '/' );
require dirname( __DIR__, 2 ) . '/tests/Support/CacheWitnessNativeManifest.php';
if ( ! Alynt_Test_Cache_Witness_Native_Manifest::verify() ) exit( 1 );
require __DIR__ . '/owned-stdio-io.php';
$cache_bytes = 0; $cache_context = null;
function alynt_cache_send( $kind, array $facts ) {
    global $cache_bytes, $cache_context;
    $m = array_merge( array( 'domain' => null === $cache_context ? 'alynt.uncertain-barrier.wait-setup.v1' : 'alynt.uncertain-barrier.wait-native.v1', 'kind' => $kind, 'role' => ALYNT_CACHE_HELPER ), $facts );
    if ( null !== $cache_context ) $m['context'] = $cache_context;
    $line = json_encode( $m, JSON_UNESCAPED_SLASHES );
    if ( ! is_string( $line ) || strlen( $line ) > 4095 || ( $cache_bytes += strlen( $line ) + 1 ) > 32768 ) throw new RuntimeException( 'output' );
    $calls = 0; alynt_stdio_probe_write( STDOUT, $line . "\n", $calls );
}
function alynt_cache_receive( $kind, $db ) {
    global $cache_bytes, $cache_context, $argv;
    $calls = 0; $line = alynt_stdio_probe_read( $calls ); $m = json_decode( $line, true, 8 );
    $keys = array( 'domain','kind','context' ); if ( 'execute' === $kind ) $keys[] = 'statement';
    if ( ! is_array( $m ) || array_keys( $m ) !== $keys || json_encode( $m, JSON_UNESCAPED_SLASHES ) !== $line ||
        ! preg_match( '/^[\x20-\x5b\x5d-\x7e]+$/D', $line ) || ( $cache_bytes += strlen( $line ) + 1 ) > 32768 ||
        'alynt.uncertain-barrier.wait-native.v1' !== $m['domain'] || $kind !== $m['kind'] ) throw new RuntimeException( 'input' );
    $c = $m['context'];
    if ( ! is_array( $c ) || array_keys( $c ) !== array( 'attempt','root','source','roles','table' ) ||
        ! is_string( $c['attempt'] ) || ! is_string( $c['root'] ) || ! preg_match( '/^[a-f0-9]{64}$/D', $c['attempt'] ) || ! preg_match( '/^[a-f0-9]{64}$/D', $c['root'] ) || $c['attempt'] === $c['root'] ||
        Alynt_Test_Uncertain_Barrier_Native_Manifest::DIGEST !== $c['source'] || $argv[3] . 'options' !== $c['table'] || ! is_array( $c['roles'] ) ||
        array_keys( $c['roles'] ) !== array( 'auth','capture','writer','barrier','blocker','monitor' ) || 6 !== count( array_unique( $c['roles'] ) ) ) throw new RuntimeException( 'context' );
    foreach ( $c['roles'] as $id ) if ( ! is_int( $id ) || $id < 1 || $id > 4294967295 ) throw new RuntimeException( 'thread' );
    if ( $c['roles'][ 'idle' === ALYNT_CACHE_HELPER ? 'blocker' : 'monitor' ] !== $db->thread_id || null !== $cache_context && $c !== $cache_context ||
        'execute' === $kind && ( ! is_int( $m['statement'] ) || $m['statement'] < 1 || $m['statement'] > 4294967295 ) ) throw new RuntimeException( 'owner' );
    $cache_context = $c; return $c;
}
function alynt_cache_zero( $db ) {
    $r = $db->query( "SHOW SESSION STATUS WHERE Variable_name IN ('Com_insert','Com_insert_select','Com_update','Com_update_multi','Com_delete','Com_delete_multi','Com_replace','Com_replace_select','Com_create_table','Com_alter_table','Com_drop_table','Com_truncate','Handler_write','Handler_update','Handler_delete')" );
    $rows = $r->fetch_all( MYSQLI_NUM ); $r->free();
    if ( 15 !== count( $rows ) ) throw new RuntimeException( 'counters' );
    foreach ( $rows as $row ) if ( '0' !== $row[1] ) throw new RuntimeException( 'dml' );
}
$db = null; $stmt = null; $exit = 1;
try {
    mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
    $db = mysqli_init(); $db->options( MYSQLI_OPT_CONNECT_TIMEOUT, 3 ); $db->options( MYSQLI_OPT_READ_TIMEOUT, 5 );
    $db->real_connect( '127.0.0.1', 'root', getenv( 'MYSQL_PWD' ), 'local', 10004 ); $db->set_charset( 'utf8mb4' );
    if ( '10.6.23-MariaDB' !== $db->server_info || $argv[4] !== $db->query( "SELECT option_value FROM {$argv[3]}options WHERE option_name='probe_owner'" )->fetch_row()[0] ) throw new RuntimeException( 'target' );
    alynt_cache_send( 'hello', array( 'thread' => $db->thread_id, 'source' => Alynt_Test_Cache_Witness_Native_Manifest::DIGEST ) );
    if ( 'idle' === ALYNT_CACHE_HELPER ) {
        alynt_cache_receive( 'bind', $db ); alynt_cache_zero( $db ); alynt_cache_send( 'ready', array( 'zero_dml' => true ) );
        alynt_cache_receive( 'finish', $db ); alynt_cache_zero( $db );
        if ( true !== $db->close() ) throw new RuntimeException( 'close' ); $db = null;
        alynt_cache_send( 'closed', array( 'zero_dml' => true ) ); $exit = 0;
    } else {
        $c = alynt_cache_receive( 'arm', $db ); $db->query( 'SET SESSION TRANSACTION READ ONLY' );
        $requester = $c['roles']['barrier']; $blocker = $c['roles']['blocker'];
        $stmt = $db->prepare( 'SELECT r.trx_mysql_thread_id,b.trx_mysql_thread_id,l.lock_table,l.lock_index FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id JOIN information_schema.INNODB_LOCKS l ON l.lock_id=w.requested_lock_id WHERE r.trx_mysql_thread_id=? AND b.trx_mysql_thread_id=?' );
        $stmt->bind_param( 'ii', $requester, $blocker ); alynt_cache_zero( $db );
        $prepared = hrtime( true ); $deadline = $prepared + 2500000000;
        alynt_cache_send( 'armed', array( 'zero_dml' => true ) );
        alynt_cache_receive( 'execute', $db ); $trigger = hrtime( true ) - $prepared;
        if ( $trigger >= 2500000000 ) throw new RuntimeException( 'late_execute' );
        $previous = $prepared + $trigger; $queries = array();
        while ( true ) {
            $now = hrtime( true );
            if ( $now >= $deadline ) break;
            $due = $previous + 125000000;
            if ( $now < $due ) { usleep( (int) min( 10000, max( 1, intdiv( $due - $now + 999, 1000 ) ) ) ); continue; }
            if ( count( $queries ) >= 125 ) throw new RuntimeException( 'query_cap' );
            $start = hrtime( true ); if ( $start >= $deadline ) break;
            $stmt->execute(); $r = $stmt->get_result(); $rows = $r->fetch_all( MYSQLI_NUM ); $r->free(); $previous = hrtime( true );
            if ( $rows || $previous >= $deadline ) throw new RuntimeException( 'unexpected_wait_or_late_query' );
            $queries[] = array( 'start_ns' => (string) ( $start - $prepared ), 'end_ns' => (string) ( $previous - $prepared ) );
        }
        $elapsed = hrtime( true ) - $prepared;
        if ( ! $queries || $elapsed > 3250000000 ) throw new RuntimeException( 'expiry' );
        $stmt->close(); $stmt = null; alynt_cache_zero( $db );
        if ( true !== $db->close() ) throw new RuntimeException( 'close' ); $db = null;
        // Expected no-match refusal: never WAITING or a positive CLOSED witness.
        alynt_cache_send( 'refused', array( 'reason' => 'expired', 'metrics' => array( 'trigger_ns' => (string) $trigger, 'elapsed_ns' => (string) $elapsed, 'queries' => $queries, 'zero_dml' => true, 'statement_closed' => true, 'session_closed' => true ) ) );
    }
} catch ( Throwable $error ) { /* Fixed failure exit only; no SQL, secrets or fabricated closure receipt. */ }
finally {
    if ( $stmt instanceof mysqli_stmt ) { try { $stmt->close(); } catch ( Throwable $ignored ) {} }
    if ( $db instanceof mysqli ) { try { $db->close(); } catch ( Throwable $ignored ) {} }
}
exit( $exit );
