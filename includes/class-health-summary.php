<?php
/**
 * Internal health summary service.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a redacted status payload for CLI, admin, and future monitoring.
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_Health_Summary {
	use Alynt_Drime_Backups_Uploader_Health_Summary_Backup_Sources;
	use Alynt_Drime_Backups_Uploader_Health_Summary_Schedule_Capability;
	use Alynt_Drime_Backups_Uploader_Health_Summary_WPvivid_Schedule_Policy;
	use Alynt_Drime_Backups_Uploader_Health_Summary_WPvivid_Activity;
	use Alynt_Drime_Backups_Uploader_Health_Summary_Warnings;

	const SCHEMA_VERSION                  = 1;
	const BACKUP_FRESHNESS_WINDOW_SECONDS = 36 * 60 * 60;
	const WPVIVID_SCHEDULE_GRACE_MIN      = 86400;
	const WPVIVID_SCHEDULE_GRACE_MAX      = 259200;
	const WPVIVID_SCHEDULE_POLICY_MAX     = 3888000; // 45 days.

	/**
	 * Settings.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Settings
	 */
	private $settings;

	/**
	 * Queue.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Queue
	 */
	private $queue;

	/**
	 * Registry.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Backup_Registry
	 */
	private $registry;

	/**
	 * Cron health.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Cron_Health
	 */
	private $cron_health;

	/**
	 * Dashboard connection state.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Dashboard_Connection|null
	 */
	private $dashboard_connection;

	/**
	 * Remote action store.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Remote_Action_Store|null
	 */
	private $remote_action_store;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Settings                  $settings Settings.
	 * @param Alynt_Drime_Backups_Uploader_Queue                     $queue Queue.
	 * @param Alynt_Drime_Backups_Uploader_Backup_Registry           $registry Registry.
	 * @param Alynt_Drime_Backups_Uploader_Cron_Health               $cron_health Cron health.
	 * @param Alynt_Drime_Backups_Uploader_Dashboard_Connection|null $dashboard_connection Dashboard connection.
	 * @param Alynt_Drime_Backups_Uploader_Remote_Action_Store|null  $remote_action_store Remote action store.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Settings $settings, Alynt_Drime_Backups_Uploader_Queue $queue, Alynt_Drime_Backups_Uploader_Backup_Registry $registry, Alynt_Drime_Backups_Uploader_Cron_Health $cron_health, $dashboard_connection = null, $remote_action_store = null ) {
		$this->settings             = $settings;
		$this->queue                = $queue;
		$this->registry             = $registry;
		$this->cron_health          = $cron_health;
		$this->dashboard_connection = $dashboard_connection instanceof Alynt_Drime_Backups_Uploader_Dashboard_Connection ? $dashboard_connection : null;
		$this->remote_action_store  = $remote_action_store instanceof Alynt_Drime_Backups_Uploader_Remote_Action_Store ? $remote_action_store : null;
	}

	/**
	 * Builds a compact health payload.
	 *
	 * @since 0.1.0
	 *
	 * @param int|false $next_scan Next scheduled scan timestamp.
	 * @param bool      $include_paths Whether to include local filesystem paths.
	 * @return array<string,mixed>
	 */
	public function status( $next_scan = false, $include_paths = false ) {
		$settings    = $this->settings->get();
		$queued      = $this->queue->all();
		$uploaded    = $this->registry->get_uploaded();
		$failed      = $this->registry->get_failed();
		$active      = $this->queue->get_active();
		$cron_state  = $this->cron_health->get();
		$cron_status = $this->cron_health->status( $settings, $next_scan );
		$warnings    = $this->warnings( $settings, $cron_status );

		$status = array(
			'schema_version'              => self::SCHEMA_VERSION,
			'site_uuid'                   => $this->settings->site_uuid(),
			'plugin_version'              => ALYNT_DRIME_BACKUPS_UPLOADER_VERSION,
			'queue_count'                 => count( $queued ),
			'uploaded_count'              => count( $uploaded ),
			'failed_count'                => count( $failed ),
			'active_upload'               => ! empty( $active ),
			'auto_scan_enabled'           => ! empty( $settings['auto_scan_enabled'] ),
			'server_cron_expected'        => ! empty( $settings['server_cron_expected'] ),
			'server_outbox_configured'    => ! empty( $settings['server_outbox_path'] ),
			'server_outbox_readable'      => $this->server_outbox_readable( $settings ),
			'wpvivid_override_configured' => ! empty( $settings['backup_path_override'] ),
			'old_wpvivid_uploader_active' => $this->old_wpvivid_uploader_active(),
			'wp_cron_disabled'            => $this->cron_health->is_wp_cron_disabled(),
			'cron_status'                 => isset( $cron_status['status'] ) ? (string) $cron_status['status'] : '',
			'cron_reason'                 => isset( $cron_status['reason'] ) ? (string) $cron_status['reason'] : '',
			'warning_count'               => count( $warnings ),
			'warnings'                    => $warnings,
			'last_runner'                 => isset( $cron_state['last_runner'] ) ? (string) $cron_state['last_runner'] : '',
			'last_runner_at'              => isset( $cron_state['last_runner_at'] ) ? absint( $cron_state['last_runner_at'] ) : 0,
			'last_scheduled_scan_at'      => isset( $cron_state['last_scheduled_scan_at'] ) ? absint( $cron_state['last_scheduled_scan_at'] ) : 0,
			'last_wp_cli_scan_at'         => isset( $cron_state['last_wp_cli_scan_at'] ) ? absint( $cron_state['last_wp_cli_scan_at'] ) : 0,
			'backup_sources'              => $this->backup_sources( $settings, $queued, $uploaded, $failed ),
		);

		if ( $this->dashboard_connection ) {
			$remote_actions = $this->dashboard_connection->remote_action_summary();

			if ( ! empty( $remote_actions ) ) {
				if ( $this->remote_action_store ) {
					$latest_action = $this->remote_action_store->latest_action();
					if ( ! empty( $latest_action ) ) {
						$remote_actions['last_action'] = $latest_action;
					}
				}
				$remote_actions['schedule_management'] = $this->schedule_management_capability( $settings, ! empty( $remote_actions['enabled'] ) );
				$status['remote_actions']              = $remote_actions;
			}
		}

		if ( $include_paths ) {
			$status['server_outbox_path']   = isset( $settings['server_outbox_path'] ) ? (string) $settings['server_outbox_path'] : '';
			$status['backup_path_override'] = isset( $settings['backup_path_override'] ) ? (string) $settings['backup_path_override'] : '';
		}

		return $status;
	}
}
