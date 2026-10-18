<?php
/**
 * Pure bounded row/coherence contract; no reader or native admission.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-closed-writer-contract-privacy.php';
require_once __DIR__ . '/class-closed-writer-json-contract.php';

/** Synthetic facts stay non-authorizing, even when every comparison passes. */
final class Alynt_Drime_Backups_Uploader_Closed_Writer_Observation_Contract {
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;

	/**
	 * Private observation fingerprint, not a posted digest.
	 *
	 * @var string
	 */
	private $binding;
	/**
	 * Existing semantic context v1; not a native certificate.
	 *
	 * @var string
	 */
	private $context;

	/**
	 * Retain hashes only, not raw target/safety bytes or connection facts.
	 *
	 * @param string $binding Private fingerprint.
	 * @param string $context Existing context.
	 */
	private function __construct( $binding, $context ) {
		$this->binding = $binding;
		$this->context = $context;
	}

	/**
	 * Model four ordered observations supplied as data, never call providers.
	 *
	 * @param mixed $scope Synthetic fixed scope/session metadata.
	 * @param mixed $safety_a First safety row.
	 * @param mixed $settings_a First target row.
	 * @param mixed $safety_b Second safety row.
	 * @param mixed $settings_b Second target row.
	 * @return self|false Data contract, never a write-capable handle.
	 */
	public static function simulate( $scope, $safety_a, $settings_a, $safety_b, $settings_b ) {
		if ( ! self::scope( $scope ) ) {
			return false;
		}
		$first  = self::row( $safety_a, true );
		$target = self::row( $settings_a, false );
		if ( false === $first || false === $target || self::row( $safety_b, true ) !== $first || self::row( $settings_b, false ) !== $target || $first[0] === $target[0] ) {
			return false;
		}
		$state = Alynt_Drime_Backups_Uploader_Closed_Writer_Json_Contract::decode( $first[4] );
		if ( false === $state || ! Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::supported( $state, $scope['site_uuid'] ) ) {
			return false;
		}
		$context = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::context( $state, $target[4], $scope['site_uuid'], $scope['blog_id'], $target[2] );
		if ( false === $context ) {
			return false;
		}
		$parts = array( 'raw_observation_contract_v1', (string) $scope['blog_id'], $scope['prefix'], $scope['database'], $scope['site_uuid'], $scope['session_id'], $context['context_digest'] );
		$hash  = hash_init( 'sha256' );
		foreach ( array_merge( $parts, $first, $target ) as $part ) {
			hash_update( $hash, strlen( $part ) . ':' . $part );
		}
		return new self( hash_final( $hash ), $context['context_digest'] );
	}

	/**
	 * Exact typed metadata; a caller's session string does not prove ownership.
	 *
	 * @param mixed $scope Synthetic metadata.
	 * @return bool
	 */
	private static function scope( $scope ) {
		return is_array( $scope ) && Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::keys( $scope, array( 'blog_id', 'prefix', 'database', 'site_uuid', 'session_id' ) ) && is_int( $scope['blog_id'] ) && $scope['blog_id'] > 0 && $scope['blog_id'] < PHP_INT_MAX && is_string( $scope['prefix'] ) && 1 === preg_match( '/^[A-Za-z0-9_]{1,64}$/D', $scope['prefix'] ) && is_string( $scope['database'] ) && 1 === preg_match( '/^[A-Za-z0-9_-]{1,64}$/D', $scope['database'] ) && Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::uuid( $scope['site_uuid'] ) && is_string( $scope['session_id'] ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $scope['session_id'] );
	}

	/**
	 * Validate a conditional bounded projection; cannot prove pre-fetch limits.
	 *
	 * @param mixed $row Synthetic projected row.
	 * @param bool  $safety Fixed selector, never a posted option name.
	 * @return array|false Canonical private tuple.
	 */
	private static function row( $row, $safety ) {
		if ( ! is_array( $row ) || ! Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::keys( $row, array( 'option_id', 'option_name', 'autoload', 'octets', 'value' ) ) ) {
			return false;
		}
		$name = $safety ? 'alynt_drime_backups_schedule_safety_state' : 'alynt_drime_backups_settings';
		$cap  = $safety ? 131072 : 1048576;
		$id   = $row['option_id'];
		$max  = (string) ( PHP_INT_MAX - 1 );
		if ( ! is_string( $id ) || strlen( $id ) > strlen( $max ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $id ) || ( strlen( $id ) === strlen( $max ) && strcmp( $id, $max ) > 0 ) || $name !== $row['option_name'] || ! in_array( $row['autoload'], array( 'no', 'off', 'auto-off' ), true ) || ! is_string( $row['value'] ) || '' === $row['value'] || strlen( $row['value'] ) > $cap || (string) strlen( $row['value'] ) !== $row['octets'] ) {
			return false;
		}
		return array( $id, $name, $row['autoload'], $row['octets'], $row['value'] );
	}

	/**
	 * Compare exact simulated observations.
	 *
	 * @param mixed $other Another simulated observation, not native context.
	 * @return bool Exact same raw/scope/session data only.
	 */
	public function matches( $other ) {
		return $other instanceof self && hash_equals( $this->binding, $other->binding );
	}

	/**
	 * Compare existing semantic context, without exposing private binding.
	 *
	 * @param mixed $digest Existing semantic context digest v1.
	 * @return bool Data equality only, never request admission.
	 */
	public function matches_context( $digest ) {
		return is_string( $digest ) && hash_equals( $this->context, $digest );
	}
}
