<?php
/**
 * Strict bounded form and envelope validation for the test-only request adapter.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No posted trust, implicit normalization, production readers or permission. */
trait Alynt_Drime_Backups_Uploader_Consent_Request_Validation {
	/**
	 * Require exact keys without relying on request key order.
	 *
	 * @param mixed $value Input.
	 * @param array $keys Allowed keys.
	 * @return bool
	 */
	private function exact_keys( $value, array $keys ) {
		return is_array( $value ) && count( $value ) === count( $keys ) && ! array_diff( $keys, array_keys( $value ) );
	}

	/**
	 * Bound slashed form input before work; all leaves must be strings.
	 *
	 * @param mixed $body Form input, not a superglobal.
	 * @param array $keys Allowed keys.
	 * @return array|false
	 */
	private function form( $body, array $keys ) {
		if ( ! $this->exact_keys( $body, $keys ) ) {
			return false;
		}
		foreach ( $body as $key => $value ) {
			if ( ! is_string( $value ) || strlen( $value ) > 2048 ) {
				return false;
			}
		}
		$raw = json_encode( $body );
		if ( ! is_string( $raw ) || strlen( $raw ) > 4096 ) {
			return false;
		}
		foreach ( $body as $key => $value ) {
			$body[ $key ] = stripslashes( $value ); // Equivalent scalar unslashing; never a WP request reader.
		}
		$raw = json_encode( $body );
		return is_string( $raw ) && strlen( $raw ) <= 4096 ? $body : false;
	}

	/**
	 * Parse canonical decimal strings without overflowing a cast.
	 *
	 * @param string $value Form integer.
	 * @return int|false
	 */
	private function decimal( $value ) {
		$limit = (string) ( PHP_INT_MAX - 3600 );
		if ( ! preg_match( '/\A(?:0|[1-9][0-9]*)\z/D', $value ) || strlen( $value ) > strlen( $limit ) || ( strlen( $value ) === strlen( $limit ) && strcmp( $value, $limit ) > 0 ) ) {
			return false;
		}
		return (int) $value;
	}

	/**
	 * Match a fixed canonical site/command UUID.
	 *
	 * @param mixed $value Identifier.
	 * @return bool
	 */
	private function uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $value );
	}

	/**
	 * Validate a private test context, never a public policy projection.
	 *
	 * @param mixed $value Fixture facts.
	 * @return bool
	 */
	private function valid_context( $value ) {
		return $this->exact_keys( $value, array( 'site_uuid', 'binding', 'generation', 'eligible', 'replacement_binding' ) ) && $value['site_uuid'] === $this->site_uuid
			&& $this->hash( $value['binding'] ) && is_int( $value['generation'] ) && $value['generation'] >= 0 && $value['generation'] <= PHP_INT_MAX - 3600 && is_bool( $value['eligible'] )
			&& ( null === $value['replacement_binding'] || ( $this->hash( $value['replacement_binding'] ) && $value['replacement_binding'] !== $value['binding'] ) );
	}

	/**
	 * Require a canonical digest/binding.
	 *
	 * @param mixed $value Digest.
	 * @return bool
	 */
	private function hash( $value ) {
		return is_string( $value ) && 1 === preg_match( '/\A[0-9a-f]{64}\z/D', $value );
	}

	/**
	 * Parse the echoed envelope; authentication happens separately.
	 *
	 * @param mixed $body Slashed form.
	 * @return array|false
	 */
	private function envelope( $body ) {
		$keys = array( 'contract', 'command_id', 'purpose', 'site_uuid', 'enrollment_binding', 'expected_generation', 'issued_at', 'expires_at', 'replacement_binding', 'confirm', 'nonce' );
		$c    = $this->form( $body, $keys );
		if ( false === $c || self::SOURCE !== $c['contract'] || ! $this->uuid( $c['command_id'] ) || $c['site_uuid'] !== $this->site_uuid || ! $this->hash( $c['enrollment_binding'] ) || ! $this->purpose( $c['purpose'], $c['confirm'] ) || '' === $c['nonce'] ) {
			return false;
		}
		foreach ( array( 'expected_generation', 'issued_at', 'expires_at' ) as $key ) {
			$c[ $key ] = $this->decimal( $c[ $key ] );
			if ( false === $c[ $key ] ) {
				return false;
			}
		}
		if ( $c['issued_at'] <= 0 || $c['expires_at'] <= $c['issued_at'] || $c['expires_at'] - $c['issued_at'] > 300 || ( 'rebind' === $c['purpose'] ? ! $this->hash( $c['replacement_binding'] ) || $c['replacement_binding'] === $c['enrollment_binding'] : '' !== $c['replacement_binding'] ) ) {
			return false;
		}
		unset( $c['nonce'] );
		return $c;
	}

	/**
	 * Require explicit purpose-specific affirmative intent.
	 *
	 * @param string $purpose Purpose.
	 * @param string $confirm Affirmation.
	 * @return bool
	 */
	private function purpose( $purpose, $confirm ) {
		return in_array( $purpose, array( 'grant', 'revoke', 'rebind' ), true ) && ( 'grant' === $purpose ? 'prepare' : 'close' ) === $confirm;
	}
}
