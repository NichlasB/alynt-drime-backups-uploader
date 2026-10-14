<?php
/**
 * Pure dormant command/closure transitions with immutable bounded receipts.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Synthetic intents never establish administrator authorization or execution. */
trait Alynt_Drime_Backups_Uploader_Consent_Ledger_Commands {
	/**
	 * Canonical digest independent of incoming array key order; not a signature.
	 *
	 * @param array $command Validated intent.
	 * @return string
	 */
	private function command_digest( array $command ) {
		$ordered = array( 'domain' => 'adb-consent-fixture-command-v1' );
		foreach ( array( 'command_id', 'purpose', 'site_uuid', 'enrollment_binding', 'expected_generation', 'issued_at', 'expires_at', 'replacement_binding' ) as $key ) {
			$ordered[ $key ] = $command[ $key ];
		}
		return hash( 'sha256', wp_json_encode( $ordered ) );
	}

	/**
	 * Find retained exact intent before stale epoch/binding or expiry checks.
	 *
	 * @param array  $state Model.
	 * @param string $id Command identity.
	 * @return array|null
	 */
	private function prior_receipt( array $state, $id ) {
		$last = $state['ledger']['last_receipt'];
		if ( null !== $last && $last['command']['command_id'] === $id ) {
			return $last;
		}
		return isset( $state['ledger']['receipts'][ $id ] ) ? $state['ledger']['receipts'][ $id ] : null;
	}

	/**
	 * Apply one transformation in memory before the outer exact-byte CAS.
	 *
	 * @param array  $state Model by reference.
	 * @param array  $command Validated original intent.
	 * @param string $digest Canonical hash.
	 * @param int    $now Valid UTC.
	 * @return true|WP_Error
	 */
	private function change_command( array &$state, array $command, $digest, $now ) {
		$grant = 'grant' === $command['purpose'];
		if ( ! $this->headroom( $state, $grant ? 2 : 1 ) ) {
			return $this->error( 'ledger_counter' );
		}
		if ( $grant && ( $this->ledger_unfinished( $state ) || $state['lifecycle']['consent'] ) ) {
			return $this->error( 'ledger_grant_blocked' );
		}
		if ( $grant && ( $command['issued_at'] > $now || $command['expires_at'] <= $now ) ) {
			return $this->error( 'ledger_expired' );
		}
		$previous = $state['ledger']['last_receipt'];
		if ( $grant && count( $state['ledger']['receipts'] ) >= self::MAX_RECEIPTS - 1 ) {
			return $this->error( 'ledger_capacity' );
		}
		++$state['generation'];
		++$state['schedule_revision'];
		++$state['lifecycle']['permission_generation'];
		$state['lifecycle']['consent']    = $grant;
		$state['lifecycle']['phase']      = $grant ? 'preparing' : ( $this->ledger_unfinished( $state ) ? 'draining' : 'disabled' );
		$state['lifecycle']['changed_at'] = $now;
		if ( 'rebind' === $command['purpose'] ) {
			$state['enrollment_binding'] = $command['replacement_binding'];
		}
		$state['ledger']['grant_command_id'] = $grant ? $command['command_id'] : null;
		$state['ledger']['last_receipt']     = array(
			'command'           => $command,
			'request_hash'      => $digest,
			'result_generation' => $state['lifecycle']['permission_generation'],
			'committed_at'      => $now,
		);
		if ( null !== $previous && count( $state['ledger']['receipts'] ) < self::MAX_RECEIPTS ) {
			$candidate = $state;
			$candidate['ledger']['receipts'][ $previous['command']['command_id'] ] = $previous;
			$raw = wp_json_encode( $candidate );
			if ( is_string( $raw ) && strlen( $raw ) <= self::MAX_BYTES ) {
				$state = $candidate;
			} elseif ( $grant || 'grant' === $previous['command']['purpose'] ) {
				return $this->error( 'ledger_capacity' ); // Never discard the grant being closed.
			}
		}
		$raw = wp_json_encode( $state );
		if ( $grant && ( count( $state['ledger']['receipts'] ) >= self::MAX_RECEIPTS || ! is_string( $raw ) || strlen( $raw ) > self::MAX_BYTES - self::CLOSURE_BYTES ) ) {
			return $this->error( 'ledger_capacity' );
		}
		return true;
	}
}
