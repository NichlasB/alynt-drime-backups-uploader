<?php
/**
 * Settings section sanitizers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.21
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizes grouped admin settings sections before persistence.
 *
 * @since 0.5.21
 */
trait Alynt_Drime_Backups_Uploader_Settings_Section_Sanitizers {
	/**
	 * Sanitizes token settings.
	 *
	 * @param array<string,mixed> $raw Raw settings.
	 * @param array<string,mixed> $current Current settings.
	 * @param array<string,mixed> $settings Sanitized settings.
	 * @return void
	 */
	private function sanitize_token_settings( array $raw, array $current, array &$settings ) {
		$incoming_token = isset( $raw['api_token'] ) ? trim( (string) wp_unslash( $raw['api_token'] ) ) : '';
		if ( '' === $incoming_token || '************' === $incoming_token ) {
			$settings['api_token'] = isset( $current['api_token'] ) ? (string) $current['api_token'] : '';
		} else {
			$settings['api_token'] = sanitize_text_field( $incoming_token );
		}
	}

	/**
	 * Sanitizes Drime destination settings.
	 *
	 * @param array<string,mixed> $raw Raw settings.
	 * @param array<string,mixed> $current Current settings.
	 * @param array<string,mixed> $settings Sanitized settings.
	 * @return void
	 */
	private function sanitize_destination_settings( array $raw, array $current, array &$settings ) {
		$current_workspace_id = isset( $current['workspace_id'] ) ? absint( $current['workspace_id'] ) : 0;
		if ( array_key_exists( 'workspace_id', $raw ) ) {
			$raw_workspace_id         = trim( (string) wp_unslash( $raw['workspace_id'] ) );
			$settings['workspace_id'] = '' === $raw_workspace_id ? 0 : max( 0, absint( $raw_workspace_id ) );
		} else {
			$settings['workspace_id'] = $current_workspace_id;
		}

		if ( isset( $raw['parent_folder_id'] ) ) {
			$parent_folder_id             = trim( (string) wp_unslash( $raw['parent_folder_id'] ) );
			$settings['parent_folder_id'] = '' === $parent_folder_id ? '' : (string) absint( $parent_folder_id );
		}

		$settings['parent_folder_hash']         = isset( $raw['parent_folder_hash'] ) ? $this->sanitize_folder_hash( (string) wp_unslash( $raw['parent_folder_hash'] ) ) : '';
		$settings['parent_folder_display_path'] = isset( $raw['parent_folder_display_path'] ) ? $this->sanitize_display_path( (string) wp_unslash( $raw['parent_folder_display_path'] ) ) : '';
		if ( '' === $settings['parent_folder_id'] ) {
			$settings['parent_folder_hash']         = '';
			$settings['parent_folder_display_path'] = '';
		}

		if ( $settings['workspace_id'] !== $current_workspace_id ) {
			$settings['parent_folder_id']           = '';
			$settings['parent_folder_hash']         = '';
			$settings['parent_folder_display_path'] = '';
		}

		$settings['relative_path'] = isset( $raw['relative_path'] ) ? $this->sanitize_relative_path( (string) wp_unslash( $raw['relative_path'] ) ) : '';
	}

	/**
	 * Sanitizes backup source settings.
	 *
	 * @param array<string,mixed> $raw Raw settings.
	 * @param array<string,mixed> $settings Sanitized settings.
	 * @return void
	 */
	private function sanitize_source_settings( array $raw, array &$settings ) {
		if ( isset( $raw['backup_path_override'] ) ) {
			$settings['backup_path_override'] = sanitize_text_field( wp_unslash( $raw['backup_path_override'] ) );
		}

		if ( isset( $raw['server_outbox_path'] ) ) {
			$settings['server_outbox_path'] = sanitize_text_field( wp_unslash( $raw['server_outbox_path'] ) );
		}

		if ( isset( $raw['server_relative_path'] ) ) {
			$settings['server_relative_path'] = $this->sanitize_relative_path( (string) wp_unslash( $raw['server_relative_path'] ) );
		}

		if ( isset( $raw['wpvivid_relative_path'] ) ) {
			$settings['wpvivid_relative_path'] = $this->sanitize_relative_path( (string) wp_unslash( $raw['wpvivid_relative_path'] ) );
		}
	}

	/**
	 * Sanitizes upload behavior settings.
	 *
	 * @param array<string,mixed> $raw Raw settings.
	 * @param array<string,mixed> $settings Sanitized settings.
	 * @return void
	 */
	private function sanitize_behavior_settings( array $raw, array &$settings ) {
		$duplicate_mode             = isset( $raw['duplicate_mode'] ) ? sanitize_key( wp_unslash( $raw['duplicate_mode'] ) ) : 'skip';
		$settings['duplicate_mode'] = in_array( $duplicate_mode, array( 'skip', 'rename' ), true ) ? $duplicate_mode : 'skip';

		$settings['auto_scan_enabled']              = ! empty( $raw['auto_scan_enabled'] );
		$settings['server_cron_expected']           = ! empty( $raw['server_cron_expected'] );
		$settings['scan_interval']                  = 'fifteen_minutes';
		$settings['min_file_age_seconds']           = isset( $raw['min_file_age_seconds'] ) ? max( 60, absint( $raw['min_file_age_seconds'] ) ) : self::DEFAULT_MIN_FILE_AGE_SECONDS;
		$settings['multipart_chunk_size_mb']        = isset( $raw['multipart_chunk_size_mb'] ) ? max( self::MIN_MULTIPART_CHUNK_SIZE_MB, min( self::MAX_MULTIPART_CHUNK_SIZE_MB, absint( $raw['multipart_chunk_size_mb'] ) ) ) : self::DEFAULT_MULTIPART_CHUNK_SIZE_MB;
		$settings['delete_local_after_upload']      = ! empty( $raw['delete_local_after_upload'] );
		$settings['server_local_retention_enabled'] = ! empty( $raw['server_local_retention_enabled'] );
		$settings['server_local_retention_keep']    = isset( $raw['server_local_retention_keep'] ) ? max( self::MIN_SERVER_LOCAL_RETENTION_KEEP, min( self::MAX_SERVER_LOCAL_RETENTION_KEEP, absint( $raw['server_local_retention_keep'] ) ) ) : self::DEFAULT_SERVER_LOCAL_RETENTION_KEEP;
		$settings['remote_retention_enabled']       = ! empty( $raw['remote_retention_enabled'] );
		$settings['remote_retention_days']          = isset( $raw['remote_retention_days'] ) ? $this->clamp_remote_retention_days( $raw['remote_retention_days'] ) : self::DEFAULT_REMOTE_RETENTION_DAYS;
		$settings['max_retries']                    = isset( $raw['max_retries'] ) ? max( 0, min( 10, absint( $raw['max_retries'] ) ) ) : 3;
	}

	/**
	 * Sanitizes failure notification settings.
	 *
	 * @param array<string,mixed> $raw Raw settings.
	 * @param array<string,mixed> $settings Sanitized settings.
	 * @return void
	 */
	private function sanitize_failure_notification_settings( array $raw, array &$settings ) {
		$settings['failure_email_enabled']    = ! empty( $raw['failure_email_enabled'] );
		$settings['failure_email_recipients'] = $this->sanitize_email_recipients( isset( $raw['failure_email_recipients'] ) ? (string) wp_unslash( $raw['failure_email_recipients'] ) : self::default_failure_email_recipients() );
	}

	/**
	 * Sanitizes diagnostics settings.
	 *
	 * @param array<string,mixed> $raw Raw settings.
	 * @param array<string,mixed> $settings Sanitized settings.
	 * @return void
	 */
	private function sanitize_diagnostics_settings( array $raw, array &$settings ) {
		$settings['diagnostics_enabled']   = ! empty( $raw['diagnostics_enabled'] );
		$settings['diagnostics_min_level'] = $this->sanitize_level( isset( $raw['diagnostics_min_level'] ) ? (string) wp_unslash( $raw['diagnostics_min_level'] ) : 'warning' );
		$settings['diagnostics_retention'] = isset( $raw['diagnostics_retention'] ) ? max( 25, min( 500, absint( $raw['diagnostics_retention'] ) ) ) : 100;
	}

	/**
	 * Clamps remote retention age to the supported day range.
	 *
	 * @param mixed $days Raw day count.
	 * @return int
	 */
	private function clamp_remote_retention_days( $days ) {
		return max( self::MIN_REMOTE_RETENTION_DAYS, min( self::MAX_REMOTE_RETENTION_DAYS, (int) $days ) );
	}
}
