<?php
/**
 * Test-loaded admission outcome/release DATA; never native evidence or authority.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-registration-observation-origin.php';
require_once __DIR__ . '/trait-registration-observation-packet-data.php';
require_once __DIR__ . '/trait-registration-admission-lifecycle-data.php';
require_once __DIR__ . '/trait-registration-admission-outcome-data.php';

/** No public operation, callback, retained origin, handle, nonce or IO. */
final class Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome {
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Data;
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Packet_Data;
	use Alynt_Drime_Backups_Uploader_Registration_Admission_Lifecycle_Data;
	use Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome_Data;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;

	/**
	 * Exact observation positions, declared on the class for PHP7.4 compatibility.
	 *
	 * @var array
	 */
	const EVENTS = array( 'writer_created', 'attempt_reserved', 'begin_sent', 'begin_ack_at', 'transaction_owned', 'bind_verified', 'dml_sent', 'dml_ack_at', 'readback', 'commit_sent', 'commit_ack_at', 'commit_idle', 'rollback_sent', 'rollback_ack_at', 'rollback_idle', 'detached', 'close_at', 'accepted_at' );

	/**
	 * Any classification attempt is permanently consumed, including invalid DATA.
	 *
	 * @var bool
	 */
	private $spent = false;

	/** Zero IO. */
	private function __construct() {}

	/**
	 * Inert fixed factory, never a provider or native capability import.
	 *
	 * @return self|false
	 */
	public static function create() {
		return 0 === func_num_args() ? new self() : false;
	}

	/**
	 * Consume one exact synthetic snapshot without retaining any supplied facts.
	 *
	 * @param mixed $data Original origin DATA, not native provenance.
	 * @param mixed $proposal Original proposal DATA.
	 * @param mixed $ids Complete claimed original collision set.
	 * @param mixed $start Original root/writer clock DATA.
	 * @param mixed $clock Post-cleanup observation clock DATA.
	 * @param mixed $facts Independent event/release/current claims.
	 * @return array|false Non-authorizing classification only.
	 */
	private function classify( $data = null, $proposal = null, $ids = null, $start = null, $clock = null, $facts = null ) {
		$available   = ! $this->spent;
		$this->spent = true;
		if ( ! $available || 6 !== func_num_args() || ! self::packet( array( $data, $proposal, $ids, $start, $clock, $facts ) ) || ! self::original( $data, $proposal, $ids ) || ! self::root_time( $start, $start, 10000000000 ) || ! self::facts( $facts ) ) {
			return false;
		}
		$history  = self::history( $facts );
		$released = self::released( $facts );
		$current  = self::root_time( $clock, $start, 10000000000 ) && $start['utc'] >= $data['mutation']['issued_at'] && $clock['utc'] < $data['mutation']['expires_at'] && $facts['events']['accepted_at'] > 0 && ! in_array( false, $facts['guards'], true ) && ! in_array( null, $facts['guards'], true );
		return array(
			'historical_outcome'         => $history,
			'client_release_claimed'     => $released,
			'current_acceptance_claimed' => $current,
			'diagnostic_candidate_only'  => 'uncertain' === $history && $released && $current,
			'candidate_only'             => true,
			'authenticated'              => false,
			'barrier_verified'           => false,
			'closure_proven'             => false,
			'retry_supported'            => false,
			'storage_supported'          => false,
			'dispatch_supported'         => false,
			'execution_supported'        => false,
		);
	}
}
