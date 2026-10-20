<?php
/** Primitive observation validation only; never another outcome classifier. */
final class Alynt_Test_Qualification_Observation_Projection {
    public static function state( $sealed, $empty, $capture, $reads, $writes ) {
        $digest = self::capture_digest( $capture );
        return array( 'sealed' => true === $sealed, 'empty' => true === $empty, 'digest' => $digest, 'reads' => is_int( $reads ) && $reads >= 0 && $reads <= 40 ? $reads : null, 'writes' => is_int( $writes ) && $writes >= 0 && $writes <= 1 ? $writes : null );
    }
    private static function capture_digest( $c ) {
        $commands = array( 'begin', 'dml', 'commit', 'rollback', 'close' );
        if ( ! is_array( $c ) || array_keys( $c ) !== array( 'valid', 'facts', 'raw_returns' ) || ! is_bool( $c['valid'] ) || ! is_array( $c['facts'] ) || array_keys( $c['facts'] ) !== array( 'events', 'acks', 'affected_rows', 'handle_remaining', 'guards' ) || ! is_array( $c['raw_returns'] ) || array_keys( $c['raw_returns'] ) !== $commands ) return null;
        $f = $c['facts'];
        if ( ! is_array( $f['events'] ) || array_keys( $f['events'] ) !== Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::EVENTS || ! is_array( $f['acks'] ) || array_keys( $f['acks'] ) !== $commands || ! is_array( $f['guards'] ) || array_keys( $f['guards'] ) !== array( 'auth', 'intent', 'physical', 'unreentered' ) || ! in_array( $f['affected_rows'], array( 'not_observed', 'unknown', 'zero', 'one', 'other' ), true ) || ! in_array( $f['handle_remaining'], array( null, false, true ), true ) ) return null;
        foreach ( $f['events'] as $n ) if ( ! is_int( $n ) || $n < 0 || $n > 32 ) return null;
        foreach ( $f['guards'] as $v ) if ( ! in_array( $v, array( null, false, true ), true ) ) return null;
        foreach ( $commands as $k ) if ( ! in_array( $c['raw_returns'][$k], array( 'not_called', 'unknown', false, true ), true ) || ! in_array( $f['acks'][$k], array( 'not_sent', 'unknown', 'yes', 'no' ), true ) ) return null;
        return hash( 'sha256', json_encode( $c ) ); // All fields now bounded primitives; no object callbacks.
    }
    public static function receipt( $returned, $result, array $before, array $after ) {
        $kind = $returned ? ( false === $result ? 'false' : 'invalid' ) : 'exception'; $projection = null;
        if ( $returned && is_array( $result ) && array_keys( $result ) === array( 'historical_outcome', 'client_release_claimed', 'current_acceptance_claimed', 'request_lifecycle_closed_claimed', 'diagnostic_candidate_only', 'candidate_only', 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) && in_array( $result['historical_outcome'], array( 'committed', 'uncertain', 'rolled_back', 'not_written', 'unsupported' ), true ) ) {
            $valid = true; foreach ( array_slice( $result, 1 ) as $v ) $valid = $valid && is_bool( $v );
            foreach ( array( 'diagnostic_candidate_only', 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $k ) $valid = $valid && false === $result[$k];
            if ( $valid && true === $result['candidate_only'] && true === $result['request_lifecycle_closed_claimed'] ) { $kind = 'closed_array'; $projection = $result; }
        }
        $same = is_string( $before['digest'] ) && $before['digest'] === $after['digest'];
        $io = null !== $before['reads'] && null !== $before['writes'] && $before['reads'] === $after['reads'] && $before['writes'] === $after['writes'];
        return array( 'valid' => $kind !== 'invalid' && $same && $io && $before['sealed'] && $after['sealed'] && $before['empty'] && $after['empty'], 'entries' => 1, 'returns' => $returned ? 1 : 0, 'kind' => $kind, 'result' => $projection, 'sealed' => $before['sealed'] && $after['sealed'], 'empty_slots' => $before['empty'] && $after['empty'], 'capture_unchanged' => $same, 'io_unchanged' => $io );
    }
}
