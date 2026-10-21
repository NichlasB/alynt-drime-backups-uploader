<?php
/** Declaration-only gate before any fixture/WordPress/native execution. */
if ( 'cli' !== PHP_SAPI || 1 !== count( $argv ) || defined( 'ABSPATH' ) || defined( 'DB_NAME' ) || function_exists( 'wp_verify_nonce' ) || class_exists( 'wpdb', false ) ) exit( 1 );
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require dirname( __DIR__, 2 ) . '/tests/Support/QualificationNativeManifest.php';
require dirname( __DIR__, 2 ) . '/tests/Support/QualificationNativeWorker.php';
require __DIR__ . '/qualification-native-channel.php';
$passed = Alynt_Test_Qualification_Native_Manifest::verify() && ! function_exists( 'wp_verify_nonce' ) && ! class_exists( 'wpdb', false ) && ! defined( 'DB_NAME' );
echo json_encode( array( 'passed' => $passed, 'wordpress_bootstrap' => false, 'source' => Alynt_Test_Qualification_Native_Manifest::identities() ) ) . "\n";
exit( $passed ? 0 : 1 );
