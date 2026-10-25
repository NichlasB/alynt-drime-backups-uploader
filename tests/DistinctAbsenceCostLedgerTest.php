<?php
/** Pure model behavior only; not genuine absence, driver observation or WordPress proof. */
require_once dirname( __DIR__ ) . '/scripts/tests/distinct-absence-cost-ledger.php';

final class DistinctAbsenceCostLedgerTest extends PHPUnit\Framework\TestCase {
    private function args() { return array( str_repeat( 'a', 12 ), str_repeat( 'b', 24 ), 1799913600, 1000, 1001, 1799913600 ); }
    private function catalog() { return Alynt_Sidebar_Projection_Catalog::build( ...$this->args() ); }
    private function events( $mode = 'autoload', $names = array( 'sidebars_widgets' ) ) {
        $events = array();
        foreach ( array( 'connect', 'charset_api', 'charset_sql', 'mode_read', 'mode_write', 'select_db' ) as $family ) $events[] = array( 1001, 'core', 'driver', $family, 'ok', 0 );
        if ( 'fallback' === $mode ) $events[] = array( 1001, 'core', 'bulk', '-', 'ok', 0 );
        $events[] = array( 1001, 'core', 'bulk', '-', 'ok', 29 );
        foreach ( $names as $name ) {
            $events[] = array( 1002, 'core', 'api', $name, 'ok', 0 );
            $events[] = array( 1002, 'core', 'read_attempt', $name, 'ok', 0 );
            $events[] = array( 1002, 'core', 'miss', $name, 'ok', 0 );
        }
        $events[] = array( 1003, 'core', 'finish', '-', 'ok', 0 );
        return $events;
    }
    private function runLedger( $events, $mode = 'autoload' ) {
        return Alynt_Test_Distinct_Absence_Cost_Ledger::evaluate( $this->catalog(), $this->args(), $mode, $events );
    }
    public function test_positive_modes_and_reached_subsets_do_not_claim_authority() {
        foreach ( array( 'autoload' => 1, 'fallback' => 2 ) as $mode => $bulk ) {
            foreach ( array( array(), array( 'sidebars_widgets' ), $this->catalog()['absent'] ) as $names ) {
                $result = $this->runLedger( $this->events( $mode, $names ), $mode );
                $this->assertTrue( $result['synthetic_match'] );
                $this->assertSame( $names, $result['distinct_absent'] );
                $this->assertSame( $bulk, $result['bulk_attempts'] );
                $this->assertSame( $bulk + count( $names ), $result['option_select_attempts'] );
                foreach ( array( 'native_proven', 'absence_proven', 'driver_complete', 'resource_proven', 'wiring_supported', 'execution_supported' ) as $claim ) $this->assertFalse( $result[$claim] );
            }
        }
    }
    public function test_exact_names_result_success_and_zero_rows_are_independent_guards() {
        $base = $this->events(); $miss = count( $base ) - 2;
        foreach ( array( 'SIDEbars_widgets', 'sidebars_widgets ', 'sidebar_widgets', "sidebars_widgets\0", 'siteurl',
            'alynt_drime_backups_settings', 'proof_owner', str_repeat( 'x', 81 ), '', null, false, array(), new stdClass() ) as $name ) {
            $bad = $base; $bad[$miss][3] = $name; $this->assertFalse( $this->runLedger( $bad ) );
        }
        foreach ( array( 'failed', 'cancelled', 'unknown', 'default', false, null ) as $outcome ) {
            $bad = $base; $bad[$miss][4] = $outcome; $this->assertFalse( $this->runLedger( $bad ) );
        }
        foreach ( array( 1, 29, -1, '0', false, null, array(), new stdClass() ) as $rows ) {
            $bad = $base; $bad[$miss][5] = $rows; $this->assertFalse( $this->runLedger( $bad ) );
        }
        $bad = $base; $bad[] = $bad[1]; $this->assertFalse( $this->runLedger( $bad ) );
        $bad = $this->events( 'autoload', array_fill( 0, 8, 'sidebars_widgets' ) );
        $this->assertFalse( $this->runLedger( $bad ) );
        foreach ( array( 'profile', 'present', 'absent', 'hidden' ) as $group ) {
            $catalog = $this->catalog(); unset( $catalog[$group] );
            $this->assertFalse( Alynt_Test_Distinct_Absence_Cost_Ledger::evaluate( $catalog, $this->args(), 'autoload', $base ) );
        }
        $old = Alynt_Fixed_Projection_Catalog::build( ...$this->args() );
        $this->assertFalse( Alynt_Test_Distinct_Absence_Cost_Ledger::evaluate( $old, $this->args(), 'autoload', $base ) );
        $catalog = $this->catalog(); $catalog['present']['sidebars_widgets'] = 'a:0:{}';
        $this->assertFalse( Alynt_Test_Distinct_Absence_Cost_Ledger::evaluate( $catalog, $this->args(), 'autoload', $base ) );
    }
    public function test_api_and_genuine_learned_cache_counts_cannot_substitute_for_distinct_misses() {
        $events = $this->events(); $miss = count( $events ) - 2; $finish = array_pop( $events );
        foreach ( array( 'sidebars_widgets', 'siteurl', 'sidebars_widgets' ) as $name ) {
            $events[] = array( 1003, 'core', 'api', $name, 'ok', 0 );
            $events[] = array( 1003, 'core', 'cache', $name, 'ok', 0 );
        }
        $events[] = $finish;
        $result = $this->runLedger( $events );
        $this->assertSame( 4, $result['api_visits'] ); $this->assertSame( 3, $result['cache_hits'] );
        $this->assertSame( array( 'sidebars_widgets' ), $result['distinct_absent'] );
        $this->assertSame( 2, $result['option_select_attempts'] );
        $bad = $this->events(); $bad[$miss][2] = 'cache'; $this->assertFalse( $this->runLedger( $bad ) );
        $bad = $this->events(); unset( $bad[$miss - 2] ); $this->assertFalse( $this->runLedger( array_values( $bad ) ) );
        $bad = $this->events(); $bad[] = $bad[$miss - 2]; $this->assertFalse( $this->runLedger( $bad ) );
        $bad = $events; $bad[] = array( 1003, 'core', 'cache', 'sidebars_widgets', 'ok', 0 );
        $this->assertFalse( $this->runLedger( $bad ) );
        $bad = $this->events(); $bad[] = $bad[$miss - 2]; $bad[] = $bad[$miss - 1]; $bad[] = $bad[$miss]; $this->assertFalse( $this->runLedger( $bad ) );
    }
    public function test_driver_setup_and_verifier_audit_are_separate_fixed_namespaces() {
        $events = $this->events(); array_unshift( $events, array( 1001, 'verifier', 'audit', 'identity_grants', 'ok', 0 ) );
        $result = $this->runLedger( $events );
        $this->assertSame( 3, $result['core_driver_api'] ); $this->assertSame( 3, $result['core_driver_sql'] );
        $this->assertSame( 1, $result['verifier_audits'] ); $this->assertSame( 2, $result['option_select_attempts'] );
        $this->assertFalse( $result['driver_complete'] );
        for ( $index = 0; $index < 6; $index++ ) {
            $bad = $this->events(); unset( $bad[$index] ); $this->assertFalse( $this->runLedger( array_values( $bad ) ) );
            $bad = $this->events(); $bad[$index][1] = 'verifier'; $this->assertFalse( $this->runLedger( $bad ) );
            $bad = $this->events(); $bad[$index][3] = 'unlisted'; $this->assertFalse( $this->runLedger( $bad ) );
        }
        $bad = $this->events(); $bad[count( $bad ) - 2][1] = 'verifier'; $this->assertFalse( $this->runLedger( $bad ) );
        $bad = $events; $bad[0][1] = 'core'; $this->assertFalse( $this->runLedger( $bad ) );
    }
    public function test_attempt_result_pairing_and_replay_refusal_cannot_be_replaced_by_counts() {
        $events = $this->events(); $miss = count( $events ) - 2;
        $result = $this->runLedger( $events );
        $this->assertSame( 2, $result['option_select_attempts'] ); $this->assertSame( 2, $result['data_results'] );
        $this->assertSame( 2, $result['conditional_option_subtotal'] );
        $bad = $events; unset( $bad[$miss - 1] ); $this->assertFalse( $this->runLedger( array_values( $bad ) ) );
        $bad = $events; unset( $bad[$miss] ); $this->assertFalse( $this->runLedger( array_values( $bad ) ) );
        $bad = $events; array_splice( $bad, $miss, 0, array( $bad[$miss - 1] ) ); $this->assertFalse( $this->runLedger( $bad ) );
        foreach ( array( 'replay', 'reconnect', 'default', 'query_filter_visit', 'num_queries', 'unknown' ) as $kind ) {
            $bad = $events; $bad[$miss - 1][2] = $kind; $this->assertFalse( $this->runLedger( $bad ) );
        }
        $bad = $events; $bad[$miss - 1][3] = 'category_base'; $this->assertFalse( $this->runLedger( $bad ) );
    }
    public function test_finish_original_epoch_and_late_effects_are_mandatory() {
        $events = $this->events(); $last = count( $events ) - 1;
        $bad = $events; array_pop( $bad ); $this->assertFalse( $this->runLedger( $bad ) );
        foreach ( array( 1001, 21000, PHP_INT_MAX, -1, 1003.0, '1003', null, false ) as $tick ) {
            $bad = $events; $bad[$last][0] = $tick; $this->assertFalse( $this->runLedger( $bad ) );
        }
        $near = $events; $near[$last][0] = 20999;
        $this->assertTrue( $this->runLedger( $near )['synthetic_match'] );
        foreach ( array( 'api', 'driver', 'finish', 'http', 'diagnostic', 'query', 'unknown' ) as $kind ) {
            $bad = $events; $bad[] = array( 1004, 'core', $kind, '-', 'ok', 0 );
            $this->assertFalse( $this->runLedger( $bad ) );
        }
        $bad = $events; array_unshift( $bad, $bad[$last] ); $this->assertFalse( $this->runLedger( $bad ) );
    }
    public function test_bounded_primitive_shapes_and_exact_fixture_limit() {
        $base = $this->events();
        foreach ( array( null, false, 'trace', new stdClass(), array(), array_reverse( $base, true ), array( 9 => $base[0] ) ) as $events ) {
            $this->assertFalse( $this->runLedger( $events ) );
        }
        foreach ( $base as $index => $event ) {
            foreach ( array( null, false, 'event', new stdClass(), array(), array_reverse( $event, true ), $event + array( 6 => 'extra' ) ) as $row ) {
                $bad = $base; $bad[$index] = $row; $this->assertFalse( $this->runLedger( $bad ) );
            }
            foreach ( range( 0, 5 ) as $slot ) foreach ( array( null, false, new stdClass(), array() ) as $value ) {
                $bad = $base; $bad[$index][$slot] = $value; $this->assertFalse( $this->runLedger( $bad ), $index . ':' . $slot );
            }
        }
        foreach ( array( 'AUTOLOAD', '', null, false, array() ) as $mode ) $this->assertFalse( $this->runLedger( $base, $mode ) );
        foreach ( range( 0, 5 ) as $slot ) {
            $args = $this->args(); $args[$slot] = new stdClass();
            $this->assertFalse( Alynt_Test_Distinct_Absence_Cost_Ledger::evaluate( $this->catalog(), $args, 'autoload', $base ) );
        }
        $audit = array( 1001, 'verifier', 'audit', 'identity_grants', 'ok', 0 );
        $exact = array_merge( array_fill( 0, 128 - count( $base ), $audit ), $base );
        $this->assertCount( 128, $exact );
        $result = $this->runLedger( $exact ); $this->assertTrue( $result['synthetic_match'] );
        $this->assertSame( 128 - count( $base ), $result['verifier_audits'] );
        $this->assertSame( 2, $result['option_select_attempts'] );
        array_unshift( $exact, $audit ); $this->assertFalse( $this->runLedger( $exact ) );
        $this->assertFalse( Alynt_Test_Distinct_Absence_Cost_Ledger::evaluate( $this->catalog(), $this->args(), 'autoload', $base, 'extra' ) );
        $changed = $this->runLedger( $base ); $changed['distinct_absent'][] = 'fake';
        $this->assertSame( array( 'sidebars_widgets' ), $this->runLedger( $base )['distinct_absent'] );
    }
}
