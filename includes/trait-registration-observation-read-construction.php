<?php
/**
 * Fixed private guarded read construction; no injected callbacks or IO ports.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Only wrappers owning the original origin and consumed read call this helper. */
trait Alynt_Drime_Backups_Uploader_Registration_Observation_Read_Construction {
	/**
	 * Build on this stack, never accept a returned blueprint or imported origin.
	 * Core/cookie facts below remain private locals, not retained handoff DATA.
	 *
	 * @param mixed $body Original small read issuance form.
	 * @return array|false Private wrapper-local construction facts only.
	 */
	private function build_observation( $body ) {
		if ( ! $this->busy || ! $this->observation_attempted || $this->interrupted ) {
			return false;
		}
		$i = Alynt_Drime_Backups_Uploader_Registration_Observation_Request::intent( $body );
		if ( 1 !== func_num_args() || false === $i || null === $this->origin || $i['observed_command_id'] !== $this->origin_data['mutation']['command_id'] ) {
			return false;
		}
		$this->active = true;
		$core         = $this->open_window();
		if ( false === $core || $this->installation( $core ) !== $this->origin_data['installation'] ) {
			return false;
		}
		$start = $this->started;
		$actor = get_current_user_id();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Original read actor/cookie pinned within this stack, not the historical mutation actor.
		$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
		$action = Alynt_Drime_Backups_Uploader_Registration_Observation_Request::issue_action( $core['blog'], $i['observed_command_id'] );
		if ( ! $this->guard( $core, $i['nonce'], $action, null ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->within_budget() ) {
			return false;
		}
		$id  = wp_generate_uuid4();
		$now = time();
		if ( ! is_string( $id ) || isset( $this->used[ $id ] ) || $now < 1 || $now > PHP_INT_MAX - 3900 ) {
			return false;
		}
		$form = array(
			'contract'            => 'scan_registration_observation_v2',
			'purpose'             => 'observe_registration',
			'command_id'          => $id,
			'site_uuid'           => $this->origin_data['scope']['site_uuid'],
			'observed_command_id' => $i['observed_command_id'],
			'issued_at'           => (string) $now,
			'expires_at'          => (string) ( $now + 300 ),
			'confirm'             => 'observe',
			'nonce'               => 'pending',
		);
		$p    = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $form );
		if ( false === $p ) {
			return false;
		}
		$form['nonce'] = wp_create_nonce( $p['action'] );
		$final         = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $form );
		if ( false === $final || $final['envelope'] !== $p['envelope'] || ! $this->origin->accepts( $body, $form, time() ) || ! $this->guard( $core, $i['nonce'], $action, $p['envelope'] ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->guard( $core, $final['nonce'], $final['action'], $p['envelope'] ) || ! $this->same_request( $core, $cookie, $actor ) || $this->started !== $start || ! $this->within_budget() ) {
			return false;
		}
		return array(
			'blueprint'      => $this->disposition() + array(
				'envelope'         => $p['envelope'],
				'nonce'            => $final['nonce'],
				'action'           => $final['action'],
				'barrier_verified' => false,
				'closure_proven'   => false,
			),
			'form'           => $form,
			'intent'         => $i,
			'core'           => $core,
			'actor'          => $actor,
			'cookie'         => $cookie,
			'issue_action'   => $action,
			'window_started' => $start,
		);
	}
}
