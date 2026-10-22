<?php
/** Fixed owned fixture safety-row blocker, never application storage. */
define( 'ALYNT_CACHE_CONTENTION_HELPER', 'blocker' );
require __DIR__ . '/cache-contention-native-helper.php';
