<?php
/** Actual root+native source bodies; all frame/auth/session/row/transport evidence simulated. */
function alynt_failure_case( $frame, $case ) {
    $history = array( 'commit' => 'committed', 'throw-begin' => 'unsupported', 'false-begin' => 'unsupported', 'begin-idle' => 'unsupported', 'throw-after-begin-ack' => 'unsupported', 'throw-prepare' => 'not_written', 'false-prepare' => 'not_written', 'throw-bind' => 'not_written', 'false-bind' => 'not_written', 'throw-execute' => 'rolled_back', 'false-execute' => 'rolled_back', 'throw-after-dml-ack' => 'rolled_back', 'throw-statement_close' => 'rolled_back', 'no-terminal-after-execute' => 'uncertain', 'throw-commit' => 'uncertain', 'false-commit' => 'uncertain', 'commit-idle' => 'uncertain', 'throw-after-commit-ack' => 'uncertain', 'throw-rollback' => 'unsupported', 'false-rollback' => 'unsupported', 'rollback-idle' => 'unsupported', 'throw-after-rollback-ack' => 'unsupported', 'throw-close' => 'committed', 'false-close' => 'committed' );
    $early = in_array( $case, array( 'throw-setup', 'throw-capture', 'throw-metadata', 'throw-auth', 'throw-construct', 'throw-final', 'malformed', 'wrong-domain', 'denied', 'get', 'stale' ), true );
    $fault = 0 === strpos( $case, 'fault-' );
    alynt_failure_check( isset( $history[$case] ) || $early || $fault || 'throw-qualify' === $case || 'standalone' === $case, 'known case' );
    $o = new Alynt_Failure_Api_Harness( $frame, $case );
    $small = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( 1, 'register_closed' );
    $GLOBALS['alynt_core_request_test']['nonce_actions'] = array( 'unit-nonce' => $small );
    $form = array( 'purpose' => 'register_closed', 'confirm' => 'register', 'nonce' => 'unit-nonce' );
    if ( 'malformed' === $case ) $form = array();
    if ( 'wrong-domain' === $case ) $GLOBALS['alynt_core_request_test']['nonce_actions']['unit-nonce'] = 'wrong-domain';
    if ( 'denied' === $case ) $GLOBALS['alynt_core_request_test']['filter_allows'] = false;
    if ( 'stale' === $case ) $GLOBALS['alynt_core_request_test']['nonce_result'] = false;
    if ( 'get' === $case ) $_SERVER['REQUEST_METHOD'] = 'GET';
    if ( in_array( $case, array( 'throw-auth', 'throw-construct' ), true ) ) $GLOBALS['alynt_core_request_test']['on']['throw-auth' === $case ? 'validate_cookie' : 'create_nonce'] = function () use ( $case ) { alynt_failure_inject( substr( $case, 6 ) ); };
    $guards = array(); $on = null; $on = function () use ( &$on, &$guards, $case, $o ) {
        $calls = array_values( array_filter( $GLOBALS['alynt_core_request_test']['calls'], function ( $c ) { return 'verify_nonce' === $c[0]; } ) );
        $guards[] = end( $calls )[1][1]; $GLOBALS['alynt_core_request_test']['on']['verify_nonce'] = $on;
        if ( count( $guards ) >= ( 'standalone' === $case ? 8 : 9 ) ) alynt_failure_check( null === $o->state( 'reader' ) && true === $o->state( 'native_outcome_closed' ), 'release before final auth callback' );
        if ( 'throw-final' === $case && 9 === count( $guards ) ) alynt_failure_inject( 'final' );
    };
    $GLOBALS['alynt_core_request_test']['on']['verify_nonce'] = $on;
    $point = substr( $case, strpos( $case, '-' ) + 1 );
    if ( 0 === strpos( $case, 'false-' ) ) $o->db->returns[$point] = false;
    if ( in_array( $case, array( 'throw-rollback', 'false-rollback', 'rollback-idle', 'throw-after-rollback-ack', 'no-terminal-after-execute' ), true ) ) $o->db->returns['execute'] = false;
    $o->db->inspect = function ( $at ) use ( $o, $case, $point ) {
        $c = $o->state( 'native_outcome' ); $e = $c['facts']['events'];
        if ( in_array( $at, array( 'begin', 'execute', 'commit', 'rollback', 'close' ), true ) ) {
            $k = 'execute' === $at ? 'dml' : $at; $marker = 'close' === $at ? 'detached' : $k . '_sent';
            alynt_failure_check( $e[$marker] > 0 && 'unknown' === $c['raw_returns'][$k] && 'unknown' === $c['facts']['acks'][$k], 'send marker and unknown before API' );
        }
        if ( 'prepare' === $at ) alynt_failure_check( 1 === $o->state( 'writes' ) && $e['attempt_reserved'] > 0 && 0 === $e['dml_sent'], 'reserve before prepare, no invented DML' );
        if ( 'commit' === $at ) alynt_failure_check( $e['readback'] > 0 && 20 === $o->rows, 'all-ten readback before commit' );
        if ( 'close' === $at ) alynt_failure_check( null === $o->state( 'reader' ) && null === $o->state( 'native_outcome_writer' ), 'detach exact owned writer before close' );
        if ( $case === 'throw-' . $at || ( 0 === strpos( $case, 'fault-' ) && 'commit' === $at ) ) alynt_failure_inject( $at );
    };
    $roles = $GLOBALS['wp_roles']; $handler = static function () { throw new LogicException( 'Original test handler.' ); };
    set_error_handler( $handler );
    try {
        $ok = $o->run( $form, 'standalone' !== $case, 'get' === $case ? 'GET' : 'POST' );
        $restored = set_error_handler( $handler ); restore_error_handler();
        alynt_failure_check( $handler === $restored, 'original error handler restored' );
    } finally { restore_error_handler(); }
    alynt_failure_check( $roles === $GLOBALS['wp_roles'], 'original role view restored' );
    $GLOBALS['failure_context'] = array( 'phase' => $o->state( 'phase' ), 'guards' => count( $guards ), 'qualifications' => $o->qualified, 'api_calls' => $o->db->calls );
    $positive = in_array( $case, array( 'commit', 'standalone' ), true );
    alynt_failure_check( $positive === $ok, 'positive/refusal contract' );
    $wanted = 'standalone' === $case || $early ? 0 : 1;
    alynt_failure_check( $wanted === $o->qualified, 'exact composed failure qualification count' );
    if ( ! $early && 'throw-qualify' !== $case && 'standalone' !== $case ) {
        $s = $o->samples[0] ?? null;
        alynt_failure_check( is_array( $s ), 'actual composed qualification observed' );
        if ( is_array( $s ) ) {
            $r = $s['result']; alynt_failure_check( false === $o->replay, 'qualification once only' );
            if ( $fault ) alynt_failure_check( false === $r, 'faulted origin/clock/seal qualification refused' );
            else {
                alynt_failure_check( is_array( $r ) && $history[$case] === $r['historical_outcome'], 'exact historical outcome, not blanket unavailable' );
                if ( is_array( $r ) ) {
                    alynt_failure_check( ( 'commit' === $case ) === $r['current_acceptance_claimed'], 'failure current acceptance never invented' );
                    foreach ( array( 'diagnostic_candidate_only', 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $k ) alynt_failure_check( false === $r[$k], 'closed authority flag: ' . $k );
                }
            }
            if ( ! $positive ) foreach ( array( 'auth', 'intent', 'physical' ) as $k ) alynt_failure_check( null === $s['guards'][$k], 'failure guard remains unperformed' );
            alynt_failure_check( 0 === $s['capture']['facts']['events']['accepted_at'] && array_fill_keys( array( 'auth', 'intent', 'physical', 'unreentered' ), null ) === $s['capture']['facts']['guards'], 'sealed native history has no current certificate' );
            if ( in_array( $case, array( 'throw-begin', 'false-begin', 'throw-commit', 'false-commit', 'throw-close', 'false-close', 'throw-rollback', 'false-rollback' ), true ) ) {
                $raw = 0 === strpos( $case, 'false-' ) ? false : 'unknown';
                alynt_failure_check( $raw === $s['capture']['raw_returns'][$point] && 'unknown' === $s['capture']['facts']['acks'][$point], 'false/throw preserve distinct unknown ack' );
            }
        }
    }
    $final_guards = in_array( $case, array( 'commit', 'throw-qualify' ), true );
    if ( ! $early ) alynt_failure_check( ( 'standalone' === $case ? 9 : ( $final_guards ? 10 : 8 ) ) === count( $guards ), 'exact independent nonce guard order/count' );
    if ( ! $early ) {
        $created = array_values( array_filter( $GLOBALS['alynt_core_request_test']['calls'], function ( $c ) { return 'create_nonce' === $c[0]; } ) );
        alynt_failure_check( 1 === count( $created ), 'one full command nonce only' ); $full = $created[0][1][0] ?? null;
        $expected = 'standalone' === $case ? array( $small, $small, $small, $small, $small, $small, $full, $small, $full ) : array( $small, $small, $small, $small, $small, $full, $small, $full );
        if ( $final_guards ) $expected = array_merge( $expected, array( $small, $full ) );
        alynt_failure_check( $expected === $guards && $small !== $full, 'separate nonce domains and exact sequence' );
    }
    if ( 0 === strpos( $case, 'throw-' ) ) alynt_failure_check( array( $point ) === $GLOBALS['injections'], 'exact expected fault reached, not assertion/bootstrap error' );
    foreach ( array( 'reader', 'physical', 'read_physical', 'native_outcome_writer', 'native_outcome', 'admission_origin', 'admission_clock', 'before', 'proposal', 'after', 'context', 'scope' ) as $k ) alynt_failure_check( null === $o->state( $k ), 'root erase: ' . $k );
    alynt_failure_check( array() === $o->state( 'admission_ids' ) && true === $o->state( 'admission_qualification_spent' ) && true === $o->state( 'consumed' ) && false === $o->state( 'admission_join_busy' ), 'consumed root and complete origin erase' );
    alynt_failure_check( array() === $GLOBALS['owned'] && array() === $o->core['db']->dbh->calls, 'filter cleanup and borrowed auth untouched' );
    $calls = $o->db->calls; alynt_failure_check( 1 >= count( array_keys( $calls, 'execute', true ) ) && 1 >= count( array_keys( $calls, 'close', true ) ) && 1 >= count( array_keys( $calls, 'rollback', true ) ), 'single execute/cleanup ceilings' );
    if ( in_array( $case, array( 'malformed', 'wrong-domain', 'denied', 'get', 'stale', 'throw-auth', 'throw-setup' ), true ) ) alynt_failure_check( 0 === $o->state( 'protected_reads' ) && array() === $calls, 'unauthorized zero protected IO' );
    alynt_failure_check( false === $o->run( $form ) && $calls === $o->db->calls, 'root replay without new IO' );
    $bad = array_filter( $GLOBALS['alynt_core_request_test']['calls'], function ( $c ) { return in_array( $c[0], array( 'protected_io', 'query' ), true ); } );
    alynt_failure_check( array() === $bad, 'no WordPress storage API attempted' );
    alynt_failure_check( null === $o->state( 'recorded_at' ) && false === $o->state( 'active' ) && true === $o->state( 'attempted' ), 'all request preparation and active state consumed' );
    alynt_failure_check( true === $o->state( 'native_outcome_closed' ) && true === $o->state( 'native_outcome_started' ) && 0 === $o->state( 'native_outcome_tick' ), 'capture consumed and tick erased' );
    alynt_failure_check( $o->state( 'protected_reads' ) <= 40 && $o->state( 'writes' ) <= 1, 'protected API budgets' );
    $native_class = Alynt_Drime_Backups_Uploader_Registration_Native_Admission::class; $reflection = new ReflectionClass( $native_class );
    alynt_failure_check( $reflection->isFinal() && $reflection->getConstructor()->isPrivate(), 'unchanged final private native construction' );
    alynt_failure_check( $reflection->getMethod( 'admit_retained' )->isPrivate() && $reflection->getMethod( 'admission_transaction' )->isPrivate(), 'no public native operation port' );
    $names = array_map( function ( $m ) { return $m->name; }, $reflection->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $names );
    alynt_failure_check( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ) === $names && false === $native_class::create( 'provider' ), 'no provider or result export surface' );
    foreach ( array( 'admission_root', 'admission_transaction', 'admission_write', 'finish', 'qualify_source' ) as $method ) {
        $native = 'qualify_source' === $method ? 'qualify_admission_outcome' : $method;
        $a = new ReflectionMethod( $o, $method ); $b = new ReflectionMethod( Alynt_Drime_Backups_Uploader_Registration_Native_Admission::class, $native );
        alynt_failure_check( $a->getFileName() === $b->getFileName() && $a->getStartLine() === $b->getStartLine() && $a->getEndLine() === $b->getEndLine(), 'same production source body: ' . $method );
    }
}
