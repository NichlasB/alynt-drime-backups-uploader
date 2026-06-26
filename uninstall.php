<?php
/**
 * Uninstall cleanup.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$alynt_drime_backups_options = array(
	'alynt_drime_backups_settings',
	'alynt_drime_backups_uploaded_files',
	'alynt_drime_backups_failed_uploads',
	'alynt_drime_backups_drime_locations',
	'alynt_drime_backups_failure_notifications',
	'alynt_drime_backups_cron_health',
	'alynt_drime_backups_upload_queue',
	'alynt_drime_backups_active_upload',
	'alynt_drime_backups_upload_lock',
	'alynt_drime_backups_logs',
	'alynt_drime_backups_file_snapshots',
	'alynt_drime_backups_outbox_file_snapshots',
);

$alynt_drime_backups_cron_hooks = array(
	'alynt_drime_backups_scan_event',
	'alynt_drime_backups_upload_event',
);

$alynt_drime_backups_cleanup_site = static function () use ( $alynt_drime_backups_options, $alynt_drime_backups_cron_hooks ) {
	foreach ( $alynt_drime_backups_options as $alynt_drime_backups_option ) {
		delete_option( $alynt_drime_backups_option );
	}

	foreach ( $alynt_drime_backups_cron_hooks as $alynt_drime_backups_cron_hook ) {
		wp_clear_scheduled_hook( $alynt_drime_backups_cron_hook );
	}
};

if ( is_multisite() ) {
	$alynt_drime_backups_sites = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $alynt_drime_backups_sites as $alynt_drime_backups_site_id ) {
		switch_to_blog( (int) $alynt_drime_backups_site_id );
		$alynt_drime_backups_cleanup_site();
		restore_current_blog();
	}

	return;
}

$alynt_drime_backups_cleanup_site();
