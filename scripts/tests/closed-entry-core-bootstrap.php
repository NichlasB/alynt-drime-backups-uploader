<?php
/** Full genuine core over supervisor-owned tables; never actual wp-config/content. */
if ( 'cli' !== PHP_SAPI || 6 !== count( $argv ) || ! preg_match( '/\Awp_alynt_entry_probe_[a-f0-9]{12}_\z/D', $argv[3] ) || ! preg_match( '/\A[a-f0-9]{24}\z/D', $argv[4] ) || '10004' !== $argv[2] || realpath( $argv[1] ) !== realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' ) || ! getenv( 'MYSQL_PWD' ) ) exit( 1 );
require_once __DIR__ . '/fixed-core-proof-storage.php';
$root = realpath( $argv[1] ); $prefix = $argv[3];
$GLOBALS['alynt_core_proof'] = array( 'prefix' => $prefix, 'queries' => 0, 'fixture_writes' => 0, 'guard_readonly' => false, 'checks' => 0, 'cases' => array(), 'events' => array(), 'protected_reads' => 0, 'denied_io' => 0 );
mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
$db = new mysqli( '127.0.0.1', 'root', getenv( 'MYSQL_PWD' ), 'local', 10004 );
alynt_core_check( $argv[4] === $db->query( "SELECT option_value FROM {$prefix}options WHERE option_name='probe_owner'" )->fetch_row()[0], 'Ownership marker mismatch.' ); $db->close();
foreach ( array( 'db.php', 'object-cache.php', 'advanced-cache.php' ) as $name ) alynt_core_check( ! file_exists( $root . '/wp-content/' . $name ), 'Unsupported drop-in.' );
define( 'ABSPATH', str_replace( '\\', '/', $root ) . '/' );
define( 'DB_HOST', '127.0.0.1:10004' ); define( 'DB_NAME', 'local' ); define( 'DB_USER', 'root' ); define( 'DB_PASSWORD', getenv( 'MYSQL_PWD' ) ); define( 'DB_CHARSET', 'utf8mb4' ); define( 'DB_COLLATE', '' );
define( 'DISABLE_WP_CRON', true ); define( 'WP_HTTP_BLOCK_EXTERNAL', true ); define( 'AUTOMATIC_UPDATER_DISABLED', true ); define( 'WP_AUTO_UPDATE_CORE', false );
define( 'WP_DEBUG', false ); define( 'WP_DEBUG_LOG', false ); define( 'WP_DEBUG_DISPLAY', false ); define( 'WP_CACHE', false ); define( 'SHORTINIT', false ); define( 'WP_ADMIN', true );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
$empty = __DIR__ . '/closed-entry-proof-no-content'; alynt_core_check( ! file_exists( $empty ), 'Isolation directory unexpectedly exists.' );
define( 'WP_PLUGIN_DIR', $empty . '/plugins' ); define( 'WPMU_PLUGIN_DIR', $empty . '/mu-plugins' ); define( 'WP_LANG_DIR', $empty . '/languages' );
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $key ) define( $key, bin2hex( random_bytes( 32 ) ) );
$table_prefix = $prefix; $GLOBALS['table_prefix'] = $prefix;
$_SERVER['HTTP_HOST'] = 'plugin-tester.local'; $_SERVER['SERVER_NAME'] = 'plugin-tester.local'; $_SERVER['REQUEST_METHOD'] = 'POST'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require_once ABSPATH . 'wp-includes/plugin.php';
add_filter( 'query', 'alynt_core_query_fence', PHP_INT_MAX );
add_filter( 'pre_http_request', static function () { throw new RuntimeException( 'External HTTP refused.' ); }, PHP_INT_MAX );
add_filter( 'pre_option_theme_roots', static function () use ( $empty ) { return array( 'alynt-core-proof-no-theme' => $empty . '/themes' ); } );
set_error_handler( static function ( $level, $message, $file, $line ) {
    if ( 0 === ( error_reporting() & $level ) ) return false;
    throw new RuntimeException( 'Genuine core diagnostic withheld.' );
} );
require ABSPATH . 'wp-settings.php'; $wpdb->suppress_errors( true );
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php'; require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-registration-closed-entry-request-owner.php';
require_once __DIR__ . '/fixed-core-proof-fixtures.php'; require_once __DIR__ . '/fixed-core-proof-denials.php';
foreach ( array( 'wp_verify_nonce', 'wp_create_nonce', 'wp_validate_auth_cookie', 'user_can', 'get_current_screen' ) as $fn ) {
    $file = realpath( ( new ReflectionFunction( $fn ) )->getFileName() );
    alynt_core_assert( 0 === strpos( $file, realpath( ABSPATH ) . DIRECTORY_SEPARATOR ) && 0 !== strpos( $file, realpath( WP_CONTENT_DIR ) . DIRECTORY_SEPARATOR ), 'Not genuine core.' );
}
alynt_core_assert( $wpdb->prefix === $prefix && 'wpdb' === get_class( $wpdb ) && ! is_multisite() && ! wp_using_ext_object_cache() && ! defined( 'WP_CLI' ), 'Unsupported core profile.' );
alynt_core_seed();
