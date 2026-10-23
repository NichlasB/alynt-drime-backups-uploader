<?php
/** Explicit Plugin Tester fixture supervisor. No production entry or native feature. */
if ( 'cli' !== PHP_SAPI || 3 !== count( $argv ) ) exit( 1 );
$root = realpath( $argv[1] ); $port = $argv[2];
if ( $root !== realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' ) || '10004' !== $port || ! getenv( 'MYSQL_PWD' ) ) exit( 1 );
$sites = json_decode( file_get_contents( 'C:/Users/Captain/AppData/Roaming/Local/sites.json' ), true ); $match = false;
foreach ( $sites as $site ) if ( 'plugin-tester.local' === ( $site['domain'] ?? null ) && realpath( $site['path'] . '/app/public' ) === $root && 10004 === ( $site['services']['mariadb']['ports']['MYSQL'][0] ?? null ) ) $match = true;
if ( ! $match ) exit( 1 );
require __DIR__ . '/fixed-core-proof-storage.php';
mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
$db = mysqli_init(); $db->options( MYSQLI_OPT_CONNECT_TIMEOUT, 3 ); $db->options( MYSQLI_OPT_READ_TIMEOUT, 5 ); $db->real_connect( '127.0.0.1', 'root', getenv( 'MYSQL_PWD' ), 'local', 10004 ); $db->set_charset( 'utf8mb4' );
$db->query( 'SET SESSION lock_wait_timeout=2' ); // Bound owned DROP metadata waits, no application settings.
alynt_core_check( 'http://plugin-tester.local' === $db->query( "SELECT option_value FROM wp_options WHERE option_name='siteurl'" )->fetch_row()[0], 'Wrong local origin.' );
function alynt_entry_inventory( $db ) {
    $names = array(); $r = $db->query( 'SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name' );
    while ( $row = $r->fetch_row() ) { alynt_core_check( 1 === preg_match( '/\A[A-Za-z0-9_]+\z/D', $row[0] ), 'Unsafe inventory.' ); $names[] = $row[0]; }
    alynt_core_check( count( $names ) >= 3, 'Incomplete application inventory.' ); $out = array();
    $r = $db->query( 'CHECKSUM TABLE ' . implode( ',', $names ) . ' EXTENDED' );
    while ( $row = $r->fetch_row() ) { alynt_core_check( is_string( $row[1] ) || is_int( $row[1] ), 'Checksum unavailable.' ); $out[$row[0]] = (string) $row[1]; }
    alynt_core_check( count( $out ) === count( $names ), 'Incomplete checksums.' ); return $out;
}
$before = alynt_entry_inventory( $db ); $actual = alynt_core_actual_digest( $db );
alynt_core_check( 0 === (int) $db->query( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name REGEXP '^wp_alynt_(entry|core)_probe_'" )->fetch_row()[0], 'Preexisting fixtures require independent review.' );
$receipt = array( 'proof' => 'genuine_core_closed_entry_helpers_not_http_root', 'cases' => array(), 'cleanup' => array(), 'production_ingress_enabled' => false, 'native_feature_execution' => false );
$cases = array( 'post-positive', 'get-positive', 'post-subscriber', 'get-subscriber', 'post-forged', 'post-expired', 'post-session', 'post-role', 'post-capability', 'post-after-role', 'post-after-session', 'post-after-actor', 'post-after-cookie', 'post-wrong-nonce', 'post-invalid-nonce', 'get-after-capability', 'get-context-change' );
foreach ( $cases as $case ) {
    $prefix = 'wp_alynt_entry_probe_' . bin2hex( random_bytes( 6 ) ) . '_'; $marker = bin2hex( random_bytes( 12 ) ); $created = array(); $process = null; $pipes = array();
    try {
        foreach ( array( 'options', 'users', 'usermeta' ) as $suffix ) {
            $table = $prefix . $suffix;
            alynt_core_check( 0 === (int) $db->query( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$table}'" )->fetch_row()[0], 'Fixture collision.' );
            $db->query( "CREATE TABLE {$table} LIKE wp_{$suffix}" ); $created[] = $table;
        }
        $stmt = $db->prepare( "INSERT INTO {$prefix}options(option_name,option_value,autoload) VALUES(?,?,'no')" );
        foreach ( array( 'probe_owner' => $marker, 'siteurl' => 'http://plugin-tester.local', 'home' => 'http://plugin-tester.local', 'active_plugins' => 'a:0:{}', 'template' => 'alynt-core-proof-no-theme', 'stylesheet' => 'alynt-core-proof-no-theme', 'blogname' => 'Disposable closed entry proof', 'WPLANG' => '' ) as $name => $value ) { $stmt->bind_param( 'ss', $name, $value ); $stmt->execute(); } $stmt->close();
        $cmd = array( PHP_BINARY, '-d', 'extension_dir=' . ini_get( 'extension_dir' ), '-d', 'extension=mysqli', '-d', 'display_errors=0', '-d', 'log_errors=0', __DIR__ . '/closed-entry-core-worker.php', $root, $port, $prefix, $marker, $case );
        $process = proc_open( $cmd, array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        alynt_core_check( is_resource( $process ), 'Owned worker did not start.' ); fclose( $pipes[0] );
        $end = microtime( true ) + 30; $status = proc_get_status( $process );
        while ( $status['running'] && microtime( true ) < $end ) { usleep( 20000 ); $status = proc_get_status( $process ); }
        if ( $status['running'] ) { proc_terminate( $process ); alynt_core_check( false, 'Owned worker exceeded bounded lifetime.' ); }
        $out = stream_get_contents( $pipes[1], 32769 ); $err = stream_get_contents( $pipes[2], 32769 ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $close = proc_close( $process ); $process = null; $exit = $status['exitcode'] >= 0 ? $status['exitcode'] : $close;
        $result = json_decode( $out, true ); $receipt['cases'][$case] = array( 'fixture_prefix' => $prefix, 'worker_exit' => $exit, 'stderr_empty' => '' === $err, 'result' => $result );
        alynt_core_check( strlen( $out ) <= 32768 && '' === $err && 0 === $exit && true === ( $result['passed'] ?? null ), 'Genuine closed-entry helper case failed.' );
    } catch ( Throwable $error ) {
        $receipt['failed'] = true; $receipt['failed_case'] = $case; $receipt['failure'] = 'Owned proof refused; no production guard changed.';
    } finally {
        $settled = true;
        if ( is_resource( $process ) ) {
            $status = proc_get_status( $process ); if ( $status['running'] ) proc_terminate( $process );
            $end = microtime( true ) + 3;
            do { $status = proc_get_status( $process ); if ( ! $status['running'] ) break; usleep( 20000 ); } while ( microtime( true ) < $end );
            $settled = ! $status['running'];
            if ( $settled ) { foreach ( $pipes as $pipe ) if ( is_resource( $pipe ) ) fclose( $pipe ); proc_close( $process ); }
            else { $receipt['failed'] = true; $receipt['cleanup_unresolved'] = true; }
        }
        foreach ( array_reverse( $created ) as $table ) {
            try {
                alynt_core_check( $settled, 'Owned worker unresolved; retain exact fixtures.' );
                $count = (int) $db->query( "SELECT COUNT(*) FROM {$table}" )->fetch_row()[0];
                $db->query( "DROP TABLE {$table}" );
                $receipt['cleanup'][] = array( 'table' => $table, 'rows_previewed' => $count, 'absent' => 0 === (int) $db->query( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$table}'" )->fetch_row()[0] );
            } catch ( Throwable $error ) { $receipt['failed'] = true; $receipt['cleanup'][] = array( 'table' => $table, 'absent' => false ); }
        }
    }
    if ( ! empty( $receipt['failed'] ) ) break; // No speculative rerun or guard widening.
}
$receipt['application_table_count'] = count( $before );
$receipt['all_application_checksums_unchanged'] = $before === alynt_entry_inventory( $db ) && $actual === alynt_core_actual_digest( $db );
$receipt['no_owned_fixtures'] = 0 === (int) $db->query( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name REGEXP '^wp_alynt_entry_probe_'" )->fetch_row()[0];
$receipt['passed'] = empty( $receipt['failed'] ) && count( $receipt['cases'] ) === count( $cases ) && ! in_array( false, array_column( $receipt['cleanup'], 'absent' ), true ) && $receipt['all_application_checksums_unchanged'] && $receipt['no_owned_fixtures'];
$db->close(); echo json_encode( $receipt, JSON_PRETTY_PRINT ) . "\n"; exit( $receipt['passed'] ? 0 : 1 );
