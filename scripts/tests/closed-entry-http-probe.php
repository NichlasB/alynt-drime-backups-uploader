<?php
/** Temporary loopback input-only fixture. No WordPress bootstrap/auth/storage. */
$approved = realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' );
if ( 'cgi-fcgi' !== PHP_SAPI || ! in_array( $_SERVER['REMOTE_ADDR'] ?? null, array( '127.0.0.1', '::1' ), true ) || 'plugin-tester.local' !== ( $_SERVER['HTTP_HOST'] ?? null ) || ! $approved || realpath( dirname( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) !== $approved || ! preg_match( '/\Aalynt-closed-entry-input-proof-[a-f0-9]{12}\.php\z/D', basename( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? null ) || '' !== ( $_SERVER['QUERY_STRING'] ?? null ) || ! in_array( $_SERVER['HTTP_X_ALYNT_INPUT_PROBE'] ?? null, array( 'metadata', 'reader' ), true ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', str_replace( '\\', '/', $approved ) . '/' );
require_once dirname( __DIR__, 2 ) . '/includes/class-registration-closed-entry-request-owner.php';
header( 'Content-Type: application/json' ); header( 'Cache-Control: no-store' );
$result = array( 'kind' => $_SERVER['HTTP_X_ALYNT_INPUT_PROBE'], 'sapi' => PHP_SAPI, 'production_entry_available' => false, 'wordpress_loaded' => function_exists( 'wp_validate_auth_cookie' ), 'native_execution' => false );
function alynt_entry_http_private( $owner, $method ) {
    $r = new ReflectionMethod( $owner, $method ); if ( PHP_VERSION_ID < 80100 ) $r->setAccessible( true ); return $r->invoke( $owner );
}
$owner = Alynt_Drime_Backups_Uploader_Registration_Closed_Entry_Request_Owner::create();
$result['production_entry_available'] = alynt_entry_http_private( $owner, 'entry_available' );
if ( 'metadata' === $result['kind'] ) {
    $stream = null;
    try {
        $stream = fopen( 'php://input', 'rb' );
        if ( ! is_resource( $stream ) ) throw new RuntimeException( 'Input unavailable.' );
        $meta = stream_get_meta_data( $stream );
        $result['metadata'] = array_intersect_key( $meta, array_flip( array( 'wrapper_type', 'stream_type', 'mode', 'uri', 'timed_out', 'blocked', 'seekable', 'eof', 'unread_bytes' ) ) );
        $result['owner_metadata_accepted'] = alynt_entry_http_private_metadata( $owner, $stream );
        $raw = ''; $reads = 0; $end = hrtime( true ) + 1000000000;
        while ( $reads < 17 && strlen( $raw ) < 16385 && hrtime( true ) < $end ) {
            ++$reads; $chunk = fread( $stream, min( 1024, 16385 - strlen( $raw ) ) );
            if ( ! is_string( $chunk ) ) throw new RuntimeException( 'Read unavailable.' );
            $raw .= $chunk; if ( feof( $stream ) || '' === $chunk ) break;
        }
        $result['bytes'] = strlen( $raw ); $result['reads'] = $reads; $result['eof'] = feof( $stream );
        $result['synthetic_body_sha256'] = hash( 'sha256', $raw ); unset( $raw );
    } catch ( Throwable $e ) { $result['probe_failed'] = true; }
    finally { $result['closed'] = is_resource( $stream ) && true === fclose( $stream ); }
} else {
    $result['reader_accepted'] = false;
    try {
        $start = alynt_entry_http_private( $owner, 'start' );
        $p = new ReflectionProperty( $owner, 'state' ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true );
        $state = $p->getValue( $owner ); $state['transport'] = alynt_entry_http_private( $owner, 'post_transport' ); $p->setValue( $owner, $state );
        $raw = $start && false !== $state['transport'] ? alynt_entry_http_private( $owner, 'read_body' ) : false;
        $result['reader_accepted'] = is_string( $raw ); $result['bytes'] = is_string( $raw ) ? strlen( $raw ) : 0; unset( $raw );
    } catch ( Throwable $e ) { $result['probe_failed'] = true; }
    finally { $result['owner_cleanup'] = alynt_entry_http_private( $owner, 'close' ); }
}
echo json_encode( $result ); // Fixed small diagnostic, no request/cookie/nonce/auth output.
function alynt_entry_http_private_metadata( $owner, $stream ) {
    $r = new ReflectionMethod( $owner, 'input_metadata' ); if ( PHP_VERSION_ID < 80100 ) $r->setAccessible( true ); return $r->invoke( $owner, $stream );
}
