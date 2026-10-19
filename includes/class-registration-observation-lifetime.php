<?php
/**
 * Consumed private synthetic observation lifetime; not genuine native ownership.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-registration-observation-origin.php';

/** No public operation, providers, imported object, actual clocks or IO. */
final class Alynt_Drime_Backups_Uploader_Registration_Observation_Lifetime {
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Data;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;

	/**
	 * Outer lifetime consumed by any attempt, including premature finish.
	 *
	 * @var bool
	 */
	private $used = false;
	/**
	 * At most one result comparison.
	 *
	 * @var bool
	 */
	private $checked = false;
	/**
	 * Internally constructed immutable DATA, never an imported capability.
	 *
	 * @var object|null
	 */
	private $origin;
	/**
	 * Original small synthetic form.
	 *
	 * @var array|null
	 */
	private $intent;
	/**
	 * Original full synthetic form.
	 *
	 * @var array|null
	 */
	private $form;
	/**
	 * Original synthetic start/UTC observations.
	 *
	 * @var array|null
	 */
	private $clock;

	/** Zero IO. */
	private function __construct() {}

	/**
	 * Fixed factory, no native caller construction seam.
	 *
	 * @return self|false Dormant DATA lifetime.
	 */
	public static function create() {
		return 0 === func_num_args() ? new self() : false;
	}

	/**
	 * Consume before checking supplied synthetic labels; not actual provenance.
	 *
	 * @param mixed $data Primitive origin data, never an existing object.
	 * @param mixed $intent Small original read form.
	 * @param mixed $form Full original read form.
	 * @param mixed $clock Synthetic original clocks.
	 * @return bool
	 */
	private function stage( $data, $intent, $form, $clock ) {
		$available    = ! $this->used;
		$this->used   = true;
		$this->origin = null;
		$this->intent = null;
		$this->form   = null;
		$this->clock  = null;
		if ( ! $available || 4 !== func_num_args() || ! self::valid_clock( $clock ) ) {
			return false;
		}
		$o = Alynt_Drime_Backups_Uploader_Registration_Observation_Origin::prepare( $data );
		if ( false === $o || ! $o->accepts( $intent, $form, $clock['utc'] ) ) {
			return false;
		}
		$this->origin = $o;
		$this->intent = $intent;
		$this->form   = $form;
		$this->clock  = $clock;
		return true;
	}

	/**
	 * Clear all retained facts before result, including failed comparison.
	 *
	 * @param mixed $raw Synthetic candidate history.
	 * @param mixed $installation Synthetic current installation.
	 * @param mixed $scope Synthetic current scope.
	 * @param mixed $intent Same small form.
	 * @param mixed $form Same full form.
	 * @param mixed $clock Same start and current synthetic clocks.
	 * @return array|false Candidate result only; never native barrier/closure.
	 */
	private function finish( $raw, $installation, $scope, $intent, $form, $clock ) {
		$available     = $this->used && ! $this->checked;
		$this->used    = true;
		$this->checked = true;
		$o             = $this->origin;
		$original      = $this->clock;
		$small         = $this->intent;
		$full          = $this->form;
		$this->origin  = null;
		$this->intent  = null;
		$this->form    = null;
		$this->clock   = null;
		if ( ! $available || 6 !== func_num_args() || ! $o instanceof Alynt_Drime_Backups_Uploader_Registration_Observation_Origin || ! self::valid_clock( $clock ) || ! self::bounded( $intent ) || ! self::bounded( $form ) || $intent !== $small || $form !== $full || $clock['started_ns'] !== $original['started_ns'] || $clock['now_ns'] < $original['now_ns'] || $clock['utc'] < $original['utc'] ) {
			return false;
		}
		return $o->observe( $raw, $intent, $form, $installation, $scope, $clock['utc'] );
	}

	/**
	 * Synthetic half-open acceptance budget, never a real timer or renewed lease.
	 *
	 * @param mixed $clock Explicit data clocks.
	 * @return bool
	 */
	private static function valid_clock( $clock ) {
		if ( ! self::bounded( $clock ) || ! self::keys( $clock, array( 'started_ns', 'now_ns', 'utc' ) ) ) {
			return false;
		}
		foreach ( $clock as $value ) {
			if ( ! is_int( $value ) || $value < 0 ) {
				return false;
			}
		}
		return $clock['utc'] > 0 && $clock['utc'] <= PHP_INT_MAX - 3600 && $clock['now_ns'] >= $clock['started_ns'] && $clock['now_ns'] - $clock['started_ns'] < 10000000000;
	}
}
