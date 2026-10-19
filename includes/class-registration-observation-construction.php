<?php
/**
 * Dormant process-local origin retention and guarded read blueprints, no IO.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-registration-request-owner.php';
require_once __DIR__ . '/class-registration-observation-origin.php';
require_once __DIR__ . '/trait-registration-observation-construction-window.php';
require_once __DIR__ . '/class-registration-native-reader.php';
require_once __DIR__ . '/trait-registration-observation-native-capture.php';
require_once __DIR__ . '/class-registration-observation-candidate.php';
require_once __DIR__ . '/trait-registration-observation-handoff-data.php';
require_once __DIR__ . '/trait-registration-observation-read-construction.php';
require_once __DIR__ . '/trait-registration-observation-guarded-handoff.php';

/** Test reflection only; no public raw frame, origin import or continuation. */
final class Alynt_Drime_Backups_Uploader_Registration_Observation_Construction {
	use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Core_Guard;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;
	use Alynt_Drime_Backups_Uploader_Registration_Eligibility;
	use Alynt_Drime_Backups_Uploader_Registration_Construction;
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Construction_Window;
	use Alynt_Drime_Backups_Uploader_Native_Observer_Session;
	use Alynt_Drime_Backups_Uploader_Native_Observer_Rows;
	use Alynt_Drime_Backups_Uploader_Scan_Observation_Rows;
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Native_Capture;
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Handoff_Data;
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Read_Construction;
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Guarded_Handoff;

	/**
	 * Existing registration constructor's non-reentrancy latch.
	 *
	 * @var bool
	 */
	private $active = false;
	/**
	 * Existing registration constructor's permanent attempt latch.
	 *
	 * @var bool
	 */
	private $consumed = false;
	/**
	 * Retention attempt is consumed even on malformed input.
	 *
	 * @var bool
	 */
	private $retention_attempted = false;
	/**
	 * Read attempt is independent and never reset.
	 *
	 * @var bool
	 */
	private $observation_attempted = false;
	/**
	 * Whole-phase re-entry fence, separate from the old constructor.
	 *
	 * @var bool
	 */
	private $busy = false;
	/**
	 * A re-entry poisons the outer construction as well.
	 *
	 * @var bool
	 */
	private $interrupted = false;
	/**
	 * Stack-local fixed core frame, cleared unconditionally.
	 *
	 * @var array|null
	 */
	private $scope;
	/**
	 * Monotonic UTC guard observation.
	 *
	 * @var int
	 */
	private $last_clock = 0;
	/**
	 * Minimal immutable original DATA, no cookie or native handle.
	 *
	 * @var array|null
	 */
	private $origin_data;
	/**
	 * Own non-authorizing pure origin.
	 *
	 * @var object|null
	 */
	private $origin;
	/**
	 * Original issued and historical IDs, never lookup candidates.
	 *
	 * @var array
	 */
	private $used = array();
	/**
	 * Exact owned filter callbacks.
	 *
	 * @var array
	 */
	private $fences = array();
	/**
	 * Real monotonic phase start; no injectable clock.
	 *
	 * @var int|float|null
	 */
	private $started;

	/** Zero IO. */
	private function __construct() {}

	/**
	 * No constructor ports for origins, callbacks, actors, clocks or connections.
	 *
	 * @return self|false
	 */
	public static function create() {
		return 0 === func_num_args() ? new self() : false;
	}

	/**
	 * Retain only this owner's own guarded construction, before any future attempt.
	 * No admission is called and this result never certifies an attempted write.
	 *
	 * @param mixed $captured Private synthetic ten-row frame in API tests only.
	 * @param mixed $body Original small mutation issuance form.
	 * @return bool Private DATA retained, not authorization or storage proof.
	 */
	private function retain_registration( $captured, $body ) {
		if ( $this->busy ) {
			$this->interrupted = true;
			return false;
		}
		if ( $this->retention_attempted || $this->observation_attempted ) {
			return false;
		}
		$this->retention_attempted = true;
		$this->busy                = true;
		$ok                        = false;
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Fail closed on warnings; prior handler restored in finally.
		set_error_handler(
			static function () {
				throw new RuntimeException( 'Observation construction unavailable.' );
			}
		);
		try {
			$core = $this->open_window();
			if ( 2 !== func_num_args() || false === $core ) {
				return false;
			}
			$actor = get_current_user_id();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Pin unchanged cookie only on this stack, never retain/export it.
			$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
			$built  = $this->construct_registration( $captured, $body );
			if ( false === $built || ! $this->same_request( $core, $cookie, $actor ) || ! $this->within_budget() ) {
				return false;
			}
			$p    = $built['proposal'];
			$data = $this->eligible_registration( $captured, $core );
			if ( false === $data || ! $this->guard( $core, $built['nonce'], $built['action'], $p['command'] ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->within_budget() ) {
				return false;
			}
			$d = array(
				'domain'          => 'registration_observation_origin_v1',
				'installation'    => $this->installation( $core ),
				'scope'           => $data['scope'],
				'manifest_digest' => Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::digest(),
				'mutation'        => $p['command'],
				'actor_id'        => $actor,
				'operation_id'    => $p['operation_id'],
				'context_digest'  => $p['command']['context_digest'],
			);
			$o = Alynt_Drime_Backups_Uploader_Registration_Observation_Origin::prepare( $d );
			if ( false === $o ) {
				return false;
			}
			$this->used = $data['used'];
			foreach ( array_merge( array( $p['command']['command_id'], $p['operation_id'], $p['terminal_id'], $p['resolution_id'] ), array_values( $p['effect_ids'] ), array_values( $p['reservation_command_ids'] ) ) as $id ) {
				$this->used[ $id ] = true;
			}
			$this->origin_data = $d;
			$this->origin      = $o;
			$ok                = true;
			return true;
		} catch ( Throwable $error ) {
			return false;
		} finally {
			$this->consumed = true;
			$this->close_window();
			if ( ! $ok ) {
				$this->clear_origin();
			}
			restore_error_handler();
		}
	}

	/**
	 * Separate fresh read authorization; original mutation is never renewed.
	 *
	 * @param mixed $body Small observation issuance form.
	 * @return array|false Private read blueprint only, no reader/writer capability.
	 */
	private function construct_observation( $body ) {
		if ( $this->busy ) {
			$this->interrupted = true;
			return false;
		}
		if ( $this->observation_attempted ) {
			return false;
		}
		$this->observation_attempted = true;
		$this->busy                  = true;
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Fail closed on warnings; prior handler restored in finally.
		set_error_handler(
			static function () {
				throw new RuntimeException( 'Observation construction unavailable.' );
			}
		);
		try {
			if ( 1 !== func_num_args() ) {
				return false;
			}
			$built = $this->build_observation( $body );
			return false === $built ? false : $built['blueprint'];
		} catch ( Throwable $error ) {
			return false;
		} finally {
			$this->clear_origin();
			$this->close_window();
			restore_error_handler();
		}
	}
}
