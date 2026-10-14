<?php
/**
 * Explicit dormant lifecycle preparation and conservative schema migration.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Never initializes from a read, inherits consent or discards uncertainty. */
trait Alynt_Drime_Backups_Uploader_Cadence_Authority_Lifecycle_Preparation {
	/**
	 * Prepare schema 3 explicitly. Completed older records are preserved verbatim.
	 *
	 * @param string $binding Expected local enrollment binding.
	 * @param string $cadence Explicit supported model cadence.
	 * @param int    $due Model due; preserved exactly for existing authority.
	 * @return array|WP_Error Strict snapshot, not activation or permission.
	 */
	public function prepare_lifecycle( $binding, $cadence, $due ) {
		$intervals = array(
			'every_15_minutes' => 900,
			'every_30_minutes' => 1800,
			'hourly'           => 3600,
		);
		if ( ! $this->valid_hash( $binding ) || ! is_string( $cadence ) || ! isset( $intervals[ $cadence ] ) || ! $this->valid_integer( $due ) || $due <= 0 ) {
			return $this->error( 'lifecycle_invalid' );
		}
		for ( $attempt = 0; $attempt < 3; $attempt++ ) {
			$now = $this->lifecycle_now();
			if ( is_wp_error( $now ) ) {
				return $now;
			}
			$row = $this->backend->read( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION );
			if ( is_wp_error( $row ) ) {
				return $row;
			}
			if ( null === $row ) {
				$state = array(
					'schema_version'     => 3,
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
				if ( ! is_array( $state ) ) {
					return $this->error( 'invalid_state' );
				}
				if ( $this->valid_state( $state ) ) {
					return $state['enrollment_binding'] === $binding && $state['authority']['cadence'] === $cadence && $state['authority']['next_due_at'] === $due && $state['lifecycle']['changed_at'] <= $now ? $state : $this->error( 'lifecycle_initialized' );
				}
				$reader = isset( $state['schema_version'] ) && 1 === $state['schema_version']
					? new Alynt_Drime_Backups_Uploader_Schedule_Safety_Store( $this->backend, $this->clock )
					: new Alynt_Drime_Backups_Uploader_Cadence_Authority_Store( $this->backend, $this->clock );
				if ( ! $reader->valid_state( $state ) || $state['enrollment_binding'] !== $binding ) {
					return $this->error( 'invalid_state' );
				}
				if ( $this->unfinished( $state ) ) {
					return $this->error( 'lifecycle_recovery_pending' );
				}
				if ( 2 === $state['schema_version'] && ( $state['authority']['cadence'] !== $cadence || $state['authority']['next_due_at'] !== $due ) ) {
					return $this->error( 'lifecycle_migration_mismatch' );
				}
				++$state['generation'];
				++$state['schedule_revision'];
			}
			if ( ! isset( $state['authority'] ) ) {
				if ( $due <= $now || $due > $now + $intervals[ $cadence ] ) {
					return $this->error( 'lifecycle_invalid' );
				}
				$state['authority'] = array(
					'mode'        => 'client_owned_v1',
					'cadence'     => $cadence,
					'next_due_at' => $due,
					'enabled'     => false,
				);
				$state['dispatch']  = null;
			}
			$state['schema_version']       = 3;
			$state['authority']['enabled'] = false;
			$state['lifecycle']            = array(
				'phase'                 => 'preparing',
				'consent'               => false,
				'permission_generation' => 0,
				'history'               => null === $row ? 'new_model' : 'unknown',
				'changed_at'            => $now,
			);
			$raw                           = wp_json_encode( $state );
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
}
