<?php
/**
 * Dormant fixed native safety-row begin, with no public execution entry point.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-real-closed-writer-native-observer.php';
require_once __DIR__ . '/trait-native-closed-begin-storage.php';
require_once __DIR__ . '/trait-native-closed-begin-session.php';

/** Test-loaded only; private visibility is not a hostile same-process sandbox. */
final class Alynt_Drime_Backups_Uploader_Native_Closed_Begin {
	use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Core_Guard;
	use Alynt_Drime_Backups_Uploader_Native_Observer_Session;
	use Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Storage;
	use Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;

	/**
	 * Exclusively owned connection, initially opened read-only.
	 *
	 * @var mysqli|null
	 */
	private $reader;
	/**
	 * Pinned physical session facts.
	 *
	 * @var array|null
	 */
	private $physical;
	/**
	 * Permanently consumed even on refusal.
	 *
	 * @var bool
	 */
	private $used = false;
	/**
	 * Private non-authorizing transition classification.
	 *
	 * @var string
	 */
	private $phase = 'unopened';
	/**
	 * Last original-window UTC second.
	 *
	 * @var int
	 */
	private $last_clock = 0;
	/**
	 * Original monotonic start.
	 *
	 * @var int|float
	 */
	private $started = 0;
	/**
	 * Private proof counter, never a public receipt.
	 *
	 * @var int
	 */
	private $protected_reads = 0;
	/**
	 * Private safety-update attempts only.
	 *
	 * @var int
	 */
	private $writes = 0;

	/** Zero IO, no providers or handles accepted. */
	private function __construct() {}

	/**
	 * Create a dormant, non-transferable one-shot owner.
	 *
	 * @return self|false
	 */
	public static function create() {
		return 0 === func_num_args() ? new self() : false;
	}

	/**
	 * No production caller. Isolated native proof observes the actual decision.
	 * Never returns raw bytes, continuation, handle or permission to change target.
	 *
	 * @param string $method Original method.
	 * @param mixed  $body Original form.
	 * @return bool Confirmed closed begin only; false can include a durable commit.
	 */
	private function begin( $method, $body ) {
		if ( $this->used ) {
			return false;
		}
		$this->used    = true;
		$this->started = hrtime( true );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact method comparison, never normalized.
		if ( 'POST' !== $method || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? null ) ) {
			return false;
		}
		$f = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::parse( $body );
		if ( false === $f || 'begin_disable' !== $f['envelope']['purpose'] || ! $this->budget( $f['envelope'] ) ) {
			return false;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Stack-local warning policy, restored unconditionally; no private diagnostics escape.
		set_error_handler(
			static function () {
				throw new RuntimeException( 'Native closed begin unavailable.' );
			}
		);
		try {
			$frame = $this->frame();
			if ( false === $frame || ! $this->profile() || ! function_exists( 'wp_generate_uuid4' ) ) {
				return false;
			}
			$auth   = $frame['db']->dbh;
			$before = $this->native_idle( $auth );
			$actor  = get_current_user_id();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Pin original cookie; never expose or persist it.
			$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
			$e      = $f['envelope'];
			$action = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::action( $e );
			if ( false === $before || false === $action || ! $this->guard( $frame, $f['nonce'], $action, $e ) || ! $this->auth_same( $frame, $auth, $before ) || ! $this->same_request( $frame, $cookie, $actor ) || ! $this->budget( $e ) ) {
				return false;
			}
			// Reserve exactly once outside locks; no callback/UUID creation in critical SQL.
			$reserved = wp_generate_uuid4();
			if ( ! $this->open_writer( $auth, $before ) || ! $this->metadata( $frame['options'] ) ) {
				return false;
			}
			$a   = $this->row( $frame['options'], true, false );
			$b   = $this->row( $frame['options'], false, false );
			$now = time(); // Fixed candidate timestamp, never refreshed under locks.
			$raw = false === $a || false === $b ? false : $this->prepare( $frame, $e, $a, $b, $reserved, $now );
			if ( ! is_string( $raw ) || ! $this->auth_same( $frame, $auth, $before ) || ! $this->guard( $frame, $f['nonce'], $action, $e ) || ! $this->auth_same( $frame, $auth, $before ) || ! $this->same_request( $frame, $cookie, $actor ) || ! $this->budget( $e ) ) {
				return false;
			}
			if ( ! $this->transact( $frame, $e, $a, $b, $raw ) ) {
				return false;
			}
			// Renew WordPress authorization only after the native transaction releases locks.
			if ( ! $this->auth_same( $frame, $auth, $before ) || ! $this->guard( $frame, $f['nonce'], $action, $e ) || ! $this->auth_same( $frame, $auth, $before ) || ! $this->same_request( $frame, $cookie, $actor ) || ! $this->budget( $e ) ) {
				return false; // Acknowledged commit remains historical fact, never replay.
			}
			$this->finish();
			return 'committed' === $this->phase && $this->budget( $e );
		} catch ( Throwable $error ) {
			return false;
		} finally {
			$this->finish();
			restore_error_handler();
		}
	}

	/**
	 * Validate the exact observed bytes BEFORE the final pre-lock guard. Legacy
	 * validators can call WordPress helpers/error hooks, so never run under locks.
	 *
	 * @param array  $frame Pinned metadata.
	 * @param array  $e Original command.
	 * @param array  $a Original safety tuple.
	 * @param array  $b Original settings tuple.
	 * @param string $reserved Once-reserved completion ID.
	 * @param int    $now Once-prepared timestamp.
	 * @return string|false Private bounded successor bytes, never admission.
	 */
	private function prepare( array $frame, array $e, array $a, array $b, $reserved, $now ) {
		$scope = array(
			'blog_id'    => $frame['blog'],
			'prefix'     => $frame['prefix'],
			'database'   => DB_NAME,
			'site_uuid'  => $e['site_uuid'],
			'session_id' => hash( 'sha256', 'native_closed_begin_v1:' . $this->physical['thread'] . ':' . $this->started ),
		);
		$o     = Alynt_Drime_Backups_Uploader_Closed_Writer_Observation_Contract::simulate( $scope, $a, $b, $a, $b );
		$s     = Alynt_Drime_Backups_Uploader_Closed_Writer_Json_Contract::decode( $a['value'] );
		if ( false === $o || ! $o->matches_context( $e['context_digest'] ) || false === $s || 8 !== $s['schema_version'] ) {
			return false;
		}
		$p   = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::plan( $s, $e, $b['value'], $now, $frame['blog'], $b['autoload'], $reserved, true );
		$raw = false === $p ? false : json_encode( $p['state'] );
		return is_string( $raw ) && false !== Alynt_Drime_Backups_Uploader_Closed_Writer_Json_Contract::decode( $raw ) && $raw !== $a['value'] && null !== $p['state']['writer']['pending'] && $this->budget( $e ) ? $raw : false;
	}

	/**
	 * Critical section: native SQL, exact tuple comparisons and fixed clocks only.
	 *
	 * @param array  $frame Pinned metadata.
	 * @param array  $e Original command.
	 * @param array  $a Validated safety tuple.
	 * @param array  $b Validated settings tuple.
	 * @param string $raw Validated exact successor, never a caller/public capability.
	 * @return bool
	 */
	private function transact( array $frame, array $e, array $a, array $b, $raw ) {
		if ( ! $this->session( false ) || ! $this->budget( $e ) || 'idle' !== $this->phase ) {
			return false;
		}
		$this->phase = 'begin_sent';
		if ( true !== $this->reader->query( 'START TRANSACTION READ WRITE' ) || ! $this->session( true ) ) {
			return false;
		}
		$this->phase = 'transaction_owned';
		// Safety first acquires the table MDL; revalidate schema before any write.
		$locked_a = $this->row( $frame['options'], true, true );
		if ( $a !== $locked_a || ! $this->metadata( $frame['options'] ) ) {
			return false;
		}
		$locked_b = $this->row( $frame['options'], false, true );
		if ( $b !== $locked_b || ! $this->budget( $e ) || ! $this->session( true ) || ! $this->write_safety( $frame['options'], $a, $raw ) ) {
			return false;
		}
		$expected           = $a;
		$expected['value']  = $raw;
		$expected['octets'] = (string) strlen( $raw );
		if ( $this->row( $frame['options'], true, true ) !== $expected || $this->row( $frame['options'], false, true ) !== $b || ! $this->session( true ) || ! $this->budget( $e ) ) {
			return false;
		}
		$this->phase = 'commit_sent'; // Latch BEFORE IO; unknown is never rollback/no-effect.
		if ( true !== $this->reader->query( 'COMMIT AND NO CHAIN NO RELEASE' ) || ! $this->session( false ) ) {
			return false;
		}
		$this->phase = 'committed';
		return $this->row( $frame['options'], true, false ) === $expected && $this->row( $frame['options'], false, false ) === $b && $this->session( false ) && $this->budget( $e );
	}

	/**
	 * Real original deadline, regression refusal and ten-second acceptance budget.
	 *
	 * @param array $e Original intent.
	 * @return bool
	 */
	private function budget( array $e ) {
		$tick = hrtime( true );
		return $tick >= $this->started && $tick - $this->started < 10000000000 && $this->window( $e );
	}
}
