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
    public function test_exact_absence_and_every_inherited_field_reject_mutation() {
        $base = Alynt_Sidebar_Projection_Catalog::build( ...$this->args() );
        $this->assertSame( array( 'hack_file', 'wp_attachment_pages_enabled', 'category_base', 'tag_base',
            'use_smilies', 'blog_public', 'theme_switched', 'sidebars_widgets' ), $base['absent'] );
        foreach ( array( 'SIDEBARS_WIDGETS', 'sidebars_widgets ', 'sidebar_widgets', 'sidebars_widgets\0', '',
            'theme_switched', 'siteurl', null, false, 7, array(), new stdClass() ) as $value ) {
            $bad = $base; $bad['absent'][7] = $value;
            $this->assertFalse( Alynt_Sidebar_Projection_Catalog::matches( $bad, ...$this->args() ) );
        }
        foreach ( $base as $group => $values ) {
            $bad = $base; unset( $bad[$group] );
            $this->assertFalse( Alynt_Sidebar_Projection_Catalog::matches( $bad, ...$this->args() ) );
            if ( ! is_array( $values ) ) continue;
            foreach ( $values as $key => $value ) {
                $bad = $base; unset( $bad[$group][$key] );
                $this->assertFalse( Alynt_Sidebar_Projection_Catalog::matches( $bad, ...$this->args() ), $group . ':' . $key );
                foreach ( array( null, false, 0, array(), new stdClass(), $value . ' ', str_repeat( 'x', 65537 ) ) as $wrong ) {
                    $bad = $base; $bad[$group][$key] = $wrong;
                    $this->assertFalse( Alynt_Sidebar_Projection_Catalog::matches( $bad, ...$this->args() ), $group . ':' . $key );
                }
            }
        }
        foreach ( array( 'present', 'hidden' ) as $group ) foreach ( array( '', 'a:0:{}' ) as $value ) {
            $bad = $base; $bad[$group]['sidebars_widgets'] = $value;
            $this->assertFalse( Alynt_Sidebar_Projection_Catalog::matches( $bad, ...$this->args() ) );
        }
        $bad = $base; $bad['absent'][] = 'sidebars_widgets';
        $this->assertFalse( Alynt_Sidebar_Projection_Catalog::matches( $bad, ...$this->args() ) );
        $bad = $base; $bad['absent'] = array_reverse( $bad['absent'] );
        $this->assertFalse( Alynt_Sidebar_Projection_Catalog::matches( $bad, ...$this->args() ) );
        $bad = $base; unset( $bad['absent'][7] ); $bad['absent']['sidebar'] = 'sidebars_widgets';
        $this->assertFalse( Alynt_Sidebar_Projection_Catalog::matches( $bad, ...$this->args() ) );
        foreach ( array( null, false, new stdClass(), array(), $base + array( 'native_proven' => true ), array_reverse( $base, true ) ) as $bad ) {
            $this->assertFalse( Alynt_Sidebar_Projection_Catalog::matches( $bad, ...$this->args() ) );
        }
    }
}
