<?php
/**
 * Synthetic post-commit hygiene observations; no WordPress cache operations.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Fixed symbolic keys only; caller observations are not native certificates. */
final class Alynt_Drime_Backups_Uploader_Cache_Publication_Contract {
	/** No instantiable publisher or cache provider. */
	private function __construct() {}

	/**
	 * Evaluate one hypothetical command. No callbacks, handles or proposed arrays.
	 *
	 * @param mixed $blog Pinned synthetic original blog ID.
	 * @param mixed $kind Safety-only begin or paired completion.
	 * @param mixed $profile Exact supported-profile observations, not detection.
	 * @param mixed $events At most 12 flat observations, never executed.
	 * @return array Non-authorizing observations, not cache convergence proof.
	 */
	public static function simulate( $blog, $kind, $profile, $events ) {
		$result = array(
			'candidate_only'          => true,
			'storage_supported'       => false,
			'dispatch_supported'      => false,
			'execution_supported'     => false,
			'historical_commit'       => false,
			'commit_unresolved'       => false,
			'publication_attempted'   => false,
			'observations_complete'   => false,
			'publication_unconfirmed' => true,
			'outward_candidate'       => false,
			'outcomes'                => array(),
		);
		if ( 4 !== func_num_args() || ! is_int( $blog ) || $blog < 1 || ! in_array( $kind, array( 'safety', 'pair' ), true ) || ! is_array( $profile ) || count( $profile ) !== 3 || false !== ( $profile['multisite'] ?? null ) || false !== ( $profile['dropins'] ?? null ) || ! array_key_exists( 'external_cache', $profile ) || ! in_array( $profile['external_cache'], array( false, null ), true ) || ! is_array( $events ) || count( $events ) > 12 || ( $events && array_keys( $events ) !== range( 0, count( $events ) - 1 ) ) ) {
			$result['outcomes'][] = 'unsupported_trace';
			return $result;
		}
		$keys       = 'pair' === $kind ? array( 'safety', 'settings', 'alloptions', 'notoptions' ) : array( 'safety', 'alloptions', 'notoptions' );
		$commit     = null;
		$released   = false;
		$authorized = true;
		$remaining  = $keys;
		$invalid    = false;
		foreach ( $events as $event ) {
			$outcome = 'rejected';
			if ( ! self::event( $event ) ) {
				$invalid = true;
			} elseif ( 'revoke' === $event['event'] ) {
				$authorized = false;
				$outcome    = 'outward_withheld';
			} elseif ( 'commit' === $event['event'] && null === $commit && ! $invalid ) {
				$commit                      = $event['ack'];
				$result['historical_commit'] = 'confirmed' === $commit;
				$result['commit_unresolved'] = 'unknown' === $commit;
				$outcome                     = 'commit_' . $commit;
			} elseif ( 'release' === $event['event'] && null !== $commit && ! $released && ! $invalid ) {
				$released = true;
				$outcome  = 'transaction_released';
			} elseif ( 'publish' === $event['event'] && $released && in_array( $commit, array( 'confirmed', 'unknown' ), true ) && ! $result['publication_attempted'] && ! $invalid ) {
				if ( $event['blog'] === $blog ) {
					$result['publication_attempted'] = true;
					$outcome                         = 'fixed_scope_hygiene_candidate';
				} else {
					$invalid = true;
				}
			} elseif ( 'delete' === $event['event'] && $result['publication_attempted'] && ! $invalid && $remaining && $event['key'] === $remaining[0] ) {
				if ( $event['blog'] !== $blog ) {
					$invalid = true;
				} else {
					array_shift( $remaining );
					$outcome                         = 'delete_' . $event['observation'];
					$result['observations_complete'] = ! $remaining;
				}
			}
			$result['outcomes'][] = $outcome;
		}
		// Revocation or missing publication cannot erase a confirmed historical fact.
		// Even all true deletes cannot certify another process or authorize replay.
		$result['outward_candidate'] = $result['historical_commit'] && $released && $authorized && ! $invalid;
		return $result;
	}

	/**
	 * Refuse arbitrary key names, scope switching and contradictory payload shapes.
	 *
	 * @param mixed $event Flat synthetic observation.
	 * @return bool
	 */
	private static function event( $event ) {
		if ( ! is_array( $event ) || ! is_string( $event['event'] ?? null ) || strlen( $event['event'] ) > 16 ) {
			return false;
		}
		switch ( $event['event'] ) {
			case 'commit':
				return 2 === count( $event ) && in_array( $event['ack'] ?? null, array( 'confirmed', 'unknown', 'rejected' ), true );
			case 'release':
			case 'revoke':
				return 1 === count( $event );
			case 'publish':
				return 2 === count( $event ) && is_int( $event['blog'] ?? null ) && $event['blog'] > 0;
			case 'delete':
				return 4 === count( $event ) && is_int( $event['blog'] ?? null ) && $event['blog'] > 0 && in_array( $event['key'] ?? null, array( 'safety', 'settings', 'alloptions', 'notoptions' ), true ) && in_array( $event['observation'] ?? null, array( 'true', 'false', 'threw' ), true );
		}
		return false;
	}
}
