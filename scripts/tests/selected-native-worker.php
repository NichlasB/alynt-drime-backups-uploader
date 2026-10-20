<?php
/** Fixed loss-only genuine-core worker. No runtime flags, endpoints or source injection. */
if ( 'cli' !== PHP_SAPI || 6 !== count( $argv ) ) exit( 1 );
$root = realpath( $argv[1] );
if ( $root !== realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' ) || '10004' !== $argv[2] || ! ctype_digit( $argv[5] ) || (int) $argv[5] < 1 || (int) $argv[5] > 65535 || $argv[2] === $argv[5] || ! preg_match( '/^wp_alynt_core_probe_[a-f0-9]{12}_$/D', $argv[3] ) || ! preg_match( '/^[a-f0-9]{24}$/D', $argv[4] ) ) exit( 1 );
require __DIR__ . '/fixed-core-proof-storage.php';
$GLOBALS['alynt_core_proof'] = array( 'prefix' => $argv[3], 'queries' => 0, 'fixture_writes' => 0, 'guard_readonly' => false, 'checks' => 0, 'cases' => array(), 'events' => array(), 'protected_reads' => 0, 'denied_io' => 0 );
try {
    $sites = json_decode( file_get_contents( 'C:/Users/Captain/AppData/Roaming/Local/sites.json' ), true ); $matched = false;
    foreach ( is_array( $sites ) ? $sites : array() as $site ) if ( 'plugin-tester.local' === ( $site['domain'] ?? null ) && realpath( $site['path'] . '/app/public' ) === $root && 10004 === ( $site['services']['mariadb']['ports']['MYSQL'][0] ?? null ) ) $matched = true;
    alynt_core_check( $matched && '' !== (string) getenv( 'MYSQL_PWD' ), 'Selected target mismatch.' );
    foreach ( array( 'db.php', 'object-cache.php', 'advanced-cache.php' ) as $dropin ) alynt_core_check( ! file_exists( $root . '/wp-content/' . $dropin ), 'Custom drop-in refused.' );
    mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
    $marker = new mysqli( '127.0.0.1', 'root', getenv( 'MYSQL_PWD' ), 'local', 10004 );
    alynt_core_check( $argv[4] === $marker->query( "SELECT option_value FROM {$argv[3]}options WHERE option_name='probe_owner'" )->fetch_row()[0], 'Selected marker mismatch.' ); $marker->close();
    define( 'ABSPATH', str_replace( '\\', '/', $root ) . '/' );
    define( 'DB_HOST', '127.0.0.1:' . $argv[5] ); define( 'DB_NAME', 'local' ); define( 'DB_USER', 'root' ); define( 'DB_PASSWORD', getenv( 'MYSQL_PWD' ) ); define( 'DB_CHARSET', 'utf8mb4' ); define( 'DB_COLLATE', '' );
    define( 'DISABLE_WP_CRON', true ); define( 'WP_HTTP_BLOCK_EXTERNAL', true ); define( 'AUTOMATIC_UPDATER_DISABLED', true ); define( 'WP_AUTO_UPDATE_CORE', false );
    define( 'WP_DEBUG', false ); define( 'WP_DEBUG_LOG', false ); define( 'WP_DEBUG_DISPLAY', false ); define( 'WP_CACHE', false ); define( 'SHORTINIT', false ); define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
    $empty = __DIR__ . '/fixed-core-proof-no-content'; alynt_core_check( ! file_exists( $empty ), 'Isolation path exists.' );
    define( 'WP_PLUGIN_DIR', $empty . '/plugins' ); define( 'WPMU_PLUGIN_DIR', $empty . '/mu-plugins' ); define( 'WP_LANG_DIR', $empty . '/languages' );
    foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $key ) define( $key, bin2hex( random_bytes( 32 ) ) );
    $table_prefix = $argv[3]; $GLOBALS['table_prefix'] = $table_prefix;
    $_SERVER['HTTP_HOST'] = 'plugin-tester.local'; $_SERVER['SERVER_NAME'] = 'plugin-tester.local'; $_SERVER['REQUEST_METHOD'] = 'POST'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
    require_once ABSPATH . 'wp-includes/plugin.php'; add_filter( 'query', 'alynt_core_query_fence', PHP_INT_MAX );
    add_filter( 'pre_http_request', function () { throw new RuntimeException( 'External HTTP refused.' ); }, PHP_INT_MAX );
    add_filter( 'pre_option_theme_roots', function () use ( $empty ) { return array( 'alynt-core-proof-no-theme' => $empty . '/themes' ); } );
    set_error_handler( function ( $severity ) { if ( 0 === ( error_reporting() & $severity ) ) return false; throw new RuntimeException( 'Core diagnostic; payload withheld.' ); } );
    require ABSPATH . 'wp-settings.php'; $wpdb->suppress_errors( true );
    alynt_core_check( ! SHORTINIT && ! defined( 'WP_CLI' ) && 'wpdb' === get_class( $wpdb ) && ! is_multisite() && ! wp_using_ext_object_cache() && $wpdb->prefix === $argv[3] && $wpdb->base_prefix === $argv[3], 'Unsupported selected core environment.' );
    alynt_core_check( $argv[4] === $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name='probe_owner'" ), 'Selected marker changed.' );
    foreach ( array( 'wp_verify_nonce', 'wp_validate_auth_cookie', 'user_can' ) as $function ) alynt_core_check( 0 === strpos( realpath( ( new ReflectionFunction( $function ) )->getFileName() ), realpath( ABSPATH . 'wp-includes' ) . DIRECTORY_SEPARATOR ), 'Authentication is not genuine core.' );
    require dirname( __DIR__, 2 ) . '/includes/class-real-closed-writer-request-owner.php';
    require __DIR__ . '/fixed-core-proof-fixtures.php'; require __DIR__ . '/fixed-core-proof-cases.php'; require __DIR__ . '/fixed-core-proof-denials.php'; alynt_core_seed();
    require __DIR__ . '/native-observer-proof-fixtures.php'; require __DIR__ . '/registration-join-proof-fixtures.php'; require __DIR__ . '/selected-native-channel.php'; require __DIR__ . '/selected-native-cases.php';
    $guards = alynt_selected_cases();
    alynt_core_check( 0 === $GLOBALS['alynt_core_proof']['protected_reads'] && 0 === $GLOBALS['alynt_core_proof']['denied_io'] && array() === headers_list(), 'Protected IO or headers escaped.' );
    foreach ( get_included_files() as $file ) alynt_core_check( 0 !== strpos( str_replace( '\\', '/', $file ), str_replace( '\\', '/', WP_CONTENT_DIR ) . '/' ), 'Site content loaded.' );
    $_COOKIE = array(); unset( $GLOBALS['current_user'] ); alynt_selected_final( $guards ); exit( 0 );
} catch ( Throwable $error ) {
    $location = array( 'selected-native-worker.php', __LINE__ );
    $frames = array_merge( array( array( 'file' => $error->getFile(), 'line' => $error->getLine() ) ), $error->getTrace() );
    foreach ( $frames as $frame ) {
        $file = basename( $frame['file'] ?? '' );
        if ( in_array( $file, array( 'selected-native-cases.php', 'selected-native-channel.php', 'selected-native-worker.php' ), true ) && isset( $frame['line'] ) && is_int( $frame['line'] ) && $frame['line'] > 0 && $frame['line'] <= 10000 ) { $location = array( $file, $frame['line'] ); break; }
    }
    echo json_encode( array( 'passed' => false, 'redacted_failure' => true, 'location' => $location ) ) . "\n";
    exit( 1 );
}
