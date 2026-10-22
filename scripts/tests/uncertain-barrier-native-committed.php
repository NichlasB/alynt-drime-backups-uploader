<?php
/** Fixed CLI case; no operator-supplied fault mode or production entry. */
if ( 'cli' !== PHP_SAPI || 6 !== count( $argv ) ) exit( 1 );
define( 'ALYNT_UNCERTAIN_NATIVE_PROFILE', 'no_fault' );
require __DIR__ . '/uncertain-barrier-native-worker.php';
