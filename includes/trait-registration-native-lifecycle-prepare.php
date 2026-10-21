<?php
/**
 * Same-root preparation and fresh post-writer-setup mutation guards.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Own full form/pin returned only to the immediate private root stack. */
trait Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Prepare {
	/**
	 * Guard before protected capture and retain original material before any DML.
	 *
	 * @param array  $core Original core frame.
	 * @param array  $body Small mutation intent.
	 * @param int    $actor Original actor.
	 * @param string $cookie Original cookie.
	 * @param array  $facts Original borrowed physical facts.
	 * @return array|false Root-local full pin and construction, never public DATA.
	 */
	private function prepare_native_lifecycle( $core, $body, $actor, $cookie, $facts ) {
		$f     = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issuance( $body );
		$issue = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( $core['blog'], 'register_closed' );
		if ( ! $this->guard( $core, $f['nonce'], $issue, null ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->lifecycle_physical( $core, $facts ) || ! $this->within_budget() ) {
			return false;
		}
		$captured = $this->lifecycle_capture( $core );
		if ( false === $captured || ! $this->guard( $core, $f['nonce'], $issue, null ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->lifecycle_physical( $core, $facts ) || ! $this->within_budget() ) {
			return false;
		}
		$built = $this->construct_registration( $captured, $body );
		if ( false === $built || ! $this->same_request( $core, $cookie, $actor ) || ! $this->lifecycle_physical( $core, $facts ) || ! $this->within_budget() ) {
			return false;
		}
		$p = $built['proposal'];
		$c = array(
			'blog_id'  => $core['blog'],
			'prefix'   => $core['prefix'],
			'database' => DB_NAME,
			'actor_id' => $actor,
		);
		if ( ! $this->guard( $core, $built['nonce'], $built['action'], $p['command'] ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->prepare_candidate( $captured, $p, $c, time() ) ) {
			return false;
		}
		$pin = $this->retain_admission_origin( $core, $captured, $actor, $this->started );
		$own = array( $pin, $built );
		return false !== $pin && $this->guard_native_mutation( $core, $body, $own, $actor, $cookie, $facts ) ? $own : false;
	}

	/**
	 * Both original domains, repeated AFTER idle writer setup and before BEGIN.
	 *
	 * @param array  $core Original core frame.
	 * @param array  $body Original small intent.
	 * @param array  $own Root-local pin/construction.
	 * @param int    $actor Original actor.
	 * @param string $cookie Original cookie.
	 * @param array  $facts Original physical facts.
	 * @return bool Exact continuity, never an imported guard certificate.
	 */
	private function guard_native_mutation( $core, $body, $own, $actor, $cookie, $facts ) {
		$built = $own[1];
		$e     = $built['proposal']['command'];
		$f     = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issuance( $body );
		$issue = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( $core['blog'], 'register_closed' );
		return $this->lifecycle_physical( $core, $facts ) && $this->guard( $core, $f['nonce'], $issue, $e ) && $this->same_request( $core, $cookie, $actor ) && $this->guard( $core, $built['nonce'], $built['action'], $e ) && $this->same_request( $core, $cookie, $actor ) && $this->lifecycle_physical( $core, $facts ) && $this->budget( $e ) && $this->admission_origin_same( $own[0] );
	}
}
