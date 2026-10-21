<?php
/** Pure selected claims only. No authentication, root execution, socket or suppression. */
require_once __DIR__ . '/SelectedQualificationManifest.php';
final class Alynt_Test_Selected_Qualification_Protocol {
    const DOMAIN = 'alynt.qualification-observation.selected.v1';
    const PROFILE = 'qualification_selected_commit_response_loss';
    public static function token( $v ) { return is_string( $v ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $v ); }
    public static function context( $c ) {
        return is_array( $c ) && array_keys( $c ) === array( 'attempt', 'root', 'pair' ) && self::token( $c['attempt'] ) && self::token( $c['root'] ) && self::token( $c['pair'] ) && 3 === count( array_unique( $c ) );
    }
    private static function thread( $v ) { return is_int( $v ) && $v > 0 && $v <= 4294967295; }
    public static function observation( $r ) {
        if ( ! is_array( $r ) || array_keys( $r ) !== array( 'valid', 'entries', 'returns', 'kind', 'result', 'sealed', 'empty_slots', 'capture_unchanged', 'io_unchanged' ) || 1 !== $r['entries'] || 1 !== $r['returns'] || 'closed_array' !== $r['kind'] ) return false;
        foreach ( array( 'valid', 'sealed', 'empty_slots', 'capture_unchanged', 'io_unchanged' ) as $k ) if ( true !== $r[$k] ) return false;
        $v = $r['result'];
        if ( ! is_array( $v ) || array_keys( $v ) !== array( 'historical_outcome', 'client_release_claimed', 'current_acceptance_claimed', 'request_lifecycle_closed_claimed', 'diagnostic_candidate_only', 'candidate_only', 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) || 'uncertain' !== $v['historical_outcome'] || ! is_bool( $v['client_release_claimed'] ) || true !== $v['request_lifecycle_closed_claimed'] || true !== $v['candidate_only'] ) return false;
        foreach ( array( 'current_acceptance_claimed', 'diagnostic_candidate_only', 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $k ) if ( false !== $v[$k] ) return false;
        return true; // Copy the actual release boolean; never derive it from external cleanup.
    }
    public static function check( $m ) {
        if ( 1 !== func_num_args() || ! is_array( $m ) || ! isset( $m['kind'] ) || ! is_string( $m['kind'] ) ) return false;
        $schemas = array( 'boot' => array( 1, 'profile' ), 'root_request' => array( 1, 'source' ), 'root' => array( 2, 'root' ), 'bind' => array( 2, 'root', 'auth', 'capture', 'writer', 'remaining_ns' ), 'bound' => array( 3, 'root', 'writer', 'pair' ), 'final' => array( 3, 'root', 'pair', 'profile', 'source', 'root_returned', 'pre_guards', 'post_guards', 'protected_reads', 'writes', 'original_slots_empty', 'observation' ) );
        if ( ! isset( $schemas[$m['kind']] ) ) return false;
        $s = $schemas[$m['kind']]; $seq = array_shift( $s );
        if ( array_keys( $m ) !== array_merge( array( 'domain', 'v', 'kind', 'seq', 'attempt' ), $s ) || self::DOMAIN !== $m['domain'] || 1 !== $m['v'] || $seq !== $m['seq'] || ! self::token( $m['attempt'] ) ) return false;
        if ( array_key_exists( 'root', $m ) && ( ! self::token( $m['root'] ) || $m['attempt'] === $m['root'] ) ) return false;
        if ( array_key_exists( 'pair', $m ) && ! self::context( array( 'attempt' => $m['attempt'], 'root' => $m['root'], 'pair' => $m['pair'] ) ) ) return false;
        if ( array_key_exists( 'profile', $m ) && self::PROFILE !== $m['profile'] ) return false;
        if ( array_key_exists( 'source', $m ) && ( ! is_array( $m['source'] ) || false === ( $expected = Alynt_Test_Selected_Qualification_Manifest::identities() ) || $m['source'] !== $expected ) ) return false;
        if ( 'bind' === $m['kind'] && ( ! self::thread( $m['auth'] ) || ! self::thread( $m['capture'] ) || ! self::thread( $m['writer'] ) || 3 !== count( array_unique( array( $m['auth'], $m['capture'], $m['writer'] ) ) ) || ! is_string( $m['remaining_ns'] ) || 1 !== preg_match( '/^(?:[6-9][0-9]{9}|10000000000)$/D', $m['remaining_ns'] ) ) ) return false;
        if ( 'bound' === $m['kind'] && ! self::thread( $m['writer'] ) ) return false;
        return 'final' !== $m['kind'] || ( false === $m['root_returned'] && 8 === $m['pre_guards'] && 0 === $m['post_guards'] && 40 === $m['protected_reads'] && 1 === $m['writes'] && true === $m['original_slots_empty'] && self::observation( $m['observation'] ) );
    }
    public static function line( $m ) { return 1 === func_num_args() && self::check( $m ) && strlen( $s = json_encode( $m, JSON_UNESCAPED_SLASHES ) . "\n" ) <= 4096 ? $s : false; }
    public static function parse( $s ) {
        if ( 1 !== func_num_args() || ! is_string( $s ) || '' === $s || strlen( $s ) > 4095 || 1 !== preg_match( '/^[\x20-\x5b\x5d-\x7e]+$/D', $s ) ) return false;
        $m = json_decode( $s, true, 8, JSON_BIGINT_AS_STRING );
        return self::check( $m ) && json_encode( $m, JSON_UNESCAPED_SLASHES ) === $s ? $m : false;
    }
}
