<?php
/** Deferred assertions survive caught runtime exceptions; synthetic evidence only. */
function alynt_native_case( $frame, $case, $class = 'Alynt_Test_Native_Lifecycle_Api' ) {
    $o = new $class( $frame, $case );
    $small = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( 1, 'register_closed' );
    $GLOBALS['alynt_core_request_test']['nonce_actions'] = array( 'unit-nonce' => $small );
    $GLOBALS['alynt_core_request_test']['calls'] = array(); $GLOBALS['alynt_core_request_test']['on'] = array();
    $on = null; $on = function () use ( &$on, $o, $case ) {
        $calls = array_values( array_filter( $GLOBALS['alynt_core_request_test']['calls'], function ( $c ) { return 'verify_nonce' === $c[0]; } ) );
        $o->guards[] = end( $calls )[1][1]; $n = count( $o->guards );
        $GLOBALS['alynt_core_request_test']['on']['verify_nonce'] = $on;
        if ( $case === 'guard-' . $n ) $GLOBALS['alynt_core_request_test']['nonce_result'] = false;
        if ( 1 === $n ) alynt_native_check( array() === $o->dbs && 0 === $o->state( 'protected_reads' ), 'auth before protected capture/factory' );
        if ( 9 === $n || 10 === $n ) alynt_native_check( isset( $o->dbs['admission'] ) && ! in_array( 'begin', $o->dbs['admission']->calls, true ), 'both mutation guards after writer setup before BEGIN' );
        if ( $n >= 11 ) {
            foreach ( array( 'before', 'proposal', 'after', 'context', 'native_outcome', 'admission_origin', 'admission_clock' ) as $k ) alynt_native_check( null === $o->state( $k ), 'writer erased before read callback: ' . $k );
            alynt_native_check( true === $o->state( 'admission_receipt' ) && 1 === count( array_keys( $o->dbs['admission']->calls, 'close', true ) ), 'confirmed writer release before read callbacks' );
        }
        if ( 21 === $n || 22 === $n ) alynt_native_check( isset( $o->dbs['barrier'] ) && ! in_array( 'begin', $o->dbs['barrier']->calls, true ), 'both read guards after barrier setup before BEGIN' );
        if ( $n >= 23 ) alynt_native_check( true === $o->state( 'barrier_receipt' ) && null === $o->state( 'reader' ) && $o->state( 'finished' ), 'barrier closed and DATA consumed before post-close guards' );
    };
    $GLOBALS['alynt_core_request_test']['on']['verify_nonce'] = $on;
    $hit = array(); $o->on = function ( $event, $owner ) use ( $case, &$hit ) {
        $at = array( 'writer-actor' => 'metadata-admission', 'barrier-actor' => 'metadata-barrier', 'barrier-cookie' => 'metadata-barrier', 'barrier-auth' => 'metadata-barrier', 'barrier-root-age' => 'metadata-barrier', 'barrier-read-age' => 'metadata-barrier', 'capture-reentry' => 'row-capture', 'writer-reentry' => 'metadata-admission', 'barrier-reentry' => 'metadata-barrier' )[$case] ?? null;
        if ( $at !== $event || $hit ) return; $hit[] = $event;
        if ( false !== strpos( $case, '-actor' ) ) $GLOBALS['alynt_core_request_test']['actor']++;
        elseif ( 'barrier-cookie' === $case ) $_COOKIE[LOGGED_IN_COOKIE] .= '-changed';
        elseif ( 'barrier-auth' === $case ) $owner->core['db']->dbh = new mysqli();
        elseif ( 'barrier-root-age' === $case ) $owner->state( 'started', $owner->state( 'started' ) - 20000000001 );
        elseif ( 'barrier-read-age' === $case ) $owner->state( 'read_started', $owner->state( 'read_started' ) - 10000000001 );
        else $owner->run();
    };
    $positive = in_array( $case, array( 'commit', 'uncertain', 'uncertain_throw', 'absence' ), true );
    if ( 'final-reentry' === $case ) $GLOBALS['native_on_remove'] = function () use ( $o, &$hit ) { $hit[] = 'remove'; $o->run(); };
    if ( 'actor' === $case ) $GLOBALS['alynt_core_request_test']['actor'] = 9;
    if ( 'role' === $case ) $GLOBALS['alynt_core_request_test']['role_now'] = false;
    if ( 'stale' === $case ) $GLOBALS['alynt_core_request_test']['nonce_result'] = false;
    if ( 'get' === $case ) $_SERVER['REQUEST_METHOD'] = 'GET';
    $args = 'malformed' === $case ? array( 'POST', array() ) : array();
    $roles = $GLOBALS['wp_roles']; $handler = static function () { throw new LogicException( 'Original isolated handler.' ); };
    set_error_handler( $handler );
    try { $r = $o->run( ...$args ); $restored = set_error_handler( $handler ); restore_error_handler(); alynt_native_check( $handler === $restored, 'handler restored' ); }
    finally { restore_error_handler(); }
    $GLOBALS['native_context'] = array( 'case' => $case, 'guards' => count( $o->guards ), 'phase' => $o->state( 'phase' ), 'qualification' => $o->qualified, 'events' => $o->events );
    alynt_native_check( $positive ? is_array( $r ) : false === $r, 'non-vacuous positive/refusal contract' );
    if ( $positive && is_array( $r ) ) {
        alynt_native_check( 9 === count( $r ) && ( 'absence' === $case ? 'not_observed_at_barrier' : 'registered_closed' ) === $r['classification'], 'exact existing native-path classification' );
        alynt_native_check( true === $r['barrier_verified'] && false === $r['candidate_only'], 'native adapter path not candidate boolean promotion' );
        foreach ( array( 'authenticated', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $k ) alynt_native_check( false === $r[$k], 'non-authorizing result: ' . $k );
        alynt_native_check( 24 === count( $o->guards ) && 4 === count( array_unique( $o->guards ) ), 'exact twenty-four guards/four nonce domains' );
        $m = $o->guards[0]; $mf = $o->guards[5]; $s = $o->guards[11]; $full = $o->guards[13];
        alynt_native_check( array( $m,$m,$m,$m,$m,$mf,$m,$mf,$m,$mf,$m,$s,$s,$full,$s,$full,$s,$full,$s,$full,$s,$full,$s,$full ) === $o->guards, 'exact guard order' );
        alynt_native_check( 11 === $GLOBALS['native_uuid'], 'ten identities plus one independent lookup' );
        alynt_native_check( 40 === $o->state( 'protected_reads' ) && 10 === $o->state( 'barrier_reads' ) && 1 === $o->state( 'writes' ), 'fixed fifty-projection/one-write ceilings reached non-vacuously' );
        alynt_native_check( ( 'commit' === $case ? 'committed' : 'uncertain' ) === $o->qualified['historical_outcome'] && true === $o->qualified['client_release_claimed'] && false === $o->qualified['current_acceptance_claimed'], 'actual reused sealed qualifier, no false promotion' );
    }
    if ( 0 === strpos( $case, 'guard-' ) ) {
        $n = (int) substr( $case, 6 ); alynt_native_check( $n === count( $o->guards ), 'exact individually denied guard reached' );
        if ( $n <= 10 ) alynt_native_check( 0 === $o->state( 'writes' ), 'denied mutation guard before DML' );
        if ( $n <= 22 ) alynt_native_check( 0 === $o->state( 'barrier_reads' ), 'denied read guard before protected observation' );
    }
    if ( in_array( $case, array( 'entry', 'malformed', 'get', 'stale', 'actor', 'role' ), true ) ) alynt_native_check( array() === $o->dbs && 0 === $o->state( 'protected_reads' ) && 0 === $GLOBALS['native_uuid'], 'early refusal before protected IO/UUID' );
    if ( $hit || in_array( $case, array( 'writer-actor', 'barrier-actor', 'barrier-cookie', 'barrier-auth', 'barrier-root-age', 'barrier-read-age', 'capture-reentry', 'writer-reentry', 'barrier-reentry', 'final-reentry' ), true ) ) alynt_native_check( count( $hit ) > 0, 'exact callback fault reached' );
    foreach ( array( 'native_resource', 'native_handle', 'reader', 'native_outcome_writer', 'physical', 'read_physical', 'barrier_auth', 'barrier_auth_facts', 'origin', 'origin_data', 'scope', 'started', 'lifecycle_clock', 'read_started', 'before', 'proposal', 'context', 'after', 'recorded_at', 'admission_origin', 'admission_clock', 'native_outcome', 'staged', 'inflight' ) as $k ) alynt_native_check( null === $o->state( $k ), 'erased private slot: ' . $k );
    foreach ( array( 'used', 'admission_ids', 'fences' ) as $k ) alynt_native_check( array() === $o->state( $k ), 'cleared identities/fences: ' . $k );
    foreach ( array( 'root_attempted', 'attempted', 'claimed', 'finished', 'consumed', 'observation_attempted', 'admission_qualification_spent' ) as $k ) alynt_native_check( true === $o->state( $k ), 'consumed: ' . $k );
    foreach ( array( 'busy', 'active', 'admission_join_busy' ) as $k ) alynt_native_check( false === $o->state( $k ), 'inactive after terminal cleanup: ' . $k );
    alynt_native_check( $roles === $GLOBALS['wp_roles'] && array() === $GLOBALS['native_owned'], 'owned filters and role view restored' );
    foreach ( $o->dbs as $kind => $db ) { alynt_native_check( 1 === count( array_keys( $db->calls, 'close', true ) ), 'one actual close attempt: ' . $kind ); alynt_native_check( 1 >= count( array_keys( $db->calls, 'begin', true ) ) && 1 >= count( array_keys( $db->calls, 'commit', true ) ), 'no native resend: ' . $kind ); }
    $old = $o->events; alynt_native_check( false === $o->run() && $old === $o->events, 'root replay has no IO' );
    $bad = array_filter( $GLOBALS['alynt_core_request_test']['calls'], function ( $c ) { return in_array( $c[0], array( 'protected_io', 'query' ), true ); } ); alynt_native_check( array() === $bad, 'no actual WordPress options/SQL attempted' );
    unset( $GLOBALS['native_on_remove'] ); return $o;
}
