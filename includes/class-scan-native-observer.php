<?php
/**
 * Dormant fixed native read-only scan observation; no effect or grant execution.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-real-closed-writer-native-observer.php';
require_once __DIR__ . '/class-scan-observation-request.php';
require_once __DIR__ . '/class-scan-observation-data.php';
require_once __DIR__ . '/trait-scan-observation-rows.php';

/** No injected context/provider, public positive decision or transferable handle. */
final class Alynt_Drime_Backups_Uploader_Scan_Native_Observer {
	use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Core_Guard;
	use Alynt_Drime_Backups_Uploader_Native_Observer_Session;
	use Alynt_Drime_Backups_Uploader_Native_Observer_Rows;
	use Alynt_Drime_Backups_Uploader_Scan_Observation_Rows;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;

	/**
	 * Non-reentrant stack lifetime.
	 *
	 * @var bool
	 */
	private $active = false;
	/**
	 * Real UTC observation only.
	 *
	 * @var int
	 */
	private $last_clock = 0;
	/**
	 * Pinned installation scope.
	 *
	 * @var array|null
	 */
	private $scope;
	/**
	 * Exclusively owned read-only connection.
	 *
	 * @var mysqli|null
	 */
	private $reader;
	/**
	 * Fixed twenty projected-row read ceiling.
	 *
	 * @var int
	 */
	private $protected_reads = 0;
	/**
	 * Original monotonic start.
	 *
	 * @var int|float
	 */
	private $started = 0;

	/** Zero IO. */
	private function __construct() {}

	/**
	 * No caller-selected provider, scope or flag.
	 *
	 * @return self|false
	 */
	public static function create() {
		return 0 === func_num_args() ? new self() : false;
	}

	/**
	 * Public result stays unavailable, even after positive private observation.
	 *
	 * @param string $method Exact POST.
	 * @param mixed  $body Original read-only form.
	 * @return array No data, receipt or execution capability.
	 */
	public function inspect( $method, $body ) {
		if ( 2 === func_num_args() ) {
			$this->observe( $method, $body );
		}
		return array(
			'outcome'             => 'unavailable',
			'source'              => 'closed_scan_native_observation_v1',
			'storage_supported'   => false,
			'dispatch_supported'  => false,
			'execution_supported' => false,
		);
	}

	/**
	 * Genuine guarded two-sweep observation, NOT atomic snapshot or admission.
	 * Reflection in the owned proof observes the actual private decision.
	 *
	 * @param string $method Exact method.
	 * @param mixed  $body Read intent.
	 * @return bool Private closed-data observation only.
	 */
	private function observe( $method, $body ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact original method; never normalized.
		if ( $this->active || 'POST' !== $method || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? null ) ) {
			return false;
		}
		$this->protected_reads = 0;
		$f                     = Alynt_Drime_Backups_Uploader_Scan_Observation_Request::parse( $body );
		if ( false === $f || ! $this->window( $f['envelope'] ) ) {
			return false;
		}
		$this->active  = true;
		$this->started = hrtime( true );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Stack-local warning-to-refusal policy, restored unconditionally; no logging.
		set_error_handler(
			static function () {
				throw new RuntimeException( 'Scan observation unavailable.' );
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
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Retain exact original private cookie across renewed guards; never output.
			$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
			$e      = $f['envelope'];
			if ( $frame !== $this->scope || false === $before || ! $this->guard( $frame, $f['nonce'], $f['action'], $e ) || ! $this->same_request( $frame, $cookie, $actor ) || ! $this->auth_same( $frame, $auth, $before ) || ! $this->budget( $e ) ) {
				return false;
			}
			if ( ! $this->open_reader( $auth, $before ) || ! $this->table_supported( $frame['options'] ) || ! $this->auth_same( $frame, $auth, $before ) || ! $this->budget( $e ) ) {
				return false;
			}
			$session = $this->native_idle( $this->reader );
			$sweeps  = array();
			for ( $pass = 0; $pass < 2; ++$pass ) {
				$rows = array();
				foreach ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) as $role ) {
					if ( false === $session || ! $this->budget( $e ) || $this->native_idle( $this->reader ) !== $session || '1' !== $session['tx_ro'] ) {
						return false;
					}
					$row = $this->scan_row( $frame['options'], $role );
					if ( false === $row ) {
						return false;
					}
					$rows[ $role ] = $row;
				}
				$sweeps[] = array(
					'blog_id'  => $frame['blog'],
					'prefix'   => $frame['prefix'],
					'database' => DB_NAME,
					'rows'     => $rows,
				);
			}
			if ( $this->native_idle( $this->reader ) !== $session || ! $this->same_request( $frame, $cookie, $actor ) || ! $this->auth_same( $frame, $auth, $before ) || ! $this->guard( $frame, $f['nonce'], $f['action'], $e ) || ! $this->same_request( $frame, $cookie, $actor ) || ! $this->auth_same( $frame, $auth, $before ) || ! $this->budget( $e ) ) {
				return false;
			}
			$valid = Alynt_Drime_Backups_Uploader_Scan_Observation_Data::consistent( $sweeps[0], $sweeps[1], $e['site_uuid'] );
			if ( ! $this->budget( $e ) || true !== $this->reader->close() ) {
				return false;
			}
			$this->reader = null;
			return $valid && $this->budget( $e );
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
				// No reconnect, repair or operations on the borrowed auth handle.
				$this->active = false;
			} finally {
				$this->active = false;
				restore_error_handler();
			}
		}
	}

	/**
	 * Original deadline and monotonic budget; blocking IO can overrun this bound.
	 *
	 * @param array $e Original typed intent.
	 * @return bool
	 */
	private function budget( array $e ) {
		$tick = hrtime( true );
		return $tick >= $this->started && $tick - $this->started < 10000000000 && $this->window( $e );
	}
}
