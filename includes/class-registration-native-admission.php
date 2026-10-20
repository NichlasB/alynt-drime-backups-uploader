<?php
/**
 * Dormant genuine-core closed registration coordinator; no public write entry.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-registration-request-owner.php';
require_once __DIR__ . '/trait-registration-admission-candidate.php';
require_once __DIR__ . '/trait-registration-admission-storage.php';
require_once __DIR__ . '/trait-registration-admission-transaction.php';
require_once __DIR__ . '/trait-native-observer-session.php';
require_once __DIR__ . '/trait-native-options-metadata.php';
require_once __DIR__ . '/trait-native-closed-begin-session.php';
require_once __DIR__ . '/trait-registration-native-outcome-capture.php';
require_once __DIR__ . '/trait-registration-native-outcome-session.php';
require_once __DIR__ . '/trait-registration-admission-root.php';
require_once __DIR__ . '/trait-registration-admission-join.php';
require_once __DIR__ . '/trait-registration-admission-qualification.php';

/** Explicit owned-proof reflection only; production never loads this class. */
final class Alynt_Drime_Backups_Uploader_Registration_Native_Admission {
	use Alynt_Drime_Backups_Uploader_Registration_Admission_Root;
	use Alynt_Drime_Backups_Uploader_Registration_Admission_Join;
	use Alynt_Drime_Backups_Uploader_Registration_Admission_Qualification;
	use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Core_Guard;
	use Alynt_Drime_Backups_Uploader_Registration_Construction;
	use Alynt_Drime_Backups_Uploader_Registration_Admission_Candidate;
	use Alynt_Drime_Backups_Uploader_Registration_Admission_Storage;
	use Alynt_Drime_Backups_Uploader_Registration_Admission_Transaction;
	use Alynt_Drime_Backups_Uploader_Native_Observer_Session;
	use Alynt_Drime_Backups_Uploader_Native_Options_Metadata;
	use Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Capture;
	use Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session, Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Session {
		Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Session::open_writer insteadof Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session;
		Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Session::finish insteadof Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session;
		Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session::open_writer as private open_writer_without_outcome;
		Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session::finish as private finish_without_outcome;
	}
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;

	/**
	 * Canonical sealed-history event names, not a second event domain.
	 *
	 * @var array
	 */
	const EVENTS = Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::EVENTS;

	/**
	 * Separate outer once-attempted lifetime; never reset by inner construction.
	 *
	 * @var bool
	 */
	private $attempted = false;
	/**
	 * Constructor stack latch.
	 *
	 * @var bool
	 */
	private $active = false;
	/**
	 * Inner builder consumed on construction/refusal.
	 *
	 * @var bool
	 */
	private $consumed = false;
	/**
	 * Original genuine core frame; cleared at completion.
	 *
	 * @var array|null
	 */
	private $scope;
	/**
	 * Owned native connection only.
	 *
	 * @var mysqli|null
	 */
	private $reader;
	/**
	 * Owned writer physical/session policy.
	 *
	 * @var array|null
	 */
	private $physical;
	/**
	 * Initial reader physical facts, cleared before writer creation.
	 *
	 * @var array|null
	 */
	private $read_physical;
	/**
	 * Private native acknowledgement phase, not a durable receipt.
	 *
	 * @var string
	 */
	private $phase = 'unopened';
	/**
	 * Original monotonic start and nondecreasing UTC observation.
	 *
	 * @var int|float
	 */
	private $started = 0;
	/**
	 * Original UTC observation, never reset by renewed authentication.
	 *
	 * @var int
	 */
	private $last_clock = 0;
	/**
	 * Twenty capture plus ten compare plus ten readback at most.
	 *
	 * @var int
	 */
	private $protected_reads = 0;
	/**
	 * Exactly one attempted ledger UPDATE at most.
	 *
	 * @var int
	 */
	private $writes = 0;

	/** Zero IO, hooks, identity issuance or dependency installation. */
	private function __construct() {}

	/**
	 * No raw frame, proposal, plan, provider or handle import.
	 *
	 * @return self|false Dormant owned-proof coordinator only.
	 */
	public static function create() {
		return 0 === func_num_args() ? new self() : false;
	}

	/**
	 * Original standalone private entry; no retained-root policy change.
	 *
	 * @param string $method Original method.
	 * @param mixed  $body Original issuance body.
	 * @return bool Private historical decision only.
	 */
	private function admit( $method, $body ) {
		return $this->admission_root( 2 === func_num_args() ? $method : null, $body, false );
	}

	/**
	 * Dormant joined entry; no injected proposal, origin, actor or clocks.
	 *
	 * @param string $method Original method.
	 * @param mixed  $body Original issuance body.
	 * @return bool Private historical decision only.
	 */
	private function admit_retained( $method, $body ) {
		return $this->admission_root( 2 === func_num_args() ? $method : null, $body, true );
	}

	/**
	 * Original installation metadata only; no adoption by nested construction.
	 *
	 * @param array $core Genuine core frame.
	 * @return bool Same frame.
	 */
	private function pin( $core ) {
		if ( null === $this->scope ) {
			$this->scope = $core;
		}
		return $this->scope === $core;
	}
}
