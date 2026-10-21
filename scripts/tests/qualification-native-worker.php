<?php
/** Dedicated no-fault full-core proof entry. No fault selector or production require. */
if ( 'cli' !== PHP_SAPI || 6 !== count( $argv ) ) exit( 1 );
$root = realpath( $argv[1] );
if ( $root !== realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' ) || '10004' !== $argv[2] || ! ctype_digit( $argv[5] ) || (int) $argv[5] < 1 || (int) $argv[5] > 65535 || $argv[2] === $argv[5] || ! preg_match( '/^wp_alynt_core_probe_[a-f0-9]{12}_$/D', $argv[3] ) || ! preg_match( '/^[a-f0-9]{24}$/D', $argv[4] ) ) exit( 1 );
try {
    $qualification_bootstrap_port = (int) $argv[5]; require __DIR__ . '/qualification-native-bootstrap.php';
    // Full independent guard prelude already passed in a different owned process.
    // This fresh process retains the same genuine root and its own early denials.
    require __DIR__ . '/native-observer-proof-fixtures.php'; require __DIR__ . '/registration-join-proof-fixtures.php';
    require dirname( __DIR__, 2 ) . '/tests/Support/QualificationNativeWorker.php'; require __DIR__ . '/qualification-native-cases.php';
    alynt_qualification_cases();
    alynt_core_check( 0 === $GLOBALS['alynt_core_proof']['protected_reads'] && 0 === $GLOBALS['alynt_core_proof']['denied_io'] && array() === headers_list(), 'qualification_escape' );
    foreach ( get_included_files() as $file ) alynt_core_check( 0 !== strpos( str_replace( '\\', '/', $file ), str_replace( '\\', '/', WP_CONTENT_DIR ) . '/' ), 'qualification_site_content' );
    $_COOKIE = array(); unset( $GLOBALS['current_user'] ); exit( 0 );
} catch ( Throwable $error ) {
    echo json_encode( Alynt_Test_Qualification_Native_Diagnostic::failure( $error ) ) . "\n";
    exit( 1 );
}
