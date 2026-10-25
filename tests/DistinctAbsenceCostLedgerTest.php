<?php
/** Pure model behavior only; not genuine absence, driver observation or WordPress proof. */
require_once dirname( __DIR__ ) . '/scripts/tests/distinct-absence-cost-ledger.php';

final class DistinctAbsenceCostLedgerTest extends PHPUnit\Framework\TestCase {
    private function args() { return array( str_repeat( 'a', 12 ), str_repeat( 'b', 24 ), 1799913600, 1000, 1001, 1799913600 ); }
    private function catalog() { return Alynt_Sidebar_Projection_Catalog::build( ...$this->args() ); }
    private function events( $mode = 'autoload', $names = array( 'sidebars_widgets' ) ) {
        $events = array();
        if ( 'fallback' === $mode ) $events[] = array( 1001, 'core', 'bulk', '-', 'ok', 0 );
        $events[] = array( 1001, 'core', 'bulk', '-', 'ok', 29 );
        foreach ( $names as $name ) $events[] = array( 1002, 'core', 'miss', $name, 'ok', 0 );
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
        $base = $this->events();
        foreach ( array( 'SIDEbars_widgets', 'sidebars_widgets ', 'sidebar_widgets', "sidebars_widgets\0", 'siteurl',
            'alynt_drime_backups_settings', 'proof_owner', str_repeat( 'x', 81 ), '', null, false, array(), new stdClass() ) as $name ) {
            $bad = $base; $bad[1][3] = $name; $this->assertFalse( $this->runLedger( $bad ) );
        }
        foreach ( array( 'failed', 'cancelled', 'unknown', 'default', false, null ) as $outcome ) {
            $bad = $base; $bad[1][4] = $outcome; $this->assertFalse( $this->runLedger( $bad ) );
        }
        foreach ( array( 1, 29, -1, '0', false, null, array(), new stdClass() ) as $rows ) {
            $bad = $base; $bad[1][5] = $rows; $this->assertFalse( $this->runLedger( $bad ) );
        }
        $bad = $base; $bad[] = $bad[1]; $this->assertFalse( $this->runLedger( $bad ) );
        $bad = array_merge( array( $base[0] ), array_fill( 0, 8, $base[1] ) );
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
}
