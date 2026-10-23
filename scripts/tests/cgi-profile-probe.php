<?php
/** Standalone owned PHP-CGI fixture. No WordPress, SQL or production includes. */
$marker = getenv( 'ALYNT_CGI_MARKER' );
$mode = getenv( 'ALYNT_CGI_CASE' );
if ( 'cgi-fcgi' !== PHP_SAPI || ! is_string( $marker ) || ! preg_match( '/\A[a-f0-9]{32}\z/D', $marker ) || basename( __DIR__ ) !== 'alynt-cgi-profile-' . $marker || realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) !== __FILE__ || ! in_array( $mode, array( 'no_fault', 'refusal', 'native_timeout', 'disconnect', 'stalled_input' ), true ) ) { http_response_code( 404 ); exit; }
header( 'Content-Type: application/json' ); header( 'Cache-Control: no-store' );
if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? null ) || '' !== ( $_SERVER['QUERY_STRING'] ?? null ) ) { http_response_code( 405 ); echo '{"refused":true,"body_read":false}'; exit; }
function alynt_cgi_stage( $name ) { if ( false === file_put_contents( __DIR__ . '/stage.json', json_encode( array( 'stage' => $name ) ) ) ) throw new RuntimeException( 'Owned stage unavailable.' ); }
register_shutdown_function( static function () { $error = error_get_last(); file_put_contents( __DIR__ . '/shutdown.json', json_encode( array( 'fatal' => null !== $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) ) ); } );
alynt_cgi_stage( 'entered' );
if ( 'native_timeout' === $mode ) { while ( true ) { usleep( 10000 ); } }
if ( 'disconnect' === $mode ) { ignore_user_abort( true ); usleep( 650000 ); alynt_cgi_stage( 'completed_after_disconnect' ); }
$stream = fopen( 'php://input', 'rb' ); $raw = ''; $reads = 0; $meta = stream_get_meta_data( $stream );
try {
    while ( $reads < 17 && strlen( $raw ) < 16385 ) { ++$reads; $chunk = fread( $stream, min( 1024, 16385 - strlen( $raw ) ) ); if ( ! is_string( $chunk ) ) throw new RuntimeException( 'Input failed.' ); $raw .= $chunk; if ( feof( $stream ) || '' === $chunk ) break; }
    $eof = feof( $stream );
} finally { $closed = fclose( $stream ); }
echo json_encode( array( 'fixture' => true, 'wordpress' => function_exists( 'wp_validate_auth_cookie' ), 'mysqli' => extension_loaded( 'mysqli' ), 'sapi' => PHP_SAPI, 'bytes' => strlen( $raw ), 'synthetic_sha256' => hash( 'sha256', $raw ), 'reads' => $reads, 'eof' => $eof, 'closed' => $closed, 'seekable' => $meta['seekable'] ?? null, 'execution_limit' => ini_get( 'max_execution_time' ), 'input_limit' => ini_get( 'max_input_time' ), 'post_limit' => ini_get( 'post_max_size' ), 'prepend' => ini_get( 'auto_prepend_file' ), 'scanned_ini' => php_ini_scanned_files() ) );
