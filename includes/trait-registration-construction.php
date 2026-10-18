<?php
/**
 * Private fixed registration blueprint construction; no storage continuation.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Called only by explicit test reflection until a native reader is approved. */
trait Alynt_Drime_Backups_Uploader_Registration_Construction {
	/**
	 * Once-attempted, fresh core guards around full data validation and nonce.
	 * No public raw-frame provider, generation flag, actor or clock argument.
	 *
	 * @param mixed $observed Already captured fixed private frame.
	 * @param mixed $body Original small issuance form.
	 * @return array|false Private blueprint only; not publicly reachable.
	 */
	private function construct_registration( $observed, $body ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact method comparison, never normalize.
		if ( $this->active || $this->consumed || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? null ) ) {
			return false;
		}
		$this->consumed = true;
		$this->active   = true;
		try {
			$f = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issuance( $body );
			if ( false === $f || 'register_closed' !== $f['purpose'] ) {
				return false;
			}
			$core = $this->frame();
			if ( false === $core || ! $this->pin( $core ) || ! function_exists( 'wp_generate_uuid4' ) || ! function_exists( 'wp_create_nonce' ) ) {
				return false;
			}
			$actor = get_current_user_id();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Original private cookie pinned only within this stack, never returned/stored.
			$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
			$action = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( $core['blog'], $f['purpose'] );
			if ( ! $this->guard( $core, $f['nonce'], $action, null ) || ! $this->same_request( $core, $cookie, $actor ) ) {
				return false;
			}
			$data = $this->eligible_registration( $observed, $core );
			if ( false === $data ) {
				return false;
			}
			$issued = time();
			if ( $issued < 1 || $issued > PHP_INT_MAX - 3900 ) {
				return false;
			}
			$ids = array();
			for ( $n = 0; $n < 10; ++$n ) {
				$id = wp_generate_uuid4();
				if ( ! is_string( $id ) || 1 !== preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $id ) || isset( $data['used'][ $id ] ) ) {
					return false;
				}
				$data['used'][ $id ] = true;
				$ids[]               = $id;
			}
			$e                              = array(
				'contract'                 => 'scan_registration_request_v2',
				'purpose'                  => 'register_closed',
				'command_id'               => $ids[0],
				'site_uuid'                => $data['scope']['site_uuid'],
				'enrollment_binding'       => $data['scope']['enrollment_binding'],
				'expected_generation'      => $data['scope']['authority_generation'],
				'expected_ledger_revision' => $data['revision'],
				'context_digest'           => str_repeat( '0', 64 ),
				'issued_at'                => $issued,
				'expires_at'               => $issued + 300,
				'confirm'                  => 'register',
			);
			$roles                          = array( 'wpvivid_snapshot', 'outbox_snapshot', 'queue_addition' );
			$p                              = array(
				'command'                 => $e,
				'actor_id'                => $actor,
				'operation_id'            => $ids[1],
				'effect_ids'              => array_combine( $roles, array_slice( $ids, 2, 3 ) ),
				'reservation_command_ids' => array_combine( $roles, array_slice( $ids, 5, 3 ) ),
				'terminal_id'             => $ids[8],
				'resolution_id'           => $ids[9],
				'dependency_digest'       => $data['dependency_digest'],
			);
			$p['command']['context_digest'] = Alynt_Drime_Backups_Uploader_Closed_Registration_Contract::registration_context( $data['scope'], $p );
			if ( ! is_string( $p['command']['context_digest'] ) || ! is_string( Alynt_Drime_Backups_Uploader_Closed_Registration_Contract::register( $data['before'], $data['scope'], $p, $issued ) ) || ! $this->guard( $core, $f['nonce'], $action, $p['command'] ) || ! $this->same_request( $core, $cookie, $actor ) ) {
				return false;
			}
			$form          = array_map( 'strval', $p['command'] );
			$form['nonce'] = 'pending';
			$parsed        = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $form );
			if ( false === $parsed ) {
				return false;
			}
			$nonce = wp_create_nonce( $parsed['action'] );
			if ( ! is_string( $nonce ) ) {
				return false;
			}
			$form['nonce'] = $nonce;
			$final         = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $form );
			if ( false === $final || $final['envelope'] !== $p['command'] || $final['nonce'] !== $nonce || ! $this->guard( $core, $f['nonce'], $action, $p['command'] ) || ! $this->same_request( $core, $cookie, $actor ) ) {
				return false;
			}
			return $this->disposition() + array(
				'proposal' => $p,
				'nonce'    => $nonce,
				'action'   => $final['action'],
			);
		} catch ( Throwable $error ) {
			return false;
		} finally {
			$this->active = false;
		}
	}
}
