<?php
/**
 * Dormant one-shot closed qualification DATA; never native/authentication proof.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-registration-admission-outcome.php';
require_once __DIR__ . '/trait-registration-sealed-outcome-data.php';

/** Private synthetic input only; no public operation, clock, callback or IO. */
final class Alynt_Drime_Backups_Uploader_Registration_Closed_Qualification {
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Data;
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Packet_Data;
	use Alynt_Drime_Backups_Uploader_Registration_Admission_Lifecycle_Data;
	use Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome_Data;
	use Alynt_Drime_Backups_Uploader_Registration_Sealed_Outcome_Data;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;

	/**
	 * Reuse canonical event names on a class, compatible with PHP7.4.
	 *
	 * @var array
	 */
	const EVENTS = Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::EVENTS;

	/**
	 * Consume before inspecting untrusted DATA; never retain input slots.
	 *
	 * @var bool
	 */
	private $spent = false;

	/** No IO, WordPress API or native execution. */
	private function __construct() {}

	/**
	 * Inert factory; no provider, actor, frame or handle arguments.
	 *
	 * @return self|false Non-authorizing test-loaded DATA owner.
	 */
	public static function create() {
		return 0 === func_num_args() ? new self() : false;
	}

	/**
	 * Consume a synthetic sealed snapshot and independent current claims once.
	 * Nothing here establishes original issuance or actual cleanup/authentication.
	 *
	 * @param mixed $data Original origin claims.
	 * @param mixed $proposal Original proposal claims.
	 * @param mixed $ids Complete claimed collision set.
	 * @param mixed $start Original synthetic root clock.
	 * @param mixed $clock Current synthetic observation clock.
	 * @param mixed $capture Sealed native-shaped primitive history claims.
	 * @param mixed $owner Separate seal/tick/empty-slot claims.
	 * @param mixed $acceptance Independent postrelease guard and final-pin claims.
	 * @return array|false Closed-policy DATA result, never a continuation.
	 */
	private function qualify( $data = null, $proposal = null, $ids = null, $start = null, $clock = null, $capture = null, $owner = null, $acceptance = null ) {
		$available   = ! $this->spent;
		$this->spent = true;
		if ( ! $available || 8 !== func_num_args() || ! self::packet( func_get_args() ) || ! self::original( $data, $proposal, $ids ) || ! is_array( $start ) || ! self::root_time( $start, $start, 10000000000 ) || $start['utc'] < $data['mutation']['issued_at'] || $start['utc'] >= $data['mutation']['expires_at'] ) {
			return false;
		}
		$facts = self::sealed_facts( $capture, $owner );
		if ( false === $facts || ! self::bounded( $acceptance ) || ! self::keys( $acceptance, array( 'domain', 'guards', 'final_pin' ) ) || 'closed_qualification_claims_v1' !== $acceptance['domain'] || ! self::keys( $acceptance['guards'], array( 'auth', 'intent', 'physical', 'unreentered' ) ) || true !== $acceptance['final_pin'] ) {
			return false;
		}
		foreach ( $acceptance['guards'] as $guard ) {
			if ( null !== $guard && ! is_bool( $guard ) ) {
				return false;
			}
		}
		$history  = self::history( $facts );
		$released = self::released( $facts );
		$current  = $released && $facts['events']['close_at'] > 0 && self::root_time( $clock, $start, 10000000000 ) && $clock['utc'] < $data['mutation']['expires_at'] && ! in_array( false, $acceptance['guards'], true ) && ! in_array( null, $acceptance['guards'], true );
		return array(
			'historical_outcome'               => $history,
			'client_release_claimed'           => $released,
			'current_acceptance_claimed'       => $current,
			'request_lifecycle_closed_claimed' => true,
			'diagnostic_candidate_only'        => false,
			'candidate_only'                   => true,
			'authenticated'                    => false,
			'barrier_verified'                 => false,
			'closure_proven'                   => false,
			'retry_supported'                  => false,
			'storage_supported'                => false,
			'dispatch_supported'               => false,
			'execution_supported'              => false,
		);
	}
}
