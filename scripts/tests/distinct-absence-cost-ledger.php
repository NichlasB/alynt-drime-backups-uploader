<?php
/** Synthetic events only. No SQL, WordPress, storage, driver, clock IO or native caller. */
require_once __DIR__ . '/sidebar-projection-catalog.php';

final class Alynt_Test_Distinct_Absence_Cost_Ledger {
    // Fixture-domain bound only; never a production query/resource budget.
    const MODEL_EVENT_LIMIT = 128;
    // One illustrative successful setup branch, not an exhaustive driver inventory.
    const MODEL_DRIVER_SEQUENCE = array( 'connect', 'charset_api', 'charset_sql', 'mode_read', 'mode_write', 'select_db' );

    /** Events: [virtual_tick, session, kind, name, outcome, rows]. */
    public static function evaluate( $catalog, $args, $mode, $events ) {
        if ( 4 !== func_num_args() || ! is_array( $args ) || array_keys( $args ) !== range( 0, 5 ) ||
            ! Alynt_Sidebar_Projection_Catalog::matches( $catalog, ...$args ) ||
            ! in_array( $mode, array( 'autoload', 'fallback' ), true ) || ! is_array( $events ) ||
            count( $events ) < 1 || count( $events ) > self::MODEL_EVENT_LIMIT || array_keys( $events ) !== range( 0, count( $events ) - 1 ) ) return false;
        $last = $args[4]; $stage = 0; $bulk = 0; $absent = array(); $pending = null; $api = 0; $cache = 0;
        $driver = 0; $driver_api = 0; $driver_sql = 0; $verifier = 0;
        $inflight = null; $attempts = 0; $results = 0;
        foreach ( $events as $event ) {
            if ( ! is_array( $event ) || array_keys( $event ) !== range( 0, 5 ) ) return false;
            list( $tick, $session, $kind, $name, $outcome, $rows ) = $event;
            if ( ! is_int( $tick ) || $tick < $last || $tick - $args[3] >= 20000 ||
                ! in_array( $session, array( 'core', 'verifier' ), true ) || ! is_string( $kind ) || ! is_string( $name ) || strlen( $name ) > 80 ||
                'ok' !== $outcome || ! is_int( $rows ) || $rows < 0 || $rows > 29 ) return false;
            $last = $tick;
            if ( 'audit' === $kind && 'verifier' === $session && 0 === $driver && 0 === $stage && 'identity_grants' === $name && 0 === $rows ) {
                $verifier++; continue;
            }
            if ( 'core' !== $session ) return false;
            if ( 'driver' === $kind ) {
                if ( 0 !== $stage || 0 !== $rows || $driver >= count( self::MODEL_DRIVER_SEQUENCE ) || self::MODEL_DRIVER_SEQUENCE[$driver] !== $name ) return false;
                $driver++;
                if ( in_array( $name, array( 'connect', 'charset_api', 'select_db' ), true ) ) $driver_api++; else $driver_sql++;
                continue;
            }
            if ( count( self::MODEL_DRIVER_SEQUENCE ) !== $driver ) return false;
            if ( 'bulk' === $kind && '-' === $name ) {
                if ( 0 === $stage && 'fallback' === $mode && 0 === $rows ) { $stage = 1; $bulk++; $attempts++; $results++; continue; }
                if ( ( ( 0 === $stage && 'autoload' === $mode ) || ( 1 === $stage && 'fallback' === $mode ) ) && 29 === $rows ) {
                    $stage = 2; $bulk++; $attempts++; $results++; continue;
                }
                return false;
            }
            if ( 2 !== $stage || 0 !== $rows ) return false;
            if ( 'api' === $kind && null === $pending && ( isset( $catalog['present'][$name] ) || in_array( $name, $catalog['absent'], true ) ) ) {
                $pending = $name; $api++; continue;
            }
            if ( $pending !== $name ) return false;
            if ( 'read_attempt' === $kind && null === $inflight && in_array( $name, $catalog['absent'], true ) && ! isset( $absent[$name] ) ) {
                $inflight = $name; $attempts++; continue;
            }
            if ( 'cache' === $kind && null === $inflight && ( isset( $absent[$name] ) || isset( $catalog['present'][$name] ) ) ) {
                $pending = null; $cache++; continue;
            }
            if ( 'miss' !== $kind || $inflight !== $name || ! in_array( $name, $catalog['absent'], true ) || isset( $absent[$name] ) ) return false;
            $absent[$name] = true;
            $pending = null; $inflight = null; $results++;
        }
        if ( 2 !== $stage || null !== $pending || null !== $inflight ) return false;
        return array( 'model' => 'alynt.absence-cost.model.v1', 'synthetic_match' => true,
            'bulk_attempts' => $bulk, 'distinct_absent' => array_keys( $absent ), 'option_select_attempts' => $attempts,
            'data_results' => $results, 'conditional_option_subtotal' => $bulk + count( $absent ),
            'api_visits' => $api, 'cache_hits' => $cache,
            'core_driver_api' => $driver_api, 'core_driver_sql' => $driver_sql, 'verifier_audits' => $verifier,
            'native_proven' => false, 'absence_proven' => false, 'driver_complete' => false,
            'resource_proven' => false, 'wiring_supported' => false, 'execution_supported' => false );
    }
}
