<?php
/**
 * Pure single-transaction lifecycle model; no native connection or SQL.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-closed-writer-contract-privacy.php';

/** Caller-supplied facts simulate guards; no transition grants authority. */
final class Alynt_Drime_Backups_Uploader_Closed_Writer_Session_Contract {
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;

	/**
	 * Simulated lifetime ID, never a driver handle.
	 *
	 * @var string
	 */
	private $session;
	/**
	 * Once-pinned simulated command identity, never generated here.
	 *
	 * @var string
	 */
	private $command;
	/**
	 * Original expiry.
	 *
	 * @var int
	 */
	private $expires;
	/**
	 * Last wall time.
	 *
	 * @var int
	 */
	private $last;
	/**
	 * Initial monotonic milliseconds.
	 *
	 * @var int
	 */
	private $start;
	/**
	 * Last monotonic milliseconds.
	 *
	 * @var int
	 */
	private $tick;
	/**
	 * Non-authorizing simulation phase.
	 *
	 * @var string
	 */
	private $phase = 'unopened';
	/**
	 * Irreversible uncertainty latch, including after close.
	 *
	 * @var bool
	 */
	private $unknown = false;
	/**
	 * Prepared timestamp must not move before commit is sent.
	 *
	 * @var int|null
	 */
	private $prepared;

	/**
	 * Retain one simulated transaction lifetime, never a native handle.
	 *
	 * @param string $session Synthetic lifetime.
	 * @param string $command Original identity.
	 * @param int    $expires Original deadline.
	 * @param int    $now Synthetic wall time.
	 * @param int    $tick Synthetic monotonic milliseconds.
	 */
	private function __construct( $session, $command, $expires, $now, $tick ) {
		$this->session = $session;
		$this->command = $command;
		$this->expires = $expires;
		$this->last    = $now;
		$this->start   = $tick;
		$this->tick    = $tick;
	}

	/**
	 * Fixed bounded intent, not a request parser or trusted native factory.
	 *
	 * @param mixed $session Synthetic lifetime ID.
	 * @param mixed $command Synthetic original identity.
	 * @param mixed $issued Original issue time.
	 * @param mixed $expires Original expiry.
	 * @param mixed $now Wall time.
	 * @param mixed $tick Monotonic milliseconds.
	 * @return self|false Data model only; no identity is generated or refreshed.
	 */
	public static function simulate( $session, $command, $issued, $expires, $now, $tick ) {
		if ( ! is_string( $session ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $session ) || ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::uuid( $command ) || ! is_int( $issued ) || $issued < 1 || ! is_int( $expires ) || $expires <= $issued || $expires - $issued > 300 || ! is_int( $now ) || $now < $issued || $now >= $expires || ! is_int( $tick ) || $tick < 0 || $tick > PHP_INT_MAX - 10000 ) {
			return false;
		}
		return new self( $session, $command, $expires, $now, $tick );
	}

	/**
	 * Check one explicit transition. Bad/uncertain evidence permanently poisons.
	 *
	 * @param mixed $event Fixed simulated event.
	 * @param mixed $facts Exactly typed session/idle metadata, not native proof.
	 * @param mixed $now Wall time.
	 * @param mixed $tick Monotonic milliseconds.
	 * @return bool Positive decisions remain non-admitting.
	 */
	public function step( $event, $facts, $now, $tick ) {
		if ( 'close' === $event ) {
			$this->unknown = $this->unknown || 'commit_sent' === $this->phase;
			$this->phase   = 'closed';
			return true;
		}
		if ( in_array( $this->phase, array( 'poisoned', 'closed' ), true ) ) {
			return false;
		}
		if ( ! is_array( $facts ) || count( $facts ) !== 3 || ( $facts['session_id'] ?? null ) !== $this->session || true !== ( $facts['autocommit'] ?? null ) || ! is_bool( $facts['in_transaction'] ?? null ) ) {
			return $this->poison();
		}
		// A definite same-session acknowledgement remains a fact after expiry.
		// It cannot grant admission or make this single-use lifetime restartable.
		if ( false === $facts['in_transaction'] && ( ( 'commit_confirmed' === $event && 'commit_sent' === $this->phase ) || ( 'rollback_confirmed' === $event && 'rollback_sent' === $this->phase ) ) ) {
			$this->phase = 'confirmed_idle';
			return true;
		}
		if ( ! is_int( $now ) || $now < $this->last || $now >= $this->expires || ! is_int( $tick ) || $tick < $this->tick || $tick - $this->start >= 10000 || ( 'commit_sent' === $event && $now !== $this->prepared ) ) {
			return $this->poison();
		}
		$this->last = $now;
		$this->tick = $tick;
		$edges      = array(
			'open_idle'        => array( 'unopened', 'idle', false ),
			'begin_sent'       => array( 'idle', 'begin_sent', false ),
			'begin_confirmed'  => array( 'begin_sent', 'transaction_owned', true ),
			'safety_locked'    => array( 'transaction_owned', 'safety_locked', true ),
			'target_locked'    => array( 'safety_locked', 'target_locked', true ),
			'pair_verified'    => array( 'target_locked', 'pair_verified', true ),
			'resolution_ready' => array( 'target_locked', 'resolution_ready', true ),
			'commit_sent'      => array( 'pair_verified', 'commit_sent', true ),
		);
		if ( 'rollback_sent' === $event && in_array( $this->phase, array( 'transaction_owned', 'safety_locked', 'target_locked', 'pair_verified', 'resolution_ready' ), true ) && true === $facts['in_transaction'] ) {
			$this->phase = 'rollback_sent';
			return true;
		}
		if ( ! is_string( $event ) || ! isset( $edges[ $event ] ) || $edges[ $event ][0] !== $this->phase || $edges[ $event ][2] !== $facts['in_transaction'] ) {
			return $this->poison();
		}
		$this->phase = $edges[ $event ][1];
		if ( 'pair_verified' === $event ) {
			$this->prepared = $now;
		}
		return true;
	}

	/**
	 * Poison cannot authorize replay or erase unknown commit.
	 *
	 * @return bool
	 */
	private function poison() {
		$this->unknown = $this->unknown || 'commit_sent' === $this->phase;
		$this->phase   = 'poisoned';
		return false;
	}

	/**
	 * Return simulation phase only.
	 *
	 * @return string
	 */
	public function phase() {
		return $this->phase;
	}

	/**
	 * Uncertainty is never a negative commit certificate.
	 *
	 * @return bool
	 */
	public function commit_unknown() {
		return $this->unknown;
	}

	/**
	 * Compare original identity without enabling a retry or transferring it.
	 *
	 * @param mixed $command Candidate command ID.
	 * @return bool Data comparison only.
	 */
	public function matches_command( $command ) {
		return $this->command === $command;
	}
}
