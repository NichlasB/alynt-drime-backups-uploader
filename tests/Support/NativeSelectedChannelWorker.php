<?php
/** Synthetic channel only: no owner/root invocation, WordPress, native extension or networking. */
if ( realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) !== realpath( __FILE__ ) ) return;
if ( 'cli' !== PHP_SAPI || 2 !== count( $argv ) || extension_loaded( 'mysqli' ) || strlen( $argv[1] ) > 8192 ) exit( 1 );
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require dirname( __DIR__, 2 ) . '/scripts/tests/native-lifecycle-proof-source.php';
require dirname( __DIR__, 2 ) . '/tests/Support/NativeQualificationSelectedManifest.php';
require dirname( __DIR__, 2 ) . '/scripts/tests/native-qualification-selected-channel.php';
try {
    $raw = base64_decode( $argv[1], true ); $facts = is_string( $raw ) && strlen( $raw ) <= 4096 ? json_decode( $raw, true, 8 ) : null;
    if ( ! is_array( $facts ) ) exit( 1 );
    $channel = new Alynt_Test_Native_Qualification_Selected_Channel(); $channel->root();
    $channel->writer( array( 'auth' => 101, 'capture' => 102, 'writer' => 103 ), '6000000000' );
    $channel->barrier( array( 'auth' => 101, 'capture' => 102, 'writer' => 103, 'barrier' => 104 ), '19000000000', '9000000000' );
    $channel->finish( $facts );
} catch ( Throwable $error ) { exit( 1 ); }
