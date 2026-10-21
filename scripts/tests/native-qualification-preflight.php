<?php
/** Socket-free definitions/source check, not a native/request/IPC worker. */
if ( 'cli' !== PHP_SAPI || 1 !== count( $argv ) ) exit( 1 );
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require __DIR__ . '/native-lifecycle-proof-source.php';
require dirname( __DIR__, 2 ) . '/tests/Support/NativeQualificationManifest.php';
try {
    if ( ! Alynt_Test_Native_Qualification_Manifest::verify() || ! Alynt_Test_Native_Qualification_Manifest::composition() ) throw new LogicException( 'Fixed observation identity refused.' );
    $c = new ReflectionClass( Alynt_Test_Native_Qualification_Owner::class );
    $public = array_map( function ( $m ) { return $m->name; }, $c->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $public );
    if ( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ) !== $public ) throw new LogicException( 'Public operation introduced.' );
    if ( function_exists( 'wp_verify_nonce' ) || class_exists( 'wpdb', false ) ) throw new LogicException( 'WordPress bootstrap forbidden.' );
    echo json_encode( array( 'passed' => true, 'two_passive_aliases_only' => true, 'final_entry_unavailable' => true, 'wordpress_or_storage_executed' => false ) ) . "\n";
} catch ( Throwable $error ) { echo json_encode( array( 'passed' => false, 'error' => get_class( $error ), 'line' => $error->getLine() ) ) . "\n"; exit( 1 ); }
