<?php
/** Private fixed target/profile helper. No supplied SQL, providers or native handles. */
if ( 'cli' !== PHP_SAPI || 6 !== count( $argv ) || ! defined( 'ALYNT_CACHE_CONTENTION_HELPER' ) ||
    $argv[1] !== realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' ) || '10004' !== $argv[2] ||
    ! preg_match( '/^wp_alynt_core_probe_[a-f0-9]{12}_$/D', $argv[3] ) || ! preg_match( '/^[a-f0-9]{24}$/D', $argv[4] ) ||
    ! in_array( $argv[5], array( 'released','held' ), true ) || ! getenv( 'MYSQL_PWD' ) ) exit( 1 );
define( 'ABSPATH', $argv[1] . '/' );
require dirname( __DIR__, 2 ) . '/tests/Support/CacheContentionNativeManifest.php';
if ( ! Alynt_Test_Cache_Contention_Native_Manifest::verify() ) exit( 1 );
require __DIR__ . '/owned-stdio-io.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-scan-effect-manifest.php';
$contention_bytes = 0; $contention_context = null; $helper_started = hrtime( true );
function alynt_contention_send( $kind, array $facts ) {
    global $contention_bytes, $contention_context, $argv;
    $m = array_merge( array( 'domain' => null === $contention_context ? 'alynt.uncertain-barrier.wait-contended-setup.v1' : 'alynt.uncertain-barrier.wait-contended.v1', 'kind' => $kind, 'role' => ALYNT_CACHE_CONTENTION_HELPER, 'profile' => $argv[5] ), $facts );
    if ( null !== $contention_context ) $m['context'] = $contention_context;
    $line = json_encode( $m, JSON_UNESCAPED_SLASHES );
    if ( ! is_string( $line ) || strlen( $line ) > 4095 || ( $contention_bytes += strlen( $line ) + 1 ) > 32768 ) throw new RuntimeException( 'output' );
    $calls = 0; alynt_stdio_probe_write( STDOUT, $line . "\n", $calls );
}
function alynt_contention_receive( $kind, $db ) {
    global $contention_bytes, $contention_context, $argv;
    $calls = 0; $line = alynt_stdio_probe_read( $calls ); $m = json_decode( $line, true, 8 );
    $keys = array( 'domain','kind','profile','context' ); if ( 'execute' === $kind ) $keys[] = 'statement';
    if ( ! is_array( $m ) || array_keys( $m ) !== $keys || json_encode( $m, JSON_UNESCAPED_SLASHES ) !== $line ||
        ! preg_match( '/^[\x20-\x5b\x5d-\x7e]+$/D', $line ) || ( $contention_bytes += strlen( $line ) + 1 ) > 32768 ||
        'alynt.uncertain-barrier.wait-contended.v1' !== $m['domain'] || $kind !== $m['kind'] || $argv[5] !== $m['profile'] ) throw new RuntimeException( 'input' );
    $c = $m['context'];
    if ( ! is_array( $c ) || array_keys( $c ) !== array( 'attempt','root','source','roles','table' ) ||
        ! is_string( $c['attempt'] ) || ! is_string( $c['root'] ) || ! preg_match( '/^[a-f0-9]{64}$/D', $c['attempt'] ) || ! preg_match( '/^[a-f0-9]{64}$/D', $c['root'] ) || $c['attempt'] === $c['root'] ||
        Alynt_Test_Uncertain_Barrier_Native_Manifest::DIGEST !== $c['source'] || $argv[3] . 'options' !== $c['table'] || ! is_array( $c['roles'] ) ||
        array_keys( $c['roles'] ) !== array( 'auth','capture','writer','barrier','blocker','monitor' ) || 6 !== count( array_unique( $c['roles'] ) ) ) throw new RuntimeException( 'context' );
    foreach ( $c['roles'] as $id ) if ( ! is_int( $id ) || $id < 1 || $id > 4294967295 ) throw new RuntimeException( 'thread' );
    if ( $c['roles'][ ALYNT_CACHE_CONTENTION_HELPER ] !== $db->thread_id || null !== $contention_context && $c !== $contention_context ||
        'execute' === $kind && ( ! is_int( $m['statement'] ) || $m['statement'] < 1 || $m['statement'] > 4294967295 ) ) throw new RuntimeException( 'owner' );
    $contention_context = $c; return $c;
}
function alynt_contention_zero( $db ) {
    $r = $db->query( "SHOW SESSION STATUS WHERE Variable_name IN ('Com_insert','Com_insert_select','Com_update','Com_update_multi','Com_delete','Com_delete_multi','Com_replace','Com_replace_select','Com_create_table','Com_alter_table','Com_drop_table','Com_truncate','Handler_write','Handler_update','Handler_delete')" );
    $rows = $r->fetch_all( MYSQLI_NUM ); $r->free();
    if ( 15 !== count( $rows ) ) throw new RuntimeException( 'counters' );
    foreach ( $rows as $row ) if ( '0' !== $row[1] ) throw new RuntimeException( 'dml' );
}
$db = null; $stmt = null; $owned_transaction = false; $exit = 1; $stage = 'connect'; $epoch = $helper_started;
try {
    mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
    $db = mysqli_init(); $db->options( MYSQLI_OPT_CONNECT_TIMEOUT, 3 ); $db->options( MYSQLI_OPT_READ_TIMEOUT, 5 );
    $db->real_connect( '127.0.0.1', 'root', getenv( 'MYSQL_PWD' ), 'local', 10004 ); $db->set_charset( 'utf8mb4' );
    $stage = 'target';
    if ( '10.6.23-MariaDB' !== $db->server_info || $argv[4] !== $db->query( "SELECT option_value FROM {$argv[3]}options WHERE option_name='probe_owner'" )->fetch_row()[0] ) throw new RuntimeException( 'target' );
    $stage = 'hello'; alynt_contention_send( 'hello', array( 'thread' => $db->thread_id, 'source' => Alynt_Test_Cache_Contention_Native_Manifest::DIGEST ) );
    $stage = 'command'; $c = alynt_contention_receive( 'blocker' === ALYNT_CACHE_CONTENTION_HELPER ? 'lock' : 'arm', $db );
    if ( 'blocker' === ALYNT_CACHE_CONTENTION_HELPER ) {
        $stage = 'policy'; $db->query( 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED' );
        $db->query( 'SET SESSION innodb_lock_wait_timeout=2' ); $db->query( 'SET SESSION lock_wait_timeout=3' );
        $stage = 'counters'; alynt_contention_zero( $db );
        $stage = 'begin'; $db->query( 'START TRANSACTION READ WRITE' ); $owned_transaction = true;
        $name = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows()['safety'][0];
        $stage = 'prepare'; $stmt = $db->prepare( "SELECT option_id FROM `{$argv[3]}options` FORCE INDEX (option_name) WHERE option_name=? LIMIT 2 FOR UPDATE NOWAIT" );
        $stage = 'lock'; $stmt->bind_param( 's', $name ); $stmt->execute(); $r = $stmt->get_result();
        if ( 1 !== $r->num_rows ) throw new RuntimeException( 'safety_row' ); $r->free(); $stmt->close(); $stmt = null;
        $stage = 'counters'; alynt_contention_zero( $db );
        $stage = 'ready'; alynt_contention_send( 'ready', array( 'zero_dml' => true ) );
        $stage = 'command'; alynt_contention_receive( 'rollback', $db );
        $stage = 'rollback';
        if ( true !== $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ) || array( '1','0' ) !== $db->query( 'SELECT CAST(@@autocommit AS CHAR),CAST(@@in_transaction AS CHAR)' )->fetch_row() ) throw new RuntimeException( 'rollback' );
        $owned_transaction = false; $stage = 'counters'; alynt_contention_zero( $db );
        alynt_contention_send( 'released', array( 'rollback_acknowledged' => true, 'zero_dml' => true ) );
    } else {
        $stage = 'policy'; $db->query( 'SET SESSION TRANSACTION READ ONLY' );
        $requester = $c['roles']['barrier']; $blocker = $c['roles']['blocker']; $table = '`local`.`' . $argv[3] . 'options`';
        $stage = 'prepare'; $stmt = $db->prepare( 'SELECT r.trx_mysql_thread_id,b.trx_mysql_thread_id,l.lock_table,l.lock_index FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id JOIN information_schema.INNODB_LOCKS l ON l.lock_id=w.requested_lock_id WHERE r.trx_mysql_thread_id=? AND b.trx_mysql_thread_id=?' );
        $stmt->bind_param( 'ii', $requester, $blocker ); $stage = 'counters'; alynt_contention_zero( $db );
        $epoch = hrtime( true ); $deadline = $epoch + 2500000000;
        $stage = 'arm'; alynt_contention_send( 'armed', array( 'zero_dml' => true ) );
        $stage = 'execute'; alynt_contention_receive( 'execute', $db ); $trigger = hrtime( true ) - $epoch;
        if ( $trigger >= 2500000000 ) throw new RuntimeException( 'late_execute' );
        $previous = $epoch + $trigger; $queries = array(); $wait = null;
        while ( true ) {
            $now = hrtime( true ); if ( $now >= $deadline ) throw new RuntimeException( 'expired' );
            $due = $previous + 125000000;
            if ( $now < $due ) { usleep( (int) min( 10000, max( 1, intdiv( $due - $now + 999, 1000 ) ) ) ); continue; }
            if ( count( $queries ) >= 125 ) throw new RuntimeException( 'query_cap' );
            $start = hrtime( true ); if ( $start >= $deadline ) throw new RuntimeException( 'expired' );
            $stage = 'query'; $stmt->execute(); $r = $stmt->get_result(); $values = $r->fetch_all( MYSQLI_NUM ); $r->free(); $previous = hrtime( true );
            if ( $previous >= $deadline ) throw new RuntimeException( 'late_query' );
            $queries[] = array( 'start_ns' => (string) ( $start - $epoch ), 'end_ns' => (string) ( $previous - $epoch ), 'matched' => (bool) $values );
            if ( ! $values ) continue;
            if ( array( array( (string) $requester, (string) $blocker, $table, 'option_name' ) ) !== array_map( function ( $row ) { return array_map( 'strval', $row ); }, $values ) ) throw new RuntimeException( 'wait_identity' );
            $wait = array( 'requester' => (int) $values[0][0], 'blocker' => (int) $values[0][1], 'monitor' => $db->thread_id, 'table' => $c['table'], 'index' => $values[0][3], 'role' => 'safety' ); break;
        }
        $stmt->close(); $stmt = null; $stage = 'counters'; alynt_contention_zero( $db );
        $stage = 'waiting'; alynt_contention_send( 'waiting', array( 'wait' => $wait, 'metrics' => array( 'trigger_ns' => (string) $trigger, 'queries' => $queries, 'zero_dml' => true ) ) );
        $stage = 'finish'; alynt_contention_receive( 'finish', $db );
    }
    $stage = 'counters'; alynt_contention_zero( $db ); $stage = 'close';
    if ( true !== $db->close() ) throw new RuntimeException( 'close' ); $db = null;
    $stage = 'closed'; alynt_contention_send( 'closed', array( 'zero_dml' => true, 'closed_ns' => (string) ( hrtime( true ) - $epoch ) ) ); $exit = 0;
} catch ( Throwable $error ) {
    $allowed = array( 'late_execute','expired','query_cap','late_query','wait_identity','safety_row','rollback','close','context','thread','owner','input','target','counters','dml','output' );
    $reason = $error instanceof mysqli_sql_exception ? 'database' : ( in_array( $error->getMessage(), $allowed, true ) ? $error->getMessage() : 'owned' );
    $code = $error instanceof mysqli_sql_exception && in_array( (int) $error->getCode(), array( 1205,1213,2006,2013 ), true ) ? (int) $error->getCode() : 0;
    try { alynt_contention_send( 'refused', array( 'failure' => array( 'stage' => $stage, 'reason' => $reason, 'code' => $code ) ) ); } catch ( Throwable $unreportable ) { $exit = 1; }
} finally {
    if ( $stmt instanceof mysqli_stmt ) { try { $stmt->close(); } catch ( Throwable $statement_unresolved ) { $exit = 1; } }
    if ( $db instanceof mysqli ) { try { if ( $owned_transaction ) $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); $db->close(); } catch ( Throwable $session_unresolved ) { $exit = 1; } }
}
exit( $exit );
