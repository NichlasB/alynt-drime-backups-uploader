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
	const OPTION_NAME             = 'alynt_drime_backups_dashboard_connection';
	const STATUS_DISABLED         = 'disabled';
	const STATUS_READY            = 'ready';
	const STATUS_TOKEN_READY      = 'token_ready';
	const STATUS_CONFIRMED        = 'confirmed';
	const STATUS_PAIRED           = 'paired';
	const STATUS_REVOKED          = 'revoked';
	const TOKEN_PREFIX            = 'adb1.';
	const ACTION_TOKEN_PREFIX     = 'adb2a.';
	const POLLING_AUTH_PREFIX     = 'adb-poll-v1.';
	const PROTOCOL_VERSION        = 1;
	const STATUS_SCHEMA_VERSION   = 1;
	const ACTION_PROTOCOL_VERSION = 2;
	const ACTION_SCAN_UPLOAD_NOW  = 'scan_upload_now';
	const ACTION_MIN_INTERVAL     = 3600;
	const ACTION_TOKEN_PURPOSE    = 'remote_action_opt_in';

	/**
	 * Returns default connection state.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'connection_status'           => self::STATUS_DISABLED,
			'dashboard_origin'            => '',
			'expected_client_origin'      => '',
			'pending_enrollment_id'       => '',
			'pairing_expires_at'          => '',
			'dashboard_origin_confirmed'  => false,
			'dashboard_site_public_id'    => '',
			'polling_key_id'              => '',
			'polling_credential_verifier' => '',
			'paired_at'                   => 0,
			'revoked_at'                  => 0,
			'last_error_code'             => '',
			'last_authenticated_read_at'  => 0,
			'status_endpoint_enabled'     => false,
			'remote_actions_enabled'      => false,
			'action_key_id'               => '',
			'action_public_key'           => '',
			'remote_actions_opted_in_at'  => 0,
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
	 * Saves local shell intent without completing pairing.
	 *
	 * @param array<string,mixed> $raw Raw admin input.
	 * @return array<string,mixed>
	 */
	public function update_shell( array $raw ) {
		$current = $this->get();
		$state   = $current;

		$action = isset( $raw['connection_action'] ) ? sanitize_key( wp_unslash( $raw['connection_action'] ) ) : '';

		if ( 'prepare' === $action && ! empty( $raw['read_only_opt_in'] ) ) {
			$state['connection_status'] = self::STATUS_READY;
			$state['last_error_code']   = '';
		} elseif ( 'parse_token' === $action && ! empty( $raw['read_only_opt_in'] ) ) {
			$token  = isset( $raw['pairing_token'] ) ? (string) wp_unslash( $raw['pairing_token'] ) : '';
			$parsed = $this->parse_pairing_token( $token );

			if ( is_wp_error( $parsed ) ) {
				$state['last_error_code'] = $parsed->get_error_code();
			} else {
				$state['connection_status']          = self::STATUS_TOKEN_READY;
				$state['dashboard_origin']           = $parsed['dashboard_origin'];
				$state['expected_client_origin']     = $parsed['expected_client_origin'];
				$state['pending_enrollment_id']      = $parsed['enrollment_id'];
				$state['pairing_expires_at']         = $parsed['expires_at'];
				$state['dashboard_origin_confirmed'] = false;
				$state['last_error_code']            = '';
			}
		} elseif ( 'confirm_origin' === $action && ! empty( $raw['confirm_dashboard_origin'] ) && self::STATUS_TOKEN_READY === $state['connection_status'] ) {
			$state['connection_status']          = self::STATUS_CONFIRMED;
			$state['dashboard_origin_confirmed'] = true;
			$state['last_error_code']            = '';
		} elseif ( 'revoke' === $action ) {
			$state                      = self::defaults();
			$state['connection_status'] = self::STATUS_REVOKED;
			$state['revoked_at']        = time();
		} elseif ( 'disable_remote_actions' === $action ) {
			$state['remote_actions_enabled']     = false;
			$state['action_key_id']              = '';
			$state['action_public_key']          = '';
			$state['remote_actions_opted_in_at'] = 0;
			$state['last_error_code']            = '';
		} elseif ( 'disable' === $action ) {
			$state = self::defaults();
		}

		if ( self::STATUS_PAIRED !== $state['connection_status'] ) {
			$state['status_endpoint_enabled'] = false;
		}
		update_option( self::OPTION_NAME, $state, false );
		$this->sync_option_cache( $state );

		return $state;
	}

	/**
	 * Completes explicit client-site V2 remote-action opt-in from a dashboard token.
	 *
	 * @since 0.5.12
	 *
	 * @param string $token         Dashboard-generated adb2a action opt-in token.
	 * @param string $client_origin Client site origin.
	 * @param string $site_uuid     Client site UUID.
	 * @return array<string,mixed>
	 */
	public function complete_remote_action_opt_in_from_token( $token, $client_origin, $site_uuid ) {
		$state  = $this->get();
		$parsed = $this->parse_action_opt_in_token_payload( $token );

		if ( is_wp_error( $parsed ) ) {
			return $this->save_pairing_error( $state, $parsed->get_error_code() );
		}

		if ( self::STATUS_PAIRED !== $state['connection_status'] || empty( $state['status_endpoint_enabled'] ) ) {
			return $this->save_pairing_error( $state, 'remote_action_opt_in_requires_pairing' );
		}

		if (
			empty( $state['dashboard_origin'] )
			|| empty( $state['expected_client_origin'] )
			|| empty( $state['dashboard_site_public_id'] )
			|| ! hash_equals( (string) $state['dashboard_origin'], $parsed['dashboard_origin'] )
			|| ! hash_equals( (string) $state['expected_client_origin'], $parsed['expected_client_origin'] )
			|| ! hash_equals( (string) $state['dashboard_site_public_id'], $parsed['dashboard_site_public_id'] )
		) {
			return $this->save_pairing_error( $state, 'remote_action_opt_in_site_mismatch' );
		}

		$client_origin = $this->normalize_public_https_origin( $client_origin );
		if ( '' === $client_origin || ! hash_equals( $parsed['expected_client_origin'], $client_origin ) ) {
			return $this->save_pairing_error( $state, 'remote_action_opt_in_origin_mismatch' );
		}

		$site_uuid = $this->sanitize_uuid( $site_uuid );
		if ( '' === $site_uuid || ! hash_equals( $parsed['site_uuid'], $site_uuid ) ) {
			return $this->save_pairing_error( $state, 'remote_action_opt_in_site_uuid_mismatch' );
		}

		if ( ! $this->is_sodium_available() ) {
			return $this->save_pairing_error( $state, 'remote_action_signing_unavailable' );
		}

		$state['remote_actions_enabled']     = true;
		$state['action_key_id']              = $parsed['action_key_id'];
		$state['action_public_key']          = $parsed['action_public_key'];
		$state['remote_actions_opted_in_at'] = time();
		$state['last_error_code']            = '';

		update_option( self::OPTION_NAME, $state, false );
		$this->sync_option_cache( $state );

		return $state;
	}

	/**
	 * Completes one-time dashboard enrollment and stores polling verifier only.
	 *
	 * @since 0.5.3
	 *
	 * @param string        $token            Pairing token.
	 * @param string        $client_origin    Client site origin.
	 * @param string        $site_uuid        Client site UUID.
	 * @param string        $uploader_version Uploader plugin version.
	 * @param callable|null $http_client      Optional HTTP transport for tests.
	 * @return array<string,mixed>
	 */
	public function complete_pairing_from_token( $token, $client_origin, $site_uuid, $uploader_version, $http_client = null ) {
		$state  = $this->get();
		$parsed = $this->parse_pairing_token_payload( $token, true );

		if ( is_wp_error( $parsed ) ) {
			return $this->save_pairing_error( $state, $parsed->get_error_code() );
		}

		if (
			self::STATUS_CONFIRMED !== $state['connection_status']
			|| empty( $state['dashboard_origin_confirmed'] )
			|| ! hash_equals( (string) $state['dashboard_origin'], $parsed['dashboard_origin'] )
			|| ! hash_equals( (string) $state['expected_client_origin'], $parsed['expected_client_origin'] )
			|| ! hash_equals( (string) $state['pending_enrollment_id'], $parsed['enrollment_id'] )
		) {
			return $this->save_pairing_error( $state, 'dashboard_origin_confirmation_required' );
		}

		$client_origin = $this->normalize_public_https_origin( $client_origin );
		if ( '' === $client_origin || ! hash_equals( $parsed['expected_client_origin'], $client_origin ) ) {
			return $this->save_pairing_error( $state, 'expected_client_origin_mismatch' );
		}

		$site_uuid = $this->sanitize_uuid( $site_uuid );
		if ( '' === $site_uuid ) {
			return $this->save_pairing_error( $state, 'site_uuid_invalid' );
		}

		$endpoint = $this->status_endpoint_for_origin( $client_origin );
		$args     = array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $parsed['secret'],
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'protocol_version'      => self::PROTOCOL_VERSION,
					'status_schema_version' => self::STATUS_SCHEMA_VERSION,
					'enrollment_id'         => $parsed['enrollment_id'],
					'site_uuid'             => $site_uuid,
					'home_url'              => $client_origin,
					'status_endpoint'       => $endpoint,
					'uploader_version'      => $this->bounded_text( $uploader_version, 64 ),
				)
			),
		);

		$request_url = $parsed['dashboard_origin'] . '/wp-json/alynt-drime-backups-dashboard/v1/enroll';
		$response    = is_callable( $http_client ) ? call_user_func( $http_client, $request_url, $args ) : wp_remote_post( $request_url, $args );

		if ( is_wp_error( $response ) ) {
			return $this->save_pairing_error( $state, 'dashboard_enrollment_request_failed' );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );

		if ( 201 !== absint( $response_code ) ) {
			return $this->save_pairing_error( $state, 'dashboard_enrollment_rejected' );
		}

		$payload = json_decode( (string) $body, true );
		if ( ! is_array( $payload ) ) {
			return $this->save_pairing_error( $state, 'dashboard_enrollment_response_invalid' );
		}

		$polling_key_id = isset( $payload['polling_key_id'] ) ? $this->sanitize_token_identifier( (string) $payload['polling_key_id'] ) : '';
		$polling_secret = isset( $payload['polling_secret'] ) ? (string) $payload['polling_secret'] : '';
		$site_public_id = isset( $payload['dashboard_site_public_id'] ) ? $this->sanitize_token_identifier( (string) $payload['dashboard_site_public_id'] ) : '';

		if (
			self::PROTOCOL_VERSION !== absint( isset( $payload['protocol_version'] ) ? $payload['protocol_version'] : 0 )
			|| '' === $site_public_id
			|| '' === $polling_key_id
			|| strlen( $polling_secret ) < 32
			|| ! preg_match( '/^[A-Za-z0-9_-]+$/', $polling_secret )
		) {
			return $this->save_pairing_error( $state, 'dashboard_enrollment_response_invalid' );
		}

		$state['connection_status']           = self::STATUS_PAIRED;
		$state['dashboard_origin']            = $parsed['dashboard_origin'];
		$state['expected_client_origin']      = $parsed['expected_client_origin'];
		$state['pending_enrollment_id']       = $parsed['enrollment_id'];
		$state['pairing_expires_at']          = $parsed['expires_at'];
		$state['dashboard_origin_confirmed']  = true;
		$state['dashboard_site_public_id']    = $site_public_id;
		$state['polling_key_id']              = $polling_key_id;
		$state['polling_credential_verifier'] = $this->hash_polling_credential( $polling_key_id, $polling_secret );
		$state['paired_at']                   = time();
		$state['revoked_at']                  = 0;
		$state['last_error_code']             = '';
		$state['status_endpoint_enabled']     = true;

		update_option( self::OPTION_NAME, $state, false );
		$this->sync_option_cache( $state );

		return $state;
	}

	/**
	 * Records a safe local pairing error without contacting the dashboard.
	 *
	 * @since 0.5.3
	 *
	 * @param string $code Error code.
	 * @return array<string,mixed>
	 */
	public function record_pairing_error( $code ) {
		return $this->save_pairing_error( $this->get(), $code );
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
	 * Records the latest authenticated dashboard read timestamp.
	 *
	 * @since 0.5.3
	 *
	 * @return void
	 */
	public function record_authenticated_read() {
		$state                               = $this->get();
		$state['last_authenticated_read_at'] = time();
		$state['last_error_code']            = '';
		update_option( self::OPTION_NAME, $state, false );
		$this->sync_option_cache( $state );
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

		$enabled = ! empty( $state['remote_actions_enabled'] )
			&& '' !== $state['action_key_id']
			&& '' !== $state['action_public_key']
			&& $this->is_sodium_available();

		return array(
			'protocol_version'            => self::ACTION_PROTOCOL_VERSION,
			'enabled'                     => $enabled,
			'key_id'                      => $enabled ? (string) $state['action_key_id'] : '',
			'allowed_actions'             => $enabled ? array( self::ACTION_SCAN_UPLOAD_NOW ) : array(),
			'sodium_available'            => $this->is_sodium_available(),
			'min_interval_seconds'        => self::ACTION_MIN_INTERVAL,
			'one_running_action_per_site' => true,
		);
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
	 * Parses a dashboard-generated pairing token and returns safe metadata.
	 *
	 * The returned array intentionally excludes the raw token and one-time
	 * secret. Later enrollment work must handle one-time credential submission
	 * without persisting the secret.
	 *
	 * @param string $token Pairing token.
	 * @return array<string,string>|WP_Error
	 */
	public function parse_pairing_token( $token ) {
		$parsed = $this->parse_pairing_token_payload( $token, false );

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		unset( $parsed['secret'] );

		return $parsed;
	}

	/**
	 * Parses a dashboard-generated V2 action opt-in token and returns safe metadata.
	 *
	 * @since 0.5.12
	 *
	 * @param string $token Action opt-in token.
	 * @return array<string,mixed>|WP_Error
	 */
	public function parse_action_opt_in_token( $token ) {
		return $this->parse_action_opt_in_token_payload( $token );
	}

	/**
	 * Parses a dashboard-generated pairing token.
	 *
	 * @param string $token Pairing token.
	 * @param bool   $include_secret Whether to include the one-time secret.
	 * @return array<string,string>|WP_Error
	 */
	private function parse_pairing_token_payload( $token, $include_secret = false ) {
		$token = trim( (string) $token );

		if ( 0 !== strpos( $token, self::TOKEN_PREFIX ) ) {
			return new WP_Error( 'pairing_token_prefix_invalid', __( 'The dashboard pairing token must begin with adb1.', 'alynt-drime-backups-uploader' ) );
		}

		$encoded = substr( $token, strlen( self::TOKEN_PREFIX ) );
		if ( '' === $encoded || ! preg_match( '/^[A-Za-z0-9_-]+$/', $encoded ) ) {
			return new WP_Error( 'pairing_token_payload_invalid', __( 'The dashboard pairing token payload is not valid.', 'alynt-drime-backups-uploader' ) );
		}

		$json = $this->base64url_decode( $encoded );
		if ( false === $json ) {
			return new WP_Error( 'pairing_token_payload_invalid', __( 'The dashboard pairing token payload could not be decoded.', 'alynt-drime-backups-uploader' ) );
		}

		$payload = json_decode( $json, true );
		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'pairing_token_payload_invalid', __( 'The dashboard pairing token payload is not JSON.', 'alynt-drime-backups-uploader' ) );
		}

		if ( 1 !== absint( isset( $payload['protocol_version'] ) ? $payload['protocol_version'] : 0 ) ) {
			return new WP_Error( 'protocol_unsupported', __( 'The dashboard pairing token uses an unsupported protocol version.', 'alynt-drime-backups-uploader' ) );
		}

		$enrollment_id = isset( $payload['enrollment_id'] ) ? $this->sanitize_uuid( (string) $payload['enrollment_id'] ) : '';
		if ( '' === $enrollment_id ) {
			return new WP_Error( 'enrollment_id_invalid', __( 'The dashboard pairing token does not include a valid enrollment ID.', 'alynt-drime-backups-uploader' ) );
		}

		$dashboard_origin = $this->normalize_public_https_origin( isset( $payload['dashboard_origin'] ) ? (string) $payload['dashboard_origin'] : '' );
		if ( '' === $dashboard_origin ) {
			return new WP_Error( 'dashboard_origin_invalid', __( 'The dashboard pairing token does not include a supported dashboard HTTPS origin.', 'alynt-drime-backups-uploader' ) );
		}

		$expected_client_origin = $this->normalize_public_https_origin( isset( $payload['expected_client_origin'] ) ? (string) $payload['expected_client_origin'] : '' );
		if ( '' === $expected_client_origin ) {
			return new WP_Error( 'expected_client_origin_invalid', __( 'The dashboard pairing token does not include a supported expected client HTTPS origin.', 'alynt-drime-backups-uploader' ) );
		}

		$secret = isset( $payload['secret'] ) ? (string) $payload['secret'] : '';
		if ( strlen( $secret ) < 32 || ! preg_match( '/^[A-Za-z0-9_-]+$/', $secret ) ) {
			return new WP_Error( 'pairing_secret_invalid', __( 'The dashboard pairing token secret is not valid.', 'alynt-drime-backups-uploader' ) );
		}

		$expires_at = isset( $payload['expires_at'] ) ? trim( (string) $payload['expires_at'] ) : '';
		$expires_ts = '' === $expires_at ? false : strtotime( $expires_at );
		if ( false === $expires_ts ) {
			return new WP_Error( 'pairing_expires_at_invalid', __( 'The dashboard pairing token expiry is not valid.', 'alynt-drime-backups-uploader' ) );
		}

		if ( $expires_ts <= time() ) {
			return new WP_Error( 'pairing_expired', __( 'The dashboard pairing token has expired.', 'alynt-drime-backups-uploader' ) );
		}

		$parsed = array(
			'enrollment_id'          => $enrollment_id,
			'dashboard_origin'       => $dashboard_origin,
			'expected_client_origin' => $expected_client_origin,
			'expires_at'             => gmdate( 'c', $expires_ts ),
		);

		if ( $include_secret ) {
			$parsed['secret'] = $secret;
		}

		return $parsed;
	}

	/**
	 * Parses a dashboard-generated V2 action opt-in token.
	 *
	 * @since 0.5.12
	 *
	 * @param string $token Action opt-in token.
	 * @return array<string,mixed>|WP_Error
	 */
	private function parse_action_opt_in_token_payload( $token ) {
		$token = trim( (string) $token );

		if ( 0 !== strpos( $token, self::ACTION_TOKEN_PREFIX ) ) {
			return new WP_Error( 'action_opt_in_token_prefix_invalid', __( 'The dashboard action opt-in token must begin with adb2a.', 'alynt-drime-backups-uploader' ) );
		}

		$encoded = substr( $token, strlen( self::ACTION_TOKEN_PREFIX ) );
		if ( '' === $encoded || ! preg_match( '/^[A-Za-z0-9_-]+$/', $encoded ) ) {
			return new WP_Error( 'action_opt_in_token_payload_invalid', __( 'The dashboard action opt-in token payload is not valid.', 'alynt-drime-backups-uploader' ) );
		}

		$json = $this->base64url_decode( $encoded );
		if ( false === $json ) {
			return new WP_Error( 'action_opt_in_token_payload_invalid', __( 'The dashboard action opt-in token payload could not be decoded.', 'alynt-drime-backups-uploader' ) );
		}

		$payload = json_decode( $json, true );
		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'action_opt_in_token_payload_invalid', __( 'The dashboard action opt-in token payload is not JSON.', 'alynt-drime-backups-uploader' ) );
		}

		if ( self::ACTION_PROTOCOL_VERSION !== absint( isset( $payload['protocol_version'] ) ? $payload['protocol_version'] : 0 ) ) {
			return new WP_Error( 'action_protocol_unsupported', __( 'The dashboard action opt-in token uses an unsupported protocol version.', 'alynt-drime-backups-uploader' ) );
		}

		if ( self::ACTION_TOKEN_PURPOSE !== ( isset( $payload['purpose'] ) ? sanitize_key( (string) $payload['purpose'] ) : '' ) ) {
			return new WP_Error( 'action_opt_in_token_purpose_invalid', __( 'The dashboard action opt-in token purpose is not valid.', 'alynt-drime-backups-uploader' ) );
		}

		$dashboard_origin = $this->normalize_public_https_origin( isset( $payload['dashboard_origin'] ) ? (string) $payload['dashboard_origin'] : '' );
		if ( '' === $dashboard_origin ) {
			return new WP_Error( 'action_dashboard_origin_invalid', __( 'The dashboard action opt-in token does not include a supported dashboard HTTPS origin.', 'alynt-drime-backups-uploader' ) );
		}

		$expected_client_origin = $this->normalize_public_https_origin( isset( $payload['expected_client_origin'] ) ? (string) $payload['expected_client_origin'] : '' );
		if ( '' === $expected_client_origin ) {
			return new WP_Error( 'action_expected_client_origin_invalid', __( 'The dashboard action opt-in token does not include a supported expected client HTTPS origin.', 'alynt-drime-backups-uploader' ) );
		}

		$dashboard_site_public_id = isset( $payload['dashboard_site_public_id'] ) ? $this->sanitize_token_identifier( (string) $payload['dashboard_site_public_id'] ) : '';
		$site_uuid                = isset( $payload['site_uuid'] ) ? $this->sanitize_uuid( (string) $payload['site_uuid'] ) : '';
		$action_key_id            = isset( $payload['action_key_id'] ) ? $this->sanitize_token_identifier( (string) $payload['action_key_id'] ) : '';
		$action_public_key        = isset( $payload['action_public_key'] ) ? $this->sanitize_action_public_key( (string) $payload['action_public_key'] ) : '';

		if ( '' === $dashboard_site_public_id || '' === $site_uuid || '' === $action_key_id || '' === $action_public_key ) {
			return new WP_Error( 'action_opt_in_token_identity_invalid', __( 'The dashboard action opt-in token does not include valid site and key identifiers.', 'alynt-drime-backups-uploader' ) );
		}

		$allowed_actions = isset( $payload['allowed_actions'] ) && is_array( $payload['allowed_actions'] ) ? array_values( array_filter( array_map( 'sanitize_key', $payload['allowed_actions'] ) ) ) : array();
		$allowed_actions = array_values( array_unique( $allowed_actions ) );
		if ( array( self::ACTION_SCAN_UPLOAD_NOW ) !== $allowed_actions ) {
			return new WP_Error( 'action_opt_in_allowed_actions_invalid', __( 'The dashboard action opt-in token does not grant the supported scan/upload-now action.', 'alynt-drime-backups-uploader' ) );
		}

		$expires_at = isset( $payload['expires_at'] ) ? trim( (string) $payload['expires_at'] ) : '';
		$expires_ts = '' === $expires_at ? false : strtotime( $expires_at );
		if ( false === $expires_ts ) {
			return new WP_Error( 'action_opt_in_expires_at_invalid', __( 'The dashboard action opt-in token expiry is not valid.', 'alynt-drime-backups-uploader' ) );
		}

		if ( $expires_ts <= time() ) {
			return new WP_Error( 'action_opt_in_expired', __( 'The dashboard action opt-in token has expired.', 'alynt-drime-backups-uploader' ) );
		}

		return array(
			'dashboard_origin'         => $dashboard_origin,
			'expected_client_origin'   => $expected_client_origin,
			'dashboard_site_public_id' => $dashboard_site_public_id,
			'site_uuid'                => $site_uuid,
			'action_key_id'            => $action_key_id,
			'action_public_key'        => $action_public_key,
			'allowed_actions'          => $allowed_actions,
			'expires_at'               => gmdate( 'c', $expires_ts ),
		);
	}

	/**
	 * Saves a safe pairing error on the local connection state.
	 *
	 * @param array<string,mixed> $state State.
	 * @param string              $code Error code.
	 * @return array<string,mixed>
	 */
	private function save_pairing_error( array $state, $code ) {
		$state['last_error_code'] = sanitize_key( (string) $code );

		if ( self::STATUS_PAIRED !== $state['connection_status'] ) {
			$state['status_endpoint_enabled'] = false;
		}

		update_option( self::OPTION_NAME, $state, false );
		$this->sync_option_cache( $state );

		return $state;
	}

	/**
	 * Sanitizes stored state.
	 *
	 * @param array<string,mixed> $state State.
	 * @return array<string,mixed>
	 */
	private function sanitize_state( array $state ) {
		$defaults = self::defaults();
		$status   = isset( $state['connection_status'] ) ? sanitize_key( $state['connection_status'] ) : self::STATUS_DISABLED;

		if ( ! in_array( $status, array( self::STATUS_DISABLED, self::STATUS_READY, self::STATUS_TOKEN_READY, self::STATUS_CONFIRMED, self::STATUS_PAIRED, self::STATUS_REVOKED ), true ) ) {
			$status = self::STATUS_DISABLED;
		}

		return array(
			'connection_status'           => $status,
			'dashboard_origin'            => isset( $state['dashboard_origin'] ) ? esc_url_raw( (string) $state['dashboard_origin'] ) : $defaults['dashboard_origin'],
			'expected_client_origin'      => isset( $state['expected_client_origin'] ) ? esc_url_raw( (string) $state['expected_client_origin'] ) : $defaults['expected_client_origin'],
			'pending_enrollment_id'       => isset( $state['pending_enrollment_id'] ) ? $this->sanitize_uuid( (string) $state['pending_enrollment_id'] ) : $defaults['pending_enrollment_id'],
			'pairing_expires_at'          => isset( $state['pairing_expires_at'] ) ? sanitize_text_field( (string) $state['pairing_expires_at'] ) : $defaults['pairing_expires_at'],
			'dashboard_origin_confirmed'  => self::STATUS_CONFIRMED === $status || ( self::STATUS_PAIRED === $status && ! empty( $state['dashboard_origin_confirmed'] ) ),
			'dashboard_site_public_id'    => isset( $state['dashboard_site_public_id'] ) ? $this->sanitize_token_identifier( (string) $state['dashboard_site_public_id'] ) : $defaults['dashboard_site_public_id'],
			'polling_key_id'              => isset( $state['polling_key_id'] ) ? $this->sanitize_token_identifier( (string) $state['polling_key_id'] ) : $defaults['polling_key_id'],
			'polling_credential_verifier' => isset( $state['polling_credential_verifier'] ) ? $this->sanitize_hash( (string) $state['polling_credential_verifier'] ) : $defaults['polling_credential_verifier'],
			'paired_at'                   => isset( $state['paired_at'] ) ? max( 0, absint( $state['paired_at'] ) ) : $defaults['paired_at'],
			'revoked_at'                  => isset( $state['revoked_at'] ) ? max( 0, absint( $state['revoked_at'] ) ) : $defaults['revoked_at'],
			'last_error_code'             => isset( $state['last_error_code'] ) ? sanitize_key( (string) $state['last_error_code'] ) : $defaults['last_error_code'],
			'last_authenticated_read_at'  => isset( $state['last_authenticated_read_at'] ) ? max( 0, absint( $state['last_authenticated_read_at'] ) ) : $defaults['last_authenticated_read_at'],
			'status_endpoint_enabled'     => self::STATUS_PAIRED === $status && ! empty( $state['status_endpoint_enabled'] ),
			'remote_actions_enabled'      => self::STATUS_PAIRED === $status && ! empty( $state['remote_actions_enabled'] ),
			'action_key_id'               => isset( $state['action_key_id'] ) ? $this->sanitize_token_identifier( (string) $state['action_key_id'] ) : $defaults['action_key_id'],
			'action_public_key'           => isset( $state['action_public_key'] ) ? $this->sanitize_action_public_key( (string) $state['action_public_key'] ) : $defaults['action_public_key'],
			'remote_actions_opted_in_at'  => isset( $state['remote_actions_opted_in_at'] ) ? max( 0, absint( $state['remote_actions_opted_in_at'] ) ) : $defaults['remote_actions_opted_in_at'],
		);
	}

	/**
	 * Decodes base64url text.
	 *
	 * @param string $value Encoded value.
	 * @return string|false
	 */
	private function base64url_decode( $value ) {
		$padded = (string) $value;
		$pad    = strlen( $padded ) % 4;

		if ( $pad ) {
			$padded .= str_repeat( '=', 4 - $pad );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Transport decoding for opaque pairing tokens, not code obfuscation.
		return base64_decode( strtr( $padded, '-_', '+/' ), true );
	}

	/**
	 * Normalizes a public HTTPS origin.
	 *
	 * @param string $origin Raw origin.
	 * @return string
	 */
	private function normalize_public_https_origin( $origin ) {
		$origin = trim( (string) $origin );
		$parts  = parse_url( $origin );

		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		if ( 'https' !== strtolower( (string) $parts['scheme'] ) ) {
			return '';
		}

		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			return '';
		}

		if ( isset( $parts['path'] ) && '' !== $parts['path'] && '/' !== $parts['path'] ) {
			return '';
		}

		if ( isset( $parts['port'] ) && 443 !== absint( $parts['port'] ) ) {
			return '';
		}

		$host = strtolower( rtrim( (string) $parts['host'], '.' ) );
		if ( '' === $host || 'localhost' === $host || false !== strpos( $host, '..' ) || preg_match( '/(^|\.)local$/', $host ) ) {
			return '';
		}

		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		return 'https://' . $host;
	}

	/**
	 * Sanitizes a UUID-style identifier.
	 *
	 * @param string $uuid UUID.
	 * @return string
	 */
	private function sanitize_uuid( $uuid ) {
		$uuid = strtolower( trim( (string) $uuid ) );

		return preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $uuid ) ? $uuid : '';
	}

	/**
	 * Sanitizes a non-secret identifier.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function sanitize_token_identifier( $value ) {
		return substr( preg_replace( '/[^A-Za-z0-9_\-\.]/', '', (string) $value ), 0, 128 );
	}

	/**
	 * Sanitizes a one-way verifier hash.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function sanitize_hash( $value ) {
		return preg_match( '/^[a-f0-9]{64}$/', (string) $value ) ? (string) $value : '';
	}

	/**
	 * Sanitizes a base64url action public key.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function sanitize_action_public_key( $value ) {
		$value = trim( (string) $value );

		return preg_match( '/^[A-Za-z0-9_-]{32,128}$/', $value ) ? $value : '';
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
	 * Sanitizes and bounds display/storage text.
	 *
	 * @param string $value Raw value.
	 * @param int    $max_length Max characters.
	 * @return string
	 */
	private function bounded_text( $value, $max_length ) {
		$value      = sanitize_text_field( (string) $value );
		$max_length = max( 1, (int) $max_length );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max_length );
		}

		return substr( $value, 0, $max_length );
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
