<?php
/**
 * Dormant closed-ledger-only transaction foundation, no public mutation method.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-real-closed-writer-native-observer.php';
require_once __DIR__ . '/class-scan-ledger-checkpoint-request.php';
require_once __DIR__ . '/class-scan-ledger-checkpoint-plan.php';
require_once __DIR__ . '/trait-native-closed-begin-storage.php';
require_once __DIR__ . '/trait-native-closed-begin-session.php';
require_once __DIR__ . '/trait-scan-ledger-checkpoint-storage.php';
require_once __DIR__ . '/trait-scan-ledger-checkpoint-transaction.php';

/** Test-loaded only. Private visibility is not a hostile same-process sandbox. */
final class Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint {
	use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Core_Guard;
	use Alynt_Drime_Backups_Uploader_Native_Observer_Session;
	use Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Storage;
	use Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session;
	use Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Storage;
	use Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Transaction;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;

	/**
	 * Exclusively owned native handle.
	 *
	 * @var mysqli|null
	 */
	private $reader;
	/**
	 * Pinned native facts.
	 *
	 * @var array|null
	 */
	private $physical;
	/**
	 * One-shot, consumed on refusal too.
	 *
	 * @var bool
	 */
	private $used = false;
	/**
	 * Historical private phase, not a receipt.
	 *
	 * @var string
	 */
	private $phase = 'unopened';
	/**
	 * Original UTC window observation.
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
	 * Three ten-row passes at most.
	 *
	 * @var int
	 */
	private $protected_reads = 0;
	/**
	 * At most one ledger-only UPDATE attempt.
	 *
	 * @var int
	 */
	private $writes = 0;

	/** Zero IO. */
	private function __construct() {}

	/**
	 * No injected handles, targets or providers.
	 *
	 * @return self|false
	 */
	public static function create() {
		return 0 === func_num_args() ? new self() : false;
	}

	/**
	 * Genuine guards surround a private fixed closed checkpoint transaction.
	 * No production caller, operation registration, outward receipt or continuation.
	 *
	 * @param string $method Exact original method.
	 * @param mixed  $body Original form.
	 * @return bool False can include an acknowledged historical commit.
	 */
	private function checkpoint( $method, $body ) {
		if ( $this->used ) {
			return false;
		}
		$this->used    = true;
		$this->started = hrtime( true );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact original method, never normalized.
		if ( 'POST' !== $method || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? null ) ) {
			return false;
		}
		$f = Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Request::parse( $body );
		if ( false === $f || ! $this->budget( $f['envelope'] ) ) {
			return false;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Stack-local warning-to-refusal policy, restored unconditionally; no logging.
		set_error_handler(
			static function () {
				throw new RuntimeException( 'Closed ledger checkpoint unavailable.' );
			}
		);
		try {
			$frame = $this->frame();
			if ( false === $frame || ! $this->profile() ) {
				return false;
			}
			$auth   = $frame['db']->dbh;
			$before = $this->native_idle( $auth );
			$actor  = get_current_user_id();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Pin private original cookie; never persist or emit it.
			$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
			$e      = $f['envelope'];
			if ( false === $before || ! $this->guard( $frame, $f['nonce'], $f['action'], $e ) || ! $this->same_request( $frame, $cookie, $actor ) || ! $this->auth_same( $frame, $auth, $before ) || ! $this->budget( $e ) ) {
				return false;
			}
			if ( ! $this->open_writer( $auth, $before ) || ! $this->metadata( $frame['options'] ) ) {
				return false;
			}
			$rows = array();
			foreach ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) as $role ) {
				if ( ! $this->budget( $e ) ) {
					return false;
				}
				$row = $this->checkpoint_row( $frame['options'], $role, false );
				if ( false === $row ) {
					return false;
				}
				$rows[ $role ] = $row;
			}
			$observed = array(
				'blog_id'  => $frame['blog'],
				'prefix'   => $frame['prefix'],
				'database' => DB_NAME,
				'rows'     => $rows,
			);
			$raw      = Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Plan::prepare( $observed, $e['site_uuid'], $e['context_digest'] );
			if ( ! is_string( $raw ) || ! $this->auth_same( $frame, $auth, $before ) || ! $this->guard( $frame, $f['nonce'], $f['action'], $e ) || ! $this->same_request( $frame, $cookie, $actor ) || ! $this->auth_same( $frame, $auth, $before ) || ! $this->budget( $e ) ) {
				return false;
			}
			if ( ! $this->transact( $frame, $e, $rows, $raw ) ) {
				return false;
			}
			// Close/release before ANY renewed WordPress callback or outward decision.
			$this->finish();
			return 'committed' === $this->phase && $this->budget( $e ) && $this->auth_same( $frame, $auth, $before ) && $this->guard( $frame, $f['nonce'], $f['action'], $e ) && $this->same_request( $frame, $cookie, $actor ) && $this->auth_same( $frame, $auth, $before ) && $this->budget( $e );
		} catch ( Throwable $error ) {
			return false;
		} finally {
			$this->finish();
			restore_error_handler();
		}
	}

	/**
	 * Original deadline and monotonic acceptance budget, no IO cancellation claim.
	 *
	 * @param array $e Original typed intent.
	 * @return bool
	 */
	private function budget( array $e ) {
		$tick = hrtime( true );
		return $tick >= $this->started && $tick - $this->started < 10000000000 && $this->window( $e );
	}
}
