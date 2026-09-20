<?php
/**
 * Dashboard connection enrollment completion helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Completes dashboard pairing and remote-action opt-in mutations.
 *
 * @since 0.5.22
 */
trait Alynt_Drime_Backups_Uploader_Dashboard_Connection_Enrollment {
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

		$rollback_preview_enabled                      = in_array( self::ACTION_SCHEDULE_ROLLBACK_PREVIEW, $parsed['allowed_actions'], true );
		$state['schedule_rollback_preview_enabled']    = $rollback_preview_enabled;
		$state['schedule_rollback_preview_enabled_at'] = $rollback_preview_enabled ? time() : 0;

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
}
