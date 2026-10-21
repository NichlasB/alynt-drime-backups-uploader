<?php
/** Shared top-level bootstrap for the two fixed owned entries, never a web/API port. */
if ( 'cli' !== PHP_SAPI || ! isset( $root, $native_qualification_bootstrap_port ) || ! in_array( basename( $argv[0] ), array( 'native-qualification-no-fault-worker.php' ), true ) ) exit( 1 );
define( 'ABSPATH', str_replace( '\\', '/', $root ) . '/' );
require dirname( __DIR__, 2 ) . '/tests/Support/NativeQualificationNoFaultManifest.php';

if ( ! Alynt_Test_Native_Qualification_No_Fault_Manifest::verify() || function_exists( 'wp_verify_nonce' ) || class_exists( 'wpdb', false ) ) exit( 1 );
require __DIR__ . '/fixed-core-proof-storage.php';
$GLOBALS['alynt_core_proof'] = array( 'prefix' => $argv[3], 'queries' => 0, 'fixture_writes' => 0, 'guard_readonly' => false, 'checks' => 0, 'cases' => array(), 'events' => array(), 'protected_reads' => 0, 'denied_io' => 0 );
$sites = json_decode( file_get_contents( 'C:/Users/Captain/AppData/Roaming/Local/sites.json' ), true ); $matched = false;
foreach ( is_array( $sites ) ? $sites : array() as $site ) if ( 'plugin-tester.local' === ( $site['domain'] ?? null ) && realpath( $site['path'] . '/app/public' ) === $root && 10004 === ( $site['services']['mariadb']['ports']['MYSQL'][0] ?? null ) ) $matched = true;
alynt_core_check( $matched && '' !== (string) getenv( 'MYSQL_PWD' ), 'qualification_target' );
foreach ( array( 'db.php', 'object-cache.php', 'advanced-cache.php' ) as $dropin ) alynt_core_check( ! file_exists( $root . '/wp-content/' . $dropin ), 'qualification_dropin' );
mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
$marker = new mysqli( '127.0.0.1', 'root', getenv( 'MYSQL_PWD' ), 'local', 10004 );
alynt_core_check( $argv[4] === $marker->query( "SELECT option_value FROM {$argv[3]}options WHERE option_name='probe_owner'" )->fetch_row()[0], 'qualification_marker' ); $marker->close();
define( 'DB_HOST', '127.0.0.1:' . $native_qualification_bootstrap_port ); define( 'DB_NAME', 'local' ); define( 'DB_USER', 'root' ); define( 'DB_PASSWORD', getenv( 'MYSQL_PWD' ) ); define( 'DB_CHARSET', 'utf8mb4' ); define( 'DB_COLLATE', '' );
define( 'DISABLE_WP_CRON', true ); define( 'WP_HTTP_BLOCK_EXTERNAL', true ); define( 'AUTOMATIC_UPDATER_DISABLED', true ); define( 'WP_AUTO_UPDATE_CORE', false );
define( 'WP_DEBUG', false ); define( 'WP_DEBUG_LOG', false ); define( 'WP_DEBUG_DISPLAY', false ); define( 'WP_CACHE', false ); define( 'SHORTINIT', false ); define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
$empty = __DIR__ . '/fixed-core-proof-no-content'; alynt_core_check( ! file_exists( $empty ), 'qualification_isolation_path' );
define( 'WP_PLUGIN_DIR', $empty . '/plugins' ); define( 'WPMU_PLUGIN_DIR', $empty . '/mu-plugins' ); define( 'WP_LANG_DIR', $empty . '/languages' );
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $key ) define( $key, bin2hex( random_bytes( 32 ) ) );
$table_prefix = $argv[3]; $GLOBALS['table_prefix'] = $table_prefix;
$_SERVER['HTTP_HOST'] = 'plugin-tester.local'; $_SERVER['SERVER_NAME'] = 'plugin-tester.local'; $_SERVER['REQUEST_METHOD'] = 'POST'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require_once ABSPATH . 'wp-includes/plugin.php'; add_filter( 'query', 'alynt_core_query_fence', PHP_INT_MAX );
add_filter( 'pre_http_request', function () { throw new RuntimeException( 'qualification_external_http' ); }, PHP_INT_MAX );
add_filter( 'pre_option_theme_roots', function () use ( $empty ) { return array( 'alynt-core-proof-no-theme' => $empty . '/themes' ); } );
set_error_handler( function ( $severity ) { if ( 0 === ( error_reporting() & $severity ) ) return false; throw new RuntimeException( 'qualification_core_diagnostic' ); } );
require ABSPATH . 'wp-settings.php'; $wpdb->suppress_errors( true );
alynt_core_check( ! SHORTINIT && ! defined( 'WP_CLI' ) && 'wpdb' === get_class( $wpdb ) && ! is_multisite() && ! wp_using_ext_object_cache() && $wpdb->prefix === $argv[3] && $wpdb->base_prefix === $argv[3], 'qualification_core_environment' );
alynt_core_check( $argv[4] === $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name='probe_owner'" ), 'qualification_core_marker' );
foreach ( array( 'wp_verify_nonce', 'wp_validate_auth_cookie', 'user_can' ) as $function ) alynt_core_check( 0 === strpos( realpath( ( new ReflectionFunction( $function ) )->getFileName() ), realpath( ABSPATH . 'wp-includes' ) . DIRECTORY_SEPARATOR ), 'qualification_genuine_core' );
require dirname( __DIR__, 2 ) . '/includes/class-real-closed-writer-request-owner.php';
require __DIR__ . '/fixed-core-proof-fixtures.php'; alynt_core_seed();
