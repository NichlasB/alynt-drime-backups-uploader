<?php
/** Real PHP serialization, synthetic inputs only; no WordPress or storage. */
final class FixedProjectionCatalogTest extends PHPUnit\Framework\TestCase {
    private function args() { return array( str_repeat( 'a', 12 ), str_repeat( 'b', 24 ), 1799913600, 1000, 1001, 1799913600 ); }
    private function catalog() {
        require_once dirname( __DIR__ ) . '/scripts/tests/fixed-projection-catalog.php';
        return Alynt_Fixed_Projection_Catalog::build( ...$this->args() );
    }
    public function test_exact_positive_catalog_and_php_primitive_values() {
        $catalog = $this->catalog(); $p = $catalog['present'];
        $this->assertTrue( Alynt_Fixed_Projection_Catalog::matches( $catalog, ...$this->args() ) );
        $this->assertCount( 29, $p ); $this->assertCount( 7, $catalog['absent'] ); $this->assertCount( 3, $catalog['hidden'] );
        $this->assertSame( array(), array_intersect( array_keys( $p ), $catalog['absent'] ) );
        $this->assertSame( 32, count( $p + $catalog['hidden'] ) );
        $this->assertSame( array( 'siteurl', 'home', 'blogname', 'active_plugins', 'template', 'stylesheet', 'WPLANG', 'wp_alynt_ro_aaaaaaaaaaaa_user_roles',
            'widget_pages', 'widget_calendar', 'widget_archives', 'widget_media_audio', 'widget_media_image', 'widget_media_gallery', 'widget_media_video',
            'widget_meta', 'widget_search', 'widget_text', 'widget_categories', 'widget_recent-posts', 'widget_recent-comments', 'widget_rss',
            'widget_tag_cloud', 'widget_nav_menu', 'widget_custom_html', 'widget_block', 'link_manager_enabled', 'permalink_structure', 'cron' ), array_keys( $p ) );
        $this->assertSame( array( 'hack_file', 'wp_attachment_pages_enabled', 'category_base', 'tag_base', 'use_smilies', 'blog_public', 'theme_switched' ), $catalog['absent'] );
        $this->assertSame( array( 'alynt_drime_backups_settings', 'alynt_drime_backups_schedule_safety_state', 'proof_owner' ), array_keys( $catalog['hidden'] ) );
        $this->assertSame( 1779, array_sum( array_map( 'strlen', $p ) ) );
        $this->assertSame( 'a:0:{}', $p['active_plugins'] );
        $this->assertSame( 'Owned projection ' . str_repeat( 'b', 24 ), $p['blogname'] );
        $this->assertSame( '', $p['WPLANG'] ); $this->assertSame( '', $p['permalink_structure'] ); $this->assertSame( '0', $p['link_manager_enabled'] );
        $widgets = array_filter( array_keys( $p ), static function ( $key ) { return 0 === strpos( $key, 'widget_' ); } );
        $this->assertCount( 18, $widgets );
        foreach ( $widgets as $widget ) $this->assertSame( 'a:1:{s:12:"_multiwidget";i:1;}', $p[$widget] );
        $this->assertArrayHasKey( 'widget_recent-posts', $p ); $this->assertArrayHasKey( 'widget_recent-comments', $p );
        $roles = array( 'administrator' => array( 'name' => 'Administrator', 'capabilities' => array( 'read' => true, 'manage_options' => true ) ) );
        $this->assertSame( serialize( $roles ), $p['wp_alynt_ro_aaaaaaaaaaaa_user_roles'] );
        // Deserialize ONLY helper-generated literals in this test, never supplied data in the helper.
        $cron = unserialize( $p['cron'], array( 'allowed_classes' => false ) );
        $this->assertSame( array( 1800000000, 'version' ), array_keys( $cron ) ); $this->assertSame( 2, $cron['version'] );
        $this->assertCount( 6, $cron[1800000000] ); $this->assertSame( 979, strlen( $p['cron'] ) );
        $schedules = array( 'wp_site_health_scheduled_check' => array( 'weekly', 604800 ), 'wp_privacy_delete_old_export_files' => array( 'hourly', 3600 ),
            'wp_privacy_personal_data_cleanup_requests' => array( 'daily', 86400 ), 'wp_version_check' => array( 'twicedaily', 43200 ),
            'wp_update_plugins' => array( 'twicedaily', 43200 ), 'wp_update_themes' => array( 'twicedaily', 43200 ) );
        $this->assertSame( array_keys( $schedules ), array_keys( $cron[1800000000] ) );
        foreach ( $cron[1800000000] as $hook => $jobs ) {
            $this->assertSame( array( '40cd750bba9870f18aada2478b24840a' ), array_keys( $jobs ) );
            $this->assertSame( array(), $jobs['40cd750bba9870f18aada2478b24840a']['args'] );
            $this->assertSame( array( 'schedule' => $schedules[$hook][0], 'args' => array(), 'interval' => $schedules[$hook][1] ), $jobs['40cd750bba9870f18aada2478b24840a'] );
        }
        $catalog['present']['blogname'] = 'changed'; $this->assertSame( $p, $this->catalog()['present'] );
        $this->assertSame( hash( 'sha256', 'alynt-fixed-projection.wp7.1.2.theme.v1' ), $catalog['profile'] );
    }
    public function test_every_required_key_and_exact_value_is_enforced() {
        $base = $this->catalog();
        foreach ( $base as $group => $values ) {
            if ( ! is_array( $values ) ) { $bad = $base; $bad[$group] = str_repeat( '0', 64 ); $this->assertFalse( Alynt_Fixed_Projection_Catalog::matches( $bad, ...$this->args() ) ); continue; }
            foreach ( $values as $key => $value ) {
                $bad = $base; unset( $bad[$group][$key] ); $this->assertFalse( Alynt_Fixed_Projection_Catalog::matches( $bad, ...$this->args() ), $group . ':' . $key );
                foreach ( array( null, false, 0, array(), new stdClass(), str_repeat( 'x', 65537 ), $value . ' ' ) as $wrong ) {
                    $bad = $base; $bad[$group][$key] = $wrong; $this->assertFalse( Alynt_Fixed_Projection_Catalog::matches( $bad, ...$this->args() ), $group . ':' . $key );
                }
            }
        }
        foreach ( array( 'SITEURL', 'siteurl ', 'widget_recent_entries', 'widget_recent_comments', 'hack_file', 'proof_owner' ) as $alias ) {
            $bad = $base; $bad['present'][$alias] = ''; $this->assertFalse( Alynt_Fixed_Projection_Catalog::matches( $bad, ...$this->args() ) );
            unset( $bad['present']['siteurl'] ); $this->assertFalse( Alynt_Fixed_Projection_Catalog::matches( $bad, ...$this->args() ) );
        }
        $bad = $base; $bad['absent'][0] = 'siteurl'; $this->assertFalse( Alynt_Fixed_Projection_Catalog::matches( $bad, ...$this->args() ) );
        $bad = $base; $bad['hidden']['hack_file'] = ''; $this->assertFalse( Alynt_Fixed_Projection_Catalog::matches( $bad, ...$this->args() ) );
        foreach ( array( null, false, new stdClass(), array(), $base + array( 'source' => 'other' ), array_reverse( $base, true ) ) as $bad ) $this->assertFalse( Alynt_Fixed_Projection_Catalog::matches( $bad, ...$this->args() ) );
    }
    public function test_cron_mutations_are_rejected_without_parsing_untrusted_serialization() {
        $base = $this->catalog(); $cron = $base['present']['cron'];
        foreach ( array( '1800000000' => '1700000000', 'i:2;' => 'i:3;', 'weekly' => 'hourly', 'i:604800;' => 'i:604801;', '40cd750bba9870f18aada2478b24840a' => str_repeat( '0', 32 ), 'a:0:{}' => 'a:1:{i:0;i:1;}' ) as $from => $to ) {
            $bad = $base; $bad['present']['cron'] = str_replace( $from, $to, $cron );
            $this->assertNotSame( $cron, $bad['present']['cron'] ); $this->assertFalse( Alynt_Fixed_Projection_Catalog::matches( $bad, ...$this->args() ) );
        }
    }
    public function test_virtual_epoch_and_strict_input_domains_never_refresh_original_deadline() {
        $this->catalog(); $args = $this->args();
        foreach ( $args as $i => $value ) foreach ( array( null, false, array(), new stdClass(), -1, 1.5, '1', PHP_INT_MAX ) as $bad ) {
            $changed = $args; $changed[$i] = $bad; $this->assertFalse( Alynt_Fixed_Projection_Catalog::build( ...$changed ) );
        }
        foreach ( array( array( 0, str_repeat( 'A', 12 ) ), array( 1, str_repeat( 'b', 24 ) . "\n" ), array( 2, 9999913600 ), array( 4, 21000 ), array( 4, 999 ), array( 5, 1799913601 ) ) as $change ) {
            $changed = $args; $changed[$change[0]] = $change[1]; $this->assertFalse( Alynt_Fixed_Projection_Catalog::build( ...$changed ) );
        }
        $args[4] = 20999; $args[5] = 1799913619;
        $later = Alynt_Fixed_Projection_Catalog::build( ...$args ); $this->assertSame( $this->catalog(), $later );
        $this->assertFalse( Alynt_Fixed_Projection_Catalog::build( ...array_merge( $args, array( 'extra' ) ) ) );
        $this->assertFalse( Alynt_Fixed_Projection_Catalog::matches( $later, ...array_merge( $args, array( 'extra' ) ) ) );
    }
}
