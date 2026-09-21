<?php
/**
 * Remote action verifier signing helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.12
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remote action verifier signing helpers.
 *
 * @since 0.5.12
 */
trait Alynt_Drime_Backups_Uploader_Remote_Action_Verifier_Signing {
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
	 * Returns whether Sodium verification is available.
	 *
	 * @return bool
	 */
	private function is_sodium_available() {
		return function_exists( 'sodium_crypto_sign_verify_detached' );
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
}
