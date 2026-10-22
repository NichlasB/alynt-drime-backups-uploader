<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/scripts/tests/uncertain-barrier-native-initial.php';
final class UncertainBarrierInitialFixtureTest extends TestCase {
    public function test_exact_initial_shape_is_not_initialized_or_modified_state() {
        $marker = str_repeat( 'a', 24 ); $rows = array(); $n = 0;
        foreach ( alynt_uncertain_initial_rows( $marker ) as $name => $value ) $rows[] = array( (string) ++$n, $name, $value, 'no' );
        $this->assertTrue( alynt_uncertain_initial_shape( $rows, $marker ) );
        foreach ( $rows as $i => $row ) foreach ( array_keys( $row ) as $k ) { $bad = $rows; $bad[$i][$k] .= 'x'; $this->assertFalse( alynt_uncertain_initial_shape( $bad, $marker ) ); }
        $this->assertFalse( alynt_uncertain_initial_shape( array_slice( $rows, 1 ), $marker ) );
        $this->assertFalse( alynt_uncertain_initial_shape( array_merge( $rows, array( $rows[0] ) ), $marker ) );
        $this->assertFalse( alynt_uncertain_initial_shape( array_reverse( $rows ), $marker ) );
        $this->assertFalse( alynt_uncertain_initial_shape( $rows, str_repeat( 'b', 24 ) ) );
        foreach ( array( null, '', str_repeat( 'a', 23 ), str_repeat( 'a', 25 ), str_repeat( 'A', 24 ) ) as $bad ) $this->assertFalse( alynt_uncertain_initial_rows( $bad ) );
    }
}
