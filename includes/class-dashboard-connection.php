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
	const OPTION_NAME        = 'alynt_drime_backups_dashboard_connection';
	const STATUS_DISABLED    = 'disabled';
	const STATUS_READY       = 'ready';
	const STATUS_TOKEN_READY = 'token_ready';
	const STATUS_CONFIRMED   = 'confirmed';
	const STATUS_PAIRED      = 'paired';
	const STATUS_REVOKED     = 'revoked';
	const TOKEN_PREFIX       = 'adb1.';

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
	 * Saves local shell intent without enabling the status endpoint.
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
		} elseif ( 'disable' === $action ) {
			$state = self::defaults();
		}

		$state['status_endpoint_enabled'] = false;
		update_option( self::OPTION_NAME, $state, false );
		$this->sync_option_cache( $state );

		return $state;
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

		return array(
			'enrollment_id'          => $enrollment_id,
			'dashboard_origin'       => $dashboard_origin,
			'expected_client_origin' => $expected_client_origin,
			'expires_at'             => gmdate( 'c', $expires_ts ),
		);
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
