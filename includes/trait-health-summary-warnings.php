<?php
/**
 * Health summary warning helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds redacted health warnings and local environment checks.
 *
 * @since 0.5.22
 */
trait Alynt_Drime_Backups_Uploader_Health_Summary_Warnings {
	/**
	 * Returns health warnings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param array<string,mixed> $cron_status Cron status.
	 * @return array<int,array<string,string>>
	 */
	private function warnings( array $settings, array $cron_status ) {
		$warnings = array();

		if ( empty( $settings['server_outbox_path'] ) ) {
			$warnings[] = array(
				'code'    => 'server_outbox_not_configured',
				'message' => __( 'Server outbox path is not configured.', 'alynt-drime-backups-uploader' ),
			);
		} elseif ( ! $this->server_outbox_readable( $settings ) ) {
			$warnings[] = array(
				'code'    => 'server_outbox_unreadable',
				'message' => __( 'Server outbox path is not readable by WordPress.', 'alynt-drime-backups-uploader' ),
			);
		}

		if ( isset( $cron_status['status'] ) && Alynt_Drime_Backups_Uploader_Cron_Health::STATUS_ATTENTION_NEEDED === $cron_status['status'] ) {
			$warnings[] = array(
				'code'    => 'server_cron_attention_needed',
				'message' => isset( $cron_status['reason'] ) ? (string) $cron_status['reason'] : __( 'Server cron attention is needed.', 'alynt-drime-backups-uploader' ),
			);
		}

		if ( $this->old_wpvivid_uploader_active() && ( ! empty( $settings['auto_scan_enabled'] ) || ! empty( $settings['backup_path_override'] ) ) ) {
			$warnings[] = array(
				'code'    => 'old_wpvivid_uploader_active',
				'message' => __( 'The old Alynt Drime WPvivid Uploader plugin is active. Disable automatic uploads in one uploader before using the WPvivid source here to avoid duplicate uploads.', 'alynt-drime-backups-uploader' ),
			);
		}

		return $warnings;
	}

	/**
	 * Returns whether the configured server outbox is readable.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return bool
	 */
	private function server_outbox_readable( array $settings ) {
		$path = isset( $settings['server_outbox_path'] ) ? trim( (string) $settings['server_outbox_path'] ) : '';

		return '' !== $path && is_dir( $path ) && is_readable( $path );
	}

	/**
	 * Returns whether the previous WPvivid-specific uploader line is active.
	 *
	 * @return bool
	 */
	private function old_wpvivid_uploader_active() {
		$basename = 'alynt-drime-wpvivid-uploader/alynt-drime-wpvivid-uploader.php';

		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}

		$active = get_option( 'active_plugins', array() );
		if ( is_array( $active ) && in_array( $basename, $active, true ) ) {
			return true;
		}

		if ( function_exists( 'get_site_option' ) ) {
			$network_active = get_site_option( 'active_sitewide_plugins', array() );
			return is_array( $network_active ) && isset( $network_active[ $basename ] );
		}

		return false;
	}
}
