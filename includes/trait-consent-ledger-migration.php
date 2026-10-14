<?php
/**
 * Explicit fixture-only migration; no read-side schema creation or repair.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Migrates only strict schema 3, retaining due/journals/history and closing consent. */
trait Alynt_Drime_Backups_Uploader_Consent_Ledger_Migration {
	/**
	 * Conditional existing-row migration; never creates missing identity or storage.
	 *
	 * @param string $binding Expected context.
	 * @param int    $expected_generation Expected permission epoch.
	 * @return array|WP_Error Internal model, not authorization.
	 */
	public function prepare_ledger( $binding, $expected_generation ) {
		if ( ! $this->enabled ) {
			return $this->error( 'ledger_disabled' );
		}
		if ( ! $this->valid_hash( $binding ) || ! $this->valid_integer( $expected_generation ) ) {
			return $this->error( 'ledger_invalid_command' );
		}
		try {
			for ( $attempt = 0; $attempt < 3; $attempt++ ) {
				$row = $this->backend->read( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION );
				if ( ! is_array( $row ) || ! isset( $row['option_value'], $row['autoload'] ) || ! is_string( $row['option_value'] ) || strlen( $row['option_value'] ) > self::MAX_BYTES || ! in_array( $row['autoload'], array( 'no', 'off', 'auto-off' ), true ) ) {
					return $this->error( 'ledger_unavailable' );
				}
				$state = json_decode( $row['option_value'], true, 16 );
				if ( ! is_array( $state ) || ( ! $this->valid_state( $state ) && ! $this->valid_lifecycle_state( $state ) ) ) {
					return $this->error( 'ledger_unavailable' );
				}
				$now = $this->ledger_now( $state );
				if ( is_wp_error( $now ) ) {
					return $now;
				}
				if ( $state['enrollment_binding'] !== $binding || $state['lifecycle']['permission_generation'] !== $expected_generation ) {
					return $this->error( 'ledger_stale' );
				}
				if ( 4 === $state['schema_version'] ) {
					return $state; // Observation only; cannot reset a grant or receipts.
				}
				if ( $this->ledger_unfinished( $state ) || ! $this->headroom( $state, 2 ) ) {
					return $this->error( 'ledger_migration_blocked' );
				}
				$state['schema_version'] = 4;
				++$state['generation'];
				++$state['schedule_revision'];
				++$state['lifecycle']['permission_generation'];
				$state['lifecycle']['consent']    = false;
				$state['lifecycle']['phase']      = 'disabled';
				$state['lifecycle']['changed_at'] = $now;
				$state['ledger']                  = array(
					'site_uuid'        => $this->site_uuid,
					'source'           => 'fixture_intent_v1',
					'grant_command_id' => null,
					'last_receipt'     => null,
					'receipts'         => array(),
				);
				$raw                              = wp_json_encode( $state );
				if ( ! $this->valid_state( $state ) || ! is_string( $raw ) || strlen( $raw ) > self::MAX_BYTES - self::CLOSURE_BYTES ) {
					return $this->error( 'ledger_capacity' );
				}
				$saved = $this->backend->swap( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION, $row['option_value'], $raw );
				if ( is_wp_error( $saved ) || ! is_bool( $saved ) ) {
					return $this->error( 'ledger_unresolved' );
				}
				if ( $saved ) {
					return $state;
				}
			}
			return $this->error( 'ledger_contention' );
		} catch ( Throwable $error ) {
			return $this->error( 'ledger_unresolved' );
		}
	}
}
