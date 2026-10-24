<?php
/** Explicit CLI feasibility worker: real core, owned options only, no route bridge. */
if ( 'cli' !== PHP_SAPI || 5 !== count( $argv ) || '--owned-bootstrap' !== $argv[1] || ! in_array( $argv[3], array( 'autoload', 'fallback' ), true ) || ! getenv( 'MYSQL_PWD' ) || 1 !== preg_match( '/\Awp_alynt_bootstrap_probe_[a-f0-9]{12}_\z/D', $argv[2] ) || 1 !== preg_match( '/\A[a-f0-9]{24}\z/D', $argv[4] ) ) exit( 1 );
require __DIR__ . '/bootstrap-containment-policy.php';
$prefix = $argv[2]; $profile = $argv[3]; $marker = $argv[4];
$root = 'C:/Users/Captain/Local Sites/plugin-tester/app/public/';
$receipt = array( 'profile' => $profile, 'checks' => 0, 'request_attempts' => array(), 'route_reached' => false, 'authentication_reached' => false, 'owner_reached' => false, 'native_execution' => false, 'admission' => false, 'production_wiring' => false );
function alynt_bootstrap_assert( $condition, $code ) { $GLOBALS['receipt']['checks']++; if ( ! $condition ) throw new RuntimeException( $code ); }
function alynt_bootstrap_cache() {
    global $wp_object_cache;
    alynt_bootstrap_assert( isset( $wp_object_cache ) && 'WP_Object_Cache' === get_class( $wp_object_cache ) && ! wp_using_ext_object_cache(), 'cache_profile' );
    alynt_bootstrap_assert( realpath( ( new ReflectionClass( $wp_object_cache ) )->getFileName() ) === realpath( ABSPATH . 'wp-includes/class-wp-object-cache.php' ), 'cache_source' );
    return $wp_object_cache->cache; // Genuine core's documented compatibility getter, observation only.
}
$phase = 'bootstrap'; $query_count = 0;
$fence = static function ( $sql ) use ( $prefix, &$phase, &$query_count ) {
    global $wpdb, $receipt;
    alynt_bootstrap_assert( ++$query_count <= 32, 'query_budget' );
    $kind = alynt_bootstrap_query_kind( $sql, $prefix );
    if ( 'bootstrap' === $phase ) {
        $cache = alynt_bootstrap_cache();
        $receipt['request_attempts'][] = $kind;
        $receipt['cold_alloptions'] = ! isset( $cache['options']['alloptions'] );
        $receipt['dispatch_before_execute'] = 0 === $wpdb->num_queries;
        $receipt['bootstrap_stack'] = array_values( array_intersect( array_column( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 16 ), 'function' ), array( 'wp_load_alloptions', 'is_blog_installed', 'wp_not_installed' ) ) );
    }
    if ( 'safe' !== $kind ) throw new DomainException( 'denied_' . $kind );
    return $sql;
};
try {
    alynt_bootstrap_assert( ! isset( $GLOBALS['wp_object_cache'] ) && ! isset( $GLOBALS['wpdb'] ), 'fresh_process' );
    foreach ( array( 'db.php', 'object-cache.php', 'advanced-cache.php', 'db-error.php' ) as $dropin ) alynt_bootstrap_assert( ! file_exists( $root . 'wp-content/' . $dropin ), 'unexpected_dropin' );
    define( 'ABSPATH', $root ); define( 'DB_HOST', '127.0.0.1:10004' ); define( 'DB_NAME', 'local' ); define( 'DB_USER', 'root' ); define( 'DB_PASSWORD', getenv( 'MYSQL_PWD' ) ); define( 'DB_CHARSET', 'utf8mb4' ); define( 'DB_COLLATE', '' );
    define( 'DISABLE_WP_CRON', true ); define( 'WP_HTTP_BLOCK_EXTERNAL', true ); define( 'AUTOMATIC_UPDATER_DISABLED', true ); define( 'WP_AUTO_UPDATE_CORE', false );
    define( 'WP_DEBUG', false ); define( 'WP_DEBUG_LOG', false ); define( 'WP_DEBUG_DISPLAY', false ); define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true ); define( 'WP_CACHE', false ); define( 'SHORTINIT', false ); define( 'WP_ADMIN', true );
    // Do not load existing wp-config, content, plugins, themes, diagnostics or browser state.
    $empty = __DIR__ . '/bootstrap-proof-absent-content'; alynt_bootstrap_assert( ! file_exists( $empty ), 'content_collision' );
    define( 'WP_CONTENT_DIR', $empty ); define( 'WP_PLUGIN_DIR', $empty . '/plugins' ); define( 'WPMU_PLUGIN_DIR', $empty . '/mu-plugins' ); define( 'WP_LANG_DIR', $empty . '/languages' );
    $table_prefix = $prefix; $GLOBALS['table_prefix'] = $prefix;
    $_SERVER['HTTP_HOST'] = 'plugin-tester.local'; $_SERVER['SERVER_NAME'] = 'plugin-tester.local'; $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['REQUEST_URI'] = '/wp-admin/tools.php?page=alynt-drime-backups-uploader'; $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
    require_once ABSPATH . 'wp-includes/plugin.php';
    add_filter( 'query', $fence, PHP_INT_MAX );
    add_filter( 'pre_http_request', static function () { throw new RuntimeException( 'http_refused' ); }, PHP_INT_MAX );
    set_error_handler( static function ( $level ) { if ( 0 === ( error_reporting() & $level ) ) return false; throw new RuntimeException( 'core_diagnostic' ); } );
    try { require ABSPATH . 'wp-settings.php'; throw new RuntimeException( 'unexpected_bootstrap_completion' ); }
    catch ( DomainException $blocked ) { alynt_bootstrap_assert( 'denied_bulk_autoload' === $blocked->getMessage(), 'unexpected_bootstrap_dependency' ); }
    alynt_bootstrap_assert( array( 'bulk_autoload' ) === $receipt['request_attempts'] && $receipt['cold_alloptions'] && $receipt['dispatch_before_execute'], 'early_cold_denial' );
    alynt_bootstrap_assert( in_array( 'wp_load_alloptions', $receipt['bootstrap_stack'], true ) && in_array( 'wp_not_installed', $receipt['bootstrap_stack'], true ), 'bootstrap_path' );
    alynt_bootstrap_assert( 'wpdb' === get_class( $wpdb ) && $wpdb->prefix === $prefix && 0 === $wpdb->num_queries, 'zero_request_data_queries' );
    // Derive the zero from real dispatch/cache evidence, never an initialized counter.
    $receipt['request_completed_data_queries'] = $wpdb->num_queries;
    $cache = alynt_bootstrap_cache();
    $receipt['request_cached_protected_values'] = count( array_intersect( array_keys( $cache['options']['alloptions'] ?? array() ), array( 'alynt_drime_backups_settings', 'alynt_drime_backups_schedule_safety_state' ) ) );
    $receipt['core_version'] = $wp_version; $receipt['route_status'] = 'blocked_before_installation_check';
    // Controls after the stopped bootstrap are NOT request/auth/route success evidence.
    $phase = 'controls'; $before = $wpdb->num_queries;
    alynt_bootstrap_assert( $marker === $wpdb->get_var( "SELECT option_value FROM {$prefix}options WHERE option_name = 'proof_safe' LIMIT 1" ) && $before + 1 === $wpdb->num_queries, 'safe_positive' );
    $receipt['safe_read_completed'] = true;
    $denied = array(
        "SELECT option_value FROM {$prefix}options WHERE option_name = 'alynt_drime_backups_settings'" => 'protected_key',
        "SELECT option_value FROM {$prefix}options WHERE option_name = 'alynt_drime_backups_schedule_safety_state'" => 'protected_key',
        "SELECT option_name, option_value FROM {$prefix}options" => 'bulk_all',
        "SELECT option_value FROM wp_options WHERE option_name = 'siteurl'" => 'unknown',
        "UPDATE {$prefix}options SET option_value = 'forbidden'" => 'unknown',
    );
    foreach ( $denied as $sql => $kind ) {
        $before = $wpdb->num_queries; $caught = false;
        try { $wpdb->query( $sql ); } catch ( DomainException $error ) { $caught = $error->getMessage() === 'denied_' . $kind; }
        alynt_bootstrap_assert( $caught && $before === $wpdb->num_queries, 'negative_dispatch' );
    }
    $receipt['prevented_control_queries'] = count( $denied );
    // Real core wp_load_alloptions() fallback must be reached, not simulated by policy tests.
    remove_filter( 'query', $fence, PHP_INT_MAX );
    $control_kinds = array();
    $fallback_fence = static function ( $sql ) use ( $prefix, $profile, &$control_kinds ) {
        $kind = alynt_bootstrap_query_kind( $sql, $prefix ); $control_kinds[] = $kind;
        if ( 'fallback' === $profile && 'bulk_autoload' === $kind ) return $sql; // Empty owned autoload set, control phase only.
        throw new DomainException( 'control_denied_' . $kind );
    };
    add_filter( 'query', $fallback_fence, PHP_INT_MAX ); $caught = false;
    try { wp_load_alloptions(); } catch ( DomainException $error ) { $caught = 'control_denied_' . ( 'fallback' === $profile ? 'bulk_all' : 'bulk_autoload' ) === $error->getMessage(); }
    alynt_bootstrap_assert( $caught && $control_kinds === ( 'fallback' === $profile ? array( 'bulk_autoload', 'bulk_all' ) : array( 'bulk_autoload' ) ), 'real_option_branch' );
    alynt_bootstrap_assert( ! isset( alynt_bootstrap_cache()['options']['alloptions'] ), 'denial_did_not_hydrate' );
    $receipt['guarded_option_branch'] = $control_kinds;
    remove_filter( 'query', $fallback_fence, PHP_INT_MAX );
    // Assertion-killed semantic control: remove read fence, permit ONLY these two
    // fixed owned-table bulk shapes, observe actual canaries and cache hydration.
    $mutant_fence = static function ( $sql ) use ( $prefix ) {
        if ( ! in_array( alynt_bootstrap_query_kind( $sql, $prefix ), array( 'bulk_autoload', 'bulk_all' ), true ) ) throw new RuntimeException( 'mutant_scope' );
        return $sql;
    };
    add_filter( 'query', $mutant_fence, PHP_INT_MAX );
    $options = wp_load_alloptions(); $cache = alynt_bootstrap_cache();
    $completed = 0;
    foreach ( array( 'alynt_drime_backups_settings', 'alynt_drime_backups_schedule_safety_state' ) as $name ) {
        alynt_bootstrap_assert( isset( $options[$name], $cache['options']['alloptions'][$name] ) && 'owned-canary-' . $name === $options[$name] && $options[$name] === $cache['options']['alloptions'][$name], 'canary_provenance' ); $completed++;
    }
    $killed = false; try { alynt_bootstrap_assert( 0 === $completed, 'protected_read_assertion' ); } catch ( RuntimeException $error ) { $killed = 'protected_read_assertion' === $error->getMessage(); }
    alynt_bootstrap_assert( $killed && 2 === $completed, 'guard_removal_must_fail' );
    $receipt['control_only_completed_canary_reads'] = $completed; $receipt['semantic_control_assertion_killed'] = $killed; $receipt['control_cache_hydrated'] = true;
    remove_filter( 'query', $mutant_fence, PHP_INT_MAX ); add_filter( 'query', $fence, PHP_INT_MAX );
    wp_cache_flush(); alynt_bootstrap_assert( array() === alynt_bootstrap_cache(), 'owned_cache_erased' );
    foreach ( get_included_files() as $file ) alynt_bootstrap_assert( false === strpos( str_replace( '\\', '/', $file ), '/wp-content/' ) && basename( $file ) !== 'wp-config.php', 'application_include' );
    $receipt['passed'] = true;
} catch ( Throwable $error ) { $receipt['passed'] = false; $receipt['failure'] = 1 === preg_match( '/\A[a-z_]+\z/D', $error->getMessage() ) ? $error->getMessage() : 'private_failure'; }
restore_error_handler();
if ( isset( $wpdb ) ) $wpdb->close();
echo json_encode( $receipt, JSON_PRETTY_PRINT ) . "\n";
exit( $receipt['passed'] ? 0 : 1 );
