<?php
/** Pure synthetic catalog tests; neither an absent-row nor a native qualification proof. */
require_once dirname( __DIR__ ) . '/scripts/tests/sidebar-projection-catalog.php';

final class SidebarProjectionCatalogTest extends PHPUnit\Framework\TestCase {
    private function args() { return array( str_repeat( 'a', 12 ), str_repeat( 'b', 24 ), 1799913600, 1000, 1001, 1799913600 ); }
    public function test_positive_v2_is_distinct_and_preserves_every_present_and_hidden_value() {
        $old = Alynt_Fixed_Projection_Catalog::build( ...$this->args() );
        $new = Alynt_Sidebar_Projection_Catalog::build( ...$this->args() );
        $this->assertTrue( Alynt_Sidebar_Projection_Catalog::matches( $new, ...$this->args() ) );
        $this->assertTrue( Alynt_Fixed_Projection_Catalog::matches( $old, ...$this->args() ) );
        $this->assertSame( hash( 'sha256', 'alynt-fixed-projection.wp7.1.2.theme.v2.sidebar-absent' ), $new['profile'] );
        $this->assertNotSame( $old['profile'], $new['profile'] );
        $this->assertSame( $old['present'], $new['present'] );
        $this->assertSame( $old['hidden'], $new['hidden'] );
        $this->assertSame( array_merge( $old['absent'], array( 'sidebars_widgets' ) ), $new['absent'] );
        $this->assertCount( 29, $new['present'] ); $this->assertCount( 8, $new['absent'] );
        $this->assertSame( 32, count( $new['present'] + $new['hidden'] ) );
        $this->assertFalse( Alynt_Sidebar_Projection_Catalog::matches( $old, ...$this->args() ) );
        $this->assertFalse( Alynt_Fixed_Projection_Catalog::matches( $new, ...$this->args() ) );
    }
}
