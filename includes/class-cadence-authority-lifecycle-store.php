<?php
/**
 * Dormant schema-3 consent/lifecycle model; no runtime activation or permission UI.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Explicit model writes only; never loaded by the production bootstrap. */
final class Alynt_Drime_Backups_Uploader_Cadence_Authority_Lifecycle_Store extends Alynt_Drime_Backups_Uploader_Cadence_Authority_Store {
	use Alynt_Drime_Backups_Uploader_Cadence_Authority_Lifecycle_Preparation;

	/**
	 * Validate schema 3 without changing the older reader's accepted schema.
	 *
	 * @param array $state Stored model.
	 * @return bool
	 */
	protected function valid_state( array $state ) {
		if ( ! $this->exact_keys( $state, array( 'schema_version', 'generation', 'enrollment_binding', 'schedule_revision', 'claims', 'authority', 'dispatch', 'lifecycle' ) ) || 3 !== $state['schema_version'] || ! is_array( $state['lifecycle'] ) ) {
			return false;
		}
		$older = $state;
		unset( $older['lifecycle'] );
		$older['schema_version'] = 2;
		if ( ! parent::valid_state( $older ) || false !== $state['authority']['enabled'] ) {
			return false; // Even synthetic consent cannot activate this foundation.
		}
		$l = $state['lifecycle'];
		if ( ! $this->exact_keys( $l, array( 'phase', 'consent', 'permission_generation', 'history', 'changed_at' ) )
			|| ! in_array( $l['phase'], array( 'preparing', 'draining', 'disabled' ), true )
			|| ! is_bool( $l['consent'] ) || ! $this->valid_integer( $l['permission_generation'] )
			|| ! in_array( $l['history'], array( 'new_model', 'unknown' ), true )
			|| ! $this->valid_integer( $l['changed_at'] ) || $l['changed_at'] <= 0 ) {
			return false;
		}
		if ( $l['consent'] && ( 'preparing' !== $l['phase'] || 0 === $l['permission_generation'] ) ) {
			return false;
		}
		return $this->unfinished( $state ) ? 'draining' === $l['phase'] && ! $l['consent'] : 'draining' !== $l['phase'];
	}

	/**
	 * Record synthetic consent/revocation in one CAS, never authenticate an administrator.
	 *
	 * @param string      $binding Current local context binding.
	 * @param int         $expected_generation Exact current permission generation.
	 * @param bool        $consent Literal model choice, not request permission.
	 * @param string|null $replacement_binding Rebind only on revocation.
	 * @return int|WP_Error New generation; no execution or activation grant.
	 */
	public function record_consent( $binding, $expected_generation, $consent, $replacement_binding = null ) {
		if ( ! $this->valid_integer( $expected_generation ) || ! is_bool( $consent )
			|| ( null !== $replacement_binding && ( $consent || ! $this->valid_hash( $replacement_binding ) ) ) ) {
			return $this->error( 'lifecycle_invalid' );
		}
		return $this->update(
			$binding,
			function ( &$state ) use ( $expected_generation, $consent, $replacement_binding ) {
				$now = $this->lifecycle_now();
				if ( is_wp_error( $now ) || $now < $state['lifecycle']['changed_at'] ) {
					return $this->error( 'lifecycle_clock_invalid' );
				}
				if ( $expected_generation !== $state['lifecycle']['permission_generation'] ) {
					return $this->error( 'lifecycle_stale' );
				}
				if ( $consent && $this->unfinished( $state ) ) {
					return $this->error( 'lifecycle_recovery_pending' );
				}
				++$state['lifecycle']['permission_generation'];
				++$state['schedule_revision'];
				$state['lifecycle']['consent']    = $consent;
				$state['lifecycle']['changed_at'] = $now;
				$state['lifecycle']['phase']      = $consent ? 'preparing' : ( $this->unfinished( $state ) ? 'draining' : 'disabled' );
				if ( null !== $replacement_binding ) {
					$state['enrollment_binding'] = $replacement_binding;
				}
				return $state['lifecycle']['permission_generation'];
			}
		);
	}

	/**
	 * Reject the older initializer; it cannot erase lifecycle state or enable authority.
	 *
	 * @param string $binding Context.
	 * @param string $cadence Cadence.
	 * @param int    $due Due.
	 * @return WP_Error
	 */
	public function initialize_authority( $binding, $cadence, $due ) {
		return $this->error( 'lifecycle_api_required' );
	}

	/**
	 * Require the generation-bound revocation API rather than a blind inherited write.
	 *
	 * @param string      $binding Context.
	 * @param string|null $replacement_binding New context.
	 * @return WP_Error
	 */
	public function invalidate( $binding, $replacement_binding = null ) {
		return $this->error( 'lifecycle_api_required' );
	}

	/**
	 * Identify all unfinished work, without lease-age-based cancellation or eviction.
	 *
	 * @param array $state Valid model.
	 * @return bool
	 */
	private function unfinished( array $state ) {
		foreach ( $state['claims'] as $record ) {
			if ( ! in_array( $record['phase'], array( 'succeeded', 'failed_closed' ), true ) ) {
				return true;
			}
		}
		return isset( $state['dispatch'] ) && ! in_array( $state['dispatch']['phase'], array( 'completed', 'cancelled' ), true );
	}

	/**
	 * Validate the injected UTC clock before lifecycle arithmetic/writes.
	 *
	 * @return int|WP_Error
	 */
	private function lifecycle_now() {
		$now = call_user_func( $this->clock );
		return is_int( $now ) && $now > 0 && $now <= PHP_INT_MAX - 3600 ? $now : $this->error( 'lifecycle_clock_invalid' );
	}
}
