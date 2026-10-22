<?php
/** Fixed direct-backend fixture lifetime. No subprocesses, actual-table writes or web path. */
if ( 'cli' !== PHP_SAPI || 6 !== count( $argv ) || ! in_array( $argv[1], array( 'create', 'cleanup' ), true ) || $argv[2] !== realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' ) || '10004' !== $argv[3] || ! preg_match( '/^wp_alynt_core_probe_[a-f0-9]{12}_$/D', $argv[4] ) || ! preg_match( '/^[a-f0-9]{24}$/D', $argv[5] ) || ! getenv( 'MYSQL_PWD' ) ) exit( 1 );
require __DIR__ . '/fixed-core-proof-storage.php';
mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
$db = mysqli_init(); $db->options( MYSQLI_OPT_CONNECT_TIMEOUT, 3 ); $db->options( MYSQLI_OPT_READ_TIMEOUT, 5 ); $db->real_connect( '127.0.0.1', 'root', getenv( 'MYSQL_PWD' ), 'local', 10004 ); $db->set_charset( 'utf8mb4' );
$prefix = $argv[4]; $marker = $argv[5]; $receipt = array( 'passed' => false ); $created = array();
function alynt_selected_inventory( $db, $exclude ) {
    $names = array(); $result = $db->query( 'SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name' );
    while ( $r = $result->fetch_row() ) { alynt_core_check( 1 === preg_match( '/^[A-Za-z0-9_]+$/D', $r[0] ), 'Unsafe inventory.' ); if ( ! in_array( $r[0], $exclude, true ) ) $names[] = $r[0]; }
    alynt_core_check( count( $names ) >= 3, 'Incomplete inventory.' ); $rows = $db->query( 'CHECKSUM TABLE ' . implode( ',', $names ) . ' EXTENDED' ); $state = array();
    while ( $r = $rows->fetch_row() ) { alynt_core_check( is_string( $r[1] ) || is_int( $r[1] ), 'Checksum unavailable.' ); $state[$r[0]] = (string) $r[1]; }
    alynt_core_check( count( $state ) === count( $names ), 'Incomplete checksums.' ); return $state;
}
try {
    $sites = json_decode( file_get_contents( 'C:/Users/Captain/AppData/Roaming/Local/sites.json' ), true ); $match = false;
    foreach ( $sites as $site ) if ( 'plugin-tester.local' === ( $site['domain'] ?? null ) && realpath( $site['path'] . '/app/public' ) === $argv[2] && 10004 === ( $site['services']['mariadb']['ports']['MYSQL'][0] ?? null ) ) $match = true;
    alynt_core_check( $match && 'http://plugin-tester.local' === $db->query( "SELECT option_value FROM wp_options WHERE option_name='siteurl'" )->fetch_row()[0], 'Fixture target mismatch.' );
    $tables = array_map( function ( $s ) use ( $prefix ) { return $prefix . $s; }, array( 'options', 'users', 'usermeta' ) );
    if ( 'create' === $argv[1] ) {
        alynt_core_check( 0 === (int) $db->query( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name REGEXP '^wp_alynt_core_probe_'" )->fetch_row()[0], 'Preexisting fixture refused.' );
        $baseline = array( 'inventory' => alynt_selected_inventory( $db, array() ), 'actual' => alynt_core_actual_digest( $db ) );
        foreach ( array( 'options', 'users', 'usermeta' ) as $suffix ) { $table = $prefix . $suffix; $db->query( "CREATE TABLE {$table} LIKE wp_{$suffix}" ); $created[] = $table; }
        $stmt = $db->prepare( "INSERT INTO {$prefix}options(option_name,option_value,autoload) VALUES(?,?,'no')" );
        foreach ( array( 'probe_owner' => $marker, 'siteurl' => 'http://plugin-tester.local', 'home' => 'http://plugin-tester.local', 'active_plugins' => 'a:0:{}', 'template' => 'alynt-core-proof-no-theme', 'stylesheet' => 'alynt-core-proof-no-theme', 'blogname' => 'Disposable native qualification no-fault proof', 'WPLANG' => '' ) as $name => $value ) { $stmt->bind_param( 'ss', $name, $value ); $stmt->execute(); }
        $stmt->close(); $receipt = array( 'passed' => true, 'baseline' => $baseline );
    } else {
        $input = stream_get_contents( STDIN, 65537 ); $claims = strlen( $input ) <= 65536 ? json_decode( $input, true ) : null;
        alynt_core_check( is_array( $claims ) && array_keys( $claims ) === array( 'baseline', 'threads', 'worker_closed', 'pipes_closed', 'relay_resolved', 'listener_closed' ) && true === $claims['worker_closed'] && true === $claims['pipes_closed'] && true === $claims['relay_resolved'] && true === $claims['listener_closed'], 'Cleanup ownership unresolved; retain.' );
        $ids = $claims['threads']; alynt_core_check( is_array( $ids ) && count( $ids ) <= 6 && count( $ids ) === count( array_unique( $ids ) ), 'Unknown sessions; retain.' );
        foreach ( $ids as $id ) alynt_core_check( is_int( $id ) && $id > 0 && $id <= 4294967295, 'Bad session; retain.' );
        $end = microtime( true ) + 3;
        do { $live = empty( $ids ) ? 0 : (int) $db->query( 'SELECT COUNT(*) FROM information_schema.processlist WHERE ID IN (' . implode( ',', $ids ) . ')' )->fetch_row()[0]; if ( 0 === $live ) break; usleep( 20000 ); } while ( microtime( true ) < $end );
        alynt_core_check( 0 === $live, 'Owned sessions remain; retain.' );
        alynt_core_check( $marker === $db->query( "SELECT option_value FROM {$prefix}options WHERE option_name='probe_owner'" )->fetch_row()[0], 'Marker changed; retain.' );
        define( 'ABSPATH', $argv[2] . '/' ); require dirname( __DIR__, 2 ) . '/includes/class-scan-effect-manifest.php';
        $locks = 0; $db->query( 'START TRANSACTION' );
        try { foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $def ) { $stmt = $db->prepare( "SELECT option_id FROM {$prefix}options FORCE INDEX (option_name) WHERE option_name=? FOR UPDATE NOWAIT" ); $stmt->bind_param( 's', $def[0] ); $stmt->execute(); $rows = $stmt->get_result(); if ( 1 === $rows->num_rows ) $locks++; $rows->free(); $stmt->close(); } }
        finally { $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
        $unchanged = $claims['baseline']['inventory'] === alynt_selected_inventory( $db, $tables ) && $claims['baseline']['actual'] === alynt_core_actual_digest( $db );
        alynt_core_check( $unchanged && 10 === $locks, 'Application state or locks changed; retain.' );
        $cleanup = array(); foreach ( array_reverse( $tables ) as $table ) { $count = (int) $db->query( "SELECT COUNT(*) FROM {$table}" )->fetch_row()[0]; $db->query( "DROP TABLE {$table}" ); $cleanup[] = array( 'rows_previewed' => $count, 'absent' => 0 === (int) $db->query( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$table}'" )->fetch_row()[0] ); }
        $unchanged = $unchanged && $claims['baseline']['inventory'] === alynt_selected_inventory( $db, array() ) && $claims['baseline']['actual'] === alynt_core_actual_digest( $db );
        $receipt = array( 'passed' => $unchanged && 10 === $locks && ! in_array( false, array_column( $cleanup, 'absent' ), true ), 'sessions_absent' => true, 'locks_available' => $locks, 'cleanup' => $cleanup, 'application_table_count' => count( $claims['baseline']['inventory'] ), 'all_application_checksums_unchanged' => $unchanged );
    }
} catch ( Throwable $error ) {
    // Creation cannot have started a worker. Only tables created in THIS call may be dropped.
    if ( 'create' === $argv[1] ) foreach ( array_reverse( $created ) as $table ) $db->query( "DROP TABLE {$table}" );
    $receipt = array( 'passed' => false, 'redacted_failure' => true, 'location' => array( basename( $error->getFile() ), $error->getLine() ), 'fixtures_retained' => 'cleanup' === $argv[1] );
}
echo json_encode( $receipt ) . "\n"; exit( $receipt['passed'] ? 0 : 1 );
