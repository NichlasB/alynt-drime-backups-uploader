<?php
/**
 * Private root ownership, original clocks and unconditional primitive erasure.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No public state setters, imported clocks or handles. */
trait Alynt_Drime_Backups_Uploader_Registration_Lifecycle_Integration_State {
	/** Entire root consumption.
	 *
	 * @var bool
	 */
	private $root_attempted = false;
	/** Nonreentrant outer lifetime.
	 *
	 * @var bool
	 */
	private $busy = false;
	/** Permanent reentry poison.
	 *
	 * @var bool
	 */
	private $interrupted = false;
	/** Inner construction latch.
	 *
	 * @var bool
	 */
	private $active = false;
	/** Inner consumption.
	 *
	 * @var bool
	 */
	private $consumed = false;
	/** Borrowed core frame, never exported.
	 *
	 * @var array|null
	 */
	private $scope;
	/** Original pre-capture start.
	 *
	 * @var int|null
	 */
	private $started;
	/** Nonregressing UTC observations.
	 *
	 * @var int
	 */
	private $last_clock = 0;
	/** Owned role-only filters.
	 *
	 * @var array
	 */
	private $fences = array();
	/** Original root clock.
	 *
	 * @var array|null
	 */
	private $lifecycle_clock;
	/** Subordinate read start.
	 *
	 * @var int|null
	 */
	private $read_started;
	/** Consumed read entry.
	 *
	 * @var bool
	 */
	private $observation_attempted = false;
	/** Minimal own origin.
	 *
	 * @var array|null
	 */
	private $origin_data;
	/** Non-authorizing origin validator.
	 *
	 * @var object|null
	 */
	private $origin;
	/** Complete reserved identities.
	 *
	 * @var array
	 */
	private $used = array();
	/** Uninstalled reader slot.
	 *
	 * @var mixed
	 */
	private $reader;
	/** Uninstalled writer slot.
	 *
	 * @var mixed
	 */
	private $native_outcome_writer;
	/** Uninstalled writer physical slot.
	 *
	 * @var mixed
	 */
	private $physical;
	/** Uninstalled reader physical slot.
	 *
	 * @var mixed
	 */
	private $read_physical;

	/**
	 * Read construction cannot reset root age or duplicate its filters.
	 *
	 * @return array|false Same core frame.
	 */
	private function open_window() {
		if ( null === $this->scope ) {
			return $this->initial_window();
		}
		$core = $this->frame();
		return false !== $core && $this->pin( $core ) && $this->within_budget() ? $core : false;
	}

	/**
	 * Original10s admission; read10s inside original20s, never a renewed root.
	 *
	 * @return bool Acceptance budget only, not hard IO cancellation.
	 */
	private function within_budget() {
		$now   = hrtime( true );
		$clock = array(
			'started_ns' => $this->started,
			'now_ns'     => $now,
			'utc'        => time(),
		);
		$limit = null === $this->read_started ? 10000000000 : 20000000000;
		if ( $this->interrupted || null === $this->lifecycle_clock || ! self::root_time( $clock, $this->lifecycle_clock, $limit ) || ( null !== $this->read_started && ( $now < $this->read_started || $now - $this->read_started >= 10000000000 ) ) ) {
			return false;
		}
		$this->lifecycle_clock = $clock;
		return true;
	}

	/** Erase all serialized admission material before ANY read-auth callback. */
	private function erase_lifecycle_writer() {
		$this->before      = null;
		$this->proposal    = null;
		$this->context     = null;
		$this->after       = null;
		$this->recorded_at = null;
		$this->clear_admission_origin();
		$this->clear_native_outcome();
	}

	/** Consume every DATA slot; native cleanup is separately owned and uninstalled. */
	private function erase_lifecycle() {
		$this->erase_lifecycle_writer();
		$this->clear_origin();
		$this->staged                        = null;
		$this->inflight                      = null;
		$this->attempted                     = true;
		$this->claimed                       = true;
		$this->finished                      = true;
		$this->consumed                      = true;
		$this->observation_attempted         = true;
		$this->admission_qualification_spent = true;
		$this->admission_join_busy           = false;
		$this->lifecycle_clock               = null;
		$this->read_started                  = null;
	}
}
