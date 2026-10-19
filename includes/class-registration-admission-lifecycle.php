<?php
/**
 * Test-loaded one-shot admission-origin lifecycle DATA, never genuine admission.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-registration-observation-handoff.php';
require_once __DIR__ . '/trait-registration-admission-lifecycle-data.php';

/** Private synthetic transitions only: no auth, actual clock, handles or IO. */
final class Alynt_Drime_Backups_Uploader_Registration_Admission_Lifecycle {
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Handoff_Data {
		stage as private stage_read;
		claim as private claim_read;
		finish as private finish_read;
	}
	use Alynt_Drime_Backups_Uploader_Registration_Admission_Lifecycle_Data;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;

	/**
	 * Fixed synthetic sequence; labels never certify actual native events.
	 *
	 * @var array
	 */
	const TRANSITIONS = array(
		'retained'           => array( 'begin_sent' ),
		'begin_sent'         => array( 'transaction_owned' ),
		'transaction_owned'  => array( 'dml_sent', 'rollback_sent' ),
		'dml_sent'           => array( 'write_acknowledged', 'rollback_sent', 'writer_released' ),
		'write_acknowledged' => array( 'commit_sent', 'rollback_sent' ),
		'commit_sent'        => array( 'committed', 'writer_released' ),
		'committed'          => array( 'writer_released' ),
		'rollback_sent'      => array( 'rolled_back' ),
		'rolled_back'        => array( 'writer_released' ),
	);
	/**
	 * One root phase, including permanent closed refusal.
	 *
	 * @var string
	 */
	private $phase = 'unopened';
	/**
	 * Original primitive origin only, never a full writer frame or nonce.
	 *
	 * @var array|null
	 */
	private $root_data;
	/**
	 * Complete supplied collision set; completeness is not native-proven.
	 *
	 * @var array|null
	 */
	private $root_ids;
	/**
	 * Original root start plus last accepted observations.
	 *
	 * @var array|null
	 */
	private $root_clock;
	/**
	 * Separate once-only read start; does not replace the root start.
	 *
	 * @var int|null
	 */
	private $read_start;
	/**
	 * Synthetic historical outcome only, never admission boolean inference.
	 *
	 * @var string|null
	 */
	private $outcome;

	/** Zero IO. */
	private function __construct() {}

	/**
	 * Fixed factory; no provider, imported owner or capability arguments.
	 *
	 * @return self|false Non-authorizing dormant DATA.
	 */
	public static function create() {
		return 0 === func_num_args() ? new self() : false;
	}

	/**
	 * Consume even invalid first retention; copy original facts before DML labels.
	 *
	 * @param mixed $data Original synthetic origin.
	 * @param mixed $proposal Complete synthetic proposal, not retained.
	 * @param mixed $ids Supplied used/reserved identities.
	 * @param mixed $clock Explicit original synthetic clock.
	 * @return bool DATA consistency only.
	 */
	private function retain( $data, $proposal, $ids, $clock ) {
		$available = 'unopened' === $this->phase;
		$this->erase();
		if ( ! $available || 4 !== func_num_args() || ! self::original( $data, $proposal, $ids ) || ! self::clock( $clock ) || $clock['utc'] < $data['mutation']['issued_at'] || $clock['utc'] >= $data['mutation']['expires_at'] ) {
			return false;
		}
		$this->root_data  = $data;
		$this->root_ids   = $ids;
		$this->root_clock = $clock;
		$this->outcome    = 'not_written';
		$this->phase      = 'retained';
		$this->attempted  = false;
		$this->claimed    = false;
		$this->finished   = false;
		return true;
	}

	/**
	 * Ordered labels only, never a native callback or SQL outcome certificate.
	 *
	 * @param mixed $event Synthetic sent/ack/release label, not a boolean.
	 * @param mixed $clock Explicit same-root clock.
	 * @return bool Valid modeled transition, not permission to execute it.
	 */
	private function advance( $event, $clock ) {
		if ( 2 !== func_num_args() || ! is_string( $event ) || ! in_array( $event, self::TRANSITIONS[ $this->phase ] ?? array(), true ) || ! self::root_time( $clock, $this->root_clock, 10000000000 ) || $clock['utc'] >= $this->root_data['mutation']['expires_at'] ) {
			$this->erase();
			return false;
		}
		$this->phase      = $event;
		$this->root_clock = $clock;
		if ( 'dml_sent' === $event ) {
			$this->outcome = 'uncertain';
		} elseif ( 'committed' === $event || 'rolled_back' === $event ) {
			$this->outcome = $event;
		}
		if ( 'writer_released' === $event && 'rolled_back' === $this->outcome ) {
			$this->erase();
		}
		return true;
	}

	/**
	 * Transfer to existing bounded DATA handoff, never read authorization.
	 *
	 * @param mixed $intent Synthetic small read form, nonce bytes not verified.
	 * @param mixed $form Synthetic full read form, nonce bytes not verified.
	 * @param mixed $clock Explicit clock at once-only read start.
	 * @return bool Candidate construction consistency only.
	 */
	private function read( $intent, $form, $clock ) {
		if ( 3 !== func_num_args() || 'writer_released' !== $this->phase || ! in_array( $this->outcome, array( 'uncertain', 'committed' ), true ) || ! self::root_time( $clock, $this->root_clock, 20000000000 ) || $clock['utc'] >= $this->root_data['mutation']['expires_at'] ) {
			$this->erase();
			return false;
		}
		$data                     = $this->root_data;
		$ids                      = $this->root_ids;
		$this->root_data          = null;
		$this->root_ids           = null;
		$read_clock               = $clock;
		$read_clock['started_ns'] = $clock['now_ns'];
		if ( ! $this->stage_read( $data, $intent, $form, $read_clock, $ids ) || ! $this->claim_read( $data, $intent, $form, $read_clock, $ids ) ) {
			$this->erase();
			return false;
		}
		$this->root_clock = $clock;
		$this->read_start = $clock['now_ns'];
		$this->phase      = 'observing';
		return true;
	}

	/**
	 * Erase before complete pending-aware candidate parsing; never a barrier.
	 *
	 * @param mixed $frame Complete synthetic frame, not protected storage.
	 * @param mixed $intent Same small form.
	 * @param mixed $form Same full form.
	 * @param mixed $clock Same root, nonregressing synthetic observation.
	 * @return array|false Candidate only; every authority flag remains false.
	 */
	private function finish( $frame, $intent, $form, $clock ) {
		if ( 4 !== func_num_args() || 'observing' !== $this->phase || ! self::root_time( $clock, $this->root_clock, 20000000000 ) ) {
			$this->erase();
			return false;
		}
		$clock['started_ns'] = $this->read_start;
		$this->phase         = 'closed';
		$this->root_clock    = null;
		$this->read_start    = null;
		$this->outcome       = null;
		return $this->finish_read( $frame, $intent, $form, $clock );
	}

	/**
	 * Explicit modeled interruption consumes without origin reconstruction.
	 *
	 * @return bool No actual process/session interruption.
	 */
	private function abort() {
		$this->erase();
		return 0 === func_num_args();
	}

	/** Erase all retained primitive slots; never touches a resource. */
	private function erase() {
		$this->phase      = 'closed';
		$this->root_data  = null;
		$this->root_ids   = null;
		$this->root_clock = null;
		$this->read_start = null;
		$this->outcome    = null;
		$this->attempted  = true;
		$this->claimed    = true;
		$this->finished   = true;
		$this->staged     = null;
		$this->inflight   = null;
	}
}
