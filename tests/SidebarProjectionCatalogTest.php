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
    public function test_original_epoch_strict_arguments_and_profile_downgrade_refuse() {
        $args = $this->args(); $base = Alynt_Sidebar_Projection_Catalog::build( ...$args );
        foreach ( $args as $index => $unused ) foreach ( array( null, false, -1, 1.5, '1', array(), new stdClass(), PHP_INT_MAX ) as $value ) {
            $bad = $args; $bad[$index] = $value;
            $this->assertFalse( Alynt_Sidebar_Projection_Catalog::build( ...$bad ) );
            $this->assertFalse( Alynt_Sidebar_Projection_Catalog::matches( $base, ...$bad ) );
        }
        foreach ( array( array( 0, str_repeat( 'A', 12 ) ), array( 1, str_repeat( 'b', 24 ) . "\n" ),
            array( 2, 9999999999 ), array( 4, 999 ), array( 4, 21000 ), array( 5, 1799913601 ) ) as $change ) {
            $bad = $args; $bad[$change[0]] = $change[1];
            $this->assertFalse( Alynt_Sidebar_Projection_Catalog::build( ...$bad ) );
        }
        $later = $args; $later[4] = 20999; $later[5] += 19;
        $this->assertSame( $base, Alynt_Sidebar_Projection_Catalog::build( ...$later ) );
        $this->assertTrue( Alynt_Sidebar_Projection_Catalog::matches( $base, ...$later ) );
        $this->assertFalse( Alynt_Sidebar_Projection_Catalog::build( ...array_merge( $args, array( 'extra' ) ) ) );
        $this->assertFalse( Alynt_Sidebar_Projection_Catalog::matches( $base, ...array_merge( $args, array( 'extra' ) ) ) );
        foreach ( array( Alynt_Fixed_Projection_Catalog::PROFILE, '', str_repeat( '0', 64 ), null, false ) as $profile ) {
            $bad = $base; $bad['profile'] = $profile;
            $this->assertFalse( Alynt_Sidebar_Projection_Catalog::matches( $bad, ...$args ) );
        }
        $old = Alynt_Fixed_Projection_Catalog::build( ...$args );
        $old['profile'] = Alynt_Sidebar_Projection_Catalog::PROFILE;
        $this->assertFalse( Alynt_Sidebar_Projection_Catalog::matches( $old, ...$args ) );
        $bad = $base; $bad['profile'] = Alynt_Fixed_Projection_Catalog::PROFILE;
        $this->assertFalse( Alynt_Fixed_Projection_Catalog::matches( $bad, ...$args ) );
        $this->assertSame( $base, Alynt_Sidebar_Projection_Catalog::build( ...$args ) );
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
