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

	/**
	 * Syncs the settings option cache after mutation.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private function sync_option_cache( array $settings ) {
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::OPTION_NAME, 'options' );
		}
		if ( function_exists( 'wp_cache_set' ) ) {
			wp_cache_set( self::OPTION_NAME, $settings, 'options' );
		}
	}

	/**
	 * Returns severity levels in ascending order.
	 *
	 * @return array<string,int>
	 *
	 * @since 0.1.0
	 */
	public static function severity_levels() {
		return array(
			'debug'    => 100,
			'info'     => 200,
			'warning'  => 300,
			'error'    => 400,
			'critical' => 500,
		);
	}

	/**
	 * Normalizes an optional Drime relative path.
	 *
	 * @param string $path Raw path.
	 * @return string
	 */
	private function sanitize_relative_path( $path ) {
		$path = sanitize_text_field( $path );
		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '#/+#', '/', $path );
		$path = trim( (string) $path );

		if ( '' === $path ) {
			return '';
		}

		$path = '/' . trim( $path, '/' );

		if ( false !== strpos( $path, '..' ) ) {
			return '';
		}

		$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ), 'strlen' ) );
		if ( count( $segments ) > self::MAX_RELATIVE_PATH_SEGMENTS ) {
			return '';
		}

		foreach ( $segments as $segment ) {
			if ( strlen( $segment ) > self::MAX_RELATIVE_PATH_SEGMENT_CHARS ) {
				return '';
			}
		}

		return $path;
	}

	/**
	 * Sanitizes a non-secret Drime folder hash.
	 *
	 * @param string $hash Hash.
	 * @return string
	 */
	private function sanitize_folder_hash( $hash ) {
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $hash );
	}

	/**
	 * Sanitizes a non-secret Drime display path.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function sanitize_display_path( $path ) {
		$path = sanitize_text_field( str_replace( '\\', '/', $path ) );
		$path = preg_replace( '#/+#', '/', $path );

		return trim( (string) $path, '/' );
	}

	/**
	 * Sanitizes a UUID v4 style identifier.
	 *
	 * @param string $uuid UUID.
	 * @return string
	 */
	private function sanitize_uuid( $uuid ) {
		$uuid = strtolower( trim( (string) $uuid ) );

		return preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $uuid ) ? $uuid : '';
	}

	/**
	 * Generates a UUID.
	 *
	 * @return string
	 */
	private function generate_site_uuid() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			$uuid = $this->sanitize_uuid( wp_generate_uuid4() );
			if ( '' !== $uuid ) {
				return $uuid;
			}
		}

		if ( function_exists( 'random_bytes' ) ) {
			$data    = random_bytes( 16 );
			$data[6] = chr( ( ord( $data[6] ) & 0x0f ) | 0x40 );
			$data[8] = chr( ( ord( $data[8] ) & 0x3f ) | 0x80 );
			$hex     = bin2hex( $data );

			return sprintf(
				'%s-%s-%s-%s-%s',
				substr( $hex, 0, 8 ),
				substr( $hex, 8, 4 ),
				substr( $hex, 12, 4 ),
				substr( $hex, 16, 4 ),
				substr( $hex, 20, 12 )
			);
		}

		$hash = md5( uniqid( 'alynt-drime-backups-', true ) );

		return sprintf(
			'%s-%s-4%s-%s-%s',
			substr( $hash, 0, 8 ),
			substr( $hash, 8, 4 ),
			substr( $hash, 13, 3 ),
			substr( '89ab', absint( hexdec( substr( $hash, 16, 1 ) ) ) % 4, 1 ) . substr( $hash, 17, 3 ),
			substr( $hash, 20, 12 )
		);
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

	/**
	 * Returns the default failure notification recipients.
	 *
	 * @return string
	 */
	private static function default_failure_email_recipients() {
		$admin_email = function_exists( 'get_option' ) ? get_option( 'admin_email', '' ) : '';

		return is_string( $admin_email ) ? self::sanitize_single_email( $admin_email ) : '';
	}

	/**
	 * Sanitizes comma- or newline-separated email recipients.
	 *
	 * @param string $recipients Raw recipients.
	 * @return string
	 */
	private function sanitize_email_recipients( $recipients ) {
		$recipients = preg_split( '/[\r\n,]+/', $recipients );
		$valid      = array();

		foreach ( (array) $recipients as $recipient ) {
			$recipient = self::sanitize_single_email( trim( (string) $recipient ) );
			if ( '' !== $recipient && self::is_valid_email( $recipient ) ) {
				$valid[] = $recipient;
			}
		}

		return implode( "\n", array_values( array_unique( $valid ) ) );
	}

	/**
	 * Sanitizes a single email value with a test-safe fallback.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private static function sanitize_single_email( $email ) {
		return trim( preg_replace( '/[\r\n]+/', '', (string) $email ) );
	}

	/**
	 * Validates a single email value with a conservative fallback.
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	private static function is_valid_email( $email ) {
		if ( function_exists( 'is_email' ) ) {
			return (bool) is_email( $email );
		}

		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
	}

	/**
	 * Sanitizes a severity level.
	 *
	 * @param string $level Level.
	 * @return string
	 */
	private function sanitize_level( $level ) {
		$level = sanitize_key( $level );

		return array_key_exists( $level, self::severity_levels() ) ? $level : 'warning';
	}
}
