<?php
/** Separate v2 proof channel; reuse only the unchanged bounded byte I/O helpers. */
require __DIR__ . '/observation-private-channel.php';
function alynt_selected_root() {
    $boot = alynt_observation_message();
    alynt_core_assert( array( 'v', 'kind', 'seq', 'attempt', 'intent' ) === array_keys( $boot ) && 2 === $boot['v'] && 'boot' === $boot['kind'] && 1 === $boot['seq'] && 'selected_commit_response_loss' === $boot['intent'] && alynt_observation_token( $boot['attempt'] ), 'Selected boot refused.' );
    alynt_observation_message( array( 'v' => 2, 'kind' => 'root_request', 'seq' => 1, 'attempt' => $boot['attempt'] ) );
    $root = alynt_observation_message();
    alynt_core_assert( array( 'v', 'kind', 'seq', 'attempt', 'root' ) === array_keys( $root ) && 2 === $root['v'] && 'root' === $root['kind'] && 2 === $root['seq'] && $boot['attempt'] === $root['attempt'] && alynt_observation_token( $root['root'] ) && $root['root'] !== $root['attempt'], 'Selected root refused.' );
    $GLOBALS['alynt_observation_channel'] = array( 'attempt' => $root['attempt'], 'root' => $root['root'], 'pair' => null, 'writer' => null, 'pin' => null );
}
function alynt_selected_bind( $o, $roles, $auth, $capture ) {
    $s =& $GLOBALS['alynt_observation_channel']; $pin = alynt_join_pin( $o ); $tick = hrtime( true ); $start = alynt_core_property( $o, 'started' );
    alynt_core_assert( null === $s['pair'] && is_array( $pin[2] ) && $pin[2]['started_ns'] === $start && $tick >= $start && $tick - $start <= 4000000000 && 'idle' === alynt_core_property( $o, 'phase' ) && 20 === alynt_core_property( $o, 'protected_reads' ) && 0 === alynt_core_property( $o, 'writes' ) && $GLOBALS['wpdb']->dbh === $auth, 'Selected original root/pin refused.' );
    $closed = false; try { $unused = $capture->thread_id; } catch ( Error $e ) { $closed = true; }
    alynt_core_assert( $closed && 3 === count( array_unique( $roles ) ), 'Selected role lifetime refused.' );
    $begin = hrtime( true );
    alynt_observation_message( array( 'v' => 2, 'kind' => 'bind', 'seq' => 2, 'attempt' => $s['attempt'], 'root' => $s['root'], 'auth' => $roles['auth'], 'capture' => $roles['capture'], 'writer' => $roles['writer'], 'remaining_ns' => (string) ( 10000000000 - ( $tick - $start ) ) ) );
    $bound = alynt_observation_message(); $now = hrtime( true );
    alynt_core_assert( array( 'v', 'kind', 'seq', 'attempt', 'root', 'writer', 'pair' ) === array_keys( $bound ) && 2 === $bound['v'] && 'bound' === $bound['kind'] && 3 === $bound['seq'] && $s['attempt'] === $bound['attempt'] && $s['root'] === $bound['root'] && $roles['writer'] === $bound['writer'] && alynt_observation_token( $bound['pair'] ) && ! in_array( $bound['pair'], array( $s['attempt'], $s['root'] ), true ) && $now >= $begin && $now - $begin <= 1000000000 && $now >= $start && $now - $start <= 4000000000, 'Selected bound refused.' );
    $s['pair'] = $bound['pair']; $s['writer'] = $roles['writer']; $s['pin'] = $pin;
}
function alynt_selected_final( $guards ) {
    $s = $GLOBALS['alynt_observation_channel']; $p = $GLOBALS['alynt_core_proof'];
    alynt_core_assert( 8 === $guards && alynt_observation_token( $s['pair'] ), 'Selected final lacks actual guards.' );
    alynt_observation_message( array( 'v' => 2, 'kind' => 'final', 'seq' => 3, 'attempt' => $s['attempt'], 'root' => $s['root'], 'pair' => $s['pair'], 'case' => 'selected_commit_response_loss', 'checks' => $p['checks'], 'groups' => count( $p['cases'] ), 'returned_false' => true, 'pre_guards' => $guards, 'post_guards' => 0, 'root_erased' => true ) );
    unset( $GLOBALS['alynt_observation_channel'] );
    alynt_core_check( '' === fread( STDIN, 1 ) && feof( STDIN ), 'Selected final requires EOF.' );
}
