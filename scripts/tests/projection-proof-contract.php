<?php
/** Pure, fixed test-only credential envelope. No I/O or production caller. */
function alynt_projection_context( $data ) {
    if ( ! is_array( $data ) || array( 'seed', 'marker', 'password', 'mode' ) !== array_keys( $data ) ) return false;
    foreach ( array( 'seed' => 12, 'marker' => 24, 'password' => 64 ) as $key => $length ) if ( ! is_string( $data[$key] ) || 1 !== preg_match( '/\A[a-f0-9]{' . $length . '}\z/D', $data[$key] ) ) return false;
    if ( ! in_array( $data['mode'], array( 'capability', 'unsafe', 'autoload', 'fallback' ), true ) ) return false;
    return array_merge( $data, array( 'user' => 'alynt_ro_' . $data['seed'] . '_i', 'prefix' => 'wp_alynt_ro_' . $data['seed'] . '_', 'base' => 'wp_alynt_ro_' . $data['seed'] . '_src_' ) );
}
function alynt_projection_check( $condition, $code ) {
    $GLOBALS['alynt_projection_receipt']['checks']++;
    if ( ! $condition ) throw new RuntimeException( $code );
}
function alynt_projection_canaries( $rows ) {
    $count = 0;
    foreach ( $rows as $name => $value ) if ( in_array( $name, array( 'alynt_drime_backups_settings', 'alynt_drime_backups_schedule_safety_state' ), true ) || ( is_string( $value ) && false !== strpos( $value, 'owned-projection-canary' ) ) ) $count++;
    return $count;
}
