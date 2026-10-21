<?php
/** Socket-free declaration gate. Never invokes a root or boots WordPress. */
if ( 'cli' !== PHP_SAPI || 1 !== count( $argv ) ) exit( 1 );
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require __DIR__ . '/native-lifecycle-proof-source.php';
require dirname( __DIR__, 2 ) . '/tests/Support/UncertainBarrierNoFaultManifest.php';
try {
    if ( ! Alynt_Test_Uncertain_Barrier_No_Fault_Manifest::composition() ) throw new LogicException( 'native_qualification_identity' );
    $c = new ReflectionClass( Alynt_Test_Uncertain_Barrier_No_Fault_Owner::class ); $public = array_map( function ( $m ) { return $m->getName(); }, $c->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $public );
    if ( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ) !== $public || function_exists( 'wp_verify_nonce' ) || class_exists( 'wpdb', false ) ) throw new LogicException( 'native_qualification_public_surface' );
    echo json_encode( array( 'passed' => true, 'source' => Alynt_Test_Uncertain_Barrier_No_Fault_Manifest::identities(), 'wordpress_bootstrap' => false, 'final_entry_unavailable' => true, 'suppression_supported' => false ) ) . "\n";
} catch ( Throwable $error ) { echo json_encode( array( 'passed' => false, 'failure' => 'native_qualification_preflight', 'location' => array( basename( $error->getFile() ), $error->getLine() ) ) ) . "\n"; exit( 1 ); }
