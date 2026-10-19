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

/** Explicit owned-proof reflection only; production never loads this class. */
final class Alynt_Drime_Backups_Uploader_Registration_Native_Admission {
	use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Core_Guard;
	use Alynt_Drime_Backups_Uploader_Registration_Construction;
	use Alynt_Drime_Backups_Uploader_Registration_Admission_Candidate;
	use Alynt_Drime_Backups_Uploader_Registration_Admission_Storage;
	use Alynt_Drime_Backups_Uploader_Registration_Admission_Transaction;
	use Alynt_Drime_Backups_Uploader_Native_Observer_Session;
	use Alynt_Drime_Backups_Uploader_Native_Options_Metadata;
	use Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;

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
	 * Original small issuance -> private fresh construction -> locked ledger commit.
	 * An unavailable result may follow an acknowledged historical commit.
	 *
	 * @param string $method Exact original POST.
	 * @param mixed  $body Original small issuance form, never a supplied blueprint.
	 * @return bool Private historical decision; no native handle or raw receipt.
	 */
	private function admit( $method, $body ) {
		if ( $this->attempted ) {
			return false;
		}
		$this->attempted = true;
		$this->active    = true;
		$this->started   = hrtime( true );
		$fences          = array();
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Stack-local warning-to-refusal policy, always restored; no logging.
		set_error_handler(
			static function () {
				throw new RuntimeException( 'Closed registration admission unavailable.' );
			}
		);
		try {
			$f = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issuance( $body );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact unchanged method only.
			if ( 2 !== func_num_args() || 'POST' !== $method || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? null ) || false === $f || 'register_closed' !== $f['purpose'] ) {
				return false;
			}
			$core = $this->frame();
			if ( false === $core || ! $this->pin( $core ) || ! $this->profile() ) {
				return false;
			}
			// Role-only core fallback and independent query fence across ALL callbacks.
			$fences['pre_wp_load_alloptions'] = static function () {
				return array();
			};
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Pinned core table; fixed role name bound separately.
			$role_sql        = $core['db']->prepare( "SELECT option_value FROM {$core['options']} WHERE option_name = %s LIMIT 1", $core['prefix'] . 'user_roles' );
			$table           = preg_quote( $core['options'], '/' );
			$fences['query'] = static function ( $sql ) use ( $role_sql, $table ) {
				if ( 1 === preg_match( '/\b(?:FROM|JOIN|UPDATE|INTO|TABLE)\s+`?' . $table . '`?(?=\s|$)/i', $sql ) && $sql !== $role_sql ) {
					throw new RuntimeException( 'Registration option access unavailable.' );
				}
				return $sql;
			};
			foreach ( $fences as $hook => $callback ) {
				add_filter( $hook, $callback, PHP_INT_MAX );
			}
			$actor = get_current_user_id();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Private original cookie compared only, never persisted or exported.
			$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
			$auth   = $core['db']->dbh;
			$facts  = $this->native_idle( $auth );
			$issue  = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( $core['blog'], 'register_closed' );
			if ( false === $facts || ! $this->guard( $core, $f['nonce'], $issue, null ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->auth_same( $core, $auth, $facts ) || ! $this->budget( null ) || ! $this->open_reader( $auth, $facts ) ) {
				return false;
			}
			$captured = $this->admission_capture( $core );
			if ( false === $captured || ! $this->guard( $core, $f['nonce'], $issue, null ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->auth_same( $core, $auth, $facts ) || ! $this->budget( null ) || true !== $this->reader->close() ) {
				return false;
			}
			$this->reader        = null;
			$this->read_physical = null;
			// No callbacks in this latch transfer; the outer attempted flag stays set.
			$this->active = false;
			$built        = $this->construct_registration( $captured, $body );
			if ( ! is_array( $built ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->auth_same( $core, $auth, $facts ) || ! $this->budget( null ) ) {
				return false;
			}
			$form          = array_map( 'strval', $built['proposal']['command'] );
			$form['nonce'] = $built['nonce'];
			$parsed        = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $form );
			$context       = array(
				'blog_id'  => $core['blog'],
				'prefix'   => $core['prefix'],
				'database' => DB_NAME,
				'actor_id' => $actor,
			);
			if ( false === $parsed || $parsed['envelope'] !== $built['proposal']['command'] || $parsed['action'] !== $built['action'] || $parsed['nonce'] !== $built['nonce'] || ! $this->prepare_candidate( $captured, $built['proposal'], $context, time() ) || ! $this->open_writer( $auth, $facts ) || ! $this->metadata( $core['options'] ) ) {
				return false;
			}
			$e = $parsed['envelope'];
			if ( ! $this->auth_same( $core, $auth, $facts ) || ! $this->guard( $core, $f['nonce'], $issue, $e ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->auth_same( $core, $auth, $facts ) || ! $this->guard( $core, $parsed['nonce'], $parsed['action'], $e ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->auth_same( $core, $auth, $facts ) || ! $this->budget( $e ) || ! $this->admission_transaction( $core, $e ) ) {
				return false;
			}
			$this->finish(); // All locks/owned handles released before WordPress callbacks.
			return 'committed' === $this->phase && $this->budget( $e ) && $this->auth_same( $core, $auth, $facts ) && $this->guard( $core, $f['nonce'], $issue, $e ) && $this->same_request( $core, $cookie, $actor ) && $this->auth_same( $core, $auth, $facts ) && $this->guard( $core, $parsed['nonce'], $parsed['action'], $e ) && $this->same_request( $core, $cookie, $actor ) && $this->auth_same( $core, $auth, $facts ) && $this->budget( $e );
		} catch ( Throwable $error ) {
			return false;
		} finally {
			$this->finish();
			$this->active        = false;
			$this->consumed      = true;
			$this->scope         = null;
			$this->read_physical = null;
			$this->before        = null;
			$this->proposal      = null;
			$this->context       = null;
			$this->after         = null;
			$this->recorded_at   = null;
			foreach ( $fences as $hook => $callback ) {
				remove_filter( $hook, $callback, PHP_INT_MAX );
			}
			restore_error_handler();
		}
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

	/**
	 * Original ten-second acceptance budget, not hard IO cancellation.
	 *
	 * @param array|null $e Original typed command or initial issuance.
	 * @return bool Original monotonic and UTC deadline still valid.
	 */
	private function budget( $e ) {
		$tick = hrtime( true );
		return $tick >= $this->started && $tick - $this->started < 10000000000 && $this->window( $e );
	}
}
