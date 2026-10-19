<?php
/**
 * Dormant same-owner native observation; never admission DML or a public route.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/trait-registration-observation-barrier-rows.php';

/** Only genuine supported native sessions may qualify a private result. */
trait Alynt_Drime_Backups_Uploader_Registration_Observation_Native_Barrier {
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Barrier_Rows;

	/**
	 * Borrowed auth handle, never configured, closed or exported.
	 *
	 * @var mysqli|null
	 */
	private $barrier_auth;
	/**
	 * Original borrowed physical facts, erased unconditionally.
	 *
	 * @var array|null
	 */
	private $barrier_auth_facts;
	/**
	 * Exclusively owned transaction physical facts.
	 *
	 * @var array|null
	 */
	private $physical;
	/**
	 * Sent-before-I/O acknowledgement state, never a DATA certificate.
	 *
	 * @var string
	 */
	private $phase = 'unopened';
	/**
	 * Exactly one safety-first sweep; independent of preceding origin capture.
	 *
	 * @var int
	 */
	private $barrier_reads = 0;
	/**
	 * Fixed session-status zero-DML checks, not row equality as a write proof.
	 *
	 * @var int
	 */
	private $zero_dml_checks = 0;
	/**
	 * Successful exact metadata checks, before BEGIN and after safety locks MDL.
	 *
	 * @var int
	 */
	private $barrier_metadata_checks = 0;

	/**
	 * Fixed small intent only; origin retained by this owner before this call.
	 *
	 * @param mixed $body Exact small read issuance.
	 * @return array|false Private qualified result, never retry/closure authority.
	 */
	private function observe_native( $body ) {
		if ( $this->busy ) {
			$this->interrupted = true;
			return false;
		}
		if ( $this->observation_attempted || $this->native_read_attempted ) {
			return false;
		}
		$this->observation_attempted = true;
		$this->native_read_attempted = true;
		$this->busy                  = true;
		$outer                       = hrtime( true );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Fail closed on diagnostics; restore original handler in finally.
		set_error_handler(
			static function () {
				throw new RuntimeException( 'Native observation unavailable.' );
			}
		);
		try {
			$i = Alynt_Drime_Backups_Uploader_Registration_Observation_Request::intent( $body );
			if ( 1 !== func_num_args() || ! $this->capture_attempted || false === $i || null === $this->origin || $i['observed_command_id'] !== $this->origin_data['mutation']['command_id'] || ! $this->profile() || ! $this->window( null ) ) {
				return false;
			}
			$core = $this->frame();
			if ( false === $core || $this->installation( $core ) !== $this->origin_data['installation'] ) {
				return false;
			}
			$this->barrier_auth       = $core['db']->dbh;
			$this->barrier_auth_facts = $this->native_idle( $this->barrier_auth );
			if ( false === $this->barrier_auth_facts ) {
				return false;
			}
			$built = $this->build_observation( $body );
			if ( false === $built ) {
				return false;
			}
			$data = $this->origin_data;
			$ids  = array_keys( $this->used );
			if ( ! $this->stage( $data, $body, $built['form'], $this->handoff_clock( $outer ), $ids ) ) {
				return false;
			}
			$this->clear_origin();
			if ( ! $this->renew_observation( $built, $outer ) || ! $this->claim( $data, $body, $built['form'], $this->handoff_clock( $outer ), $ids ) || ! $this->open_writer( $this->barrier_auth, $this->barrier_auth_facts ) || ! $this->barrier_metadata( $core['options'] ) || ! $this->renew_observation( $built, $outer ) ) {
				return false;
			}
			$frame = $this->barrier_capture( $core, $outer, $built['blueprint']['envelope'] );
			if ( false === $frame ) {
				return false;
			}
			$this->close_barrier();
			if ( 'barrier_released' !== $this->phase ) {
				return false;
			}
			$this->phase = 'closed';
			$result      = $this->finish( $frame, $body, $built['form'], $this->handoff_clock( $outer ) );
			if ( ! is_array( $result ) || ! $this->renew_observation( $built, $outer ) ) {
				return false;
			}
			$result['classification']   = 'not_observed_in_candidate' === $result['classification'] ? 'not_observed_at_barrier' : $result['classification'];
			$result['barrier_verified'] = true;
			$result['candidate_only']   = false;
			return $result;
		} catch ( Throwable $error ) {
			return false;
		} finally {
			$this->close_barrier();
			$this->attempted          = true;
			$this->claimed            = true;
			$this->finished           = true;
			$this->staged             = null;
			$this->inflight           = null;
			$this->barrier_auth       = null;
			$this->barrier_auth_facts = null;
			$this->clear_origin();
			$this->close_window();
			restore_error_handler();
		}
	}

	/**
	 * Fixed native-only critical section. No WordPress, parser or auth callbacks.
	 *
	 * @param array $core Pinned installation metadata.
	 * @param int   $outer Original monotonic start before construction.
	 * @param array $e Own fresh typed read query.
	 * @return array|false Captured frame, only after acknowledged release.
	 */
	private function barrier_capture( $core, $outer, $e ) {
		if ( 'idle' !== $this->phase || ! $this->session( false ) || ! $this->barrier_budget( $outer, $e ) || ! $this->barrier_zero_dml() ) {
			return false;
		}
		$this->phase = 'begin_sent';
		if ( true !== $this->reader->query( 'START TRANSACTION READ WRITE' ) || ! $this->session( true ) ) {
			return false;
		}
		$this->phase = 'transaction_owned';
		$rows        = array();
		foreach ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) as $role ) {
			if ( ! $this->barrier_budget( $outer, $e ) || ! $this->session( true ) ) {
				return false;
			}
			$row = $this->barrier_row( $core['options'], $role );
			if ( false === $row || ( 'safety' === $role && ! $this->barrier_metadata( $core['options'] ) ) ) {
				return false;
			}
			$rows[ $role ] = $row;
		}
		if ( 10 !== $this->barrier_reads || 2 !== $this->barrier_metadata_checks || ! $this->session( true ) || ! $this->barrier_budget( $outer, $e ) ) {
			return false;
		}
		$this->phase = 'commit_sent';
		if ( true !== $this->reader->query( 'COMMIT AND NO CHAIN NO RELEASE' ) || ! $this->session( false ) || ! $this->barrier_zero_dml() || ! $this->barrier_budget( $outer, $e ) ) {
			return false;
		}
		$this->phase = 'barrier_released';
		return array(
			'blog_id'  => $core['blog'],
			'prefix'   => $core['prefix'],
			'database' => DB_NAME,
			'rows'     => $rows,
		);
	}

	/**
	 * Original strict budget; native waits can overrun but never renew acceptance.
	 *
	 * @param int   $outer Original start.
	 * @param array $e Own read query, not the historical mutation deadline.
	 * @return bool
	 */
	private function barrier_budget( $outer, $e ) {
		return ! $this->interrupted && self::clock( $this->handoff_clock( $outer ) ) && $this->window( $e );
	}

	/**
	 * Fixed policy executes on the owned native connection, never a callback.
	 *
	 * @param string $table Pinned identifier.
	 * @return bool Successful native inspection, not a caller certificate.
	 */
	private function barrier_metadata( $table ) {
		if ( ! $this->metadata( $table ) ) {
			return false;
		}
		++$this->barrier_metadata_checks;
		return true;
	}
}
