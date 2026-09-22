<?php
/**
 * Dashboard connection sanitization helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.21
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizes dashboard connection state and identifiers.
 *
 * @since 0.5.21
 */
trait Alynt_Drime_Backups_Uploader_Dashboard_Connection_Sanitizers {
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
			'connection_status'                    => $status,
			'dashboard_origin'                     => isset( $state['dashboard_origin'] ) ? esc_url_raw( (string) $state['dashboard_origin'] ) : $defaults['dashboard_origin'],
			'expected_client_origin'               => isset( $state['expected_client_origin'] ) ? esc_url_raw( (string) $state['expected_client_origin'] ) : $defaults['expected_client_origin'],
			'pending_enrollment_id'                => isset( $state['pending_enrollment_id'] ) ? $this->sanitize_uuid( (string) $state['pending_enrollment_id'] ) : $defaults['pending_enrollment_id'],
			'pairing_expires_at'                   => isset( $state['pairing_expires_at'] ) ? sanitize_text_field( (string) $state['pairing_expires_at'] ) : $defaults['pairing_expires_at'],
			'dashboard_origin_confirmed'           => self::STATUS_CONFIRMED === $status || ( self::STATUS_PAIRED === $status && ! empty( $state['dashboard_origin_confirmed'] ) ),
			'dashboard_site_public_id'             => isset( $state['dashboard_site_public_id'] ) ? $this->sanitize_token_identifier( (string) $state['dashboard_site_public_id'] ) : $defaults['dashboard_site_public_id'],
			'polling_key_id'                       => isset( $state['polling_key_id'] ) ? $this->sanitize_token_identifier( (string) $state['polling_key_id'] ) : $defaults['polling_key_id'],
			'polling_credential_verifier'          => isset( $state['polling_credential_verifier'] ) ? $this->sanitize_hash( (string) $state['polling_credential_verifier'] ) : $defaults['polling_credential_verifier'],
			'paired_at'                            => isset( $state['paired_at'] ) ? max( 0, absint( $state['paired_at'] ) ) : $defaults['paired_at'],
			'revoked_at'                           => isset( $state['revoked_at'] ) ? max( 0, absint( $state['revoked_at'] ) ) : $defaults['revoked_at'],
			'last_error_code'                      => isset( $state['last_error_code'] ) ? sanitize_key( (string) $state['last_error_code'] ) : $defaults['last_error_code'],
			'last_authenticated_read_at'           => isset( $state['last_authenticated_read_at'] ) ? max( 0, absint( $state['last_authenticated_read_at'] ) ) : $defaults['last_authenticated_read_at'],
			'status_endpoint_enabled'              => self::STATUS_PAIRED === $status && ! empty( $state['status_endpoint_enabled'] ),
			'remote_actions_enabled'               => self::STATUS_PAIRED === $status && ! empty( $state['remote_actions_enabled'] ),
			'action_key_id'                        => isset( $state['action_key_id'] ) ? $this->sanitize_token_identifier( (string) $state['action_key_id'] ) : $defaults['action_key_id'],
			'action_public_key'                    => isset( $state['action_public_key'] ) ? $this->sanitize_action_public_key( (string) $state['action_public_key'] ) : $defaults['action_public_key'],
			'remote_actions_opted_in_at'           => isset( $state['remote_actions_opted_in_at'] ) ? max( 0, absint( $state['remote_actions_opted_in_at'] ) ) : $defaults['remote_actions_opted_in_at'],
			'schedule_mutation_enabled'            => self::STATUS_PAIRED === $status && ! empty( $state['remote_actions_enabled'] ) && ! empty( $state['schedule_mutation_enabled'] ),
			'schedule_mutation_enabled_at'         => isset( $state['schedule_mutation_enabled_at'] ) ? max( 0, absint( $state['schedule_mutation_enabled_at'] ) ) : $defaults['schedule_mutation_enabled_at'],
			'schedule_rollback_preview_enabled'    => self::STATUS_PAIRED === $status && ! empty( $state['remote_actions_enabled'] ) && ! empty( $state['schedule_rollback_preview_enabled'] ),
			'schedule_rollback_preview_enabled_at' => isset( $state['schedule_rollback_preview_enabled_at'] ) ? max( 0, absint( $state['schedule_rollback_preview_enabled_at'] ) ) : $defaults['schedule_rollback_preview_enabled_at'],
		);
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
}
