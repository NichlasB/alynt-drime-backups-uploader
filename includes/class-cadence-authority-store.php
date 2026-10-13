<?php
/**
 * Dormant client-owned cadence authority. Executes no work and writes no cron.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Internal model only; production scheduler and remote actions do not consume it. */
class Alynt_Drime_Backups_Uploader_Cadence_Authority_Store extends Alynt_Drime_Backups_Uploader_Schedule_Safety_Store {
	use Alynt_Drime_Backups_Uploader_Cadence_Authority_Validation;
	use Alynt_Drime_Backups_Uploader_Cadence_Authority_Mutations;
	use Alynt_Drime_Backups_Uploader_Cadence_Authority_Dispatch;

	/**
	 * Explicit creation/migration. Never repairs or discards legacy journals.
	 *
	 * @param string $binding Enrollment.
	 * @param string $cadence Initial model cadence.
	 * @param int    $due Initial due time.
	 * @return array|WP_Error
	 */
	public function initialize_authority( $binding, $cadence, $due ) {
		$now = call_user_func( $this->clock );
		if ( ! $this->valid_hash( $binding ) || $this->interval( $cadence ) <= 0 || ! $this->valid_integer( $due ) || $due < $now ) {
			return $this->error( 'authority_invalid' );
		}
		for ( $attempt = 0; $attempt < 3; $attempt++ ) {
			$row = $this->backend->read( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION );
			if ( is_wp_error( $row ) ) {
				return $row;
			}
			if ( null === $row ) {
				$state = array(
					'schema_version'     => 2,
					'generation'         => 0,
					'enrollment_binding' => $binding,
					'schedule_revision'  => 0,
					'claims'             => array(),
				);
			} else {
				if ( ! is_array( $row ) || ! isset( $row['option_value'], $row['autoload'] ) || ! is_string( $row['option_value'] ) || strlen( $row['option_value'] ) > self::MAX_BYTES || ! in_array( $row['autoload'], array( 'no', 'off', 'auto-off' ), true ) ) {
					return $this->error( 'invalid_state' );
				}
				$state = json_decode( $row['option_value'], true, 16 );
				if ( is_array( $state ) && $this->valid_state( $state ) ) {
					return $state['enrollment_binding'] === $binding && $state['authority']['enabled'] && $state['authority']['cadence'] === $cadence && $state['authority']['next_due_at'] === $due ? $state : $this->error( 'authority_initialized' );
				}
				if ( ! is_array( $state ) || ! parent::valid_state( $state ) || $state['enrollment_binding'] !== $binding ) {
					return $this->error( 'invalid_state' );
				}
				foreach ( $state['claims'] as $record ) {
					if ( array_key_exists( 'authority_evidence_version', $record ) ) {
						return $this->error( 'invalid_state' );
					}
					if ( ! in_array( $record['phase'], array( 'succeeded', 'failed_closed' ), true ) ) {
						return $this->error( 'migration_recovery_pending' );
					}
				}
				$state['schema_version'] = 2;
				++$state['generation'];
				++$state['schedule_revision'];
			}
			$state['authority'] = array(
				'mode'        => 'client_owned_v1',
				'cadence'     => $cadence,
				'next_due_at' => $due,
				'enabled'     => true,
			);
			$state['dispatch']  = null;
			$raw                = wp_json_encode( $state );
			if ( ! $this->valid_state( $state ) || ! is_string( $raw ) || strlen( $raw ) > self::MAX_BYTES ) {
				return $this->error( 'invalid_state' );
			}
			$saved = null === $row ? $this->backend->create( $raw ) : $this->backend->swap( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION, $row['option_value'], $raw );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			if ( $saved ) {
				return $state;
			}
		}
		return $this->error( 'contention' );
	}

	/**
	 * Hash internal authority evidence; never accepted as legacy/wire evidence.
	 *
	 * @param array $state Model state (including a candidate state).
	 * @return string|WP_Error
	 */
	public function authority_fingerprint( array $state ) {
		if ( ! $this->valid_state( $state ) ) {
			return $this->error( 'invalid_state' );
		}
		return hash(
			'sha256',
			wp_json_encode(
				array(
					'version'     => 1,
					'binding'     => $state['enrollment_binding'],
					'revision'    => $state['schedule_revision'],
					'mode'        => $state['authority']['mode'],
					'cadence'     => $state['authority']['cadence'],
					'next_due_at' => $state['authority']['next_due_at'],
					'enabled'     => $state['authority']['enabled'],
				)
			)
		);
	}

	/**
	 * Reject the legacy initializer on schema-2 callers.
	 *
	 * @param string $binding Enrollment.
	 * @return WP_Error
	 */
	public function initialize( $binding ) {
		return $this->error( 'authority_api_required' );
	}

	/**
	 * Reject unreviewed legacy claims on this model.
	 *
	 * @param string $binding Enrollment.
	 * @param string $source Source.
	 * @param array  $evidence Evidence.
	 * @return WP_Error
	 */
	public function claim( $binding, $source, array $evidence ) {
		return $this->error( 'authority_api_required' );
	}

	/**
	 * Reject separate legacy journal writes that could imply an authority mutation.
	 *
	 * @param string $binding Enrollment.
	 * @param string $source Source.
	 * @param string $owner Owner.
	 * @param string $hash Request hash.
	 * @param string $from Prior phase.
	 * @param string $to New phase.
	 * @return WP_Error
	 */
	public function transition( $binding, $source, $owner, $hash, $from, $to ) {
		return $this->error( 'authority_api_required' );
	}
}
