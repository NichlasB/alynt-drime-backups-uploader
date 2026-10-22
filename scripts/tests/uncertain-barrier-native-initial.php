<?php
/** Pure initial-fixture contract. No WordPress, storage, native calls or handles. */
function alynt_uncertain_initial_rows( $marker ) {
    if ( ! is_string( $marker ) || 1 !== preg_match( '/^[a-f0-9]{24}$/D', $marker ) ) return false;
    return array( 'probe_owner' => $marker, 'siteurl' => 'http://plugin-tester.local', 'home' => 'http://plugin-tester.local', 'active_plugins' => 'a:0:{}', 'template' => 'alynt-core-proof-no-theme', 'stylesheet' => 'alynt-core-proof-no-theme', 'blogname' => 'Disposable native qualification no-fault proof', 'WPLANG' => '' );
}
function alynt_uncertain_initial_shape( $rows, $marker ) {
    $expected = alynt_uncertain_initial_rows( $marker );
    if ( ! is_array( $rows ) || false === $expected || 8 !== count( $rows ) ) return false;
    $n = 0;
    foreach ( $expected as $name => $value ) {
        if ( ( $rows[$n] ?? null ) !== array( (string) ( $n + 1 ), $name, $value, 'no' ) ) return false;
        ++$n;
    }
    return true;
}
