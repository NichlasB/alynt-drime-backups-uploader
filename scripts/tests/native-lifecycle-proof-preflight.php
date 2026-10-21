<?php
/** Socket-free source verification. Safe for CI; never starts the native proof. */
if ( 'cli' !== PHP_SAPI || 1 !== count( $argv ) ) exit( 1 );
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require __DIR__ . '/native-lifecycle-proof-source.php';
require __DIR__ . '/native-lifecycle-proof-manifest.php';
try {
    if ( ! Alynt_Test_Native_Lifecycle_Manifest::verify() || ! Alynt_Test_Native_Lifecycle_Manifest::composition() ) throw new LogicException( 'Source identity unavailable.' );
    $source = Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle::create();
    $proof = Alynt_Test_Native_Lifecycle_Owner::create();
    foreach ( array( array( $source, false ), array( $proof, true ) ) as $case ) {
        $m = new ReflectionMethod( $case[0], 'native_entry_available' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true );
        if ( ! $m->isPrivate() || $case[1] !== $m->invoke( $case[0] ) ) throw new LogicException( 'Entry distinction changed.' );
        $public = array_map( function ( $method ) { return $method->name; }, ( new ReflectionClass( $case[0] ) )->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $public );
        if ( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ) !== $public ) throw new LogicException( 'Public operation introduced.' );
    }
    if ( function_exists( 'wp_verify_nonce' ) || class_exists( 'wpdb', false ) ) throw new LogicException( 'Preflight bootstrapped WordPress.' );
    echo json_encode( array( 'passed' => true, 'composition_equal_except_fixed_entry' => true, 'final_entry_unavailable' => true, 'wordpress_or_storage_executed' => false ) ) . "\n";
} catch ( Throwable $error ) { echo json_encode( array( 'passed' => false, 'failure' => get_class( $error ) . ':' . basename( $error->getFile() ) . ':' . $error->getLine() ) ) . "\n"; exit( 1 ); }
