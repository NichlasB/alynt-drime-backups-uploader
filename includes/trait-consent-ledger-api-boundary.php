<?php
/**
 * Deny inherited legacy mutations on the dormant consent successor.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No implicit creation, legacy claim, journal resolution or blind invalidation. */
trait Alynt_Drime_Backups_Uploader_Consent_Ledger_API_Boundary {
	/**
	 * Reject initialization without the explicit migration contract.
	 *
	 * @param string $binding Context.
	 * @return WP_Error
	 */
	public function initialize( $binding ) {
		unset( $binding );
		return $this->error( 'ledger_api_required' );
	}

	/**
	 * Reject inherited work reservation.
	 *
	 * @param string $binding Context.
	 * @param string $source Source.
	 * @param array  $evidence Evidence.
	 * @return WP_Error
	 */
	public function claim( $binding, $source, array $evidence ) {
		unset( $binding, $source, $evidence );
		return $this->error( 'ledger_api_required' );
	}

	/**
	 * Reject inherited journal transitions, including apparent recovery.
	 *
	 * @param string $binding Context.
	 * @param string $source Source.
	 * @param string $owner Owner.
	 * @param string $hash Hash.
	 * @param string $from Prior phase.
	 * @param string $to New phase.
	 * @return WP_Error
	 */
	public function transition( $binding, $source, $owner, $hash, $from, $to ) {
		unset( $binding, $source, $owner, $hash, $from, $to );
		return $this->error( 'ledger_api_required' );
	}

	/**
	 * Reject invalidation without an exact command receipt/epoch.
	 *
	 * @param string      $binding Context.
	 * @param string|null $replacement_binding New context.
	 * @return WP_Error
	 */
	public function invalidate( $binding, $replacement_binding = null ) {
		unset( $binding, $replacement_binding );
		return $this->error( 'ledger_api_required' );
	}
}
