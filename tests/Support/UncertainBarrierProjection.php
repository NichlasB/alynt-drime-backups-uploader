<?php
/** Bounded DATA projection only. Refusal is not a timeout witness or native proof. */
final class Alynt_Test_Uncertain_Barrier_Projection {
    public static function capture( $returned, $result, $phase, $receipt, $reads, $barrier, $writes, $metadata, $zero ) {
        $keys = array( 'candidate_only', 'storage_supported', 'dispatch_supported', 'execution_supported', 'retry_supported', 'authenticated', 'classification', 'barrier_verified', 'closure_proven' );
        $positive = is_array( $result ) && array_keys( $result ) === $keys && 'registered_closed' === $result['classification'] && true === $result['barrier_verified'];
        if ( $positive ) foreach ( array_diff( $keys, array( 'classification', 'barrier_verified' ) ) as $key ) $positive = $positive && false === $result[$key];
        if ( $positive ) foreach ( $keys as $key ) if ( null !== ReflectionReference::fromArrayElement( $result, $key ) ) $positive = false;
        $negative = false === $result && false === $receipt && in_array( $phase, array( 'rolled_back', 'poisoned' ), true );
        if ( true !== $returned || ( ! $positive && ! $negative ) || 40 !== $reads || 1 !== $writes ||
            ( $positive ? ( true !== $receipt || 'barrier_released' !== $phase || 10 !== $barrier || 2 !== $metadata || 2 !== $zero ) : ( 1 !== $barrier || 1 !== $metadata || 1 !== $zero ) ) ) return false;
        return array( 'outcome' => $positive ? 'observed' : 'refused', 'root_kind' => $positive ? 'array' : 'false', 'phase' => $phase,
            'barrier_receipt' => $receipt, 'admission_reads' => $reads, 'barrier_reads' => $barrier, 'writes' => $writes,
            'metadata_checks' => $metadata, 'zero_dml_checks' => $zero, 'root_result' => $positive ? $result : null );
    }
    public static function terminal( $event, $guards ) {
        if ( ! is_array( $event ) || array_keys( $event ) !== array( 'terminal', 'qualification' ) || ! is_array( $event['terminal'] ) || ! is_array( $event['qualification'] ) ) return false;
        $t = $event['terminal']; $q = $event['qualification'];
        if ( array_keys( $t ) !== array( 'outcome', 'root_kind', 'phase', 'barrier_receipt', 'admission_reads', 'barrier_reads', 'writes', 'metadata_checks', 'zero_dml_checks', 'root_result' ) ||
            false === self::capture( true, $t['root_result'] ?? false, $t['phase'], $t['barrier_receipt'], $t['admission_reads'], $t['barrier_reads'], $t['writes'], $t['metadata_checks'], $t['zero_dml_checks'] ) ||
            $t !== self::capture( true, $t['root_result'] ?? false, $t['phase'], $t['barrier_receipt'], $t['admission_reads'], $t['barrier_reads'], $t['writes'], $t['metadata_checks'], $t['zero_dml_checks'] ) ) return false;
        $order = array( 'M','M','M','M','M','MF','M','MF','M','MF','M','S','S','F','S','F','S','F','S','F','S','F','S','F' );
        if ( array_slice( $order, 0, 'observed' === $t['outcome'] ? 24 : 22 ) !== $guards ||
            array_keys( $q ) !== array( 'valid', 'entries', 'returns', 'kind', 'result', 'sealed', 'empty_slots', 'capture_unchanged', 'io_unchanged' ) ||
            1 !== $q['entries'] || 1 !== $q['returns'] || 'closed_array' !== $q['kind'] || ! is_array( $q['result'] ) ) return false;
        foreach ( array( 'valid', 'sealed', 'empty_slots', 'capture_unchanged', 'io_unchanged' ) as $k ) if ( true !== $q[$k] ) return false;
        $r = $q['result'];
        if ( array_keys( $r ) !== array( 'historical_outcome', 'client_release_claimed', 'current_acceptance_claimed', 'request_lifecycle_closed_claimed', 'diagnostic_candidate_only', 'candidate_only', 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) ||
            ! in_array( $r['historical_outcome'], 'observed' === $t['outcome'] ? array( 'committed', 'uncertain' ) : array( 'uncertain' ), true ) ) return false;
        foreach ( array_slice( $r, 1, null, true ) as $k => $v ) if ( $v !== in_array( $k, array( 'client_release_claimed', 'request_lifecycle_closed_claimed', 'candidate_only' ), true ) ) return false;
        return array( 'terminal' => $t, 'guard_order' => $guards, 'qualification' => $q,
            'native_proven' => false, 'timeout_proven' => false, 'cleanup_proven' => false, 'execution_supported' => false );
    }
}
