<?php
/**
 * Bounded in-memory scan-effect ordering; never a reader or admission token.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Hypothetical participants only. Nothing here authenticates or persists. */
final class Alynt_Drime_Backups_Uploader_Scan_Effect_Fence_Contract {
	/** No public owner, provider or transferable lifetime. */
	private function __construct() {}

	/**
	 * Evaluate a synthetic trace, not evidence supplied to a native owner.
	 *
	 * @param mixed $generation Initial hypothetical generation.
	 * @param mixed $events At most 64 flat fixed-vocabulary events.
	 * @return array Redacted candidate observations; every support flag is false.
	 */
	public static function simulate( $generation, $events ) {
		$result = array(
			'candidate_only'      => true,
			'storage_supported'   => false,
			'dispatch_supported'  => false,
			'execution_supported' => false,
			'quiescent_candidate' => false,
			'unresolved'          => false,
			'pending_count'       => 0,
			'committed_effects'   => 0,
			'outcomes'            => array(),
		);
		if ( 2 !== func_num_args() || ! is_int( $generation ) || $generation < 1 || $generation >= PHP_INT_MAX || ! is_array( $events ) || count( $events ) > 64 || ( $events && array_keys( $events ) !== range( 0, count( $events ) - 1 ) ) ) {
			$result['outcomes'][] = 'invalid_trace';
			return $result;
		}
		$operations = array();
		$revisions  = array(
			'snapshot' => 0,
			'queue'    => 0,
		);
		$mode       = 'active_candidate';
		foreach ( $events as $event ) {
			$outcome = 'rejected';
			if ( ! self::event( $event ) ) {
				$result['unresolved'] = true;
				$mode                 = 'uncertain';
			} elseif ( 'register' === $event['event'] ) {
				if ( 'active_candidate' === $mode && $event['generation'] === $generation && 'scheduled' === $event['entry'] && 0 === $result['pending_count'] && count( $operations ) < 16 && ! isset( $operations[ $event['operation'] ] ) ) {
					$operations[ $event['operation'] ] = array(
						'identity' => self::identity( $event ),
						'phase'    => 'registered',
						'prepared' => array(),
					);
					++$result['pending_count'];
					$outcome = 'registered_candidate';
				}
			} elseif ( 'close' === $event['event'] ) {
				if ( 'active_candidate' === $mode && $generation === $event['generation'] && ! $result['unresolved'] ) {
					$mode = 'closed';
					++$generation;
					$outcome = 'ingress_closed_candidate';
				}
			} elseif ( 'drift' === $event['event'] ) {
				// Abstract competing writer, including upload removal; no queue bytes.
				++$revisions[ $event['target'] ];
				$outcome = 'target_changed';
			} elseif ( 'barrier' === $event['event'] ) {
				if ( 'closed' === $mode && $generation === $event['generation'] && 'all_modeled' === $event['participants'] && 0 === $result['pending_count'] && ! $result['unresolved'] ) {
					$outcome = 'quiescent_candidate';
				}
			} elseif ( isset( $operations[ $event['operation'] ] ) ) {
				$operation =& $operations[ $event['operation'] ];
				if ( self::identity( $event ) === $operation['identity'] && 'registered' === $operation['phase'] ) {
					if ( 'terminal' === $event['event'] ) {
						$operation['phase'] = 'terminal';
						--$result['pending_count'];
						$outcome = 'terminal_candidate';
					} elseif ( 'active_candidate' === $mode && $generation === $event['generation'] && ! $result['unresolved'] ) {
						$target = $event['target'];
						if ( 'prepare' === $event['event'] ) {
							$operation['prepared'][ $target ] = $revisions[ $target ];
							$outcome                          = 'prepared_candidate';
						} elseif ( isset( $operation['prepared'][ $target ] ) && $operation['prepared'][ $target ] === $revisions[ $target ] ) {
							// One abstract serialized boundary, NOT a check-then-write API.
							unset( $operation['prepared'][ $target ] );
							if ( 'unknown' === $event['ack'] ) {
								$operation['phase']   = 'unresolved';
								$result['unresolved'] = true;
								$mode                 = 'uncertain';
								$outcome              = 'effect_unresolved';
							} elseif ( 'confirmed' === $event['ack'] ) {
								++$revisions[ $target ];
								++$result['committed_effects'];
								$outcome = 'effect_committed_candidate';
							} else {
								$outcome = 'effect_not_committed';
							}
						}
					}
				}
				unset( $operation );
			}
			// No cached value, expired lease or empty queue event can set this fact.
			$result['quiescent_candidate'] = 'quiescent_candidate' === $outcome;
			$result['outcomes'][]          = $outcome;
		}
		return $result;
	}

	/**
	 * Fixed flat input vocabulary; reject objects, callbacks and unbounded text.
	 *
	 * @param mixed $event Synthetic event.
	 * @return bool
	 */
	private static function event( $event ) {
		if ( ! is_array( $event ) || ! is_string( $event['event'] ?? null ) || strlen( $event['event'] ) > 16 ) {
			return false;
		}
		$keys = array(
			'register' => array( 'operation', 'generation', 'intent', 'context', 'entry' ),
			'prepare'  => array( 'operation', 'generation', 'intent', 'context', 'target' ),
			'effect'   => array( 'operation', 'generation', 'intent', 'context', 'target', 'ack' ),
			'terminal' => array( 'operation', 'generation', 'intent', 'context' ),
			'close'    => array( 'generation' ),
			'drift'    => array( 'target' ),
			'barrier'  => array( 'generation', 'participants' ),
		);
		if ( ! isset( $keys[ $event['event'] ] ) || count( $event ) !== count( $keys[ $event['event'] ] ) + 1 ) {
			return false;
		}
		foreach ( $keys[ $event['event'] ] as $key ) {
			$value = $event[ $key ] ?? null;
			if ( 'generation' === $key ) {
				$valid = is_int( $value ) && $value > 0 && $value <= PHP_INT_MAX;
			} elseif ( in_array( $key, array( 'operation', 'intent', 'context' ), true ) ) {
				$valid = is_string( $value ) && 64 === strlen( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $value );
			} else {
				$enums = array(
					'entry'        => array( 'scheduled', 'admin', 'cli', 'remote' ),
					'target'       => array( 'snapshot', 'queue' ),
					'ack'          => array( 'confirmed', 'rejected', 'unknown' ),
					'participants' => array( 'all_modeled', 'unproven' ),
				);
				$valid = in_array( $value, $enums[ $key ], true );
			}
			if ( ! $valid ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Compare immutable simulated ownership, never authenticate it.
	 *
	 * @param array $event Valid flat event.
	 * @return array
	 */
	private static function identity( $event ) {
		return array( $event['operation'], $event['generation'], $event['intent'], $event['context'] );
	}
}
