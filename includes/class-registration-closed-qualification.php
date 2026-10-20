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
		return $available && 8 === func_num_args() ? self::closed_qualification_data( $data, $proposal, $ids, $start, $clock, $capture, $owner, $acceptance ) : false;
	}
}
