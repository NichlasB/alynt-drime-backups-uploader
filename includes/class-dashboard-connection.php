<?php
/**
 * Dashboard connection storage.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores local central-dashboard pairing state.
 *
 * This class intentionally does not expose a status endpoint, contact a
 * dashboard, or store raw credential material. Version 1 begins disabled and
 * remains disabled until a later authenticated endpoint slice completes.
 *
 * @since 0.5.3
 */
class Alynt_Drime_Backups_Uploader_Dashboard_Connection {
	use Alynt_Drime_Backups_Uploader_Dashboard_Connection_Token_Parsing;
	use Alynt_Drime_Backups_Uploader_Dashboard_Connection_Sanitizers;
	use Alynt_Drime_Backups_Uploader_Dashboard_Connection_State_Transitions;
	use Alynt_Drime_Backups_Uploader_Dashboard_Connection_Enrollment;

	const OPTION_NAME                      = 'alynt_drime_backups_dashboard_connection';
	const STATUS_DISABLED                  = 'disabled';
	const STATUS_READY                     = 'ready';
	const STATUS_TOKEN_READY               = 'token_ready';
	const STATUS_CONFIRMED                 = 'confirmed';
	const STATUS_PAIRED                    = 'paired';
	const STATUS_REVOKED                   = 'revoked';
	const TOKEN_PREFIX                     = 'adb1.';
	const ACTION_TOKEN_PREFIX              = 'adb2a.';
	const POLLING_AUTH_PREFIX              = 'adb-poll-v1.';
	const PROTOCOL_VERSION                 = 1;
	const STATUS_SCHEMA_VERSION            = 1;
	const ACTION_PROTOCOL_VERSION          = 2;
	const ACTION_SCAN_UPLOAD_NOW           = 'scan_upload_now';
	const ACTION_SCHEDULE_PREVIEW          = 'schedule_preview';
	const ACTION_SCHEDULE_APPLY            = 'schedule_apply';
	const ACTION_SCHEDULE_ROLLBACK_PREVIEW = 'schedule_rollback_preview';
	const ACTION_MIN_INTERVAL              = 3600;
	const ACTION_SCHEDULE_MIN_INTERVAL     = 60;
	const ACTION_TOKEN_PURPOSE             = 'remote_action_opt_in';

	/**
	 * Returns default connection state.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'connection_status'                    => self::STATUS_DISABLED,
			'dashboard_origin'                     => '',
			'expected_client_origin'               => '',
			'pending_enrollment_id'                => '',
			'pairing_expires_at'                   => '',
			'dashboard_origin_confirmed'           => false,
			'dashboard_site_public_id'             => '',
			'polling_key_id'                       => '',
			'polling_credential_verifier'          => '',
			'paired_at'                            => 0,
			'revoked_at'                           => 0,
			'last_error_code'                      => '',
			'last_authenticated_read_at'           => 0,
			'status_endpoint_enabled'              => false,
			'remote_actions_enabled'               => false,
			'action_key_id'                        => '',
			'action_public_key'                    => '',
			'remote_actions_opted_in_at'           => 0,
			'schedule_mutation_enabled'            => false,
			'schedule_mutation_enabled_at'         => 0,
			'schedule_rollback_preview_enabled'    => false,
			'schedule_rollback_preview_enabled_at' => 0,
		);
	}

	/**
	 * Ensures the dashboard connection option exists with autoload disabled.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::defaults(), '', false );
		}
	}

	/**
	 * Returns stored connection state merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public function get() {
		$state = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $state ) ) {
			$state = array();
		}

		return $this->sanitize_state( array_merge( self::defaults(), $state ) );
	}

	/**
	 * Returns whether the future external status endpoint may be exposed.
	 *
	 * @return bool
	 */
	public function is_status_endpoint_enabled() {
		$state = $this->get();

		return self::STATUS_PAIRED === $state['connection_status'] && ! empty( $state['status_endpoint_enabled'] );
	}

	/**
	 * Verifies a dashboard polling Authorization header.
	 *
	 * @since 0.5.3
	 *
	 * @param string $authorization Authorization header.
	 * @return bool
	 */
	public function verify_polling_authorization( $authorization ) {
		if ( ! $this->is_status_endpoint_enabled() ) {
			return false;
		}

		$authorization = trim( (string) $authorization );
		if ( ! preg_match( '/^Bearer\s+' . preg_quote( self::POLLING_AUTH_PREFIX, '/' ) . '([A-Za-z0-9_\-\.]{1,128})\.([A-Za-z0-9_-]{32,})$/', $authorization, $matches ) ) {
			return false;
		}

		$state  = $this->get();
		$key_id = $this->sanitize_token_identifier( $matches[1] );
		$secret = (string) $matches[2];

		if ( '' === $key_id || '' === $state['polling_key_id'] || ! hash_equals( (string) $state['polling_key_id'], $key_id ) ) {
			return false;
		}

		$expected = isset( $state['polling_credential_verifier'] ) ? (string) $state['polling_credential_verifier'] : '';
		$actual   = $this->hash_polling_credential( $key_id, $secret );

		return '' !== $expected && hash_equals( $expected, $actual );
	}

	/**
	 * Builds a redacted V2 remote-action capability summary.
	 *
	 * @since 0.5.3
	 *
	 * @return array<string,mixed>
	 */
	public function remote_action_summary() {
		$state = $this->get();

		if ( self::STATUS_PAIRED !== $state['connection_status'] || empty( $state['status_endpoint_enabled'] ) ) {
			return array();
		}

		$enabled         = ! empty( $state['remote_actions_enabled'] )
			&& '' !== $state['action_key_id']
			&& '' !== $state['action_public_key']
			&& $this->is_sodium_available();
		$allowed_actions = array();

		if ( $enabled ) {
			$allowed_actions = array( self::ACTION_SCAN_UPLOAD_NOW, self::ACTION_SCHEDULE_PREVIEW );
			if ( $this->is_schedule_mutation_enabled() ) {
				$allowed_actions[] = self::ACTION_SCHEDULE_APPLY;
			}
			if ( $this->is_schedule_rollback_preview_enabled() ) {
				$allowed_actions[] = self::ACTION_SCHEDULE_ROLLBACK_PREVIEW;
			}
		}

		return array(
			'protocol_version'            => self::ACTION_PROTOCOL_VERSION,
			'enabled'                     => $enabled,
			'key_id'                      => $enabled ? (string) $state['action_key_id'] : '',
			'allowed_actions'             => $allowed_actions,
			'sodium_available'            => $this->is_sodium_available(),
			'min_interval_seconds'        => self::ACTION_MIN_INTERVAL,
			'one_running_action_per_site' => true,
		);
	}

	/**
	 * Returns whether local schedule mutation is explicitly enabled.
	 *
	 * @since 0.5.19
	 *
	 * @return bool
	 */
	public function is_schedule_mutation_enabled() {
		$state = $this->get();

		return self::STATUS_PAIRED === $state['connection_status']
			&& ! empty( $state['status_endpoint_enabled'] )
			&& ! empty( $state['remote_actions_enabled'] )
			&& ! empty( $state['schedule_mutation_enabled'] );
	}

	/**
	 * Returns whether non-mutating schedule rollback preview is explicitly enabled.
	 *
	 * @since 0.5.21
	 *
	 * @return bool
	 */
	public function is_schedule_rollback_preview_enabled() {
		$state = $this->get();

		return self::STATUS_PAIRED === $state['connection_status']
			&& ! empty( $state['status_endpoint_enabled'] )
			&& ! empty( $state['remote_actions_enabled'] )
			&& ! empty( $state['schedule_rollback_preview_enabled'] );
	}

	/**
	 * Returns the local site UUID used for V2 remote-action binding.
	 *
	 * @since 0.5.12
	 *
	 * @return string
	 */
	public function site_uuid_for_remote_actions() {
		$settings = get_option( Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME, array() );

		if ( ! is_array( $settings ) || empty( $settings['site_uuid'] ) ) {
			return '';
		}

		return $this->sanitize_uuid( (string) $settings['site_uuid'] );
	}

	/**
	 * Builds the fixed status endpoint for a client origin.
	 *
	 * @since 0.5.3
	 *
	 * @param string $origin Public HTTPS origin.
	 * @return string
	 */
	public function status_endpoint_for_origin( $origin ) {
		$origin = $this->normalize_public_https_origin( $origin );

		return '' === $origin ? '' : $origin . '/wp-json/alynt-drime-backups-uploader/v1/status';
	}

	/**
	 * Returns whether Ed25519 support is available.
	 *
	 * @return bool
	 */
	private function is_sodium_available() {
		return function_exists( 'sodium_crypto_sign_verify_detached' );
	}

	/**
	 * Hashes polling credentials for local verifier-only storage.
	 *
	 * @param string $key_id Polling key ID.
	 * @param string $secret Polling secret.
	 * @return string
	 */
	private function hash_polling_credential( $key_id, $secret ) {
		return hash( 'sha256', 'adb-poll-v1|' . (string) $key_id . '|' . (string) $secret );
	}

	/**
	 * Syncs the option cache after mutation.
	 *
	 * @param array<string,mixed> $state State.
	 * @return void
	 */
	private function sync_option_cache( array $state ) {
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::OPTION_NAME, 'options' );
		}
		if ( function_exists( 'wp_cache_set' ) ) {
			wp_cache_set( self::OPTION_NAME, $state, 'options' );
		}
	}
}
