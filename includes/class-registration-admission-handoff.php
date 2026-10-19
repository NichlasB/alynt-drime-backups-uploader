<?php
/**
 * Dormant private one-attempt DATA lifetime, not the future native coordinator.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-registration-admission-plan.php';

/** Explicit test reflection only. No public operation or actual request context. */
final class Alynt_Drime_Backups_Uploader_Registration_Admission_Handoff {
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;

	/**
	 * Consumed on first staging or premature comparison, even refusal.
	 *
	 * @var bool
	 */
	private $used = false;
	/**
	 * At most one comparison attempt.
	 *
	 * @var bool
	 */
	private $checked = false;
	/**
	 * Private immutable candidate; cannot be imported from an external owner.
	 *
	 * @var object|null
	 */
	private $plan;
	/**
	 * Original synthetic monotonic/UTC observations, never renewed.
	 *
	 * @var array|null
	 */
	private $clock;

	/** Zero IO, identity generation or dependency injection. */
	private function __construct() {}

	/**
	 * No provider, handle, target, enablement or actual clock argument.
	 *
	 * @return self|false Dormant data lifetime only.
	 */
	public static function create() {
		return 0 === func_num_args() ? new self() : false;
	}

	/**
	 * Consume before validating synthetic data. Never adopt an existing plan.
	 *
	 * @param mixed $frame Synthetic capture.
	 * @param mixed $proposal Complete synthetic proposal.
	 * @param mixed $context Explicit synthetic scope/actor.
	 * @param mixed $clock Original synthetic clocks.
	 * @return bool Private staged candidate, never native authorization.
	 */
	private function stage( $frame, $proposal, $context, $clock ) {
		$available   = ! $this->used;
		$this->used  = true;
		$this->plan  = null;
		$this->clock = null;
		if ( ! $available || 4 !== func_num_args() || ! self::valid_clock( $clock ) ) {
			return false;
		}
		$this->plan = Alynt_Drime_Backups_Uploader_Registration_Admission_Plan::prepare( $frame, $proposal, $context, $clock['utc'] );
		if ( ! $this->plan instanceof Alynt_Drime_Backups_Uploader_Registration_Admission_Plan ) {
			$this->plan = null;
			return false;
		}
		$this->clock = $clock;
		return true;
	}

	/**
	 * Consume before comparison; release candidate on positive or refusal.
	 * No parser, callback, real clock, continuation, receipt or handle is returned.
	 *
	 * @param mixed $frame Synthetic re-observation, no locks certified.
	 * @param mixed $proposal Original complete proposal.
	 * @param mixed $context Original explicit scope/actor.
	 * @param mixed $clock Same original start and current synthetic clocks.
	 * @return bool Data consistency only. All support flags remain false.
	 */
	private function revalidate( $frame, $proposal, $context, $clock ) {
		$available     = $this->used && ! $this->checked;
		$this->used    = true;
		$this->checked = true;
		$plan          = $this->plan;
		$original      = $this->clock;
		$this->plan    = null;
		$this->clock   = null;
		return $available && 4 === func_num_args() && $plan instanceof Alynt_Drime_Backups_Uploader_Registration_Admission_Plan && self::valid_clock( $clock ) && $clock['started_ns'] === $original['started_ns'] && $clock['now_ns'] >= $original['now_ns'] && $clock['utc'] >= $original['utc'] && $plan->matches( $frame, $proposal, $context, $clock['utc'] );
	}

	/**
	 * Fixed typed budget; acceptance only, not a real timer or IO cancellation.
	 *
	 * @param mixed $clock Synthetic explicit clocks.
	 * @return bool Original elapsed time is strictly below ten seconds.
	 */
	private static function valid_clock( $clock ) {
		if ( ! is_array( $clock ) || array_keys( $clock ) !== array( 'started_ns', 'now_ns', 'utc' ) ) {
			return false;
		}
		foreach ( $clock as $key => $value ) {
			if ( null !== ReflectionReference::fromArrayElement( $clock, $key ) || ! is_int( $value ) || $value < 0 ) {
				return false;
			}
		}
		return $clock['utc'] > 0 && $clock['utc'] <= PHP_INT_MAX - 3600 && $clock['now_ns'] >= $clock['started_ns'] && $clock['now_ns'] - $clock['started_ns'] < 10000000000;
	}
}
