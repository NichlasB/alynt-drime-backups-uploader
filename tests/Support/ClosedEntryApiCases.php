<?php
/** Bounded deterministic cases; source-backed owner, not the synthetic policy model. */
function alynt_entry_configure( $case ) {
    $s =& $GLOBALS['alynt_core_request_test'];
    $io = Alynt_Test_Closed_Entry_Io::class;
    $change = function ( $label, $callback ) use ( &$s ) { $s['on'][$label] = $callback; };
    switch ( $case ) {
        case 'post-slashed':
            $_POST['nonce'] = addslashes( "n+'\\é%" );
            $s['nonce_actions'] = array( "n+'\\é%" => $s['expected_action'] ); break;
        case 'post-get': $_SERVER['REQUEST_METHOD'] = 'GET'; break;
        case 'post-query': $_SERVER['QUERY_STRING'] = 'actor=7'; break;
        case 'post-get-map': $_GET = array( 'page' => 'x' ); break;
        case 'post-files': $_FILES = array( 'file' => array() ); break;
        case 'post-length-zero': $_SERVER['CONTENT_LENGTH'] = '0'; break;
        case 'post-length-leading': $_SERVER['CONTENT_LENGTH'] = '01'; break;
        case 'post-length-large': $_SERVER['CONTENT_LENGTH'] = '16385'; break;
        case 'post-length-int': $_SERVER['CONTENT_LENGTH'] = 123; break;
        case 'post-length-missing': unset( $_SERVER['CONTENT_LENGTH'] ); break;
        case 'post-type': $_SERVER['CONTENT_TYPE'] .= ', application/json'; break;
        case 'post-type-large': $_SERVER['CONTENT_TYPE'] = str_repeat( 'x', 129 ); break;
        case 'post-override': $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = ''; break;
        case 'post-encoding': $_SERVER['HTTP_CONTENT_ENCODING'] = 'gzip'; break;
        case 'post-transfer': $_SERVER['HTTP_TRANSFER_ENCODING'] = 'chunked'; break;
        case 'post-alias': $_POST['purpose.extra'] = $_POST['purpose']; unset( $_POST['purpose'] ); break;
        case 'post-array': $_POST['nonce'] = array( 'unit-nonce' ); break;
        case 'post-reference': $value = 'unit-nonce'; $_POST['nonce'] =& $value; $GLOBALS['alynt_entry_reference'] =& $value; break;
        case 'post-denied': case 'get-denied': $s['filter_allows'] = false; break;
        case 'post-guest': case 'get-guest': $s['actor'] = 0; break;
        case 'post-session': case 'get-session': $s['session_now'] = false; break;
        case 'post-role-revoked': case 'get-role-revoked': $s['role_now'] = false; break;
        case 'post-expired': case 'get-expired': $s['expiration'] = (string) $io::$utc; break;
        case 'post-wrong-domain': $s['nonce_actions'] = array( 'unit-nonce' => 'alynt_drime_backups_scan_registration_receipt_v2:wrong' ); break;
        case 'post-stale': $s['nonce_result'] = false; break;
        case 'post-nonce-two': $s['nonce_result'] = 2; break;
        case 'post-nonce-string': $s['nonce_result'] = '1'; break;
        case 'post-nonce-throw': $s['throw'] = 'verify_nonce'; break;
        case 'post-user-throw': case 'get-user-throw': $s['throw'] = 'user'; break;
        case 'post-capability-throw': case 'get-capability-throw': $s['throw'] = 'capability'; break;
        case 'post-role-throw': case 'get-role-throw': $s['throw'] = 'roles'; break;
        case 'post-multisite': $s['multisite'] = true; break;
        case 'post-external-cache': $s['external_cache'] = true; break;
        case 'post-cache-null': $s['external_cache'] = null; break;
        case 'post-cache-zero': $s['external_cache'] = 0; break;
        case 'post-revoked-after': case 'get-revoked-after':
            $change( 'post-revoked-after' === $case ? 'verify_nonce' : 'create_nonce', function () use ( &$s ) { $s['role_now'] = false; } ); break;
        case 'post-actor-after': case 'get-actor-after':
            $change( 'post-actor-after' === $case ? 'verify_nonce' : 'create_nonce', function () use ( &$s ) { $s['actor'] = 8; $s['cookie_actor'] = 8; } ); break;
        case 'post-cookie-after': case 'get-cookie-after':
            $change( 'post-cookie-after' === $case ? 'verify_nonce' : 'create_nonce', function () { $_COOKIE[LOGGED_IN_COOKIE] = 'another-valid-cookie'; } ); break;
        case 'post-db-after': $change( 'verify_nonce', function () { $GLOBALS['wpdb'] = new wpdb(); } ); break;
        case 'post-blog-after': $change( 'verify_nonce', function () use ( &$s ) { $s['blog'] = 2; } ); break;
        case 'post-transport-after': $change( 'verify_nonce', function () { $_POST['nonce'] = 'changed'; } ); break;
        case 'post-metadata-after': $change( 'verify_nonce', function () { $_SERVER['CONTENT_TYPE'] .= '; charset=UTF-8'; } ); break;
        case 'post-method-after': $change( 'verify_nonce', function () { $_SERVER['REQUEST_METHOD'] = 'GET'; } ); break;
        case 'post-reentry': case 'get-reentry':
            $change( 'post-reentry' === $case ? 'verify_nonce' : 'create_nonce', function () {
                $next = Alynt_Drime_Backups_Uploader_Registration_Closed_Entry_Request_Owner::create();
                alynt_entry_check( false === alynt_entry_invoke( $next, 'issue_get' ), 'nested instance refused' );
            } ); break;
        case 'post-deadline': $change( 'input_read', function () use ( $io ) { $io::$mono += 10000000000; } ); break;
        case 'post-auth-deadline': case 'get-auth-deadline':
            $change( 'capability', function () use ( $io ) { $io::$mono += 10000000000; } ); break;
        case 'post-utc-regression': $change( 'input_read', function () use ( $io ) { --$io::$utc; } ); break;
        case 'post-mono-regression': $change( 'input_read', function () use ( $io ) { $io::$mono = 0; } ); break;
        case 'post-lastmono-regression':
            $change( 'input_read', function () use ( $io ) { $io::$mono = 3000000000; } );
            $change( 'input_close', function () use ( $io ) { $io::$mono = 2000000000; } ); break;
        case 'post-cleanup-reentry':
            $change( 'remove_filter', function () {
                alynt_entry_check( false === alynt_entry_invoke( Alynt_Drime_Backups_Uploader_Registration_Closed_Entry_Request_Owner::create(), 'issue_get' ), 'cleanup reentry refused' );
            } ); break;
        case 'get-wrong-method': $_SERVER['REQUEST_METHOD'] = 'POST'; break;
        case 'get-query-import': $_GET['actor'] = '7'; break;
        case 'get-post-import': $_POST = array( 'nonce' => 'unit-nonce' ); break;
        case 'get-screen': $GLOBALS['alynt_entry_screen']->id = 'dashboard'; break;
        case 'get-nonadmin': $GLOBALS['alynt_entry_admin'] = false; break;
        case 'get-screen-after': $change( 'create_nonce', function () { $GLOBALS['alynt_entry_screen']->id = 'dashboard'; } ); break;
        case 'get-transport-after': $change( 'create_nonce', function () { $_GET['nonce'] = 'import'; } ); break;
        case 'get-method-after': $change( 'create_nonce', function () { $_SERVER['REQUEST_METHOD'] = 'POST'; } ); break;
        case 'reader-max': $io::$sizes = array(); $io::$bytes = str_repeat( 'a', 16384 ); $_SERVER['CONTENT_LENGTH'] = '16384'; break;
        case 'reader-overflow': $io::$sizes = array(); $io::$bytes = str_repeat( 'a', 16385 ); $_SERVER['CONTENT_LENGTH'] = '16384'; break;
        case 'reader-mismatch': $_SERVER['CONTENT_LENGTH'] = '1'; break;
        case 'reader-calls': $io::$sizes = array_fill( 0, 18, 1 ); break;
        case 'reader-short': $io::$sizes = array( 1, 2, 3 ); break;
    }
    if ( 'post-slashed' === $case ) $io::$bytes = http_build_query( array_map( 'stripslashes', $_POST ), '', '&', PHP_QUERY_RFC3986 );
    if ( 'post-slashed' === $case ) $_SERVER['CONTENT_LENGTH'] = (string) strlen( $io::$bytes );
    if ( 0 === strpos( $case, 'io-' ) ) $io::$fault = substr( $case, 3 );
    if ( 'reader-calls' === $case ) $io::$fault = 'never-eof';
    if ( 'post-duplicate' === $case ) { $io::$bytes = str_replace( 'purpose=register_closed', 'nonce=unit-nonce', $io::$bytes ); $_SERVER['CONTENT_LENGTH'] = (string) strlen( $io::$bytes ); }
    if ( 'post-wire-mismatch' === $case ) $_POST['nonce'] = 'other';
}
