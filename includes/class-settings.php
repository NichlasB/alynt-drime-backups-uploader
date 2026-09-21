<?php
/**
 * Settings storage and validation.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin settings.
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_Settings {
	use Alynt_Drime_Backups_Uploader_Settings_Section_Sanitizers;
	use Alynt_Drime_Backups_Uploader_Settings_Path_Sanitizers;
	use Alynt_Drime_Backups_Uploader_Settings_Site_Identity;
	use Alynt_Drime_Backups_Uploader_Settings_Notification_Sanitizers;

	const OPTION_NAME                         = 'alynt_drime_backups_settings';
	const MIN_MULTIPART_CHUNK_SIZE_MB         = 5;
	const MAX_MULTIPART_CHUNK_SIZE_MB         = 256;
	const DEFAULT_MULTIPART_CHUNK_SIZE_MB     = 128;
	const DEFAULT_MIN_FILE_AGE_SECONDS        = 300;
	const MIN_REMOTE_RETENTION_DAYS           = 1;
	const MAX_REMOTE_RETENTION_DAYS           = 365;
	const DEFAULT_REMOTE_RETENTION_DAYS       = 60;
	const MIN_SERVER_LOCAL_RETENTION_KEEP     = 1;
	const MAX_SERVER_LOCAL_RETENTION_KEEP     = 30;
	const DEFAULT_SERVER_LOCAL_RETENTION_KEEP = 2;
	const MAX_RELATIVE_PATH_SEGMENTS          = 20;
	const MAX_RELATIVE_PATH_SEGMENT_CHARS     = 120;
	const PERSONAL_WORKSPACE_ID               = 0;
	const ALLOWED_WORKSPACE_IDS_CONSTANT      = 'ALYNT_DRIME_ALLOWED_WORKSPACE_IDS';

	/**
	 * Returns default settings.
	 *
	 * @return array<string,mixed>
	 *
	 * @since 0.1.0
	 */
	public static function defaults() {
		return array(
			'api_token'                      => '',
			'workspace_id'                   => 0,
			'parent_folder_id'               => '',
			'parent_folder_hash'             => '',
			'parent_folder_display_path'     => '',
			'relative_path'                  => '',
			'backup_path_override'           => '',
			'server_outbox_path'             => '',
			'server_relative_path'           => '',
			'wpvivid_relative_path'          => '',
			'site_uuid'                      => '',
			'duplicate_mode'                 => 'skip',
			'auto_scan_enabled'              => false,
			'server_cron_expected'           => false,
			'scan_interval'                  => 'fifteen_minutes',
			'min_file_age_seconds'           => self::DEFAULT_MIN_FILE_AGE_SECONDS,
			'multipart_chunk_size_mb'        => self::DEFAULT_MULTIPART_CHUNK_SIZE_MB,
			'delete_local_after_upload'      => false,
			'server_local_retention_enabled' => false,
			'server_local_retention_keep'    => self::DEFAULT_SERVER_LOCAL_RETENTION_KEEP,
			'remote_retention_enabled'       => false,
			'remote_retention_days'          => self::DEFAULT_REMOTE_RETENTION_DAYS,
			'failure_email_enabled'          => false,
			'failure_email_recipients'       => self::default_failure_email_recipients(),
			'max_retries'                    => 3,
			'diagnostics_enabled'            => false,
			'diagnostics_min_level'          => 'warning',
			'diagnostics_retention'          => 100,
		);
	}

	/**
	 * Ensures the settings option exists with autoload disabled.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public static function maybe_install() {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::defaults(), '', false );
		}
	}

	/**
	 * Returns merged settings.
	 *
	 * @return array<string,mixed>
	 *
	 * @since 0.1.0
	 */
	public function get() {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return array_merge( self::defaults(), $settings );
	}

	/**
	 * Updates settings after sanitization.
	 *
	 * @param array<string,mixed> $raw Raw settings.
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.1.0
	 */
	public function update( array $raw ) {
		$sanitized              = $this->sanitize( $raw, $this->get() );
		$workspace_id_submitted = array_key_exists( 'workspace_id', $raw ) && '' !== trim( (string) wp_unslash( $raw['workspace_id'] ) );
		if ( $workspace_id_submitted && ! self::is_workspace_id_allowed( absint( $sanitized['workspace_id'] ) ) ) {
			return new WP_Error( 'alynt_drime_workspace_not_allowed', self::workspace_not_allowed_message() );
		}

		update_option( self::OPTION_NAME, $sanitized, false );
		$this->sync_option_cache( $sanitized );

		return $sanitized;
	}

	/**
	 * Returns whether a settings array matches the persisted option.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return bool
	 *
	 * @since 0.1.0
	 */
	public function is_persisted( array $settings ) {
		return $settings === $this->get();
	}

	/**
	 * Sanitizes settings.
	 *
	 * @param array<string,mixed> $raw Raw settings.
	 * @param array<string,mixed> $current Current settings.
	 * @return array<string,mixed>
	 *
	 * @since 0.1.0
	 */
	public function sanitize( array $raw, array $current ) {
		$settings              = self::defaults();
		$settings['site_uuid'] = $this->sanitize_uuid( isset( $current['site_uuid'] ) ? (string) $current['site_uuid'] : '' );

		$this->sanitize_token_settings( $raw, $current, $settings );
		$this->sanitize_destination_settings( $raw, $current, $settings );
		$this->sanitize_source_settings( $raw, $settings );
		$this->sanitize_behavior_settings( $raw, $settings );
		$this->sanitize_failure_notification_settings( $raw, $settings );
		$this->sanitize_diagnostics_settings( $raw, $settings );

		return $settings;
	}

	/**
	 * Returns whether a token is configured.
	 *
	 * @return bool
	 *
	 * @since 0.1.0
	 */
	public function has_token() {
		$settings = $this->get();

		return '' !== trim( (string) $settings['api_token'] );
	}

	/**
	 * Returns allowed Drime workspace IDs from wp-config.php.
	 *
	 * @since 0.2.0
	 *
	 * @return array<int,int>
	 */
	public static function allowed_workspace_ids() {
		if ( ! defined( self::ALLOWED_WORKSPACE_IDS_CONSTANT ) ) {
			return array();
		}

		$value = constant( self::ALLOWED_WORKSPACE_IDS_CONSTANT );
		if ( is_array( $value ) ) {
			$raw_ids = $value;
		} else {
			$raw_ids = preg_split( '/[\s,]+/', (string) $value );
		}

		$ids = array();
		foreach ( $raw_ids as $raw_id ) {
			$id = absint( $raw_id );
			if ( self::PERSONAL_WORKSPACE_ID === $id ) {
				continue;
			}

			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Returns whether workspace allowlisting is enabled.
	 *
	 * @since 0.2.0
	 *
	 * @return bool
	 */
	public static function workspace_allowlist_enabled() {
		return defined( self::ALLOWED_WORKSPACE_IDS_CONSTANT );
	}

	/**
	 * Returns whether a workspace ID may be used for backups.
	 *
	 * @since 0.2.0
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return bool
	 */
	public static function is_workspace_id_allowed( $workspace_id ) {
		$workspace_id = absint( $workspace_id );
		if ( self::PERSONAL_WORKSPACE_ID === $workspace_id ) {
			return false;
		}

		$allowed = self::allowed_workspace_ids();
		if ( empty( $allowed ) ) {
			return true;
		}

		return in_array( $workspace_id, $allowed, true );
	}

	/**
	 * Returns the admin-facing workspace restriction message.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public static function workspace_not_allowed_message() {
		if ( self::workspace_allowlist_enabled() ) {
			return __( 'The selected Drime workspace is not allowed by this site configuration. Choose an allowed workspace or update ALYNT_DRIME_ALLOWED_WORKSPACE_IDS in wp-config.php.', 'alynt-drime-backups-uploader' );
		}

		return __( 'The personal/default Drime workspace cannot be used for backup destinations. Choose a team/workspace destination before saving or uploading.', 'alynt-drime-backups-uploader' );
	}

	/**
	 * Returns a stable non-secret site UUID, generating it when missing.
	 *
	 * @since 0.1.1
	 *
	 * @return string
	 */
	public function site_uuid() {
		$settings = $this->get();
		$uuid     = $this->sanitize_uuid( isset( $settings['site_uuid'] ) ? (string) $settings['site_uuid'] : '' );

		if ( '' !== $uuid ) {
			return $uuid;
		}

		$uuid                  = $this->generate_site_uuid();
		$settings['site_uuid'] = $uuid;
		update_option( self::OPTION_NAME, $settings, false );
		$this->sync_option_cache( $settings );

		return $uuid;
	}
}
