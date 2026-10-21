<?php
/** Exact Plugin Tester owner; only fresh disposable tables and one no-fault worker. */
if ( 'cli' !== PHP_SAPI || 3 !== count( $argv ) ) exit( 1 );
require __DIR__ . '/native-lifecycle-proof-manifest.php';
if ( ! Alynt_Test_Native_Lifecycle_Manifest::verify() ) exit( 1 );
$root = realpath( $argv[1] );
if ( ! $root || $root !== realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' ) || '10004' !== $argv[2] || '' === (string) getenv( 'MYSQL_PWD' ) ) exit( 1 );
$sites = json_decode( file_get_contents( 'C:/Users/Captain/AppData/Roaming/Local/sites.json' ), true ); $matched = false;
foreach ( is_array( $sites ) ? $sites : array() as $site ) if ( 'plugin-tester.local' === ( $site['domain'] ?? null ) && realpath( $site['path'] . '/app/public' ) === $root && 10004 === ( $site['services']['mariadb']['ports']['MYSQL'][0] ?? null ) ) $matched = true;
if ( ! $matched ) exit( 1 );
foreach ( array( 'db.php', 'object-cache.php', 'advanced-cache.php' ) as $dropin ) if ( file_exists( $root . '/wp-content/' . $dropin ) ) exit( 1 );
mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
$db = new mysqli( '127.0.0.1', 'root', getenv( 'MYSQL_PWD' ), 'local', 10004 ); $db->set_charset( 'utf8mb4' );
$db->query( 'SET SESSION lock_wait_timeout=3' ); $db->query( 'SET SESSION innodb_lock_wait_timeout=2' );
require __DIR__ . '/fixed-core-proof-storage.php';
alynt_core_check( 'http://plugin-tester.local' === $db->query( "SELECT option_value FROM wp_options WHERE option_name='siteurl'" )->fetch_row()[0], 'Wrong local origin.' );
$prefix = 'wp_alynt_core_probe_' . bin2hex( random_bytes( 6 ) ) . '_'; $marker = bin2hex( random_bytes( 12 ) );
$created = array(); $process = null; $pipes = array(); $safe = false;
$receipt = array( 'proof_kind' => 'same_root_native_adapter_genuine_guards_one_no_fault_commit', 'fixture_prefix' => $prefix );
$before = alynt_core_actual_digest( $db );
try {
    foreach ( array( 'options', 'users', 'usermeta' ) as $suffix ) {
        $table = $prefix . $suffix;
        alynt_core_check( 0 === (int) $db->query( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$table}'" )->fetch_row()[0], 'Fixture already exists.' );
        $db->query( "CREATE TABLE {$table} LIKE wp_{$suffix}" ); $created[] = $table;
    }
    $insert = $db->prepare( "INSERT INTO {$prefix}options(option_name,option_value,autoload) VALUES(?,?,'no')" );
    foreach ( array( 'probe_owner' => $marker, 'siteurl' => 'http://plugin-tester.local', 'home' => 'http://plugin-tester.local', 'active_plugins' => 'a:0:{}', 'template' => 'alynt-core-proof-no-theme', 'stylesheet' => 'alynt-core-proof-no-theme', 'blogname' => 'Disposable native lifecycle proof', 'WPLANG' => '' ) as $name => $value ) { $insert->bind_param( 'ss', $name, $value ); $insert->execute(); } $insert->close();
    $command = array( PHP_BINARY, '-d', 'extension_dir=' . ini_get( 'extension_dir' ), '-d', 'extension=mysqli', '-d', 'display_errors=0', '-d', 'log_errors=0', __DIR__ . '/native-lifecycle-proof-worker.php', $root, $argv[2], $prefix, $marker );
    $process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
    alynt_core_check( is_resource( $process ), 'Worker start failed.' ); fclose( $pipes[0] );
    $end = microtime( true ) + 90;
    do { $status = proc_get_status( $process ); if ( ! $status['running'] ) break; if ( microtime( true ) >= $end ) { proc_terminate( $process ); break; } usleep( 20000 ); } while ( true );
    $out = stream_get_contents( $pipes[1], 32769 ); $err = stream_get_contents( $pipes[2], 32769 ); fclose( $pipes[1] ); fclose( $pipes[2] );
    $close = proc_close( $process ); $process = null; $exit = ! $status['running'] ? $status['exitcode'] : $close;
    $receipt['worker'] = json_decode( $out, true ); $receipt['worker_exit'] = $exit; $receipt['worker_stderr_empty'] = '' === $err;
    alynt_core_check( strlen( $out ) + strlen( $err ) <= 32768 && is_array( $receipt['worker'] ), 'Worker bounded receipt unavailable.' );
    $threads = $receipt['worker']['owned_threads'] ?? null; unset( $receipt['worker']['owned_threads'] );
    alynt_core_check( is_array( $threads ) && count( $threads ) >= 1 && count( $threads ) <= 32, 'Owned cleanup correlation unavailable; retain fixtures.' );
    foreach ( $threads as $id ) alynt_core_check( is_int( $id ) && $id > 0, 'Invalid owned session identity.' );
    $end = microtime( true ) + 3;
    do { $remaining = (int) $db->query( 'SELECT COUNT(*) FROM information_schema.processlist WHERE ID IN (' . implode( ',', $threads ) . ')' )->fetch_row()[0]; if ( 0 === $remaining ) break; usleep( 20000 ); } while ( microtime( true ) < $end );
    $safe = 0 === $remaining; $receipt['known_sessions_absent_before_drop'] = $safe;
    alynt_core_check( 0 === $exit && '' === $err && true === ( $receipt['worker']['passed'] ?? null ), 'No-fault worker did not pass.' );
} catch ( Throwable $error ) {
    $receipt['failed'] = true; $receipt['failure'] = $error instanceof RuntimeException ? $error->getMessage() : 'Supervisor failure; private diagnostics withheld.';
} finally {
    if ( is_resource( $process ) ) { proc_terminate( $process ); foreach ( $pipes as $pipe ) if ( is_resource( $pipe ) ) fclose( $pipe ); proc_close( $process ); }
    $receipt['cleanup'] = array();
    foreach ( array_reverse( $created ) as $table ) {
        try {
            alynt_core_check( $safe, 'Worker session ownership unresolved; retain exact fixtures.' );
            $rows = (int) $db->query( "SELECT COUNT(*) FROM {$table}" )->fetch_row()[0];
            $db->query( "DROP TABLE {$table}" );
            $receipt['cleanup'][$table] = array( 'rows_previewed' => $rows, 'absence_verified' => 0 === (int) $db->query( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$table}'" )->fetch_row()[0] );
        } catch ( Throwable $error ) { $receipt['failed'] = true; $receipt['cleanup'][$table] = array( 'absence_verified' => false ); }
    }
    $receipt['actual_options_users_usermeta_unchanged'] = $before === alynt_core_actual_digest( $db );
}
$clean = 3 === count( $receipt['cleanup'] ); foreach ( $receipt['cleanup'] as $row ) $clean = $clean && $row['absence_verified'];
$receipt['source_manifest_unchanged'] = Alynt_Test_Native_Lifecycle_Manifest::verify();
$receipt['passed'] = empty( $receipt['failed'] ) && $clean && $receipt['actual_options_users_usermeta_unchanged'] && $receipt['source_manifest_unchanged'];
$db->close(); echo json_encode( $receipt, JSON_PRETTY_PRINT ) . "\n"; exit( $receipt['passed'] ? 0 : 1 );
