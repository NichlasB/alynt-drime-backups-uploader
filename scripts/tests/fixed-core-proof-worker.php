<?php
/** Native full-core worker, isolated before core bootstrap; not a site endpoint. */
if ( 'cli' !== PHP_SAPI || ! in_array( count( $argv ), array( 5, 6 ), true ) ) exit( 1 );
$proof = $argv[5] ?? 'guard'; if ( ! in_array( $proof, array( 'guard', 'native-observation', 'native-closed-begin', 'native-paired-completion', 'native-scan-observation', 'native-scan-ledger', 'registration-construction', 'registration-native-reader', 'registration-native-admission', 'registration-observation-construction', 'registration-observation-handoff', 'registration-native-barrier', 'registration-native-contention', 'registration-lifecycle-owner', 'registration-native-outcome' ), true ) ) exit( 1 );
$root = realpath( $argv[1] );
if ( ! $root || $root !== realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' ) || ! ctype_digit( $argv[2] ) || ! preg_match( '/^wp_alynt_core_probe_[a-f0-9]{12}_$/D', $argv[3] ) || ! preg_match( '/^[a-f0-9]{24}$/D', $argv[4] ) ) exit( 1 );
require __DIR__ . '/fixed-core-proof-storage.php';
$GLOBALS['alynt_core_proof'] = array( 'prefix' => $argv[3], 'queries' => 0, 'fixture_writes' => 0, 'guard_readonly' => false, 'checks' => 0, 'cases' => array(), 'events' => array(), 'protected_reads' => 0, 'denied_io' => 0 );
$receipt = array( 'passed' => false );
try {
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
    require __DIR__ . '/fixed-core-proof-fixtures.php'; require __DIR__ . '/fixed-core-proof-cases.php'; require __DIR__ . '/fixed-core-proof-denials.php';
    alynt_core_seed();
    if ( 'guard' === $proof ) { alynt_core_cases(); alynt_core_denials(); }
    else {
        require __DIR__ . '/native-observer-proof-fixtures.php';
        if ( 'registration-native-outcome' === $proof ) { require __DIR__ . '/registration-outcome-proof-fixtures.php'; require __DIR__ . '/registration-outcome-proof-cases.php'; require __DIR__ . '/registration-outcome-proof-failures.php'; alynt_outcome_cases(); alynt_outcome_failures(); }
        elseif ( 'registration-lifecycle-owner' === $proof ) { require __DIR__ . '/registration-lifecycle-proof-fixtures.php'; require __DIR__ . '/registration-lifecycle-proof-cases.php'; require __DIR__ . '/registration-lifecycle-proof-denials.php'; alynt_lifecycle_cases(); alynt_lifecycle_denials(); }
        elseif ( 'registration-native-contention' === $proof ) { require __DIR__ . '/registration-contention-proof.php'; require __DIR__ . '/registration-contention-failures.php'; alynt_contention_cases(); alynt_contention_failures(); }
        elseif ( 'registration-native-barrier' === $proof ) { require __DIR__ . '/registration-barrier-proof-fixtures.php'; require __DIR__ . '/registration-barrier-proof-cases.php'; require __DIR__ . '/registration-barrier-proof-denials.php'; require __DIR__ . '/registration-barrier-proof-locks.php'; alynt_barrier_cases(); alynt_barrier_denials(); alynt_barrier_locks(); }
        elseif ( 'registration-observation-handoff' === $proof ) { require __DIR__ . '/registration-handoff-proof-fixtures.php'; require __DIR__ . '/registration-handoff-proof-cases.php'; require __DIR__ . '/registration-handoff-proof-denials.php'; alynt_handoff_cases(); alynt_handoff_denials(); }
        elseif ( 'registration-observation-construction' === $proof ) { require __DIR__ . '/registration-observation-proof-fixtures.php'; require __DIR__ . '/registration-observation-proof-cases.php'; require __DIR__ . '/registration-observation-proof-denials.php'; alynt_origin_cases(); alynt_origin_denials(); }
        elseif ( 'registration-construction' === $proof ) { require __DIR__ . '/registration-proof-fixtures.php'; require __DIR__ . '/registration-proof-cases.php'; require __DIR__ . '/registration-proof-denials.php'; alynt_registration_cases(); alynt_registration_denials(); }
        elseif ( 'registration-native-reader' === $proof ) { require __DIR__ . '/registration-native-proof-fixtures.php'; require __DIR__ . '/registration-native-proof-cases.php'; require __DIR__ . '/registration-native-proof-denials.php'; alynt_registration_native_cases(); alynt_registration_native_denials(); }
        elseif ( 'registration-native-admission' === $proof ) { require __DIR__ . '/registration-admission-proof-fixtures.php'; require __DIR__ . '/registration-admission-proof-cases.php'; alynt_admission_cases(); }
        elseif ( 'native-observation' === $proof ) { require __DIR__ . '/native-observer-proof-cases.php'; alynt_native_cases(); }
        elseif ( 'native-scan-observation' === $proof ) { require __DIR__ . '/native-scan-proof-fixtures.php'; require __DIR__ . '/native-scan-proof-cases.php'; alynt_scan_cases(); }
        elseif ( 'native-scan-ledger' === $proof ) { require __DIR__ . '/native-scan-proof-fixtures.php'; require __DIR__ . '/native-scan-ledger-fixtures.php'; require __DIR__ . '/native-scan-ledger-cases.php'; alynt_scan_ledger_cases(); }
        elseif ( 'native-closed-begin' === $proof ) { require __DIR__ . '/native-closed-begin-proof-fixtures.php'; require __DIR__ . '/native-closed-begin-proof-cases.php'; alynt_native_begin_cases(); }
        else { require __DIR__ . '/native-paired-proof-fixtures.php'; require __DIR__ . '/native-paired-proof-cases.php'; alynt_native_pair_cases(); }
    }
    alynt_core_check( 0 === $GLOBALS['alynt_core_proof']['protected_reads'] && 0 === $GLOBALS['alynt_core_proof']['denied_io'] && array() === headers_list(), 'Protected IO or browser headers escaped.' );
    foreach ( get_included_files() as $file ) alynt_core_check( 0 !== strpos( str_replace( '\\', '/', $file ), str_replace( '\\', '/', WP_CONTENT_DIR ) . '/' ), 'Site content code was loaded.' );
    $receipt = array( 'passed' => true, 'proof_mode' => $proof, 'wordpress_version' => $wp_version, 'php_version' => PHP_VERSION, 'native_cache_flag_type' => $native_cache_flag_type, 'native_full_core' => true, 'site_plugins_and_themes_loaded' => false, 'checks' => $GLOBALS['alynt_core_proof']['checks'], 'cases' => $GLOBALS['alynt_core_proof']['cases'], 'protected_option_api_reads' => 0, 'public_storage_dispatch_execution' => false, 'browser_headers_emitted' => false );
    if ( 'guard' === $proof ) $receipt['protected_reads'] = 0; // Preserve the existing guard-only receipt key; native mode labels API reads explicitly.
} catch ( Throwable $error ) {
    $receipt['failed'] = true; $receipt['failure'] = $error instanceof RuntimeException ? $error->getMessage() : 'Unexpected core proof failure; private diagnostics withheld.';
    $receipt['failure_location'] = array( basename( $error->getFile() ), $error->getLine() );
    if ( isset( $wpdb ) && function_exists( 'alynt_core_private' ) ) {
        $receipt['frame_diagnostics'] = array( 'blog_positive_integer' => is_int( get_current_blog_id() ) && get_current_blog_id() > 0, 'native_user' => isset( $GLOBALS['current_user'] ) && 'WP_User' === get_class( $GLOBALS['current_user'] ), 'native_roles' => isset( $GLOBALS['wp_roles'] ) && 'WP_Roles' === get_class( $GLOBALS['wp_roles'] ), 'custom_global_roles_absent' => empty( $GLOBALS['wp_user_roles'] ), 'base_prefix_matches' => $wpdb->base_prefix === $wpdb->prefix, 'blog_prefix_matches' => $wpdb->get_blog_prefix( get_current_blog_id() ) === $wpdb->prefix );
        foreach ( array( 'options', 'users', 'usermeta' ) as $suffix ) $receipt['frame_diagnostics'][$suffix . '_matches'] = $wpdb->$suffix === $wpdb->prefix . $suffix;
        $receipt['frame_diagnostics']['not_switched'] = empty( $GLOBALS['switched'] ) && empty( $GLOBALS['_wp_switched_stack'] );
        $receipt['frame_diagnostics']['dependencies'] = alynt_core_private( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Owner::create(), 'dependencies' );
        $receipt['frame_diagnostics']['multisite_false'] = false === is_multisite(); $receipt['frame_diagnostics']['external_cache_flag_type'] = gettype( wp_using_ext_object_cache() );
    }
    if ( isset( $GLOBALS['alynt_core_proof']['refused_callers'] ) ) $receipt['refused_callers'] = $GLOBALS['alynt_core_proof']['refused_callers'];
} finally { $_COOKIE = array(); unset( $GLOBALS['current_user'] ); }
echo json_encode( $receipt, JSON_PRETTY_PRINT ) . "\n";
exit( $receipt['passed'] ? 0 : 1 );
