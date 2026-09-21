<?php
/**
 * Plugin dependency accessors.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin dependency accessors.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Plugin_Accessors {
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
	 * Dashboard connection getter.
	 *
	 * @return Alynt_Drime_Backups_Uploader_Dashboard_Connection
	 *
	 * @since 0.5.3
	 */
	public function dashboard_connection() {

		return $this->dashboard_connection;
	}

	/**
	 * Remote action store getter.
	 *
	 * @return Alynt_Drime_Backups_Uploader_Remote_Action_Store
	 *
	 * @since 0.5.12
	 */
	public function remote_action_store() {

		return $this->remote_action_store;
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
	 * Cron getter.
	 *
	 * @return Alynt_Drime_Backups_Uploader_Cron
	 *
	 * @since 0.5.19
	 */
	public function cron() {

		return $this->cron;
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
