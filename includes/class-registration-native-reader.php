<?php
/**
 * Dormant fixed read-only capture and private registration construction.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-registration-request-owner.php';
require_once __DIR__ . '/trait-native-observer-session.php';
require_once __DIR__ . '/trait-native-observer-rows.php';
require_once __DIR__ . '/trait-scan-observation-rows.php';

/** Test-loaded only; no public read, provider, writer, endpoint or continuation. */
final class Alynt_Drime_Backups_Uploader_Registration_Native_Reader {
	use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Core_Guard;
	use Alynt_Drime_Backups_Uploader_Native_Observer_Session;
	use Alynt_Drime_Backups_Uploader_Native_Observer_Rows;
	use Alynt_Drime_Backups_Uploader_Scan_Observation_Rows;
	use Alynt_Drime_Backups_Uploader_Registration_Eligibility;
	use Alynt_Drime_Backups_Uploader_Registration_Construction;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;

	/**
	 * Stack reentrancy latch.
	 *
	 * @var bool
	 */
	private $active = false;
	/**
	 * Once-attempted construction, including failed capture.
	 *
	 * @var bool
	 */
	private $consumed = false;
	/**
	 * Read attempt cannot be retried or nested during construction.
	 *
	 * @var bool
	 */
	private $read_attempted = false;
	/**
	 * Original core installation frame.
	 *
	 * @var array|null
	 */
	private $scope;
	/**
	 * Real clock monotonic observation.
	 *
	 * @var int
	 */
	private $last_clock = 0;
	/**
	 * Owned native read-only connection, never returned.
	 *
	 * @var mysqli|null
	 */
	private $reader;
	/**
	 * Fixed twenty-row projection ceiling.
	 *
	 * @var int
	 */
	private $protected_reads = 0;
	/**
	 * Original monotonic start, never reset by a renewed guard.
	 *
	 * @var int|float
	 */
	private $started = 0;

	/** Zero IO and no dependency installation or identity generation. */
	private function __construct() {}

	/**
	 * Zero-argument creation only; there is no public operation method.
	 *
	 * @return self|false
	 */
	public static function create() {
		return 0 === func_num_args() ? new self() : false;
	}

	/**
	 * Fixed post-auth capture, NOT an atomic snapshot, writer or admission.
	 * The private result describes captured bytes which may already be stale.
	 * Only the owned proof invokes this method through reflection today.
	 *
	 * @param string $method Exact POST.
	 * @param mixed  $body Original small registration issuance form.
	 * @return array|false Private non-authorizing blueprint, no native handle.
	 */
	private function read_registration( $method, $body ) {
		if ( 2 !== func_num_args() || $this->active || $this->consumed || $this->read_attempted ) {
			return false;
		}
		$this->read_attempted = true;
		$this->active         = true;
		$this->started        = hrtime( true );
		$autoload_fence       = null;
		$query_fence          = null;
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Stack-local warning-to-refusal policy; no logging, restored below.
		set_error_handler(
			static function () {
				throw new RuntimeException( 'Registration capture unavailable.' );
			}
		);
		try {
			$f = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issuance( $body );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact method comparison only; no normalized request source.
			if ( 'POST' !== $method || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? null ) || false === $f || 'register_closed' !== $f['purpose'] ) {
				return false;
			}
			$core = $this->frame();
			if ( false === $core || ! $this->pin( $core ) || ! $this->profile() ) {
				return false;
			}
			// A real WP_Roles lookup must not bulk-load unrelated protected
			// options before capability/nonce admission. Force core's exact
			// single-role-option fallback, not a fabricated role/capability map.
			$autoload_fence = static function () {
				return array();
			};
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Pinned core table identifier, fixed role name bound separately.
			$role_sql    = $core['db']->prepare( "SELECT option_value FROM {$core['options']} WHERE option_name = %s LIMIT 1", $core['prefix'] . 'user_roles' );
			$table       = preg_quote( $core['options'], '/' );
			$query_fence = static function ( $sql ) use ( $role_sql, $table ) {
				if ( 1 === preg_match( '/\b(?:FROM|JOIN|UPDATE|INTO|TABLE)\s+`?' . $table . '`?(?=\s|$)/i', $sql ) && $sql !== $role_sql ) {
					throw new RuntimeException( 'Registration option access unavailable.' );
				}
				return $sql;
			};
			add_filter( 'pre_wp_load_alloptions', $autoload_fence, PHP_INT_MAX );
			add_filter( 'query', $query_fence, PHP_INT_MAX );
			$actor = get_current_user_id();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Original private cookie compared only within this stack; never exported.
			$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
			$action = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( $core['blog'], $f['purpose'] );
			$auth   = $core['db']->dbh;
			// Native metadata only, never protected option rows, before callbacks.
			$before = $this->native_idle( $auth );
			if ( false === $before || ! $this->guard( $core, $f['nonce'], $action, null ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->auth_same( $core, $auth, $before ) || ! $this->registration_budget() ) {
				return false;
			}
			if ( ! $this->open_reader( $auth, $before ) || ! $this->table_supported( $core['options'] ) || ! $this->auth_same( $core, $auth, $before ) || ! $this->registration_budget() ) {
				return false;
			}
			$session = $this->native_idle( $this->reader );
			$sweeps  = array();
			for ( $pass = 0; $pass < 2; ++$pass ) {
				$rows = array();
				foreach ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) as $role ) {
					if ( false === $session || '1' !== $session['tx_ro'] || ! $this->registration_budget() || $this->native_idle( $this->reader ) !== $session ) {
						return false;
					}
					$row = $this->scan_row( $core['options'], $role );
					if ( false === $row ) {
						return false;
					}
					$rows[ $role ] = $row;
				}
				$sweeps[] = array(
					'blog_id'  => $core['blog'],
					'prefix'   => $core['prefix'],
					'database' => DB_NAME,
					'rows'     => $rows,
				);
			}
			if ( $this->native_idle( $this->reader ) !== $session || ! Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::compare( $sweeps[0], $sweeps[1] )['same_candidate'] || ! $this->same_request( $core, $cookie, $actor ) || ! $this->auth_same( $core, $auth, $before ) || ! $this->guard( $core, $f['nonce'], $action, null ) || ! $this->same_request( $core, $cookie, $actor ) || ! $this->auth_same( $core, $auth, $before ) || ! $this->registration_budget() ) {
				return false;
			}
			if ( true !== $this->reader->close() ) {
				return false;
			}
			$this->reader = null;
			// No callbacks between releasing the capture latch and the builder's
			// own latch. read_attempted continues to block nested native attempts.
			$this->active = false;
			$blueprint    = $this->construct_registration( $sweeps[0], $body );
			return is_array( $blueprint ) && $this->same_request( $core, $cookie, $actor ) && $this->auth_same( $core, $auth, $before ) && $this->registration_budget() ? $blueprint : false;
		} catch ( Throwable $error ) {
			return false;
		} finally {
			$this->consumed = true;
			$owned          = $this->reader;
			$this->reader   = null;
			try {
				if ( $owned instanceof mysqli ) {
					$owned->close();
				}
			} catch ( Throwable $error ) {
				// Refused lifetime only; never reconnect/repair a borrowed handle.
				$this->consumed = true;
			} finally {
				$this->active = false;
				if ( null !== $query_fence ) {
					remove_filter( 'query', $query_fence, PHP_INT_MAX );
				}
				if ( null !== $autoload_fence ) {
					remove_filter( 'pre_wp_load_alloptions', $autoload_fence, PHP_INT_MAX );
				}
				restore_error_handler();
			}
		}
	}

	/**
	 * Pin the original core frame used by both capture and construction.
	 *
	 * @param array $core Native core metadata.
	 * @return bool
	 */
	private function pin( $core ) {
		if ( null === $this->scope ) {
			$this->scope = $core;
		}
		return $this->scope === $core;
	}

	/**
	 * Ten-second acceptance budget; blocking IO is not a hard wall-time bound.
	 *
	 * @return bool
	 */
	private function registration_budget() {
		$tick = hrtime( true );
		return $tick >= $this->started && $tick - $this->started < 10000000000 && $this->window( null );
	}
}
