<?php
/**
 * Plugin hook registration helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin hook registration helpers.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Plugin_Hooks {
	/**
	 * Registers hooks.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	private function hooks() {

		add_action( 'admin_menu', array( $this->admin_page, 'register_menu' ) );

		add_action( 'admin_enqueue_scripts', array( $this->admin_page, 'enqueue_assets' ) );

		add_action( 'admin_post_alynt_drime_backups_save_settings', array( $this, 'handle_save_settings' ) );

		add_action( 'admin_post_alynt_drime_backups_save_dashboard_connection', array( $this, 'handle_save_dashboard_connection' ) );

		add_action( 'admin_post_alynt_drime_backups_test_connection', array( $this, 'handle_test_connection' ) );

		add_action( 'admin_post_alynt_drime_backups_send_test_failure_email', array( $this, 'handle_send_test_failure_email' ) );

		add_action( 'admin_post_alynt_drime_backups_scan_now', array( $this, 'handle_scan_now' ) );

		add_action( 'admin_post_alynt_drime_backups_upload_next', array( $this, 'handle_upload_next' ) );

		add_action( 'admin_post_alynt_drime_backups_requeue_failed_upload', array( $this, 'handle_requeue_failed_upload' ) );

		add_action( 'admin_post_alynt_drime_backups_preview_remote_retention', array( $this, 'handle_preview_remote_retention' ) );

		add_action( 'admin_post_alynt_drime_backups_run_remote_retention', array( $this, 'handle_run_remote_retention' ) );

		add_action( 'admin_post_alynt_drime_backups_clear_active_upload', array( $this, 'handle_clear_active_upload' ) );

		add_action( 'admin_post_alynt_drime_backups_export_diagnostics', array( $this, 'handle_export_diagnostics' ) );

		add_action( 'admin_post_alynt_drime_backups_clear_diagnostics', array( $this, 'handle_clear_diagnostics' ) );

		add_action( 'wp_ajax_alynt_drime_backups_list_folders', array( $this, 'handle_ajax_list_folders' ) );

		add_action( 'wp_ajax_alynt_drime_backups_list_workspaces', array( $this, 'handle_ajax_list_workspaces' ) );

		add_action( 'wp_ajax_alynt_drime_backups_preview_destination', array( $this, 'handle_ajax_preview_destination' ) );

		add_action( 'rest_api_init', array( $this->dashboard_status_rest_controller, 'register_routes' ) );

		add_action( 'rest_api_init', array( $this->dashboard_action_intents_rest_controller, 'register_routes' ) );

		$this->cron->hooks();

		$this->remote_action_worker->hooks();
	}
}
