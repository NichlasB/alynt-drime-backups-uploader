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
	const SCHEMA_VERSION = 1;

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
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Settings        $settings Settings.
	 * @param Alynt_Drime_Backups_Uploader_Queue           $queue Queue.
	 * @param Alynt_Drime_Backups_Uploader_Backup_Registry $registry Registry.
	 * @param Alynt_Drime_Backups_Uploader_Cron_Health     $cron_health Cron health.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Settings $settings, Alynt_Drime_Backups_Uploader_Queue $queue, Alynt_Drime_Backups_Uploader_Backup_Registry $registry, Alynt_Drime_Backups_Uploader_Cron_Health $cron_health ) {
		$this->settings    = $settings;
		$this->queue       = $queue;
		$this->registry    = $registry;
		$this->cron_health = $cron_health;
	}

	/**
	 * Builds a compact health payload.
	 *
	 * @param int|false $next_scan Next scheduled scan timestamp.
	 * @param bool      $include_paths Whether to include local filesystem paths.
	 * @return array<string,mixed>
	 */
	public function status( $next_scan = false, $include_paths = false ) {
		$settings    = $this->settings->get();
		$active      = $this->queue->get_active();
		$cron_state  = $this->cron_health->get();
		$cron_status = $this->cron_health->status( $settings, $next_scan );

		$status = array(
			'schema_version'              => self::SCHEMA_VERSION,
			'plugin_version'              => ALYNT_DRIME_BACKUPS_UPLOADER_VERSION,
			'queue_count'                 => count( $this->queue->all() ),
			'uploaded_count'              => count( $this->registry->get_uploaded() ),
			'failed_count'                => count( $this->registry->get_failed() ),
			'active_upload'               => ! empty( $active ),
			'auto_scan_enabled'           => ! empty( $settings['auto_scan_enabled'] ),
			'server_cron_expected'        => ! empty( $settings['server_cron_expected'] ),
			'server_outbox_configured'    => ! empty( $settings['server_outbox_path'] ),
			'wpvivid_override_configured' => ! empty( $settings['backup_path_override'] ),
			'wp_cron_disabled'            => $this->cron_health->is_wp_cron_disabled(),
			'cron_status'                 => isset( $cron_status['status'] ) ? (string) $cron_status['status'] : '',
			'cron_reason'                 => isset( $cron_status['reason'] ) ? (string) $cron_status['reason'] : '',
			'last_runner'                 => isset( $cron_state['last_runner'] ) ? (string) $cron_state['last_runner'] : '',
			'last_runner_at'              => isset( $cron_state['last_runner_at'] ) ? absint( $cron_state['last_runner_at'] ) : 0,
			'last_scheduled_scan_at'      => isset( $cron_state['last_scheduled_scan_at'] ) ? absint( $cron_state['last_scheduled_scan_at'] ) : 0,
			'last_wp_cli_scan_at'         => isset( $cron_state['last_wp_cli_scan_at'] ) ? absint( $cron_state['last_wp_cli_scan_at'] ) : 0,
		);

		if ( $include_paths ) {
			$status['server_outbox_path']   = isset( $settings['server_outbox_path'] ) ? (string) $settings['server_outbox_path'] : '';
			$status['backup_path_override'] = isset( $settings['backup_path_override'] ) ? (string) $settings['backup_path_override'] : '';
		}

		return $status;
	}
}
