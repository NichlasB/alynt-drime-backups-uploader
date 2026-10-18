<?php
/**
 * Dormant genuine-core planning observation; never receipt resolution/admission.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-real-closed-writer-request-contract.php';
require_once __DIR__ . '/trait-real-closed-writer-core-guard.php';
require_once __DIR__ . '/trait-native-observer-session.php';
require_once __DIR__ . '/trait-native-observer-rows.php';
require_once __DIR__ . '/class-closed-writer-observation-contract.php';

/** Test-loaded only. No injectable providers, storage handles or public success. */
final class Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Native_Observer {
	use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Core_Guard;
	use Alynt_Drime_Backups_Uploader_Native_Observer_Session;
	use Alynt_Drime_Backups_Uploader_Native_Observer_Rows;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;

	/**
	 * Stack-owned non-reentrant lifetime.
	 *
	 * @var bool
	 */
	private $active = false;
	/**
	 * Last real UTC second.
	 *
	 * @var int
	 */
	private $last_clock = 0;
	/**
	 * Pinned installation frame, not a permission.
	 *
	 * @var array|null
	 */
	private $scope;
	/**
	 * Only the private, nonpersistent read-only connection.
	 *
	 * @var mysqli|null
	 */
	private $reader;
	/**
	 * Private diagnostic counter; no exported row/SQL data.
	 *
	 * @var int
	 */
	private $protected_reads = 0;
	/**
	 * Monotonic start; never a caller clock.
	 *
	 * @var int|float
	 */
	private $started = 0;

	/** Zero IO. */
	private function __construct() {}

	/**
	 * Fixed zero-argument dormant owner.
	 *
	 * @return self|false
	 */
	public static function create() {
		return 0 === func_num_args() ? new self() : false;
	}

	/**
	 * No result/receipt/handle is published, even on a positive private observation.
	 *
	 * @param string $method Exact POST.
	 * @param mixed  $body Original bounded read-intent form.
	 * @return array Fixed non-authorizing disposition.
	 */
	public function inspect( $method, $body ) {
		if ( 2 === func_num_args() ) {
			$this->observe( $method, $body );
		}
		return array(
			'outcome'             => 'unavailable',
			'source'              => 'closed_native_observation_v1',
			'storage_supported'   => false,
			'dispatch_supported'  => false,
			'execution_supported' => false,
		);
	}

	/**
	 * Observe outside locks, discard all raw data, close the owned connection.
	 * Reflection in the isolated proof observes this real decision, not a true port.
	 *
	 * @param string $method Method.
	 * @param mixed  $body Form.
	 * @return object|false Hashes-only data contract, never admission/resolution.
	 */
	private function observe( $method, $body ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact method bytes; no coercion or normalization.
		if ( $this->active || 'POST' !== $method || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? null ) ) {
			return false;
		}
		$this->protected_reads = 0;
		$f                     = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::parse( $body );
		if ( false === $f || 'receipt_read' !== $f['envelope']['purpose'] || ! $this->window( $f['envelope'] ) ) {
			return false;
		}
		$this->active  = true;
		$this->started = hrtime( true );
		// mysqli warning policy is local to this stack; do not alter mysqli_report().
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Scoped native warning-to-refusal policy, restored in finally; no diagnostic output.
		set_error_handler(
			static function () {
				throw new RuntimeException( 'Native observation unavailable.' );
			}
		);
		try {
			$frame = $this->frame();
			if ( false === $frame || ! $this->profile() ) {
				return false;
			}
			if ( null === $this->scope ) {
				$this->scope = $frame;
			}
			$auth   = $frame['db']->dbh;
			$before = $this->native_idle( $auth );
			$actor  = get_current_user_id();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Pin exact original cookie across both independently renewed core guards; never export it.
			$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
			$e      = $f['envelope'];
			$action = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::action( $e );
			if ( $frame !== $this->scope || false === $before || false === $action || ! $this->guard( $frame, $f['nonce'], $action, $e ) || ! $this->same_request( $frame, $cookie, $actor ) || ! $this->auth_same( $frame, $auth, $before ) || ! $this->budget( $e ) ) {
				return false;
			}
			if ( ! $this->open_reader( $auth, $before ) || ! $this->table_supported( $frame['options'] ) || ! $this->auth_same( $frame, $auth, $before ) || ! $this->budget( $e ) ) {
				return false;
			}
			$session = $this->native_idle( $this->reader );
			$rows    = array();
			foreach ( array( true, false, true, false ) as $safety ) {
				if ( ! $this->budget( $e ) || false === $session || $this->native_idle( $this->reader ) !== $session ) {
					return false;
				}
				$row = $this->raw_row( $frame['options'], $safety );
				if ( false === $row ) {
					return false;
				}
				$rows[] = $row;
			}
			if ( $this->native_idle( $this->reader ) !== $session || ! $this->same_request( $frame, $cookie, $actor ) || ! $this->auth_same( $frame, $auth, $before ) || ! $this->guard( $frame, $f['nonce'], $action, $e ) || ! $this->same_request( $frame, $cookie, $actor ) || ! $this->auth_same( $frame, $auth, $before ) || ! $this->budget( $e ) ) {
				return false;
			}
			$parsed = Alynt_Drime_Backups_Uploader_Serialized_Settings_Parser::parse( $rows[1]['value'] );
			if ( false === $parsed || ( $parsed['value']['site_uuid'] ?? null ) !== $e['site_uuid'] ) {
				return false;
			}
			$observation = Alynt_Drime_Backups_Uploader_Closed_Writer_Observation_Contract::simulate(
				array(
					'blog_id'    => $frame['blog'],
					'prefix'     => $frame['prefix'],
					'database'   => DB_NAME,
					'site_uuid'  => $parsed['value']['site_uuid'],
					'session_id' => hash( 'sha256', 'native_planning_v1:' . $session['thread'] . ':' . $this->started ),
				),
				$rows[0],
				$rows[1],
				$rows[2],
				$rows[3]
			);
			// A positive decision also requires an acknowledged close of our handle.
			if ( ! $this->budget( $e ) || true !== $this->reader->close() ) {
				return false;
			}
			$this->reader = null;
			return $this->budget( $e ) ? $observation : false;
		} catch ( Throwable $error ) {
			return false;
		} finally {
			$owned        = $this->reader;
			$this->reader = null;
			try {
				if ( $owned instanceof mysqli ) {
					$owned->close();
				}
			} catch ( Throwable $error ) {
				// Never repair, reconnect or touch the borrowed authentication session.
				$this->active = false;
			} finally {
				$this->active = false;
				restore_error_handler();
			}
		}
	}

	/**
	 * Original UTC deadline and bounded monotonic work; blocking IO can overrun.
	 *
	 * @param array $e Original envelope.
	 * @return bool
	 */
	private function budget( array $e ) {
		$tick = hrtime( true );
		return $tick >= $this->started && $tick - $this->started < 10000000000 && $this->window( $e );
	}
}
