<?php
/** Confirmed Plugin Tester supervisor. Owns only three fresh disposable tables. */
if ( 'cli' !== PHP_SAPI || ! in_array( count( $argv ), array( 3, 4 ), true ) ) exit( 1 );
$proof = $argv[3] ?? 'guard'; if ( ! in_array( $proof, array( 'guard', 'native-observation', 'native-closed-begin', 'native-paired-completion', 'native-scan-observation', 'native-scan-ledger', 'registration-construction', 'registration-native-reader', 'registration-native-admission' ), true ) ) exit( 1 );
$root = realpath( $argv[1] ); $approved = realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' );
if ( ! $root || $root !== $approved || ! ctype_digit( $argv[2] ) || '' === (string) getenv( 'MYSQL_PWD' ) ) exit( 1 );
$sites = json_decode( file_get_contents( 'C:/Users/Captain/AppData/Roaming/Local/sites.json' ), true ); $site = null;
foreach ( is_array( $sites ) ? $sites : array() as $candidate ) if ( 'plugin-tester.local' === ( $candidate['domain'] ?? null ) && realpath( $candidate['path'] . '/app/public' ) === $root ) $site = $candidate;
if ( ! $site || (int) $argv[2] !== ( $site['services']['mariadb']['ports']['MYSQL'][0] ?? null ) ) exit( 1 );
foreach ( array( 'db.php', 'object-cache.php', 'advanced-cache.php' ) as $dropin ) if ( file_exists( $root . '/wp-content/' . $dropin ) ) exit( 1 );
mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
$db = new mysqli( '127.0.0.1', 'root', getenv( 'MYSQL_PWD' ), 'local', (int) $argv[2] ); $db->set_charset( 'utf8mb4' );
require __DIR__ . '/fixed-core-proof-storage.php';
alynt_core_check( 'http://plugin-tester.local' === $db->query( "SELECT option_value FROM wp_options WHERE option_name='siteurl'" )->fetch_row()[0], 'Wrong local origin.' );
$prefix = 'wp_alynt_core_probe_' . bin2hex( random_bytes( 6 ) ) . '_'; $marker = bin2hex( random_bytes( 12 ) );
$created = array(); $process = null; $pipes = array(); $receipt = array( 'proof_kind' => array( 'guard' => 'fixed_genuine_core_request_guard_only', 'native-observation' => 'fixed_native_read_only_planning_observation', 'native-closed-begin' => 'fixed_native_owned_safety_row_closed_begin', 'native-paired-completion' => 'fixed_native_owned_pair_and_locked_resolution', 'native-scan-observation' => 'fixed_native_ten_row_closed_codec_observation', 'native-scan-ledger' => 'fixed_native_closed_ledger_only_checkpoint', 'registration-construction' => 'genuine_core_private_registration_captured_owned_frame_only', 'registration-native-reader' => 'genuine_core_fixed_read_only_registration_capture_and_construction', 'registration-native-admission' => 'genuine_core_closed_registration_owned_ledger_admission' )[$proof], 'fixture_prefix' => $prefix );
$before = alynt_core_actual_digest( $db );
try {
    foreach ( array( 'options', 'users', 'usermeta' ) as $suffix ) {
        $table = $prefix . $suffix;
        alynt_core_check( 0 === (int) $db->query( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$table}'" )->fetch_row()[0], 'Fixture already exists.' );
        $db->query( "CREATE TABLE {$table} LIKE wp_{$suffix}" ); $created[] = $table;
    }
    $insert = $db->prepare( "INSERT INTO {$prefix}options(option_name,option_value,autoload) VALUES(?,?,'no')" );
    foreach ( array( 'probe_owner' => $marker, 'siteurl' => 'http://plugin-tester.local', 'home' => 'http://plugin-tester.local', 'active_plugins' => 'a:0:{}', 'template' => 'alynt-core-proof-no-theme', 'stylesheet' => 'alynt-core-proof-no-theme', 'blogname' => 'Disposable guard proof', 'WPLANG' => '' ) as $name => $value ) { $insert->bind_param( 'ss', $name, $value ); $insert->execute(); }
    $command = array( PHP_BINARY, '-d', 'extension_dir=' . ini_get( 'extension_dir' ), '-d', 'extension=mysqli', '-d', 'display_errors=0', '-d', 'log_errors=0', __DIR__ . '/fixed-core-proof-worker.php', $root, $argv[2], $prefix, $marker );
    $command[] = $proof;
    $process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
    alynt_core_check( is_resource( $process ), 'Worker start failed.' ); fclose( $pipes[0] );
    stream_set_blocking( $pipes[1], false ); stream_set_blocking( $pipes[2], false ); $out = ''; $err = ''; $end = microtime( true ) + 90; $status = null;
    do {
        $out .= stream_get_contents( $pipes[1] ); $err .= stream_get_contents( $pipes[2] );
        $status = proc_get_status( $process );
        if ( ! $status['running'] ) break;
        if ( strlen( $out ) + strlen( $err ) > 65536 || microtime( true ) >= $end ) { proc_terminate( $process ); break; }
        usleep( 20000 );
    } while ( true );
    $out .= stream_get_contents( $pipes[1] ); $err .= stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
    $close = proc_close( $process ); $exit = $status && ! $status['running'] ? $status['exitcode'] : $close;
    $receipt['worker'] = json_decode( $out, true ); $receipt['worker_stderr_empty'] = '' === $err; $receipt['worker_exit'] = $exit;
    alynt_core_check( 0 === $exit && '' === $err && is_array( $receipt['worker'] ) && true === ( $receipt['worker']['passed'] ?? null ), 'Native core worker did not pass.' );
} catch ( Throwable $error ) {
    $receipt['failed'] = true; $receipt['failure'] = $error instanceof RuntimeException ? $error->getMessage() : 'Supervisor failure; private diagnostics withheld.';
    $receipt['failure_location'] = array( basename( $error->getFile() ), $error->getLine() );
} finally {
    if ( is_resource( $process ) ) { proc_terminate( $process ); foreach ( $pipes as $pipe ) if ( is_resource( $pipe ) ) fclose( $pipe ); proc_close( $process ); }
    $receipt['cleanup'] = array();
    foreach ( array_reverse( $created ) as $table ) {
        try {
            $rows = (int) $db->query( "SELECT COUNT(*) FROM {$table}" )->fetch_row()[0]; // Mandatory exact-target preview before owned cleanup.
            $db->query( "DROP TABLE {$table}" );
            $receipt['cleanup'][$table] = array( 'rows_previewed' => $rows, 'absence_verified' => 0 === (int) $db->query( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$table}'" )->fetch_row()[0] );
        } catch ( Throwable $error ) { $receipt['failed'] = true; $receipt['cleanup'][$table] = array( 'absence_verified' => false, 'cleanup_failed' => true ); }
    }
    $receipt['actual_options_users_usermeta_unchanged'] = $before === alynt_core_actual_digest( $db );
}
$clean = 3 === count( $receipt['cleanup'] ); foreach ( $receipt['cleanup'] as $row ) $clean = $clean && $row['absence_verified'];
$receipt['passed'] = empty( $receipt['failed'] ) && $clean && $receipt['actual_options_users_usermeta_unchanged'];
echo json_encode( $receipt, JSON_PRETTY_PRINT ) . "\n";
exit( $receipt['passed'] ? 0 : 1 );
