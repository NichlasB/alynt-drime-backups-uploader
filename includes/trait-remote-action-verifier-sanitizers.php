<?php
/**
 * Remote action verifier sanitizers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.12
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remote action verifier sanitizers.
 *
 * @since 0.5.12
 */
trait Alynt_Drime_Backups_Uploader_Remote_Action_Verifier_Sanitizers {
	/**
	 * Returns local site UUID.
	 *
	 * @return string
	 */
	private function local_site_uuid() {
		if ( method_exists( $this->connection, 'site_uuid_for_remote_actions' ) ) {
			return (string) $this->connection->site_uuid_for_remote_actions();
		}

		$settings = get_option( Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME, array() );
		if ( ! is_array( $settings ) || empty( $settings['site_uuid'] ) ) {
			return '';
		}

		return $this->sanitize_uuid( (string) $settings['site_uuid'] );
	}

	/**
	 * Decodes base64url.
	 *
	 * @param string $value Value.
	 * @return string|false
	 */
	private function base64url_decode( $value ) {
		$value = (string) $value;
		if ( '' === $value || ! preg_match( '/^[A-Za-z0-9_-]+$/', $value ) ) {
			return false;
		}

		$pad = strlen( $value ) % 4;
		if ( $pad ) {
			$value .= str_repeat( '=', 4 - $pad );
		}

		return base64_decode( strtr( $value, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Transport decoding for keys/signatures.
	}

	/**
	 * Normalizes a public HTTPS origin.
	 *
	 * @param string $origin Origin.
	 * @return string
	 */
	private function normalize_public_https_origin( $origin ) {
		$origin = trim( (string) $origin );
		$parts  = parse_url( $origin );

		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || 'https' !== strtolower( (string) $parts['scheme'] ) ) {
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
		if ( '' === $host || 'localhost' === $host || false !== strpos( $host, '..' ) || preg_match( '/(^|\.)local$/', $host ) || false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		return 'https://' . $host;
	}

	/**
	 * Sanitizes UUID.
	 *
	 * @param string $uuid UUID.
	 * @return string
	 */
	private function sanitize_uuid( $uuid ) {
		$uuid = strtolower( trim( (string) $uuid ) );

		return preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $uuid ) ? $uuid : '';
	}

	/**
	 * Sanitizes identifier.
	 *
	 * @param string $identifier Identifier.
	 * @return string
	 */
	private function sanitize_identifier( $identifier ) {
		return substr( preg_replace( '/[^A-Za-z0-9_\-\.]/', '', (string) $identifier ), 0, 128 );
	}

	/**
	 * Sanitizes a SHA-256 hash.
	 *
	 * @since 0.5.19
	 *
	 * @param string $hash Hash.
	 * @return string
	 */
	private function sanitize_hash( $hash ) {
		return preg_match( '/^[a-f0-9]{64}$/', (string) $hash ) ? (string) $hash : '';
	}

	/**
	 * Creates WP_Error with status.
	 *
	 * @param string $code Code.
	 * @param string $message Message.
	 * @param int    $status HTTP status.
	 * @return WP_Error
	 */
	private function error( $code, $message, $status ) {
		return new WP_Error( sanitize_key( (string) $code ), $message, array( 'status' => absint( $status ) ) );
	}
}
