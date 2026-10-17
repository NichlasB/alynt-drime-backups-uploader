<?php
/**
 * Bounded fixture intents and receipts; never authenticated commands.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Exact closed-only domain; no target values, arbitrary names or callbacks. */
trait Alynt_Drime_Backups_Uploader_Closed_Writer_Barrier_Records {
	/**
	 * Fixed canonical field order, independent of input array order.
	 *
	 * @param string $purpose Fixture operation.
	 * @return array
	 */
	private function barrier_keys( $purpose ) {
		return 'begin' === $purpose
			? array( 'command_id', 'site_uuid', 'enrollment_binding', 'expected_generation', 'kind', 'target', 'fields', 'before_hash', 'after_hash', 'issued_at', 'expires_at' )
			: array( 'command_id', 'operation_id', 'site_uuid', 'enrollment_binding', 'expected_generation', 'closure_generation', 'observed_hash', 'issued_at', 'expires_at' );
	}

	/**
	 * Require a closed fixture intent with a fixed original 300-second window.
	 *
	 * @param mixed  $c Intent.
	 * @param string $purpose Explicit method purpose.
	 * @return bool
	 */
	private function valid_barrier_command( $c, $purpose ) {
		if ( ! in_array( $purpose, array( 'begin', 'finish', 'abandon' ), true ) || ! is_array( $c ) || ! $this->exact_keys( $c, $this->barrier_keys( $purpose ) )
			|| ! $this->valid_uuid( $c['command_id'] ) || $c['site_uuid'] !== $this->site_uuid || ! $this->valid_hash( $c['enrollment_binding'] ) || ! $this->valid_integer( $c['expected_generation'] )
			|| ! is_int( $c['issued_at'] ) || $c['issued_at'] <= 0 || $c['issued_at'] > PHP_INT_MAX - 3600 || ! is_int( $c['expires_at'] ) || $c['expires_at'] <= $c['issued_at'] || $c['expires_at'] > $c['issued_at'] + 300 ) {
			return false;
		}
		if ( 'begin' !== $purpose ) {
			return $this->valid_uuid( $c['operation_id'] ) && $c['command_id'] !== $c['operation_id'] && $this->valid_integer( $c['closure_generation'] ) && $this->valid_hash( $c['observed_hash'] );
		}
		$map = array(
			'settings_patch'   => array( 'settings', array( 'auto_scan_enabled', 'scan_interval' ) ),
			'connection_patch' => array( 'connection', array( 'action_key_id', 'dashboard_origin', 'remote_actions_enabled', 'status_endpoint_enabled' ) ),
		);
		if ( ! is_string( $c['kind'] ) || ! isset( $map[ $c['kind'] ] ) || $c['target'] !== $map[ $c['kind'] ][0] || ! is_array( $c['fields'] ) || empty( $c['fields'] ) || count( $c['fields'] ) > 4 || array_values( $c['fields'] ) !== $c['fields'] ) {
			return false;
		}
		foreach ( $c['fields'] as $field ) {
			if ( ! is_string( $field ) || ! in_array( $field, $map[ $c['kind'] ][1], true ) ) {
				return false;
			}
		}
		$fields = array_values( array_unique( $c['fields'] ) );
		sort( $fields, SORT_STRING );
		return $fields === $c['fields'] && $this->valid_hash( $c['before_hash'] ) && $this->valid_hash( $c['after_hash'] ) && $c['before_hash'] !== $c['after_hash'];
	}

	/**
	 * Canonical fixture consistency digest; not a signature or authorization.
	 *
	 * @param array  $c Exact intent.
	 * @param string $purpose Method purpose.
	 * @return string
	 */
	private function barrier_digest( array $c, $purpose ) {
		$ordered = array(
			'domain'  => self::BARRIER_SOURCE,
			'purpose' => $purpose,
		);
		foreach ( $this->barrier_keys( $purpose ) as $key ) {
			$ordered[ $key ] = $c[ $key ];
		}
		return hash( 'sha256', wp_json_encode( $ordered ) );
	}

	/**
	 * Validate an immutable bounded receipt with exact epoch/time semantics.
	 *
	 * @param mixed $r Receipt.
	 * @return bool
	 */
	private function valid_barrier_receipt( $r ) {
		if ( ! is_array( $r ) || ! $this->exact_keys( $r, array( 'purpose', 'command', 'request_hash', 'result_generation', 'committed_at' ) ) || ! $this->valid_barrier_command( $r['command'], $r['purpose'] )
			|| ! $this->valid_hash( $r['request_hash'] ) || ! $this->valid_integer( $r['result_generation'] ) || ! is_int( $r['committed_at'] ) || $r['committed_at'] <= 0 || $r['committed_at'] > PHP_INT_MAX - 3600 ) {
			return false;
		}
		$c   = $r['command'];
		$raw = wp_json_encode( $r );
		return $c['expected_generation'] < PHP_INT_MAX - 1 && $r['result_generation'] === $c['expected_generation'] + 1 && $c['issued_at'] <= $r['committed_at'] && $r['committed_at'] < $c['expires_at']
			&& hash_equals( $r['request_hash'], $this->barrier_digest( $c, $r['purpose'] ) ) && is_string( $raw ) && strlen( $raw ) <= self::RECEIPT_BYTES;
	}

	/**
	 * A terminal fixture observation refers to the exact original closure.
	 *
	 * @param array $r Terminal receipt.
	 * @param array $open Begin receipt.
	 * @return bool
	 */
	private function valid_barrier_completion( array $r, array $open ) {
		$c = $r['command'];
		$b = $open['command'];
		if ( $c['operation_id'] !== $b['command_id'] || $c['closure_generation'] !== $open['result_generation'] || $c['enrollment_binding'] !== $b['enrollment_binding'] || $r['committed_at'] < $open['committed_at'] ) {
			return false;
		}
		return 'finish' === $r['purpose']
			? $c['observed_hash'] === $b['after_hash'] && $c['issued_at'] === $b['issued_at'] && $c['expires_at'] === $b['expires_at']
			: $c['issued_at'] >= $open['committed_at'] && in_array( $c['observed_hash'], array( $b['before_hash'], $b['after_hash'] ), true );
	}
}
