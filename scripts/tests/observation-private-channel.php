<?php
/** Owned proof-only inherited streams. No endpoint, SQL or runtime callback added. */
if ( 'cli' !== PHP_SAPI || ! defined( 'ABSPATH' ) || ! isset( $GLOBALS['alynt_core_proof'] ) ) exit( 1 );
require_once __DIR__ . '/owned-stdio-io.php';

function alynt_observation_message( $value = null ) {
    static $bytes = 0; static $lines = 0; $calls = 0;
    if ( null === $value ) {
        $line = alynt_stdio_probe_read( $calls );
        alynt_core_assert( 1 === preg_match( '/^[\x20-\x5b\x5d-\x7e]+$/D', $line ), 'Private channel byte profile refused.' );
        $value = json_decode( $line, true );
        alynt_core_assert( is_array( $value ) && json_encode( $value ) === $line, 'Private channel canonical form refused.' );
    } else { $line = json_encode( $value ); }
    $bytes += strlen( $line ) + 1; ++$lines;
    alynt_core_assert( strlen( $line ) < 4096 && $bytes <= 32768 && $lines <= 8, 'Private channel envelope exceeded.' );
    if ( 0 === $calls ) alynt_stdio_probe_write( STDOUT, $line . "\n", $calls );
    return $value;
}
function alynt_observation_token( $value ) { return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $value ); }
function alynt_observation_root() {
    $boot = alynt_observation_message();
    alynt_core_assert( array( 'v', 'kind', 'seq', 'attempt', 'intent' ) === array_keys( $boot ) && 1 === $boot['v'] && 'boot' === $boot['kind'] && 1 === $boot['seq'] && 'observe_only' === $boot['intent'] && alynt_observation_token( $boot['attempt'] ), 'Private boot refused.' );
    alynt_observation_message( array( 'v' => 1, 'kind' => 'root_request', 'seq' => 1, 'attempt' => $boot['attempt'] ) );
    $root = alynt_observation_message();
    alynt_core_assert( array( 'v', 'kind', 'seq', 'attempt', 'root' ) === array_keys( $root ) && 1 === $root['v'] && 'root' === $root['kind'] && 2 === $root['seq'] && $boot['attempt'] === $root['attempt'] && alynt_observation_token( $root['root'] ) && $root['root'] !== $root['attempt'], 'Private root refused.' );
    $GLOBALS['alynt_observation_channel'] = array( 'attempt' => $root['attempt'], 'root' => $root['root'], 'pair' => null, 'writer' => null, 'pin' => null );
}
function alynt_observation_bind( $o, $roles, $auth, $capture ) {
    $s =& $GLOBALS['alynt_observation_channel']; $pin = alynt_join_pin( $o ); $tick = hrtime( true ); $start = alynt_core_property( $o, 'started' );
    alynt_core_assert( null === $s['pair'] && is_array( $pin[2] ) && $pin[2]['started_ns'] === $start && $tick >= $start && $tick - $start <= 4000000000 &&
        'idle' === alynt_core_property( $o, 'phase' ) && 20 === alynt_core_property( $o, 'protected_reads' ) && 0 === alynt_core_property( $o, 'writes' ) && $GLOBALS['wpdb']->dbh === $auth, 'Original idle root binding refused.' );
    $closed = false; try { $unused = $capture->thread_id; } catch ( Error $error ) { $closed = true; }
    alynt_core_assert( $closed && 3 === count( array_unique( $roles ) ), 'Capture lifetime or distinct roles refused.' );
    $begin = hrtime( true );
    alynt_observation_message( array( 'v' => 1, 'kind' => 'bind', 'seq' => 2, 'attempt' => $s['attempt'], 'root' => $s['root'], 'auth' => $roles['auth'], 'capture' => $roles['capture'], 'writer' => $roles['writer'], 'remaining_ns' => (string) ( 10000000000 - ( $tick - $start ) ) ) );
    $bound = alynt_observation_message(); $now = hrtime( true );
    alynt_core_assert( array( 'v', 'kind', 'seq', 'attempt', 'root', 'writer', 'pair' ) === array_keys( $bound ) && 1 === $bound['v'] && 'bound' === $bound['kind'] && 3 === $bound['seq'] && $s['attempt'] === $bound['attempt'] && $s['root'] === $bound['root'] && $roles['writer'] === $bound['writer'] &&
        alynt_observation_token( $bound['pair'] ) && ! in_array( $bound['pair'], array( $s['attempt'], $s['root'] ), true ) && $now >= $begin && $now - $begin <= 1000000000 && $now >= $start && $now - $start <= 4000000000, 'Private bound acknowledgement refused.' );
    $s['pair'] = $bound['pair']; $s['writer'] = $roles['writer']; $s['pin'] = $pin;
}
function alynt_observation_recheck( $o ) {
    $s = $GLOBALS['alynt_observation_channel']; $now = hrtime( true ); $start = alynt_core_property( $o, 'started' );
    alynt_core_assert( alynt_observation_token( $s['pair'] ) && $s['pin'] === alynt_join_pin( $o ) && $s['writer'] === alynt_core_property( $o, 'reader' )->thread_id && $s['pin'][2]['started_ns'] === $start && $now >= $start && $now - $start <= 4000000000 &&
        'idle' === alynt_core_property( $o, 'phase' ) && 20 === alynt_core_property( $o, 'protected_reads' ) && 0 === alynt_core_property( $o, 'writes' ), 'Guard eight changed original root/pair.' );
}
function alynt_observation_final() {
    $s = $GLOBALS['alynt_observation_channel']; $proof = $GLOBALS['alynt_core_proof'];
    alynt_core_assert( alynt_observation_token( $s['pair'] ) && $proof['checks'] >= 1 && $proof['checks'] <= 8192 && count( $proof['cases'] ) >= 1 && count( $proof['cases'] ) <= 32, 'Private final envelope refused.' );
    alynt_observation_message( array( 'v' => 1, 'kind' => 'final', 'seq' => 3, 'attempt' => $s['attempt'], 'root' => $s['root'], 'pair' => $s['pair'], 'passed' => true, 'checks' => $proof['checks'], 'groups' => count( $proof['cases'] ) ) );
    unset( $GLOBALS['alynt_observation_channel'] );
    alynt_core_check( '' === fread( STDIN, 1 ) && feof( STDIN ), 'Private final requires clean EOF.' );
}
