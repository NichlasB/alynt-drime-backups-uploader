<?php
/** Fixed idle session. No transaction, lock or contention. */
define( 'ALYNT_CACHE_HELPER', 'idle' );
require __DIR__ . '/cache-witness-native-helper.php';
