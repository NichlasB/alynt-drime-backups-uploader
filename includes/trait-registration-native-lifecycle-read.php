<?php
/**
 * Same-root guarded read with post-setup/pre-BEGIN and post-close checks.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Own typed envelope and source-observed barrier, never supplied qualification. */
trait Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Read {
	/**
	 * One fresh read under the original actor/clock, after writer erasure.
	 *
	 * @param array  $core Original core.
	 * @param array  $body Original small mutation intent.
	 * @param int    $actor Original actor.
	 * @param string $cookie Original cookie.
	 * @param array  $facts Original physical facts.
	 * @return array|false Private observation, no execution/retry authority.
	 */
	private function continue_lifecycle( $core, $body, $actor, $cookie, $facts ) {
		$f        = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issuance( $body );
		$original = array( $this->origin_data, array_keys( $this->used ) );
		$issue    = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( $core['blog'], 'register_closed' );
		if ( null === $this->origin || ! $this->guard( $core, $f['nonce'], $issue, $original[0]['mutation'] ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->lifecycle_physical( $core, $facts ) || ! $this->within_budget() ) {
			return false;
		}
		$intent = array(
			'purpose'             => 'observe_registration',
			'confirm'             => 'observe',
			'observed_command_id' => $original[0]['mutation']['command_id'],
			'nonce'               => wp_create_nonce( Alynt_Drime_Backups_Uploader_Registration_Observation_Request::issue_action( $core['blog'], $original[0]['mutation']['command_id'] ) ),
		);
		if ( array( $this->origin_data, array_keys( $this->used ) ) !== $original || ! $this->same_request( $core, $cookie, $actor ) || ! $this->lifecycle_physical( $core, $facts ) || ! $this->within_budget() ) {
			return false;
		}
		$this->observation_attempted = true;
		$built                       = $this->build_observation( $intent );
		if ( false === $built || array( $this->origin_data, array_keys( $this->used ) ) !== $original || ! $this->renew_lifecycle_read( $built, $core, $cookie, $actor, $facts ) || ! $this->stage( $original[0], $intent, $built['form'], $this->lifecycle_read_clock(), $original[1] ) ) {
			return false;
		}
		$this->clear_origin();
		if ( ! $this->renew_lifecycle_read( $built, $core, $cookie, $actor, $facts ) || ! $this->claim( $original[0], $intent, $built['form'], $this->lifecycle_read_clock(), $original[1] ) || ! $this->renew_lifecycle_read( $built, $core, $cookie, $actor, $facts ) || ! $this->setup_native_barrier( $core ) || ! $this->renew_lifecycle_read( $built, $core, $cookie, $actor, $facts ) ) {
			return false;
		}
		$captured = $this->barrier_capture( $core, $this->read_started, $built['blueprint']['envelope'] );
		$closed   = $this->release_native_barrier();
		if ( false === $captured || ! $closed || 10 !== $this->barrier_reads || 2 !== $this->barrier_metadata_checks || 2 !== $this->zero_dml_checks ) {
			return false;
		}
		$result = $this->finish( $captured, $intent, $built['form'], $this->lifecycle_read_clock() );
		if ( ! is_array( $result ) || ! $this->renew_lifecycle_read( $built, $core, $cookie, $actor, $facts ) ) {
			return false;
		}
		$result['classification']   = 'not_observed_in_candidate' === $result['classification'] ? 'not_observed_at_barrier' : $result['classification'];
		$result['barrier_verified'] = true;
		$result['candidate_only']   = false;
		return $result;
	}

	/**
	 * Fixed NEW idle barrier; caller must renew both read guards after setup.
	 *
	 * @param array $core Original core.
	 * @return bool No BEGIN, protected rows or permission yet.
	 */
	private function setup_native_barrier( $core ) {
		if ( true !== $this->admission_receipt || null !== $this->barrier_receipt || null !== $this->native_resource || null !== $this->native_outcome || null !== $this->before || null !== $this->proposal || ! $this->within_budget() ) {
			return false;
		}
		$this->native_resource = 'barrier';
		$this->barrier_receipt = false;
		return $this->open_lifecycle_session( true ) && $this->barrier_metadata( $core['options'] );
	}
}
