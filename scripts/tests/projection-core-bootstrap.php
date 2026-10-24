<?php
/** Included only by the explicit owned worker, never core/production. */
if ( ! isset( $c, $receipt ) || ! in_array( $c['mode'], array( 'autoload', 'fallback' ), true ) || 'cli' !== PHP_SAPI ) exit( 1 );
$root = 'C:/Users/Captain/Local Sites/plugin-tester/app/public/';
alynt_projection_check( ! isset( $GLOBALS['wpdb'] ) && ! isset( $GLOBALS['wp_object_cache'] ), 'fresh_worker' );
foreach ( array( 'db.php', 'object-cache.php', 'advanced-cache.php', 'db-error.php' ) as $file ) alynt_projection_check( ! file_exists( $root . 'wp-content/' . $file ), 'unexpected_dropin' );
$empty = __DIR__ . '/projection-proof-absent-content'; alynt_projection_check( ! file_exists( $empty ), 'content_collision' );
define( 'ABSPATH', $root ); define( 'DB_HOST', '127.0.0.1:10004' ); define( 'DB_NAME', 'local' ); define( 'DB_USER', $c['user'] ); define( 'DB_PASSWORD', $c['password'] ); define( 'DB_CHARSET', 'utf8mb4' ); define( 'DB_COLLATE', '' );
define( 'DISABLE_WP_CRON', true ); define( 'WP_HTTP_BLOCK_EXTERNAL', true ); define( 'AUTOMATIC_UPDATER_DISABLED', true ); define( 'WP_AUTO_UPDATE_CORE', false ); define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
define( 'WP_DEBUG', false ); define( 'WP_DEBUG_LOG', false ); define( 'WP_DEBUG_DISPLAY', false ); define( 'WP_CACHE', false ); define( 'SHORTINIT', false ); define( 'WP_ADMIN', true );
define( 'WP_CONTENT_DIR', $empty ); define( 'WP_PLUGIN_DIR', $empty . '/plugins' ); define( 'WPMU_PLUGIN_DIR', $empty . '/mu-plugins' ); define( 'WP_LANG_DIR', $empty . '/languages' );
$table_prefix = $c['prefix']; $GLOBALS['table_prefix'] = $table_prefix;
$_SERVER['HTTP_HOST'] = 'plugin-tester.local'; $_SERVER['SERVER_NAME'] = 'plugin-tester.local'; $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['REQUEST_URI'] = '/wp-admin/tools.php?page=alynt-drime-backups-uploader'; $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require_once ABSPATH . 'wp-includes/plugin.php';
$allowed_keys = array( 'siteurl', 'home', 'blogname', 'active_plugins', 'template', 'stylesheet', 'WPLANG', $table_prefix . 'user_roles' );
$queries = 0; $bulk = 0;
add_filter( 'query', static function ( $sql ) use ( $table_prefix, $allowed_keys, &$queries, &$bulk, &$receipt ) {
    global $wp_object_cache, $wpdb;
    alynt_projection_check( ++$queries <= 32 && is_string( $sql ) && strlen( $sql ) <= 4096, 'query_budget' );
    if ( 1 === $queries ) {
        alynt_projection_check( 'wpdb' === get_class( $wpdb ) && 'WP_Object_Cache' === get_class( $wp_object_cache ) && ! wp_using_ext_object_cache(), 'genuine_cold_profile' );
        alynt_projection_check( realpath( ( new ReflectionClass( $wp_object_cache ) )->getFileName() ) === realpath( ABSPATH . 'wp-includes/class-wp-object-cache.php' ) && array() === $wp_object_cache->cache, 'cold_cache_source' );
        $receipt['cold_genuine_cache'] = true;
    }
    $table = preg_quote( $table_prefix . 'options', '/' );
    if ( 1 === preg_match( '/\ASELECT option_name, option_value FROM ' . $table . '(?: WHERE autoload IN \( \'[a-z-]+\'(?:, \'[a-z-]+\')* \))?\z/D', $sql ) ) { $bulk++; return $sql; }
    foreach ( $allowed_keys as $key ) if ( "SELECT option_value FROM {$table_prefix}options WHERE option_name = '{$key}' LIMIT 1" === $sql || "SELECT option_value FROM {$table_prefix}options WHERE option_name = '{$key}'" === $sql ) return $sql;
    $receipt['blocked_kind'] = 1 === preg_match( '/\A\s*SELECT\b/i', $sql ) ? 'unapproved_read' : 'unapproved_write_or_statement';
    $receipt['blocked_query_sha256'] = hash( 'sha256', $sql );
    if ( 1 === preg_match( "/WHERE option_name = '([a-zA-Z0-9_]{1,80})'(?: LIMIT 1)?\z/D", $sql, $match ) ) $receipt['blocked_option_dependency'] = $match[1];
    throw new DomainException( 'owned_bootstrap_dependency' );
}, PHP_INT_MAX );
add_filter( 'pre_cache_alloptions', static function ( $options ) use ( $allowed_keys, &$receipt ) {
    alynt_projection_check( is_array( $options ) && 0 === alynt_projection_canaries( $options ) && array() === array_diff( array_keys( $options ), $allowed_keys ), 'cache_value_provenance' );
    $receipt['hydrated_safe_keys'] = count( $options ); return $options; // Observer only; no replacement/short circuit.
}, PHP_INT_MAX );
add_filter( 'pre_http_request', static function () { throw new RuntimeException( 'external_http_refused' ); }, PHP_INT_MAX );
set_error_handler( static function ( $level ) { if ( 0 === ( error_reporting() & $level ) ) return false; throw new RuntimeException( 'core_diagnostic' ); } );
try { require ABSPATH . 'wp-settings.php'; $receipt['bootstrap_status'] = 'completed_not_route_or_authentication'; }
catch ( DomainException $error ) { alynt_projection_check( 'owned_bootstrap_dependency' === $error->getMessage(), 'unexpected_core_refusal' ); $receipt['bootstrap_status'] = 'blocked_at_first_dependency'; }
restore_error_handler();
alynt_projection_check( isset( $receipt['cold_genuine_cache'], $receipt['hydrated_safe_keys'] ) && 8 === $receipt['hydrated_safe_keys'], 'earned_bulk_positive' );
alynt_projection_check( ( 'fallback' === $c['mode'] ? 2 : 1 ) <= $bulk, 'actual_bulk_branch' );
$receipt['bulk_queries'] = $bulk; $receipt['completed_data_queries'] = $wpdb->num_queries; $receipt['core_version'] = $wp_version;
$receipt['cached_protected_values'] = alynt_projection_canaries( $wp_object_cache->cache['options']['alloptions'] ?? array() );
alynt_projection_check( 0 === $receipt['cached_protected_values'], 'no_cached_protected_values' );
foreach ( get_included_files() as $file ) alynt_projection_check( basename( $file ) !== 'wp-config.php' && false === strpos( str_replace( '\\', '/', $file ), '/wp-content/' ), 'application_include' );
$receipt['route_reached'] = false; $receipt['authentication_reached'] = false; $receipt['owner_reached'] = false;
$wpdb->close();
