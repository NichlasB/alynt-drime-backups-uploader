<?php
/** Isolated owner/IO protocol evidence only. No WordPress, sockets or storage. */
if ( PHP_SAPI !== 'cli' || realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) !== __FILE__ ) return;
define( 'ALYNT_CLOSED_ENTRY_ISOLATED_WORKER', true );
$case = $argv[1] ?? ''; $mutation = $argv[2] ?? '';
$checks = 0; $failures = array(); $loaded = false;
function alynt_entry_check( $value, $label ) { ++$GLOBALS['checks']; if ( ! $value ) $GLOBALS['failures'][] = $label; }
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' ); define( 'LOGGED_IN_COOKIE', 'alynt_unit_logged_in' );
require_once __DIR__ . '/ClosedEntryApiFixture.php';
require_once __DIR__ . '/ClosedEntryApiCases.php';
$GLOBALS['alynt_core_request_test'] = array(
    'calls' => array(), 'on' => array(), 'throw' => '', 'multisite' => false, 'external_cache' => false, 'blog' => 1,
    'actor' => 7, 'cookie_actor' => 7, 'logged_in' => true, 'session_now' => true, 'session_cache' => true,
    'user_now' => true, 'user_cache' => true, 'role_now' => true, 'role_cache' => true, 'filter_allows' => true,
    'nonce_result' => 1, 'expiration' => '2000000900', 'expected_action' => 'alynt_drime_backups_scan_registration_issue_v2:1:register_closed',
);
$s =& $GLOBALS['alynt_core_request_test'];
$GLOBALS['wpdb'] = new wpdb(); $GLOBALS['wp_roles'] = new WP_Roles( 1 ); $original_roles = $GLOBALS['wp_roles'];
$GLOBALS['alynt_entry_admin'] = true; $GLOBALS['alynt_entry_screen'] = new WP_Screen(); $GLOBALS['alynt_entry_case'] = $case;
$foreign = static function ( $v ) { return $v; };
$GLOBALS['alynt_entry_filters'] = array( 'query' => array( array( $foreign, 3 ) ), 'pre_wp_load_alloptions' => array( array( $foreign, 4 ) ) );
$original_filters = $GLOBALS['alynt_entry_filters'];
$_COOKIE = array( LOGGED_IN_COOKIE => 'local-7|2000000900|synthetic-session|synthetic-hmac' ); $_GET = array(); $_FILES = array();
$_POST = array( 'action' => 'alynt_drime_backups_register_closed', 'purpose' => 'register_closed', 'confirm' => 'register', 'nonce' => 'unit-nonce' );
Alynt_Test_Closed_Entry_Io::$bytes = http_build_query( $_POST, '', '&', PHP_QUERY_RFC3986 );
Alynt_Test_Closed_Entry_Io::$sizes = array( 3, 7 );
$_SERVER = array( 'REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/x-www-form-urlencoded', 'QUERY_STRING' => '', 'CONTENT_LENGTH' => (string) strlen( Alynt_Test_Closed_Entry_Io::$bytes ) );
$get = 0 === strpos( $case, 'get-' ); $reader = 0 === strpos( $case, 'reader-' ) || 0 === strpos( $case, 'io-' );
if ( $get ) { $_SERVER = array( 'REQUEST_METHOD' => 'GET', 'QUERY_STRING' => 'page=alynt-drime-backups-uploader' ); $_GET = array( 'page' => 'alynt-drime-backups-uploader' ); $_POST = array(); }
alynt_entry_configure( $case );
alynt_entry_load( ! in_array( $case, array( 'closed', 'get-closed' ), true ), $mutation );
$owner = Alynt_Drime_Backups_Uploader_Registration_Closed_Entry_Request_Owner::create();
$loaded = is_object( $owner ); alynt_entry_check( $loaded, 'actual fixed class instantiated' );
$original_error = static function () { throw new RuntimeException( 'foreign handler preserved' ); }; set_error_handler( $original_error );
$s['calls'] = array();
$s['on']['parse_cookie'] = function () use ( $case ) {
    foreach ( array( 'query', 'pre_wp_load_alloptions' ) as $hook ) alynt_entry_check( count( $GLOBALS['alynt_entry_filters'][$hook] ) === 2, 'owned fences installed before authentication: ' . $hook );
    if ( 'post-fences' !== $case ) return;
    $query = $GLOBALS['alynt_entry_filters']['query'][1][0]; $all = $GLOBALS['alynt_entry_filters']['pre_wp_load_alloptions'][1][0];
    alynt_entry_check( array() === $all(), 'no alloptions capture' );
    foreach ( array( "SELECT option_value FROM wp_options WHERE option_name = 'wp_user_roles' LIMIT 1", "SELECT * FROM wp_users WHERE ID = '7' LIMIT 1", "SELECT * FROM wp_users WHERE user_login = 'local-7' LIMIT 1", 'SELECT user_id, meta_key, meta_value FROM wp_usermeta WHERE user_id IN (7) ORDER BY umeta_id ASC' ) as $sql ) alynt_entry_check( $query( $sql ) === $sql, 'narrow core read allowance' );
    foreach ( array( "SELECT option_value FROM wp_options WHERE option_name = 'alynt_drime_backups_settings' LIMIT 1", 'UPDATE wp_users SET ID = 1', 'SELECT * FROM wp_users WHERE ID = 8 LIMIT 1', "SELECT * FROM wp_users WHERE user_login = 'another-user' LIMIT 1", "SELECT * FROM wp_users WHERE user_login = 'x' OR 1=1 LIMIT 1", 'SELECT * FROM wp_options', 'SELECT * FROM wp_users; SELECT * FROM wp_options' ) as $sql ) {
        $denied = false; try { $query( $sql ); } catch ( RuntimeException $e ) { $denied = true; } alynt_entry_check( $denied, 'query fence rejects protected/write/foreign/ambiguous read' );
    }
};
if ( $reader ) {
    alynt_entry_check( alynt_entry_invoke( $owner, 'start' ), 'private reader original clock opened' );
    $state = alynt_entry_state( $owner, 'state' ); $state['transport'] = alynt_entry_invoke( $owner, 'post_transport' ); alynt_entry_state( $owner, 'state', $state );
    try { $result = alynt_entry_invoke( $owner, 'read_body' ); } catch ( Throwable $e ) { $result = false; }
    $positive = in_array( $case, array( 'reader-short', 'reader-max' ), true );
    alynt_entry_check( $positive ? $result === Alynt_Test_Closed_Entry_Io::$bytes : false === $result, 'reader completion/bounds/error outcome: ' . $case );
    alynt_entry_invoke( $owner, 'close' );
} else {
    $result = alynt_entry_invoke( $owner, $get ? 'issue_get' : 'inspect_post' );
    $positive = in_array( $case, array( 'post-valid', 'post-slashed', 'post-nonce-two', 'post-cache-null', 'post-fences', 'get-valid', 'post-repeat' ), true );
    $expected = $get ? 'stubNonce1' : array_diff_key( $_POST, array( 'action' => true ) );
    alynt_entry_check( $positive ? $result === $expected : false === $result, 'guarded private checkpoint outcome: ' . $case );
    $calls = array_column( $s['calls'], 0 );
    $nonce_call = $get ? 'create_nonce' : 'verify_nonce';
    $nonce = array_values( array_filter( $s['calls'], function ( $v ) use ( $nonce_call ) { return $v[0] === $nonce_call; } ) );
    if ( $positive ) {
        alynt_entry_check( 1 === count( $nonce ), 'one and only one small nonce phase' );
        if ( $nonce ) alynt_entry_check( end( $nonce[0][1] ) === $s['expected_action'], 'exact original blog/register_closed small nonce domain' );
        $protocol = array_values( array_filter( $calls, function ( $v ) { return in_array( $v, array( 'parse_cookie', 'validate_cookie', 'capability', 'logged_in', 'verify_nonce', 'create_nonce' ), true ); } ) );
        $auth = array( 'parse_cookie', 'validate_cookie', 'capability', 'logged_in' );
        alynt_entry_check( $protocol === array_merge( $auth, array( $nonce_call ), $auth ), 'fresh current auth/capability brackets small nonce' );
    }
    if ( in_array( $case, array( 'post-denied', 'get-denied', 'post-guest', 'get-guest', 'post-session', 'get-session', 'post-role-revoked', 'get-role-revoked', 'post-expired', 'get-expired' ), true ) ) alynt_entry_check( array() === $nonce, 'failed first auth cannot inspect/create nonce' );
    if ( 'post-buffer-drift' === $case ) alynt_entry_check( array() === $nonce && ! in_array( 'parse_cookie', $calls, true ), 'untrusted buffer refuses before authentication and nonce' );
    if ( in_array( $case, array( 'closed', 'get-closed' ), true ) ) {
        alynt_entry_check( array() === $s['calls'], 'unproved profile refuses before all external APIs/IO' );
        alynt_entry_check( false === Alynt_Drime_Backups_Uploader_Registration_Closed_Entry_Request_Owner::create( true ), 'no injected enable port' );
        $public = array_map( function ( $m ) { return $m->name; }, ( new ReflectionClass( $owner ) )->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $public );
        alynt_entry_check( array( '__serialize', '__unserialize', 'create' ) === $public, 'no public operational or write-capable API' );
        try { serialize( $owner ); alynt_entry_check( false, 'serialization denied' ); } catch ( LogicException $e ) { alynt_entry_check( true, 'serialization denied' ); }
        try { $copy = clone $owner; alynt_entry_check( false, 'clone denied' ); } catch ( Error $e ) { alynt_entry_check( true, 'clone denied' ); }
        $class = get_class( $owner );
        try { unserialize( 'O:' . strlen( $class ) . ':"' . $class . '":0:{}' ); alynt_entry_check( false, 'unserialization denied' ); } catch ( LogicException $e ) { alynt_entry_check( true, 'unserialization denied' ); }
    }
    $count = count( $s['calls'] );
    $next = Alynt_Drime_Backups_Uploader_Registration_Closed_Entry_Request_Owner::create();
    alynt_entry_check( false === alynt_entry_invoke( $next, 'inspect_post' ), 'new instance cannot retry consumed/refused request' );
    alynt_entry_check( $count === count( $s['calls'] ), 'retry performs no dependency/clock/input work' );
}
$current = set_error_handler( $original_error ); restore_error_handler();
alynt_entry_check( $current === $original_error, 'original foreign error handler restored' ); restore_error_handler();
alynt_entry_check( $GLOBALS['wp_roles'] === $original_roles, 'original role view restored on all exits' );
alynt_entry_check( $GLOBALS['alynt_entry_filters'] === $original_filters, 'only owned filters removed; foreign identities preserved' );
alynt_entry_check( array() === alynt_entry_state( $owner, 'state' ) && array() === alynt_entry_state( $owner, 'fences' ) && null === alynt_entry_state( $owner, 'error_handler' ), 'original bytes/pins/clock/fences erased' );
$calls = array_column( $s['calls'], 0 ); alynt_entry_check( ! in_array( 'protected_io', $calls, true ) && ! in_array( 'query', $calls, true ), 'no protected/storage/UUID execution, even on caught failure' );
alynt_entry_check( ! class_exists( 'Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle', false ) && ! extension_loaded( 'mysqli' ), 'no native composition/extension loaded' );
$io = Alynt_Test_Closed_Entry_Io::class;
alynt_entry_check( count( $io::$opened ) <= 1 && count( $io::$reads ) <= 17 && $io::$position <= 16385, 'fixed open/read/byte budget' );
foreach ( $io::$reads as $length ) alynt_entry_check( is_int( $length ) && $length >= 1 && $length <= 1024, 'bounded individual read' );
alynt_entry_check( null === $io::$handle && ( ! $io::$opened || 'open-false' === $io::$fault || 1 === $io::$closed ), 'owned synthetic handle closed exactly once' );
if ( $get || 'closed' === $case || in_array( $case, array( 'post-get', 'post-query', 'post-get-map', 'post-files', 'post-length-zero', 'post-length-leading', 'post-length-large', 'post-length-int', 'post-length-missing', 'post-type', 'post-type-large', 'post-override', 'post-encoding', 'post-transfer', 'post-alias', 'post-array', 'post-reference' ), true ) ) alynt_entry_check( array() === $io::$opened, 'wrong transport and every GET avoid body acquisition' );
echo json_encode( array( 'ok' => ! $failures, 'loaded' => $loaded, 'case' => $case, 'checks' => $checks, 'failures' => $failures, 'authentication' => false, 'storage' => false, 'native_execution' => false, 'sapi_proof' => false ) );
exit( $failures ? 1 : 0 );
