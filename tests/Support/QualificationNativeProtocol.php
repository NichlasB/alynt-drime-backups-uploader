<?php
/** Pure native-proof grammar, not source authentication or an execution API. */
final class Alynt_Test_Qualification_Native_Protocol {
    const DOMAIN = 'alynt.qualification-observation.native.v1';
    const PROFILE = 'qualification_no_fault';
    public static function token( $v ) { return is_string( $v ) && 64 === strlen( $v ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $v ); }
    public static function roles( $v ) {
        if ( ! is_array( $v ) || 3 !== count( $v ) ) return false;
        foreach ( $v as $n ) if ( ! is_int( $n ) || $n < 1 || $n > 4294967295 ) return false;
        return 3 === count( array_unique( $v ) );
    }
    private static function receipt( $r ) {
        if ( ! is_array( $r ) || array_keys( $r ) !== array( 'valid', 'entries', 'returns', 'kind', 'result', 'sealed', 'empty_slots', 'capture_unchanged', 'io_unchanged' ) || 1 !== $r['entries'] || 1 !== $r['returns'] || 'closed_array' !== $r['kind'] ) return false;
        foreach ( array( 'valid', 'sealed', 'empty_slots', 'capture_unchanged', 'io_unchanged' ) as $k ) if ( true !== $r[$k] ) return false;
        $v = $r['result'];
        if ( ! is_array( $v ) || array_keys( $v ) !== array( 'historical_outcome', 'client_release_claimed', 'current_acceptance_claimed', 'request_lifecycle_closed_claimed', 'diagnostic_candidate_only', 'candidate_only', 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) || 'committed' !== $v['historical_outcome'] ) return false;
        foreach ( array( 'client_release_claimed', 'current_acceptance_claimed', 'request_lifecycle_closed_claimed', 'candidate_only' ) as $k ) if ( true !== $v[$k] ) return false;
        foreach ( array( 'diagnostic_candidate_only', 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $k ) if ( false !== $v[$k] ) return false;
        return true; // Validate the actual no-fault projection, never classify or repair it.
    }
    public static function check( $m ) {
        if ( 1 !== func_num_args() || ! is_array( $m ) || ! isset( $m['kind'] ) ) return false;
        $schemas = array( 'boot' => array( 1, 'profile' ), 'root_request' => array( 1, 'source' ), 'root' => array( 2, 'root' ), 'bind' => array( 2, 'root', 'auth', 'capture', 'writer', 'remaining_ns' ), 'bound' => array( 3, 'root', 'writer', 'pair' ), 'final' => array( 3, 'root', 'pair', 'profile', 'source', 'root_returned', 'pre_guards', 'post_guards', 'protected_reads', 'writes', 'original_slots_empty', 'observation' ) );
        if ( ! is_string( $m['kind'] ) || ! isset( $schemas[$m['kind']] ) ) return false;
        $s = $schemas[$m['kind']]; $seq = array_shift( $s );
        if ( array_keys( $m ) !== array_merge( array( 'domain', 'v', 'kind', 'seq', 'attempt' ), $s ) || self::DOMAIN !== $m['domain'] || 1 !== $m['v'] || $seq !== $m['seq'] || ! self::token( $m['attempt'] ) ) return false;
        if ( array_key_exists( 'root', $m ) && ( ! self::token( $m['root'] ) || $m['attempt'] === $m['root'] ) ) return false;
        if ( array_key_exists( 'pair', $m ) && ( ! self::token( $m['pair'] ) || in_array( $m['pair'], array( $m['attempt'], $m['root'] ), true ) ) ) return false;
        if ( array_key_exists( 'profile', $m ) && self::PROFILE !== $m['profile'] ) return false;
        if ( array_key_exists( 'source', $m ) && ( ! is_array( $m['source'] ) || $m['source'] !== Alynt_Test_Qualification_Native_Manifest::identities() ) ) return false;
        if ( 'bind' === $m['kind'] && ( ! self::roles( array( $m['auth'], $m['capture'], $m['writer'] ) ) || ! is_string( $m['remaining_ns'] ) || 1 !== preg_match( '/^[1-9][0-9]{9,10}$/D', $m['remaining_ns'] ) || strlen( $m['remaining_ns'] ) > 11 || (float) $m['remaining_ns'] < 6000000000 || (float) $m['remaining_ns'] > 10000000000 ) ) return false;
        if ( 'bound' === $m['kind'] && ( ! is_int( $m['writer'] ) || $m['writer'] < 1 || $m['writer'] > 4294967295 ) ) return false;
        return 'final' !== $m['kind'] || ( true === $m['root_returned'] && 8 === $m['pre_guards'] && 2 === $m['post_guards'] && 40 === $m['protected_reads'] && 1 === $m['writes'] && true === $m['original_slots_empty'] && self::receipt( $m['observation'] ) );
    }
    public static function line( $m ) { return 1 === func_num_args() && self::check( $m ) && strlen( $s = json_encode( $m, JSON_UNESCAPED_SLASHES ) . "\n" ) <= 4096 ? $s : false; }
    public static function parse( $s ) {
        if ( 1 !== func_num_args() || ! is_string( $s ) || '' === $s || strlen( $s ) > 4095 || 1 !== preg_match( '/^[\x20-\x5b\x5d-\x7e]+$/D', $s ) ) return false;
        $m = json_decode( $s, true, 8, JSON_BIGINT_AS_STRING );
        return self::check( $m ) && json_encode( $m, JSON_UNESCAPED_SLASHES ) === $s ? $m : false;
    }
}
