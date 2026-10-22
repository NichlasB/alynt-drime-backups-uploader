<?php
/** Fixed cache-aware exact wait observer, not a timeout-as-proof shortcut. */
define( 'ALYNT_CACHE_CONTENTION_HELPER', 'monitor' );
require __DIR__ . '/cache-contention-native-helper.php';
