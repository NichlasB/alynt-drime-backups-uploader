<?php
/** Socket-free candidate values only. No WordPress, SQL, clocks or production caller. */
final class Alynt_Fixed_Projection_Catalog {
    // Fingerprint of the literal model label, NOT a core-source/installation certificate.
    const PROFILE = '8c8eb406d22d6e0033b64c2cd1255e64925fd08450e1c4965fbacdb36189cacc';

    /** Integer virtual wall seconds / monotonic milliseconds; no deadline refresh. */
    public static function build( $seed, $marker, $wall, $start, $tick, $now ) {
        if ( 6 !== func_num_args() || ! is_string( $seed ) || ! is_string( $marker ) ||
            1 !== preg_match( '/\A[a-f0-9]{12}\z/D', $seed ) || 1 !== preg_match( '/\A[a-f0-9]{24}\z/D', $marker ) ) return false;
        foreach ( array( $wall, $start, $tick, $now ) as $number ) if ( ! is_int( $number ) || $number < 0 ) return false;
        if ( $wall < 1000000000 || $wall > 9999913599 || $tick < $start || $tick - $start >= 20000 ||
            $now !== $wall + intdiv( $tick - $start, 1000 ) ) return false;
        $epoch = $wall + 86400;
        if ( $epoch <= $now ) return false;
        $present = array(
            'siteurl' => 'http://plugin-tester.local', 'home' => 'http://plugin-tester.local',
            'blogname' => 'Owned projection ' . $marker, 'active_plugins' => serialize( array() ),
            'template' => 'owned-no-theme', 'stylesheet' => 'owned-no-theme', 'WPLANG' => '',
            'wp_alynt_ro_' . $seed . '_user_roles' => serialize( array( 'administrator' => array(
                'name' => 'Administrator', 'capabilities' => array( 'read' => true, 'manage_options' => true ),
            ) ) ),
        );
        foreach ( array( 'pages', 'calendar', 'archives', 'media_audio', 'media_image', 'media_gallery', 'media_video',
            'meta', 'search', 'text', 'categories', 'recent-posts', 'recent-comments', 'rss', 'tag_cloud', 'nav_menu', 'custom_html', 'block' ) as $widget ) {
            $present['widget_' . $widget] = serialize( array( '_multiwidget' => 1 ) );
        }
        $present['link_manager_enabled'] = '0'; $present['permalink_structure'] = '';
        $jobs = array( 'wp_site_health_scheduled_check' => array( 'weekly', 604800 ),
            'wp_privacy_delete_old_export_files' => array( 'hourly', 3600 ),
            'wp_privacy_personal_data_cleanup_requests' => array( 'daily', 86400 ),
            'wp_version_check' => array( 'twicedaily', 43200 ), 'wp_update_plugins' => array( 'twicedaily', 43200 ),
            'wp_update_themes' => array( 'twicedaily', 43200 ) );
        $cron = array();
        foreach ( $jobs as $hook => $job ) $cron[$epoch][$hook][md5( serialize( array() ) )] = array(
            'schedule' => $job[0], 'args' => array(), 'interval' => $job[1],
        );
        $cron['version'] = 2; $present['cron'] = serialize( $cron );
        return array( 'profile' => self::PROFILE, 'present' => $present,
            'absent' => array( 'hack_file', 'wp_attachment_pages_enabled', 'category_base', 'tag_base', 'use_smilies', 'blog_public', 'theme_switched' ),
            'hidden' => array( 'alynt_drime_backups_settings' => 'owned-projection-canary-settings',
                'alynt_drime_backups_schedule_safety_state' => 'owned-projection-canary-safety', 'proof_owner' => $marker ) );
    }

    /** Compare bounded primitive strings; never unserialize a supplied value. */
    public static function matches( $candidate, $seed, $marker, $wall, $start, $tick, $now ) {
        if ( 7 !== func_num_args() || ! is_array( $candidate ) || array( 'profile', 'present', 'absent', 'hidden' ) !== array_keys( $candidate ) ) return false;
        foreach ( array( 'present' => 29, 'absent' => 7, 'hidden' => 3 ) as $group => $count ) {
            if ( ! is_array( $candidate[$group] ) || count( $candidate[$group] ) !== $count ) return false;
            foreach ( $candidate[$group] as $key => $value ) {
                if ( ! is_string( $value ) || strlen( $value ) > 65536 ||
                    ( 'absent' !== $group && ( ! is_string( $key ) || strlen( $key ) > 80 ) ) ) return false;
            }
        }
        $expected = self::build( $seed, $marker, $wall, $start, $tick, $now );
        return false !== $expected && $candidate === $expected;
    }
}
