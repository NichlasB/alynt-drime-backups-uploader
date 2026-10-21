<?php
/** Fixed genuine-core bootstrap for this new owned proof only; no wp-config import. */
if ( 'cli' !== PHP_SAPI || ! isset( $root ) || 'native-lifecycle-proof-worker.php' !== basename( $argv[0] ) ) exit( 1 );
$sites = json_decode( file_get_contents( 'C:/Users/Captain/AppData/Roaming/Local/sites.json' ), true ); $matched = false;
foreach ( is_array( $sites ) ? $sites : array() as $site ) if ( 'plugin-tester.local' === ( $site['domain'] ?? null ) && realpath( $site['path'] . '/app/public' ) === $root && ( $site['services']['mariadb']['ports']['MYSQL'][0] ?? null ) === (int) $argv[2] ) $matched = true;
alynt_core_check( $matched && '' !== (string) getenv( 'MYSQL_PWD' ), 'Native worker target metadata mismatch.' );
foreach ( array( 'db.php', 'object-cache.php', 'advanced-cache.php' ) as $dropin ) alynt_core_check( ! file_exists( $root . '/wp-content/' . $dropin ), 'Custom drop-in refused.' );
// Verify parent-owned storage before full core can execute any fixture writes.
mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
$marker_db = new mysqli( '127.0.0.1', 'root', getenv( 'MYSQL_PWD' ), 'local', (int) $argv[2] );
alynt_core_check( $argv[4] === $marker_db->query( "SELECT option_value FROM {$argv[3]}options WHERE option_name='probe_owner'" )->fetch_row()[0], 'Pre-bootstrap ownership marker mismatch.' );
$marker_db->close();
define( 'ABSPATH', str_replace( '\\', '/', $root ) . '/' );
define( 'DB_HOST', '127.0.0.1:' . $argv[2] ); define( 'DB_NAME', 'local' ); define( 'DB_USER', 'root' ); define( 'DB_PASSWORD', getenv( 'MYSQL_PWD' ) ); define( 'DB_CHARSET', 'utf8mb4' ); define( 'DB_COLLATE', '' );
define( 'DISABLE_WP_CRON', true ); define( 'WP_HTTP_BLOCK_EXTERNAL', true ); define( 'AUTOMATIC_UPDATER_DISABLED', true ); define( 'WP_AUTO_UPDATE_CORE', false );
define( 'WP_DEBUG', false ); define( 'WP_DEBUG_LOG', false ); define( 'WP_DEBUG_DISPLAY', false ); define( 'WP_CACHE', false ); define( 'SHORTINIT', false );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
// Empty, verified nonexistent directories stop all site plugin and theme code loading.
$empty = __DIR__ . '/fixed-core-proof-no-content'; alynt_core_check( ! file_exists( $empty ), 'Proof content isolation path exists.' );
define( 'WP_PLUGIN_DIR', $empty . '/plugins' ); define( 'WPMU_PLUGIN_DIR', $empty . '/mu-plugins' ); define( 'WP_LANG_DIR', $empty . '/languages' );
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $key ) define( $key, bin2hex( random_bytes( 32 ) ) );
$table_prefix = $argv[3]; $GLOBALS['table_prefix'] = $table_prefix;
$_SERVER['HTTP_HOST'] = 'plugin-tester.local'; $_SERVER['SERVER_NAME'] = 'plugin-tester.local'; $_SERVER['REQUEST_METHOD'] = 'POST'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require_once ABSPATH . 'wp-includes/plugin.php';
add_filter( 'query', 'alynt_core_query_fence', PHP_INT_MAX );
add_filter( 'pre_http_request', function () { throw new RuntimeException( 'External HTTP attempted.' ); }, PHP_INT_MAX );
add_filter( 'pre_option_theme_roots', function () use ( $empty ) { return array( 'alynt-core-proof-no-theme' => $empty . '/themes' ); } );
set_error_handler( function ( $severity, $message, $file, $line ) {
    if ( 0 === ( error_reporting() & $severity ) ) return false;
    throw new RuntimeException( 'Core diagnostic ' . $severity . ' at ' . basename( $file ) . ':' . $line . '; payload withheld.' );
} );
require ABSPATH . 'wp-settings.php'; // No actual wp-config, plugin, theme, credential or session is imported.
$wpdb->suppress_errors( true );
alynt_core_check( ! SHORTINIT && ! defined( 'WP_CLI' ) && 'wpdb' === get_class( $wpdb ) && ! is_multisite() && ! wp_using_ext_object_cache(), 'Unsupported native core environment.' );
$native_cache_flag_type = gettype( wp_using_ext_object_cache() ); // Observe untouched native state; never normalize it for this proof.
alynt_core_check( $wpdb->prefix === $argv[3] && $wpdb->base_prefix === $argv[3] && $wpdb->get_blog_prefix( get_current_blog_id() ) === $argv[3], 'Native fixture prefix mismatch.' );
alynt_core_check( $argv[4] === $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name='probe_owner'" ), 'Owned fixture marker mismatch.' );
foreach ( array( 'wp_verify_nonce', 'wp_validate_auth_cookie', 'user_can' ) as $function ) {
    $file = realpath( ( new ReflectionFunction( $function ) )->getFileName() );
    alynt_core_check( 0 === strpos( $file, realpath( ABSPATH . 'wp-includes' ) . DIRECTORY_SEPARATOR ), 'Authentication API is not genuine core.' );
}
require dirname( __DIR__, 2 ) . '/includes/class-real-closed-writer-request-owner.php';
require __DIR__ . '/fixed-core-proof-fixtures.php';
alynt_core_seed();
