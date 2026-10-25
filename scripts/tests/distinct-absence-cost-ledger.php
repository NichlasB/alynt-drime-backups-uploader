<?php
/** Synthetic events only. No SQL, WordPress, storage, driver, clock IO or native caller. */
require_once __DIR__ . '/sidebar-projection-catalog.php';

final class Alynt_Test_Distinct_Absence_Cost_Ledger {
    // Fixture-domain bound only; never a production query/resource budget.
    const MODEL_EVENT_LIMIT = 128;

    /** Events: [virtual_tick, session, kind, name, outcome, rows]. */
    public static function evaluate( $catalog, $args, $mode, $events ) {
        if ( 4 !== func_num_args() || ! is_array( $args ) || array_keys( $args ) !== range( 0, 5 ) ||
            ! Alynt_Sidebar_Projection_Catalog::matches( $catalog, ...$args ) ||
            ! in_array( $mode, array( 'autoload', 'fallback' ), true ) || ! is_array( $events ) ||
            count( $events ) < 1 || count( $events ) > self::MODEL_EVENT_LIMIT || array_keys( $events ) !== range( 0, count( $events ) - 1 ) ) return false;
        $last = $args[4]; $stage = 0; $bulk = 0; $absent = array();
        foreach ( $events as $event ) {
            if ( ! is_array( $event ) || array_keys( $event ) !== range( 0, 5 ) ) return false;
            list( $tick, $session, $kind, $name, $outcome, $rows ) = $event;
            if ( ! is_int( $tick ) || $tick < $last || $tick - $args[3] >= 20000 ||
                'core' !== $session || ! is_string( $kind ) || ! is_string( $name ) || strlen( $name ) > 80 ||
                'ok' !== $outcome || ! is_int( $rows ) || $rows < 0 || $rows > 29 ) return false;
            $last = $tick;
            if ( 'bulk' === $kind && '-' === $name ) {
                if ( 0 === $stage && 'fallback' === $mode && 0 === $rows ) { $stage = 1; $bulk++; continue; }
                if ( ( ( 0 === $stage && 'autoload' === $mode ) || ( 1 === $stage && 'fallback' === $mode ) ) && 29 === $rows ) {
                    $stage = 2; $bulk++; continue;
                }
                return false;
            }
            if ( 'miss' !== $kind || 2 !== $stage || 0 !== $rows || ! in_array( $name, $catalog['absent'], true ) || isset( $absent[$name] ) ) return false;
            $absent[$name] = true;
        }
        if ( 2 !== $stage ) return false;
        return array( 'model' => 'alynt.absence-cost.model.v1', 'synthetic_match' => true,
            'bulk_attempts' => $bulk, 'distinct_absent' => array_keys( $absent ), 'option_select_attempts' => $bulk + count( $absent ),
            'native_proven' => false, 'absence_proven' => false, 'driver_complete' => false,
            'resource_proven' => false, 'wiring_supported' => false, 'execution_supported' => false );
    }
}
