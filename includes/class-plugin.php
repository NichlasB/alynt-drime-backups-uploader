<?php
/**
 * Plugin orchestrator.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

/**
 * Main plugin class.
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_Plugin {
	use Alynt_Drime_Backups_Uploader_Plugin_Admin_Actions;
	use Alynt_Drime_Backups_Uploader_Plugin_Retention_Actions;
	use Alynt_Drime_Backups_Uploader_Plugin_Destination_Ajax_Actions;
	use Alynt_Drime_Backups_Uploader_Plugin_Failed_Upload_Actions;
	use Alynt_Drime_Backups_Uploader_Plugin_Notification_Actions;

	/**

	 * Settings.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Settings
	 */

	private $settings;

	/**

	 * Logger.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Logger
	 */

	private $logger;

	/**
	 * Failure notifier.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Failure_Notifier
	 */
	private $notifier;

	/**

	 * Detector.
	 *
	 * @var Alynt_Drime_Backups_Uploader_WPvivid_Detector
	 */

	private $detector;

	/**

	 * Scanner.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Scanner
	 */

	private $scanner;

	/**

	 * Registry.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Backup_Registry
	 */

	private $registry;

	/**

	 * Queue.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Queue
	 */

	private $queue;

	/**

	 * Client.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Drime_Client
	 */

	private $client;

	/**
	 * Folder browser.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Folder_Browser
	 */
	private $folder_browser;

	/**
	 * Workspace browser.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Workspace_Browser
	 */
	private $workspace_browser;

	/**

	 * Uploader.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Uploader
	 */

	private $uploader;

	/**

	 * Cron.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Cron
	 */

	private $cron;

	/**
	 * Remote retention.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Remote_Retention
	 */
	private $retention;

	/**
	 * Cron health.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Cron_Health
	 */
	private $cron_health;

	/**
	 * Health summary.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Health_Summary
	 */
	private $health_summary;

	/**

	 * Admin page.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Admin_Page
	 */

	private $admin_page;

	/**

	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {

		$this->settings = new Alynt_Drime_Backups_Uploader_Settings();

		$this->logger = new Alynt_Drime_Backups_Uploader_Logger( $this->settings );

		$this->notifier = new Alynt_Drime_Backups_Uploader_Failure_Notifier( $this->settings, $this->logger );

		$this->detector = new Alynt_Drime_Backups_Uploader_WPvivid_Detector();

		$this->scanner = new Alynt_Drime_Backups_Uploader_Scanner( $this->settings, $this->detector, $this->logger );

		$this->registry = new Alynt_Drime_Backups_Uploader_Backup_Registry();

		$this->queue = new Alynt_Drime_Backups_Uploader_Queue();

		$this->client = new Alynt_Drime_Backups_Uploader_Drime_Client( $this->settings, $this->logger );

		$this->workspace_browser = new Alynt_Drime_Backups_Uploader_Workspace_Browser( $this->client );

		$this->folder_browser = new Alynt_Drime_Backups_Uploader_Folder_Browser( $this->settings, $this->client, $this->logger );

		$this->uploader = new Alynt_Drime_Backups_Uploader_Uploader( $this->settings, $this->client, $this->queue, $this->registry, $this->logger, $this->notifier );

		$this->cron = new Alynt_Drime_Backups_Uploader_Cron( $this );

		$this->retention = new Alynt_Drime_Backups_Uploader_Remote_Retention( $this->settings, $this->client, $this->registry, $this->logger );

		$this->cron_health = new Alynt_Drime_Backups_Uploader_Cron_Health();

		$this->health_summary = new Alynt_Drime_Backups_Uploader_Health_Summary( $this->settings, $this->queue, $this->registry, $this->cron_health );

		$this->admin_page = new Alynt_Drime_Backups_Uploader_Admin_Page( $this );

		$this->hooks();
	}

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

		$this->cron->hooks();
	}

	/**
	 * Scans and queues stable files.
	 *
	 * @return array<string,mixed>
	 *
	 * @since 0.1.0
	 */
	public function scan_and_queue() {
		$result = $this->scanner->scan();

		if ( ! empty( $result['errors'] ) ) {
			$this->log_scan_errors( $result['errors'] );
			return $result;
		}

		$queued           = $this->queue_scan_candidates( $result['candidates'] );
		$result['queued'] = $queued;
		if ( $this->queue->last_persistence_failed() ) {
			$message            = __( 'Backup packages were found, but the upload queue could not be saved. Confirm the site database is writable, then scan again.', 'alynt-drime-backups-uploader' );
			$result['errors'][] = $message;
			$this->logger->event( 'scanner', 'error', 'queue_save_failed', $message, array( 'found' => count( $result['candidates'] ) ) );
		}
		$this->logger->event(
			'scanner',
			'info',
			'scan_finished',
			'Backup scan finished.',
			array(
				'found'  => count( $result['candidates'] ),
				'queued' => $queued,
			)
		);

		return $result;
	}

	/**
	 * Logs scan errors.
	 *
	 * @param array<int,string> $errors Errors.
	 * @return void
	 *
	 * @since 0.1.0
	 */
	private function log_scan_errors( array $errors ) {
		foreach ( $errors as $error ) {
			$this->logger->event( 'scanner', 'error', 'scan_error', $error );
		}
	}

	/**
	 * Queues candidates that have not already uploaded.
	 *
	 * @param array<int,array<string,mixed>> $candidates Candidates.
	 * @return int
	 */
	private function queue_scan_candidates( array $candidates ) {
		return $this->queue->add_many( $candidates, $this->registry->get_uploaded() );
	}

	/**

	 * Settings getter.
	 *
	 * @return Alynt_Drime_Backups_Uploader_Settings
	 *
	 * @since 0.1.0
	 */
	public function settings() {

		return $this->settings;
	}

	/**

	 * Logger getter.
	 *
	 * @return Alynt_Drime_Backups_Uploader_Logger
	 *
	 * @since 0.1.0
	 */
	public function logger() {

		return $this->logger;
	}

	/**
	 * Failure notifier getter.
	 *
	 * @return Alynt_Drime_Backups_Uploader_Failure_Notifier
	 *
	 * @since 0.1.0
	 */
	public function notifier() {

		return $this->notifier;
	}

	/**

	 * Detector getter.
	 *
	 * @return Alynt_Drime_Backups_Uploader_WPvivid_Detector
	 *
	 * @since 0.1.0
	 */
	public function detector() {

		return $this->detector;
	}

	/**

	 * Registry getter.
	 *
	 * @return Alynt_Drime_Backups_Uploader_Backup_Registry
	 *
	 * @since 0.1.0
	 */
	public function registry() {

		return $this->registry;
	}

	/**

	 * Queue getter.
	 *
	 * @return Alynt_Drime_Backups_Uploader_Queue
	 *
	 * @since 0.1.0
	 */
	public function queue() {

		return $this->queue;
	}

	/**
	 * Folder browser getter.
	 *
	 * @return Alynt_Drime_Backups_Uploader_Folder_Browser
	 *
	 * @since 0.3.0
	 */
	public function folder_browser() {

		return $this->folder_browser;
	}

	/**
	 * Workspace browser getter.
	 *
	 * @return Alynt_Drime_Backups_Uploader_Workspace_Browser
	 *
	 * @since 0.2.0
	 */
	public function workspace_browser() {

		return $this->workspace_browser;
	}

	/**

	 * Uploader getter.
	 *
	 * @return Alynt_Drime_Backups_Uploader_Uploader
	 *
	 * @since 0.1.0
	 */
	public function uploader() {

		return $this->uploader;
	}

	/**
	 * Remote retention getter.
	 *
	 * @return Alynt_Drime_Backups_Uploader_Remote_Retention
	 *
	 * @since 0.1.0
	 */
	public function retention() {

		return $this->retention;
	}

	/**
	 * Cron health getter.
	 *
	 * @return Alynt_Drime_Backups_Uploader_Cron_Health
	 *
	 * @since 0.3.0
	 */
	public function cron_health() {

		return $this->cron_health;
	}

	/**
	 * Health summary getter.
	 *
	 * @return Alynt_Drime_Backups_Uploader_Health_Summary
	 *
	 * @since 0.1.0
	 */
	public function health_summary() {

		return $this->health_summary;
	}
}
