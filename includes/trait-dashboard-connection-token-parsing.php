<?php
/**
 * Dashboard connection token parsing helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses dashboard-generated pairing and action opt-in tokens.
 *
 * @since 0.5.22
 */
trait Alynt_Drime_Backups_Uploader_Dashboard_Connection_Token_Parsing {
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
		sort( $allowed_actions );
		$supported_actions = array( self::ACTION_SCAN_UPLOAD_NOW, self::ACTION_SCHEDULE_PREVIEW );
		sort( $supported_actions );
		$apply_supported_actions = array( self::ACTION_SCAN_UPLOAD_NOW, self::ACTION_SCHEDULE_PREVIEW, self::ACTION_SCHEDULE_APPLY );
		sort( $apply_supported_actions );
		$rollback_preview_supported_actions = array( self::ACTION_SCAN_UPLOAD_NOW, self::ACTION_SCHEDULE_PREVIEW, self::ACTION_SCHEDULE_APPLY, self::ACTION_SCHEDULE_ROLLBACK_PREVIEW );
		sort( $rollback_preview_supported_actions );
		$legacy_actions = array( self::ACTION_SCAN_UPLOAD_NOW );
		if ( $legacy_actions !== $allowed_actions && $supported_actions !== $allowed_actions && $apply_supported_actions !== $allowed_actions && $rollback_preview_supported_actions !== $allowed_actions ) {
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
}
