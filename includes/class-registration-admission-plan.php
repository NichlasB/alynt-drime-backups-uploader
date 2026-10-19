<?php
/**
 * Dormant immutable candidate data, never an importable native capability.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/trait-registration-admission-candidate.php';
require_once __DIR__ . '/trait-closed-writer-contract-privacy.php';

/** No authentication, native connection, system clock or public byte export. */
final class Alynt_Drime_Backups_Uploader_Registration_Admission_Plan {
	use Alynt_Drime_Backups_Uploader_Registration_Admission_Candidate;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;

	/** Zero IO. */
	private function __construct() {}

	/**
	 * Build DATA only; the native owner never accepts this object as input.
	 *
	 * @param mixed $frame Synthetic complete frame.
	 * @param mixed $proposal Synthetic fixed proposal.
	 * @param mixed $context Synthetic installation/actor metadata.
	 * @param mixed $now Synthetic UTC.
	 * @return self|false Immutable non-authorizing candidate.
	 */
	public static function prepare( $frame, $proposal, $context, $now ) {
		if ( 4 !== func_num_args() ) {
			return false;
		}
		$plan = new self();
		return $plan->prepare_candidate( $frame, $proposal, $context, $now ) ? $plan : false;
	}

	/**
	 * Data consistency only; no lock, actual caller or native provenance proof.
	 *
	 * @param mixed $frame Synthetic re-observation.
	 * @param mixed $proposal Same proposal.
	 * @param mixed $context Same explicit metadata.
	 * @param mixed $now Synthetic UTC.
	 * @return bool Candidate equality only.
	 */
	public function matches( $frame, $proposal, $context, $now ) {
		return 4 === func_num_args() && $this->same_candidate( $frame, $proposal, $context, $now );
	}
}
