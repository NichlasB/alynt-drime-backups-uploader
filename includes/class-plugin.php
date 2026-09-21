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
	use Alynt_Drime_Backups_Uploader_Plugin_Hooks;
	use Alynt_Drime_Backups_Uploader_Plugin_Scan_Queue;
	use Alynt_Drime_Backups_Uploader_Plugin_Accessors;
	use Alynt_Drime_Backups_Uploader_Plugin_Admin_Actions;
	use Alynt_Drime_Backups_Uploader_Plugin_Retention_Actions;
	use Alynt_Drime_Backups_Uploader_Plugin_Destination_Ajax_Actions;
	use Alynt_Drime_Backups_Uploader_Plugin_Failed_Upload_Actions;
	use Alynt_Drime_Backups_Uploader_Plugin_Notification_Actions;
	use Alynt_Drime_Backups_Uploader_Plugin_Dashboard_Actions;

	/**

	 * Settings.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Settings
	 */

	private $settings;

	/**
	 * Dashboard connection.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Dashboard_Connection
	 */
	private $dashboard_connection;

	/**
	 * Remote action store.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Remote_Action_Store
	 */
	private $remote_action_store;

	/**
	 * Remote action verifier.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Remote_Action_Verifier
	 */
	private $remote_action_verifier;

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
	 * Dashboard status REST controller.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Dashboard_Status_REST_Controller
	 */
	private $dashboard_status_rest_controller;

	/**
	 * Dashboard action intents REST controller.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Dashboard_Action_Intents_REST_Controller
	 */
	private $dashboard_action_intents_rest_controller;

	/**
	 * Remote action worker.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Remote_Action_Worker
	 */
	private $remote_action_worker;

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

		$this->dashboard_connection = new Alynt_Drime_Backups_Uploader_Dashboard_Connection();

		$this->remote_action_store = new Alynt_Drime_Backups_Uploader_Remote_Action_Store();

		$this->remote_action_verifier = new Alynt_Drime_Backups_Uploader_Remote_Action_Verifier( $this->dashboard_connection );

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

		$this->health_summary = new Alynt_Drime_Backups_Uploader_Health_Summary( $this->settings, $this->queue, $this->registry, $this->cron_health, $this->dashboard_connection, $this->remote_action_store );

		$this->dashboard_status_rest_controller = new Alynt_Drime_Backups_Uploader_Dashboard_Status_REST_Controller( $this->dashboard_connection, $this->health_summary );

		$this->dashboard_action_intents_rest_controller = new Alynt_Drime_Backups_Uploader_Dashboard_Action_Intents_REST_Controller( $this->remote_action_verifier, $this->remote_action_store );

		$this->remote_action_worker = new Alynt_Drime_Backups_Uploader_Remote_Action_Worker( $this, $this->remote_action_store );

		$this->admin_page = new Alynt_Drime_Backups_Uploader_Admin_Page( $this );

		$this->hooks();
	}
}
