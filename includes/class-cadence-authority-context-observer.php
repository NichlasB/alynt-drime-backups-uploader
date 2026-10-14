<?php
/**
 * Dormant read-only schema-3 observation; no permission or production provider.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Explicit injected readers only; never creates identity, state or a policy projection. */
final class Alynt_Drime_Backups_Uploader_Cadence_Authority_Context_Observer {
	use Alynt_Drime_Backups_Uploader_Cadence_Authority_Context_Facts;

	/**
	 * Strict injected model reader.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Cadence_Authority_Lifecycle_Store
	 */
	private $store;
	/**
	 * Type-preserving settings fact reader.
	 *
	 * @var callable
	 */
	private $settings_reader;
	/**
	 * Type-preserving connection fact reader.
	 *
	 * @var callable
	 */
	private $connection_reader;
	/**
	 * Explicit UTC clock.
	 *
	 * @var callable
	 */
	private $clock;
	/**
	 * Dormant unless literally enabled by trusted construction.
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * Construction performs zero reads and registers nothing.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Cadence_Authority_Lifecycle_Store $store Strict model reader.
	 * @param callable                                                       $settings_reader Raw exact two-field fact slice, not merged defaults.
	 * @param callable                                                       $connection_reader Raw exact enrollment fact slice, not normalized state.
	 * @param callable                                                       $clock UTC seconds.
	 * @param bool                                                           $enabled Explicit dormant observer switch, never authority permission.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Cadence_Authority_Lifecycle_Store $store, callable $settings_reader, callable $connection_reader, callable $clock, $enabled = false ) {
		$this->store             = $store;
		$this->settings_reader   = $settings_reader;
		$this->connection_reader = $connection_reader;
		$this->clock             = $clock;
		$this->enabled           = true === $enabled;
	}

	/**
	 * Observe twice and classify negatively. No private input or projection is returned.
	 *
	 * @param mixed $entry_point Existing internal entry-point vocabulary.
	 * @return array Redacted four-field result, never dispatch or execution permission.
	 */
	public function observe( $entry_point ) {
		$neutral = Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy::evaluate( $entry_point );
		if ( 'outside_scope' === $neutral['disposition'] || in_array( $neutral['code'], array( 'entry_point_unknown', 'rollback_disabled' ), true ) ) {
			return $neutral;
		}
		if ( ! $this->enabled ) {
			return $this->blocked( 'observer_disabled' );
		}
		try {
			$now = call_user_func( $this->clock );
			if ( ! $this->valid_clock( $now ) ) {
				return $this->blocked( 'clock_invalid' );
			}
			$first = $this->store->snapshot();
			if ( is_wp_error( $first ) ) {
				return $this->blocked( 'observation_unavailable' );
			}
			if ( $first['lifecycle']['changed_at'] > $now ) {
				return $this->blocked( 'observation_stale' );
			}
			$one = $this->read_facts( $now );
			if ( false === $one ) {
				return $this->blocked( 'observation_unavailable' );
			}
			$second = $this->store->snapshot();
			if ( is_wp_error( $second ) ) {
				return $this->blocked( 'observation_unavailable' );
			}
			$two = $this->read_facts( $now );
			$end = call_user_func( $this->clock );
			if ( false === $two ) {
				return $this->blocked( 'observation_unavailable' );
			}
			if ( ! $this->valid_clock( $end ) || $end < $now || $end - $now >= 300 || $second['lifecycle']['changed_at'] > $now ) {
				return $this->blocked( 'observation_stale' );
			}
			if ( $first !== $second || $one !== $two ) {
				return $this->blocked( 'observation_changed' );
			}
			if ( ! hash_equals( $second['enrollment_binding'], $two['binding'] ) ) {
				return $this->blocked( 'binding_changed' );
			}
			foreach ( $second['claims'] as $record ) {
				if ( ! in_array( $record['phase'], array( 'succeeded', 'failed_closed' ), true ) ) {
					return $this->blocked( 'inspection_required' );
				}
			}
			if ( null !== $second['dispatch'] && ! in_array( $second['dispatch']['phase'], array( 'completed', 'cancelled' ), true ) ) {
				return $this->blocked( 'inspection_required' );
			}
			return $this->blocked( $two['auto_scan_enabled'] ? 'provenance_unavailable' : 'automatic_scan_disabled' );
		} catch ( Throwable $error ) {
			return $this->blocked( 'observation_unavailable' ); // Never echo provider errors or infer no write.
		}
	}

	/**
	 * Validate time before arithmetic.
	 *
	 * @param mixed $now Injected time.
	 * @return bool
	 */
	private function valid_clock( $now ) {
		return is_int( $now ) && $now > 0 && $now <= PHP_INT_MAX - 300;
	}

	/**
	 * Fixed redacted output; no caller-controlled codes or state.
	 *
	 * @param string $code Internal fixed reason.
	 * @return array
	 */
	private function blocked( $code ) {
		return array(
			'disposition'         => 'blocked',
			'code'                => $code,
			'dispatch_supported'  => false,
			'execution_supported' => false,
		);
	}
}
