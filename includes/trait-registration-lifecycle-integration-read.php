<?php
/**
 * Fresh same-request diagnostic admission, separate read domains and private DATA.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Does not install a native observer or turn qualification into permission. */
trait Alynt_Drime_Backups_Uploader_Registration_Lifecycle_Integration_Read {
	/**
	 * One surviving same-root origin, never a previously returned result.
	 *
	 * @param array  $core Original core.
	 * @param array  $body Original small mutation intent.
	 * @param int    $actor Original actor.
	 * @param string $cookie Original cookie.
	 * @param mixed  $facts Original physical facts.
	 * @return array|false Non-authorizing candidate only.
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
		if ( ! $this->renew_lifecycle_read( $built, $core, $cookie, $actor, $facts ) || ! $this->claim( $original[0], $intent, $built['form'], $this->lifecycle_read_clock(), $original[1] ) || ! $this->renew_lifecycle_read( $built, $core, $cookie, $actor, $facts ) ) {
			return false;
		}
		$captured = $this->lifecycle_read( $core );
		$result   = $this->finish( $captured, $intent, $built['form'], $this->lifecycle_read_clock() );
		return is_array( $result ) && $this->renew_lifecycle_read( $built, $core, $cookie, $actor, $facts ) ? $result : false;
	}

	/**
	 * Same original request and both independently minted read nonce domains.
	 *
	 * @param array  $built Own read construction.
	 * @param array  $core Original core.
	 * @param string $cookie Original cookie.
	 * @param int    $actor Original actor.
	 * @param mixed  $facts Original physical facts.
	 * @return bool Isolated guard consistency, not auth-at-commit.
	 */
	private function renew_lifecycle_read( $built, $core, $cookie, $actor, $facts ) {
		$e = $built['blueprint']['envelope'];
		return $built['core'] === $core && $built['actor'] === $actor && $built['cookie'] === $cookie && $this->same_request( $core, $cookie, $actor ) && $this->lifecycle_physical( $core, $facts ) && $this->guard( $core, $built['intent']['nonce'], $built['issue_action'], $e ) && $this->same_request( $core, $cookie, $actor ) && $this->guard( $core, $built['blueprint']['nonce'], $built['blueprint']['action'], $e ) && $this->same_request( $core, $cookie, $actor ) && $this->lifecycle_physical( $core, $facts ) && $this->within_budget() && self::clock( $this->lifecycle_read_clock() );
	}

	/**
	 * Fresh read phase remains independently subordinate to the original root.
	 *
	 * @return array Primitive clocks only.
	 */
	private function lifecycle_read_clock() {
		return array(
			'started_ns' => $this->read_started,
			'now_ns'     => hrtime( true ),
			'utc'        => time(),
		);
	}
}
