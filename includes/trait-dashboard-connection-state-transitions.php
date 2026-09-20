<?php
/**
 * Dashboard connection state transition helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles dashboard connection state transitions and enrollment mutations.
 *
 * @since 0.5.22
 */
trait Alynt_Drime_Backups_Uploader_Dashboard_Connection_State_Transitions {
	/**
	 * Saves local shell intent without completing pairing.
	 *
	 * @param array<string,mixed> $raw Raw admin input.
	 * @return array<string,mixed>
	 */
	public function update_shell( array $raw ) {
		$current = $this->get();
		$state   = $current;

		$action = isset( $raw['connection_action'] ) ? sanitize_key( wp_unslash( $raw['connection_action'] ) ) : '';

		if ( 'prepare' === $action && ! empty( $raw['read_only_opt_in'] ) ) {
			$state['connection_status'] = self::STATUS_READY;
			$state['last_error_code']   = '';
		} elseif ( 'parse_token' === $action && ! empty( $raw['read_only_opt_in'] ) ) {
			$token  = isset( $raw['pairing_token'] ) ? (string) wp_unslash( $raw['pairing_token'] ) : '';
			$parsed = $this->parse_pairing_token( $token );

			if ( is_wp_error( $parsed ) ) {
				$state['last_error_code'] = $parsed->get_error_code();
			} else {
				$state['connection_status']          = self::STATUS_TOKEN_READY;
				$state['dashboard_origin']           = $parsed['dashboard_origin'];
				$state['expected_client_origin']     = $parsed['expected_client_origin'];
				$state['pending_enrollment_id']      = $parsed['enrollment_id'];
				$state['pairing_expires_at']         = $parsed['expires_at'];
				$state['dashboard_origin_confirmed'] = false;
				$state['last_error_code']            = '';
			}
		} elseif ( 'confirm_origin' === $action && ! empty( $raw['confirm_dashboard_origin'] ) && self::STATUS_TOKEN_READY === $state['connection_status'] ) {
			$state['connection_status']          = self::STATUS_CONFIRMED;
			$state['dashboard_origin_confirmed'] = true;
			$state['last_error_code']            = '';
		} elseif ( 'revoke' === $action ) {
			$state                      = self::defaults();
			$state['connection_status'] = self::STATUS_REVOKED;
			$state['revoked_at']        = time();
		} elseif ( 'disable_remote_actions' === $action ) {
			$state['remote_actions_enabled']               = false;
			$state['action_key_id']                        = '';
			$state['action_public_key']                    = '';
			$state['remote_actions_opted_in_at']           = 0;
			$state['schedule_mutation_enabled']            = false;
			$state['schedule_mutation_enabled_at']         = 0;
			$state['schedule_rollback_preview_enabled']    = false;
			$state['schedule_rollback_preview_enabled_at'] = 0;
			$state['last_error_code']                      = '';
		} elseif ( 'enable_schedule_mutation' === $action ) {
			if ( self::STATUS_PAIRED === $state['connection_status'] && ! empty( $state['remote_actions_enabled'] ) && ! empty( $raw['schedule_mutation_opt_in'] ) ) {
				$state['schedule_mutation_enabled']    = true;
				$state['schedule_mutation_enabled_at'] = time();
				$state['last_error_code']              = '';
			} else {
				$state['last_error_code'] = 'schedule_mutation_opt_in_required';
			}
		} elseif ( 'disable_schedule_mutation' === $action ) {
			$state['schedule_mutation_enabled']            = false;
			$state['schedule_mutation_enabled_at']         = 0;
			$state['schedule_rollback_preview_enabled']    = false;
			$state['schedule_rollback_preview_enabled_at'] = 0;
			$state['last_error_code']                      = '';
		} elseif ( 'disable' === $action ) {
			$state = self::defaults();
		}

		if ( self::STATUS_PAIRED !== $state['connection_status'] ) {
			$state['status_endpoint_enabled'] = false;
		}
		update_option( self::OPTION_NAME, $state, false );
		$this->sync_option_cache( $state );

		return $state;
	}

	/**
	 * Records a safe local pairing error without contacting the dashboard.
	 *
	 * @since 0.5.3
	 *
	 * @param string $code Error code.
	 * @return array<string,mixed>
	 */
	public function record_pairing_error( $code ) {
		return $this->save_pairing_error( $this->get(), $code );
	}

	/**
	 * Records the latest authenticated dashboard read timestamp.
	 *
	 * @since 0.5.3
	 *
	 * @return void
	 */
	public function record_authenticated_read() {
		$state                               = $this->get();
		$state['last_authenticated_read_at'] = time();
		$state['last_error_code']            = '';
		update_option( self::OPTION_NAME, $state, false );
		$this->sync_option_cache( $state );
	}

	/**
	 * Saves a safe pairing error on the local connection state.
	 *
	 * @param array<string,mixed> $state State.
	 * @param string              $code Error code.
	 * @return array<string,mixed>
	 */
	private function save_pairing_error( array $state, $code ) {
		$state['last_error_code'] = sanitize_key( (string) $code );

		if ( self::STATUS_PAIRED !== $state['connection_status'] ) {
			$state['status_endpoint_enabled'] = false;
		}

		update_option( self::OPTION_NAME, $state, false );
		$this->sync_option_cache( $state );

		return $state;
	}
}
