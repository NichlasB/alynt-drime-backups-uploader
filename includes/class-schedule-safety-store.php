<?php
/**
 * Dormant client-local scheduling safety storage.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stores single-use claims and protected journals; executes no schedule work. */
class Alynt_Drime_Backups_Uploader_Schedule_Safety_Store {
	use Alynt_Drime_Backups_Uploader_Schedule_Safety_State_Validation;
	use Alynt_Drime_Backups_Uploader_Schedule_Safety_State_Transitions;

	const MAX_CLAIMS = 50;
	const MAX_BYTES  = 131072;
	const RETENTION  = 86400;

	/**
	 * Atomic storage adapter.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS
	 */
	private $backend;
	/**
	 * UTC seconds clock.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * No option is created by construction or plugin loading.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS $backend Storage.
	 * @param callable|null                                           $clock UTC seconds clock for deterministic tests.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS $backend, $clock = null ) {
		$this->backend = $backend;
		$this->clock   = null === $clock ? 'time' : $clock;
	}

	/**
	 * Explicit initialization on an approved local caller; existing state is never repaired.
	 *
	 * @param string $binding Enrollment/key-context hash.
	 * @return array|WP_Error
	 */
	public function initialize( $binding ) {
		if ( ! $this->valid_hash( $binding ) ) {
			return $this->error( 'invalid_state' );
		}
		$row = $this->backend->read( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( null === $row ) {
			$state   = array(
				'schema_version'     => 1,
				'generation'         => 0,
				'enrollment_binding' => $binding,
				'schedule_revision'  => 0,
				'claims'             => array(),
			);
			$created = $this->backend->create( wp_json_encode( $state ) );
			if ( is_wp_error( $created ) ) {
				return $created;
			}
		}
		$state = $this->snapshot();
		return is_wp_error( $state ) ? $state : ( $state['enrollment_binding'] === $binding ? $state : $this->error( 'binding_changed' ) );
	}

	/**
	 * Get a strict internal snapshot, never exported as status.
	 *
	 * @return array|WP_Error
	 */
	public function snapshot() {
		$loaded = $this->load();
		return is_wp_error( $loaded ) ? $loaded : $loaded['state'];
	}

	/**
	 * Atomically reserve one source. Evidence comes from a future trusted evaluator.
	 *
	 * @param string $binding Enrollment context.
	 * @param string $source Source apply UUID.
	 * @param array  $evidence Strict client-owned scalar evidence.
	 * @return array|WP_Error
	 */
	public function claim( $binding, $source, array $evidence ) {
		if ( ! $this->valid_uuid( $source ) || ! $this->valid_evidence( $evidence ) || $source === $evidence['action_id'] || $source === $evidence['preview_action_id'] || $evidence['action_id'] === $evidence['preview_action_id'] ) {
			return $this->error( 'invalid_state' );
		}
		return $this->update(
			$binding,
			function ( &$state ) use ( $source, $evidence ) {
				if ( isset( $state['claims'][ $source ] ) ) {
					$record = $state['claims'][ $source ];
					foreach ( $evidence as $key => $value ) {
						if ( $record[ $key ] !== $value ) {
							return $this->error( 'source_claimed' );
						}
					}
					return $record; // Exact duplicate is an observation, never a new lease.
				}
				$now      = call_user_func( $this->clock );
				$deadline = min( $evidence['metadata_expires_at'], $evidence['preview_expires_at'], $evidence['preview_created_at'] + 900, $evidence['intent_expires_at'] );
				if ( $deadline <= $now || $evidence['metadata_captured_at'] > $evidence['preview_created_at'] || $evidence['preview_created_at'] > $now
					|| $evidence['metadata_expires_at'] > $evidence['metadata_captured_at'] + 3600 || $evidence['intent_expires_at'] > $now + 300 ) {
					return $this->error( 'expired' );
				}
				foreach ( $state['claims'] as $id => $old ) {
					if ( $old['action_id'] === $evidence['action_id'] ) {
						return $this->error( 'source_claimed' );
					}
					if ( in_array( $old['phase'], array( 'succeeded', 'failed_closed' ), true ) && $old['completed_at'] + self::RETENTION <= $now ) {
						unset( $state['claims'][ $id ] );
					}
				}
				if ( count( $state['claims'] ) >= self::MAX_CLAIMS ) {
					return $this->error( 'capacity_reached' );
				}
				$record                     = array_merge(
					$evidence,
					array(
						'enrollment_binding' => $state['enrollment_binding'],
						'schedule_revision'  => $state['schedule_revision'],
						'created_at'         => $now,
						'deadline'           => $deadline,
						'lease_owner'        => $evidence['action_id'],
						'lease_deadline'     => min( $deadline, $now + 300 ),
						'phase'              => 'claimed',
						'completed_at'       => 0,
					)
				);
				$state['claims'][ $source ] = $record;
				return $record;
			}
		);
	}

	/**
	 * Load state plus its exact stored bytes.
	 *
	 * @return array|WP_Error
	 */
	private function load() {
		$row = $this->backend->read( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( ! is_array( $row ) || ! isset( $row['option_value'], $row['autoload'] ) || ! is_string( $row['option_value'] ) || strlen( $row['option_value'] ) > self::MAX_BYTES || ! in_array( $row['autoload'], array( 'no', 'off', 'auto-off' ), true ) ) {
			return $this->error( 'invalid_state' );
		}
		$state = json_decode( $row['option_value'], true, 16 );
		return is_array( $state ) && $this->valid_state( $state ) ? array(
			'state' => $state,
			'raw'   => $row['option_value'],
		) : $this->error( 'invalid_state' );
	}

	/**
	 * Bounded conditional mutation. Caller failure never overwrites invalid state.
	 *
	 * @param string   $binding Expected enrollment.
	 * @param callable $change Pure bounded state transformation.
	 * @return mixed|WP_Error
	 */
	private function update( $binding, $change ) {
		for ( $attempt = 0; $attempt < 3; $attempt++ ) {
			$loaded = $this->load();
			if ( is_wp_error( $loaded ) ) {
				return $loaded;
			}
			$state = $loaded['state'];
			if ( ! $this->valid_hash( $binding ) || ! hash_equals( $state['enrollment_binding'], $binding ) ) {
				return $this->error( 'binding_changed' );
			}
			$result = $change( $state );
			if ( is_wp_error( $result ) || $state === $loaded['state'] ) {
				return $result;
			}
			++$state['generation'];
			$raw = wp_json_encode( $state );
			if ( ! $this->valid_state( $state ) || ! is_string( $raw ) || strlen( $raw ) > self::MAX_BYTES ) {
				return $this->error( 'invalid_state' );
			}
			$saved = $this->backend->swap( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION, $loaded['raw'], $raw );
			if ( is_wp_error( $saved ) || $saved ) {
				return is_wp_error( $saved ) ? $saved : $result;
			}
		}
		return $this->error( 'contention' );
	}

	/**
	 * Build a support-safe error without exposing stored state.
	 *
	 * @param string $code Stable suffix.
	 * @return WP_Error
	 */
	private function error( $code ) {
		return new WP_Error( 'schedule_safety_' . $code, 'Schedule safety operation is unavailable.' );
	}
}
