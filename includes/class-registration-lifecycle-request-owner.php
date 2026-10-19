<?php
/**
 * Dormant fixed same-stack origin retention, not a native admission/release owner.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-registration-request-owner.php';
require_once __DIR__ . '/class-registration-observation-origin.php';
require_once __DIR__ . '/trait-registration-observation-construction-window.php';
require_once __DIR__ . '/trait-registration-observation-packet-data.php';
require_once __DIR__ . '/trait-registration-admission-lifecycle-data.php';

/** Isolated API construction only; no public operation, provider or native port. */
final class Alynt_Drime_Backups_Uploader_Registration_Lifecycle_Request_Owner {
	use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Core_Guard;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;
	use Alynt_Drime_Backups_Uploader_Registration_Eligibility;
	use Alynt_Drime_Backups_Uploader_Registration_Construction;
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Construction_Window;
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Data;
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Packet_Data;
	use Alynt_Drime_Backups_Uploader_Registration_Admission_Lifecycle_Data;

	/**
	 * Constructor guard state, never reset after an attempt.
	 *
	 * @var bool
	 */
	private $active = false;
	/**
	 * Original constructor attempt consumed even on refusal.
	 *
	 * @var bool
	 */
	private $consumed = false;
	/**
	 * Entire fixed root attempt, not just the inner constructor.
	 *
	 * @var bool
	 */
	private $attempted = false;
	/**
	 * Re-entry poisons the outer root.
	 *
	 * @var bool
	 */
	private $interrupted = false;
	/**
	 * Whole-root callback fence.
	 *
	 * @var bool
	 */
	private $busy = false;
	/**
	 * Stack-local core frame, erased on every exit.
	 *
	 * @var array|null
	 */
	private $scope;
	/**
	 * Core monotonic UTC observation.
	 *
	 * @var int
	 */
	private $last_clock = 0;
	/**
	 * Exact owned filter callbacks only.
	 *
	 * @var array
	 */
	private $fences = array();
	/**
	 * Original monotonic root start, never supplied by callers.
	 *
	 * @var int|float|null
	 */
	private $started;
	/**
	 * Minimal original construction data, never adopted from history.
	 *
	 * @var array|null
	 */
	private $origin_data;
	/**
	 * Private immutable consistency object, not authority.
	 *
	 * @var object|null
	 */
	private $origin;
	/**
	 * Complete historical and all ten fresh identities.
	 *
	 * @var array
	 */
	private $used = array();
	/**
	 * Original real start and pre-retention observations.
	 *
	 * @var array|null
	 */
	private $root_clock;
	/**
	 * Preparation phase only; no writer-sent/released labels admitted.
	 *
	 * @var string
	 */
	private $phase = 'unopened';

	/** Zero IO. */
	private function __construct() {}

	/**
	 * No injected origin, frame provider, actor, clocks or handles.
	 *
	 * @return self|false Dormant fixed owner only.
	 */
	public static function create() {
		return 0 === func_num_args() ? new self() : false;
	}

	/**
	 * Own one guarded proposal, retain before final guards, erase on exit.
	 * No admission call, synthetic outcome import or read issuance is permitted.
	 *
	 * @param mixed $captured Private captured-frame seam in isolated API tests.
	 * @param mixed $body Original small register issuance form.
	 * @return bool Construction consistency only; never native authorization.
	 */
	private function prepare_admission( $captured, $body ) {
		if ( $this->busy ) {
			$this->interrupted = true;
			$this->clear_origin();
			return false;
		}
		if ( $this->attempted ) {
			return false;
		}
		$this->attempted = true;
		$this->busy      = true;
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Fail closed on diagnostics; restore previous handler unconditionally.
		set_error_handler(
			static function () {
				throw new RuntimeException( 'Admission preparation unavailable.' );
			}
		);
		try {
			$core = $this->open_window();
			$f    = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issuance( $body );
			if ( 2 !== func_num_args() || false === $core || false === $f || 'register_closed' !== $f['purpose'] ) {
				return false;
			}
			$start = $this->started;
			$actor = get_current_user_id();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Same-stack exact cookie pin only; never retain/export it.
			$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
			if ( ! $this->same_request( $core, $cookie, $actor ) || ! $this->within_budget() ) {
				return false;
			}
			$built = $this->construct_registration( $captured, $body );
			if ( false === $built || ! $this->same_request( $core, $cookie, $actor ) || $this->started !== $start || ! $this->within_budget() ) {
				return false;
			}
			$p    = $built['proposal'];
			$data = $this->eligible_registration( $captured, $core );
			if ( false === $data || ! $this->guard( $core, $built['nonce'], $built['action'], $p['command'] ) || ! $this->same_request( $core, $cookie, $actor ) || $this->started !== $start || ! $this->within_budget() ) {
				return false;
			}
			$d   = array(
				'domain'          => 'registration_observation_origin_v1',
				'installation'    => $this->installation( $core ),
				'scope'           => $data['scope'],
				'manifest_digest' => Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::digest(),
				'mutation'        => $p['command'],
				'actor_id'        => $actor,
				'operation_id'    => $p['operation_id'],
				'context_digest'  => $p['command']['context_digest'],
			);
			$ids = array_merge( array_keys( $data['used'] ), array( $p['command']['command_id'], $p['operation_id'], $p['terminal_id'], $p['resolution_id'] ), array_values( $p['effect_ids'] ), array_values( $p['reservation_command_ids'] ) );
			if ( ! self::original( $d, $p, $ids ) ) {
				return false;
			}
			$this->origin_data = $d;
			$this->origin      = Alynt_Drime_Backups_Uploader_Registration_Observation_Origin::prepare( $d );
			$this->used        = array_fill_keys( $ids, true );
			$this->root_clock  = array(
				'started_ns' => $start,
				'now_ns'     => hrtime( true ),
				'utc'        => time(),
			);
			$this->phase       = 'origin_retained';
			$action            = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( $core['blog'], 'register_closed' );
			if ( ! $this->guard( $core, $f['nonce'], $action, $p['command'] ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->guard( $core, $built['nonce'], $built['action'], $p['command'] ) || ! $this->same_request( $core, $cookie, $actor ) || $this->started !== $start || ! $this->within_budget() ) {
				return false;
			}
			$now = array(
				'started_ns' => $this->started,
				'now_ns'     => hrtime( true ),
				'utc'        => time(),
			);
			return self::root_time( $now, $this->root_clock, 10000000000 ) && $this->origin_data === $d && array_keys( $this->used ) === $ids;
		} catch ( Throwable $error ) {
			return false;
		} finally {
			$this->consumed   = true;
			$this->phase      = 'closed';
			$this->root_clock = null;
			$this->clear_origin();
			try {
				$this->close_window();
			} finally {
				restore_error_handler();
			}
		}
	}
}
