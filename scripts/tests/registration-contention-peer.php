<?php
/** CLI-only owned fixture peer. No WordPress bootstrap, endpoint or production port. */
if ( 'cli' !== PHP_SAPI || 6 !== count( $argv ) || ! ctype_digit( $argv[1] ) || ! preg_match( '/^wp_alynt_core_probe_[a-f0-9]{12}_$/D', $argv[2] ) || ! preg_match( '/^[a-f0-9]{24}$/D', $argv[3] ) || ! in_array( $argv[4], array( 'commit', 'rollback', 'timeout', 'disconnect', 'late' ), true ) || ! ctype_digit( $argv[5] ) || (int) $argv[5] < 1 || (int) $argv[5] > 65535 ) exit( 1 );
$db = null; $control = null; $in_transaction = false; $exit = 0;
$channel = stream_socket_client( 'tcp://127.0.0.1:' . $argv[5], $errno, $message, 3 ); if ( ! is_resource( $channel ) ) exit( 1 );
function alynt_peer_emit( array $event ) { $bytes = json_encode( $event ) . "\n"; if ( strlen( $bytes ) !== fwrite( $GLOBALS['channel'], $bytes ) ) throw new RuntimeException( 'Peer response failed.' ); }
function alynt_peer_line() {
    $channel = $GLOBALS['channel']; stream_set_blocking( $channel, false ); $line = ''; $end = hrtime( true ) + 12000000000;
    do { $part = fgets( $channel, 262145 ); if ( is_string( $part ) ) $line .= $part; if ( strlen( $line ) > 262143 ) throw new RuntimeException( 'Oversized peer command.' ); if ( substr( $line, -1 ) === "\n" ) break; if ( feof( $channel ) ) throw new RuntimeException( 'Peer parent closed.' ); usleep( 10000 ); } while ( hrtime( true ) < $end );
    if ( '' === $line || substr( $line, -1 ) !== "\n" ) throw new RuntimeException( 'Peer command deadline.' );
    return $line;
}
try {
    set_error_handler( static function () { throw new RuntimeException( 'Owned peer diagnostic; private details withheld.' ); } );
    alynt_peer_emit( array( 'event' => 'hello', 'marker' => $argv[3] ) );
    $sites = json_decode( file_get_contents( 'C:/Users/Captain/AppData/Roaming/Local/sites.json' ), true ); $matched = 0;
    foreach ( $sites as $site ) if ( 'plugin-tester.local' === ( $site['domain'] ?? null ) && realpath( $site['path'] . '/app/public' ) === realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' ) && (int) $argv[1] === ( $site['services']['mariadb']['ports']['MYSQL'][0] ?? null ) ) $matched++;
    if ( 1 !== $matched || '' === (string) getenv( 'MYSQL_PWD' ) ) throw new RuntimeException( 'Peer target refused.' );
    mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
    $db = mysqli_init(); $db->options( MYSQLI_OPT_CONNECT_TIMEOUT, 3 ); $db->options( MYSQLI_OPT_READ_TIMEOUT, 5 ); $db->options( MYSQLI_OPT_LOCAL_INFILE, false );
    $db->real_connect( '127.0.0.1', 'root', getenv( 'MYSQL_PWD' ), 'local', (int) $argv[1] ); $db->set_charset( 'utf8mb4' );
    $table = $argv[2] . 'options';
    if ( $argv[3] !== $db->query( "SELECT option_value FROM {$table} WHERE option_name='probe_owner'" )->fetch_row()[0] ) throw new RuntimeException( 'Peer marker refused.' );
    $input = json_decode( alynt_peer_line(), true );
    if ( ! is_array( $input ) || array_keys( $input ) !== array( 'before', 'replacement' ) ) throw new RuntimeException( 'Peer data refused.' );
    foreach ( $input as $v ) if ( ! is_string( $v ) || strlen( $v ) > 131072 || ! is_array( json_decode( $v, true ) ) ) throw new RuntimeException( 'Peer bytes refused.' );
    $db->query( 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED' ); $db->query( 'SET SESSION innodb_lock_wait_timeout=2' ); $db->query( 'SET SESSION lock_wait_timeout=3' );
    // A late writer does not acquire safety until the genuine observer has closed.
    if ( 'late' === $argv[4] ) { alynt_peer_emit( array( 'event' => 'ready', 'thread' => $db->thread_id ) ); if ( "later\n" !== alynt_peer_line() ) throw new RuntimeException( 'Late gate refused.' ); }
    define( 'ABSPATH', 'C:/Users/Captain/Local Sites/plugin-tester/app/public/' ); require dirname( __DIR__, 2 ) . '/includes/class-scan-effect-manifest.php';
    $db->query( 'START TRANSACTION READ WRITE' ); $in_transaction = true; $ledger = null;
    foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $role => $def ) {
        $name = $def[0];
        $stmt = $db->prepare( "SELECT BINARY option_value FROM {$table} FORCE INDEX(option_name) WHERE option_name=? LIMIT 2 FOR UPDATE" ); $stmt->bind_param( 's', $name ); $stmt->execute(); $r = $stmt->get_result();
        if ( 1 !== $r->num_rows ) throw new RuntimeException( 'Peer row preview refused.' ); $row = $r->fetch_row(); if ( 'ledger' === $role ) $ledger = $row[0]; $r->free(); $stmt->close();
    }
    if ( $ledger !== $input['before'] ) throw new RuntimeException( 'Peer before-image refused.' );
    // Approved single-row fixture DML, exclusively on the peer, never observer/auth.
    if ( 'disconnect' !== $argv[4] ) {
        $name = 'alynt_drime_backups_scan_effect_state'; $stmt = $db->prepare( "UPDATE {$table} SET option_value=? WHERE option_name=? AND BINARY option_value=?" );
        $stmt->bind_param( 'sss', $input['replacement'], $name, $input['before'] ); $stmt->execute(); if ( 1 !== $stmt->affected_rows ) throw new RuntimeException( 'Peer exact owned CAS failed.' ); $stmt->close();
    }
    if ( 'late' !== $argv[4] ) {
        alynt_peer_emit( array( 'event' => 'ready', 'thread' => $db->thread_id ) );
        $target = trim( alynt_peer_line() ); if ( ! ctype_digit( $target ) || (int) $target <= 0 || (int) $target === $db->thread_id ) throw new RuntimeException( 'Peer observer identity refused.' );
        $control = new mysqli( '127.0.0.1', 'root', getenv( 'MYSQL_PWD' ), 'local', (int) $argv[1] );
        $end = hrtime( true ) + 8000000000; $waiting = false; $lock_table = '`local`.`' . $table . '`';
        $stmt = $control->prepare( 'SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX requester ON requester.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX blocker ON blocker.trx_id=w.blocking_trx_id JOIN information_schema.INNODB_LOCKS l ON l.lock_id=w.requested_lock_id WHERE requester.trx_mysql_thread_id=? AND blocker.trx_mysql_thread_id=? AND l.lock_table=?' );
        $thread = $db->thread_id; $stmt->bind_param( 'iis', $target, $thread, $lock_table );
        do { $stmt->execute(); $r = $stmt->get_result(); $waiting = 1 === (int) $r->fetch_row()[0]; $r->free(); if ( $waiting ) break; usleep( 10000 ); } while ( hrtime( true ) < $end ); $stmt->close();
        if ( ! $waiting ) throw new RuntimeException( 'Actual owned observer wait not established.' );
        alynt_peer_emit( array( 'event' => 'contended' ) );
        if ( 'timeout' === $argv[4] && "release\n" !== alynt_peer_line() ) throw new RuntimeException( 'Release gate refused.' );
        if ( 'disconnect' === $argv[4] ) { $control->query( 'KILL CONNECTION ' . (int) $target ); alynt_peer_emit( array( 'event' => 'disconnected' ) ); }
    }
    $commit = in_array( $argv[4], array( 'commit', 'late' ), true ); $db->query( $commit ? 'COMMIT AND NO CHAIN NO RELEASE' : 'ROLLBACK AND NO CHAIN NO RELEASE' ); $in_transaction = false;
    $r = $db->query( "SHOW SESSION STATUS WHERE Variable_name='Com_update'" ); $updates = $r->fetch_row()[1]; $r->free();
    if ( $updates !== ( 'disconnect' === $argv[4] ? '0' : '1' ) ) throw new RuntimeException( 'Peer DML accounting differs.' );
    alynt_peer_emit( array( 'event' => 'finished', 'committed' => $commit, 'updates' => (int) $updates ) );
} catch ( Throwable $error ) { $exit = 1; try { alynt_peer_emit( array( 'event' => 'failed', 'reason' => $error instanceof RuntimeException ? $error->getMessage() : 'Native peer failure; private diagnostics withheld.' ) ); } catch ( Throwable $closed_channel ) { $exit = 1; } }
finally {
    try { if ( $in_transaction && $db instanceof mysqli ) $db->rollback(); } catch ( Throwable $rollback_failure ) { $exit = 1; }
    try { if ( $control instanceof mysqli ) $control->close(); if ( $db instanceof mysqli ) $db->close(); } catch ( Throwable $close_failure ) { $exit = 1; }
    fclose( $channel ); restore_error_handler();
}
exit( $exit );
