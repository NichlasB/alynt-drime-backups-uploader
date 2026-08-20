<?php
/**
 * Remote action request verifier.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.12
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verifies signed V2 dashboard action intents.
 *
 * @since 0.5.12
 */
class Alynt_Drime_Backups_Uploader_Remote_Action_Verifier {
	const SIGNING_PREFIX   = 'ADB-ACTION-V2';
	const REST_ROUTE       = '/wp-json/alynt-drime-backups-uploader/v2/action-intents';
	const FRESHNESS_WINDOW = 300;

	/**
	 * Dashboard connection.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Dashboard_Connection
	 */
	private $connection;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Dashboard_Connection $connection Dashboard connection.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Dashboard_Connection $connection ) {
		$this->connection = $connection;
	}

	/**
	 * Verifies and returns a safe intent.
	 *
	 * @param string $method HTTP method.
	 * @param string $route REST route.
	 * @param string $raw_body Raw request body.
	 * @param string $key_id Header key ID.
	 * @param string $signature Header signature.
	 * @param string $signed_at Header signed-at timestamp.
	 * @return array<string,mixed>|WP_Error
	 */
	public function verify( $method, $route, $raw_body, $key_id, $signature, $signed_at ) {
		$state = $this->connection->get();

		if (
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_PAIRED !== $state['connection_status']
			|| empty( $state['status_endpoint_enabled'] )
			|| empty( $state['remote_actions_enabled'] )
		) {
			return $this->error( 'remote_actions_disabled', __( 'Remote actions are not enabled for this client site.', 'alynt-drime-backups-uploader' ), 403 );
		}

		if ( ! $this->is_sodium_available() ) {
			return $this->error( 'remote_action_signing_unavailable', __( 'Remote action signing support is unavailable on this client site.', 'alynt-drime-backups-uploader' ), 501 );
		}

		$key_id    = $this->sanitize_identifier( $key_id );
		$signature = trim( (string) $signature );
		$signed_at = sanitize_text_field( (string) $signed_at );

		if ( '' === $key_id || '' === $signature || '' === $signed_at ) {
			return $this->error( 'action_headers_missing', __( 'The remote action request is missing required signed headers.', 'alynt-drime-backups-uploader' ), 400 );
		}

		if ( empty( $state['action_key_id'] ) || ! hash_equals( (string) $state['action_key_id'], $key_id ) ) {
			return $this->error( 'action_key_mismatch', __( 'The remote action key is not recognized by this client site.', 'alynt-drime-backups-uploader' ), 403 );
		}

		if ( strtoupper( sanitize_key( (string) $method ) ) !== 'POST' || self::REST_ROUTE !== '/' . ltrim( (string) $route, '/' ) ) {
			return $this->error( 'action_route_invalid', __( 'The remote action route is not supported.', 'alynt-drime-backups-uploader' ), 404 );
		}

		$signed_ts = strtotime( $signed_at );
		if ( false === $signed_ts || abs( time() - $signed_ts ) > self::FRESHNESS_WINDOW ) {
			return $this->error( 'action_signature_expired', __( 'The remote action signature timestamp is outside the allowed freshness window.', 'alynt-drime-backups-uploader' ), 401 );
		}

		$body = $this->parse_body( $raw_body );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$expires_ts = strtotime( $body['expires_at'] );
		if ( false === $expires_ts || $expires_ts <= time() ) {
			return $this->error( 'action_intent_expired', __( 'The remote action intent has expired.', 'alynt-drime-backups-uploader' ), 409 );
		}

		if ( empty( $state['dashboard_site_public_id'] ) || ! hash_equals( (string) $state['dashboard_site_public_id'], $body['dashboard_site_public_id'] ) ) {
			return $this->error( 'action_dashboard_site_mismatch', __( 'The remote action is not for this dashboard site record.', 'alynt-drime-backups-uploader' ), 403 );
		}

		$local_site_uuid = $this->local_site_uuid();
		if ( '' === $local_site_uuid || ! hash_equals( $local_site_uuid, $body['site_uuid'] ) ) {
			return $this->error( 'action_site_uuid_mismatch', __( 'The remote action is not for this client site.', 'alynt-drime-backups-uploader' ), 403 );
		}

		$canonical_body = $this->canonical_json( $body );
		if ( is_wp_error( $canonical_body ) ) {
			return $canonical_body;
		}

		$origin = isset( $state['expected_client_origin'] ) ? $this->normalize_public_https_origin( (string) $state['expected_client_origin'] ) : '';
		if ( '' === $origin ) {
			return $this->error( 'action_origin_invalid', __( 'The paired client origin is not valid for remote action verification.', 'alynt-drime-backups-uploader' ), 403 );
		}

		$signing_input = $this->signing_input( 'POST', self::REST_ROUTE, $origin, $canonical_body, $signed_at );
		if ( ! $this->verify_signature( (string) $state['action_public_key'], $signing_input, $signature ) ) {
			return $this->error( 'action_signature_invalid', __( 'The remote action signature is not valid.', 'alynt-drime-backups-uploader' ), 401 );
		}

		$body['request_hash'] = hash( 'sha256', $canonical_body );

		return $body;
	}

	/**
	 * Parses and validates a bounded JSON body.
	 *
	 * @param string $raw_body Raw body.
	 * @return array<string,mixed>|WP_Error
	 */
	private function parse_body( $raw_body ) {
		$raw_body = (string) $raw_body;
		if ( '' === $raw_body || strlen( $raw_body ) > 8192 ) {
			return $this->error( 'action_body_invalid', __( 'The remote action body is invalid.', 'alynt-drime-backups-uploader' ), 400 );
		}

		$body = json_decode( $raw_body, true );
		if ( ! is_array( $body ) ) {
			return $this->error( 'action_body_invalid', __( 'The remote action body is not valid JSON.', 'alynt-drime-backups-uploader' ), 400 );
		}

		$allowed = array( 'protocol_version', 'action_id', 'dashboard_site_public_id', 'site_uuid', 'action_type', 'requested_at', 'expires_at', 'idempotency_key' );
		$extra   = array_diff( array_keys( $body ), $allowed );
		if ( ! empty( $extra ) ) {
			return $this->error( 'action_body_keys_invalid', __( 'The remote action body contains unsupported fields.', 'alynt-drime-backups-uploader' ), 400 );
		}

		$parsed = array(
			'protocol_version'         => absint( isset( $body['protocol_version'] ) ? $body['protocol_version'] : 0 ),
			'action_id'                => isset( $body['action_id'] ) ? $this->sanitize_uuid( (string) $body['action_id'] ) : '',
			'dashboard_site_public_id' => isset( $body['dashboard_site_public_id'] ) ? $this->sanitize_identifier( (string) $body['dashboard_site_public_id'] ) : '',
			'site_uuid'                => isset( $body['site_uuid'] ) ? $this->sanitize_uuid( (string) $body['site_uuid'] ) : '',
			'action_type'              => isset( $body['action_type'] ) ? sanitize_key( (string) $body['action_type'] ) : '',
			'requested_at'             => isset( $body['requested_at'] ) ? sanitize_text_field( (string) $body['requested_at'] ) : '',
			'expires_at'               => isset( $body['expires_at'] ) ? sanitize_text_field( (string) $body['expires_at'] ) : '',
			'idempotency_key'          => isset( $body['idempotency_key'] ) ? $this->sanitize_identifier( (string) $body['idempotency_key'] ) : '',
		);

		if (
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::ACTION_PROTOCOL_VERSION !== $parsed['protocol_version']
			|| '' === $parsed['action_id']
			|| '' === $parsed['dashboard_site_public_id']
			|| '' === $parsed['site_uuid']
			|| Alynt_Drime_Backups_Uploader_Dashboard_Connection::ACTION_SCAN_UPLOAD_NOW !== $parsed['action_type']
			|| '' === $parsed['idempotency_key']
			|| false === strtotime( $parsed['requested_at'] )
			|| false === strtotime( $parsed['expires_at'] )
		) {
			return $this->error( 'action_intent_invalid', __( 'The remote action intent is not supported by this client site.', 'alynt-drime-backups-uploader' ), 400 );
		}

		return $parsed;
	}

	/**
	 * Builds deterministic JSON for signing.
	 *
	 * @param array<string,mixed> $body Body.
	 * @return string|WP_Error
	 */
	public function canonical_json( array $body ) {
		$body    = $this->sort_recursive( $body );
		$encoded = wp_json_encode( $body, JSON_UNESCAPED_SLASHES );

		if ( false === $encoded ) {
			return $this->error( 'action_body_encode_failed', __( 'The remote action body could not be encoded.', 'alynt-drime-backups-uploader' ), 400 );
		}

		return (string) $encoded;
	}

	/**
	 * Builds the V2 signing input.
	 *
	 * @param string $method HTTP method.
	 * @param string $route Route.
	 * @param string $origin Origin.
	 * @param string $body_json Body JSON.
	 * @param string $signed_at Signed-at timestamp.
	 * @return string
	 */
	public function signing_input( $method, $route, $origin, $body_json, $signed_at ) {
		return implode(
			"\n",
			array(
				self::SIGNING_PREFIX,
				strtoupper( sanitize_key( (string) $method ) ),
				'/' . ltrim( (string) $route, '/' ),
				rtrim( strtolower( (string) $origin ), '/' ),
				hash( 'sha256', (string) $body_json ),
				sanitize_text_field( (string) $signed_at ),
			)
		);
	}

	/**
	 * Verifies a detached Ed25519 signature.
	 *
	 * @param string $public_key Public key.
	 * @param string $signing_input Signing input.
	 * @param string $signature Signature.
	 * @return bool
	 */
	private function verify_signature( $public_key, $signing_input, $signature ) {
		$decoded_public_key = $this->base64url_decode( $public_key );
		$decoded_signature  = $this->base64url_decode( $signature );

		if (
			false === $decoded_public_key
			|| false === $decoded_signature
			|| SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen( $decoded_public_key )
			|| SODIUM_CRYPTO_SIGN_BYTES !== strlen( $decoded_signature )
		) {
			return false;
		}

		return sodium_crypto_sign_verify_detached( $decoded_signature, (string) $signing_input, $decoded_public_key );
	}

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
	 * Returns whether Sodium verification is available.
	 *
	 * @return bool
	 */
	private function is_sodium_available() {
		return function_exists( 'sodium_crypto_sign_verify_detached' );
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
	 * Recursively sorts array keys.
	 *
	 * @param array<string,mixed> $value Value.
	 * @return array<string,mixed>
	 */
	private function sort_recursive( array $value ) {
		foreach ( $value as $key => $child ) {
			if ( is_array( $child ) ) {
				$value[ $key ] = $this->sort_recursive( $child );
			}
		}

		ksort( $value );

		return $value;
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
