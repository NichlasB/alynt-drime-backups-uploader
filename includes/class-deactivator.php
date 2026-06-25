<?php
/**
 * Deactivation tasks.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivation handler.
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_Deactivator {
	/**
	 * Runs on deactivation.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( Alynt_Drime_Backups_Uploader_Cron::SCAN_EVENT );
		wp_clear_scheduled_hook( Alynt_Drime_Backups_Uploader_Cron::UPLOAD_EVENT );
	}
}
