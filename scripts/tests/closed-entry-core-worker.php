<?php
/** Genuine private helper proof; production entry stays false, no source rewrites. */
if ( 'cli' !== PHP_SAPI || 6 !== count( $argv ) ) exit( 1 );
$receipt = array( 'passed' => false, 'http_root_exercised' => false, 'ingress_enabled' => false, 'native_execution' => false );
try {
    require __DIR__ . '/closed-entry-core-bootstrap.php';
    $case = $argv[5]; $get = 0 === strpos( $case, 'get-' ); $restore = null;
    alynt_core_login( false !== strpos( $case, 'subscriber' ) ? 90000002 : 90000001 );
    $action = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( get_current_blog_id(), 'register_closed' );
    $nonce = wp_create_nonce( 'post-wrong-nonce' === $case ? $action . ':wrong' : $action );
    if ( 'post-invalid-nonce' === $case ) $nonce = 'invalid-intent';
    $alternate = new WP_User( 90000003 );
    $alternate_token = WP_Session_Tokens::get_instance( 90000001 )->create( time() + 900 );
    $alternate_cookie = wp_generate_auth_cookie( 90000001, time() + 900, 'logged_in', $alternate_token );
    if ( 'post-forged' === $case ) $_COOKIE[LOGGED_IN_COOKIE] .= 'x';
    if ( 'post-expired' === $case ) alynt_core_login( 90000001, time() - 7200 );
    if ( in_array( $case, array( 'post-session', 'post-role', 'post-capability' ), true ) ) {
        get_user_meta( 90000001 ); get_option( $wpdb->prefix . 'user_roles' );
        $restore = alynt_core_change( substr( $case, 5 ) );
    }
    $_GET = array(); $_FILES = array(); $_POST = array( 'action' => 'alynt_drime_backups_register_closed', 'purpose' => 'register_closed', 'confirm' => 'register', 'nonce' => addslashes( $nonce ) );
    $_SERVER['QUERY_STRING'] = ''; $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded'; $_SERVER['CONTENT_LENGTH'] = (string) strlen( http_build_query( array_map( 'stripslashes', $_POST ) ) );
    if ( $get ) { unset( $_SERVER['CONTENT_TYPE'], $_SERVER['CONTENT_LENGTH'] ); $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['QUERY_STRING'] = 'page=alynt-drime-backups-uploader'; $_GET = array( 'page' => 'alynt-drime-backups-uploader' ); $_POST = array(); set_current_screen( 'tools_page_alynt-drime-backups-uploader' ); }
    $owner = Alynt_Drime_Backups_Uploader_Registration_Closed_Entry_Request_Owner::create(); $roles = $GLOBALS['wp_roles'];
    $writes = $GLOBALS['alynt_core_proof']['fixture_writes']; $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    $start = alynt_core_private( $owner, 'start' );
    if ( ! $get ) {
        $p = new ReflectionProperty( $owner, 'state' ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true );
        $state = $p->getValue( $owner ); $state['transport'] = alynt_core_private( $owner, 'post_transport' ); $p->setValue( $owner, $state );
    }
    $GLOBALS['alynt_core_proof']['events'] = array();
    $nonce_observer = static function ( $life, $domain ) use ( $action ) { alynt_core_assert( $domain === $action, 'Wrong small nonce action.' ); $GLOBALS['alynt_core_proof']['events'][] = 'small_nonce'; return $life; };
    add_filter( 'nonce_life', $nonce_observer, 10, 2 );
    $first = false; $second = false; $intent = false; $thrown = false;
    try {
        $pinned = $start && alynt_core_private( $owner, 'pin_original' ) && alynt_core_private( $owner, 'open_fences' );
        $first = $pinned && alynt_core_private( $owner, $get ? 'get_authenticated' : 'post_authenticated' );
        if ( $first ) {
            $intent = $get ? is_string( wp_create_nonce( $action ) ) : in_array( wp_verify_nonce( $nonce, $action ), array( 1, 2 ), true );
            if ( 'post-after-actor' === $case ) $GLOBALS['current_user'] = $alternate;
            if ( 'post-after-cookie' === $case ) $_COOKIE[LOGGED_IN_COOKIE] = $alternate_cookie;
            if ( in_array( $case, array( 'post-after-role', 'post-after-session', 'get-after-capability' ), true ) ) {
                // Independent owned-row mutation models revocation outside the fenced request.
                $change = substr( $case, $get ? 10 : 11 ); $owned = new mysqli( '127.0.0.1', 'root', getenv( 'MYSQL_PWD' ), 'local', 10004 );
                if ( 'role' === $change ) { $r = $owned->query( "SELECT option_value FROM {$prefix}options WHERE option_name='{$prefix}user_roles'" )->fetch_row()[0]; $v = unserialize( $r, array( 'allowed_classes' => false ) ); unset( $v['administrator']['capabilities']['manage_options'] ); $value = serialize( $v ); $q = $owned->prepare( "UPDATE {$prefix}options SET option_value=? WHERE option_name='{$prefix}user_roles'" ); }
                else { $key = 'session' === $change ? 'session_tokens' : $prefix . 'capabilities'; $value = 'a:0:{}'; $q = $owned->prepare( "UPDATE {$prefix}usermeta SET meta_value=? WHERE user_id=90000001 AND meta_key=?" ); $q->bind_param( 'ss', $value, $key ); }
                if ( 'role' === $change ) $q->bind_param( 's', $value ); $q->execute(); alynt_core_assert( 1 === $q->affected_rows, 'Owned revocation not exercised.' ); $q->close(); $owned->close();
            }
            if ( 'get-context-change' === $case ) set_current_screen( 'dashboard' );
            $second = $intent && alynt_core_private( $owner, $get ? 'get_authenticated' : 'post_authenticated' );
        }
    } catch ( Throwable $e ) { $thrown = true; }
    finally { $clean = alynt_core_private( $owner, 'close' ); $after_guard_writes = $GLOBALS['alynt_core_proof']['fixture_writes']; remove_filter( 'nonce_life', $nonce_observer, 10 ); $GLOBALS['alynt_core_proof']['guard_readonly'] = false; if ( $restore ) $restore(); }
    $positive = in_array( $case, array( 'post-positive', 'get-positive' ), true );
    alynt_core_assert( ! $thrown, 'Unexpected exception is not a qualified negative auth outcome.' );
    if ( false !== strpos( $case, '-after-' ) || in_array( $case, array( 'get-context-change', 'post-wrong-nonce', 'post-invalid-nonce' ), true ) ) alynt_core_assert( $first, 'Negative boundary lacks genuine first-auth positive control.' );
    if ( false !== strpos( $case, '-after-' ) || 'get-context-change' === $case ) alynt_core_assert( $intent && ! $second, 'Revocation/context change must follow a genuine valid small nonce.' );
    alynt_core_assert( $positive ? ( $first && $intent && $second && $clean && ! $thrown ) : ! ( $first && $intent && $second && $clean ), 'Genuine helper outcome differed.' );
    if ( $positive ) alynt_core_assert( array( 'authenticated', 'capability', 'small_nonce', 'authenticated', 'capability' ) === $GLOBALS['alynt_core_proof']['events'], 'Genuine core guard ordering differs.' );
    alynt_core_assert( $roles === $GLOBALS['wp_roles'] && array() === alynt_core_property( $owner, 'state' ) && array() === alynt_core_property( $owner, 'fences' ), 'Original role view or private state escaped.' );
    alynt_core_assert( $writes === $after_guard_writes && 0 === $GLOBALS['alynt_core_proof']['protected_reads'] && 0 === $GLOBALS['alynt_core_proof']['denied_io'], 'Non-fixture/protected IO or guarded core writes.' );
    $disabled = Alynt_Drime_Backups_Uploader_Registration_Closed_Entry_Request_Owner::create();
    alynt_core_assert( false === alynt_core_private( $disabled, $get ? 'issue_get' : 'inspect_post' ), 'Production entry gate changed.' );
    foreach ( get_included_files() as $file ) alynt_core_assert( 0 !== strpos( str_replace( '\\', '/', $file ), str_replace( '\\', '/', WP_CONTENT_DIR ) . '/' ), 'Actual site content loaded.' );
    $receipt += array( 'case' => $case, 'checks' => $GLOBALS['alynt_core_proof']['checks'], 'wordpress_version' => $wp_version, 'php_version' => PHP_VERSION, 'events' => $GLOBALS['alynt_core_proof']['events'], 'core_api_helpers_only' => true, 'admin_screen_cli_simulated' => $get );
    $receipt['passed'] = true;
} catch ( Throwable $error ) {
    $receipt['failure'] = 'Genuine helper refused; private diagnostics withheld.';
    $receipt['location'] = array( basename( $error->getFile() ), $error->getLine() );
    $receipt['first_auth'] = $first ?? false; $receipt['nonce_phase'] = $intent ?? false; $receipt['second_auth'] = $second ?? false; $receipt['helper_threw'] = $thrown ?? false;
} finally { $_COOKIE = array(); unset( $GLOBALS['current_user'] ); }
echo json_encode( $receipt ) . "\n"; exit( $receipt['passed'] ? 0 : 1 );
