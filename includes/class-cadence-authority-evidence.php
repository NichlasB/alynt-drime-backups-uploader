<?php
/**
 * Explicitly injected read-only authority evidence; no production activation.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Reports model observations, never permission to dispatch or roll back. */
class Alynt_Drime_Backups_Uploader_Cadence_Authority_Evidence {
	use Alynt_Drime_Backups_Uploader_Cadence_Authority_Evidence_Context;
	use Alynt_Drime_Backups_Uploader_Cadence_Authority_Evidence_Inspection;

	/**
	 * Model reader.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Cadence_Authority_Store
	 */
	private $store;
	/**
	 * Local enrollment reader.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Dashboard_Connection
	 */
	private $connection;
	/**
	 * UTC seconds clock.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Construction does not initialize authority, add hooks or write options.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Cadence_Authority_Store $store Model.
	 * @param Alynt_Drime_Backups_Uploader_Dashboard_Connection    $connection Local pairing.
	 * @param callable|null                                        $clock UTC clock.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Cadence_Authority_Store $store, Alynt_Drime_Backups_Uploader_Dashboard_Connection $connection, $clock = null ) {
		$this->store      = $store;
		$this->connection = $connection;
		$this->clock      = null === $clock ? 'time' : $clock;
	}

	/**
	 * Read a redacted status projection. This is not a remote-action capability.
	 *
	 * @return array|WP_Error
	 */
	public function status() {
		$context = $this->context();
		$state   = $this->store->snapshot();
		$now     = call_user_func( $this->clock );
		if ( is_wp_error( $context ) || is_wp_error( $state ) || ! is_int( $now ) || $now <= 0 || $now > PHP_INT_MAX - 3600 ) {
			return $this->error( 'unavailable' );
		}
		if ( ! hash_equals( $state['enrollment_binding'], $context['binding'] ) ) {
			return $this->error( 'binding_changed' );
		}
		return array(
			'contract_version'      => 1,
			'mode'                  => 'client_owned_v1',
			'site_uuid'             => $context['site_uuid'],
			'key_id'                => $context['key_id'],
			'authority_revision'    => $state['schedule_revision'],
			'current_cadence'       => $state['authority']['cadence'],
			'next_due_at'           => $state['authority']['next_due_at'],
			'authority_fingerprint' => $this->store->authority_fingerprint( $state ),
			'model_enabled'         => $state['authority']['enabled'],
			'pending'               => $this->pending_phase( $state ),
			'observed_at'           => $now,
			'expires_at'            => $now + 300,
			'dispatch_supported'    => false,
			'execution_supported'   => false,
		);
	}

	/**
	 * Expose the internal binding to an explicit future initializer, not to status.
	 *
	 * @return string|WP_Error
	 */
	public function enrollment_binding() {
		$context = $this->context();
		return is_wp_error( $context ) ? $context : $context['binding'];
	}

	/**
	 * Summarize uncertainty without journal or ownership disclosure.
	 *
	 * @param array $state Strict model state.
	 * @return string
	 */
	private function pending_phase( array $state ) {
		$pending = 'none';
		if ( null !== $state['dispatch'] && in_array( $state['dispatch']['phase'], array( 'claimed', 'started', 'recovery_required' ), true ) ) {
			$pending = $state['dispatch']['phase'];
		}
		foreach ( $state['claims'] as $record ) {
			if ( in_array( $record['phase'], array( 'write_started', 'recovery_required' ), true ) ) {
				return 'recovery_required';
			}
			if ( 'claimed' === $record['phase'] && 'none' === $pending ) {
				$pending = 'claimed';
			}
		}
		return $pending;
	}

	/**
	 * Return stable support-safe failures only.
	 *
	 * @param string $code Failure suffix.
	 * @return WP_Error
	 */
	private function error( $code ) {
		return new WP_Error( 'authority_evidence_' . $code, 'Cadence authority evidence is unavailable.' );
	}
}
