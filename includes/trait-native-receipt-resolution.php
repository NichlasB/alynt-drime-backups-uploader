<?php
/**
 * Dormant, separately authenticated receipt observation with an in-flight barrier.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No writes, replay or absence-based no-effect conclusion. */
trait Alynt_Drime_Backups_Uploader_Native_Receipt_Resolution {
	/**
	 * Fresh receipt purpose/window/nonce, never the expired mutation envelope.
	 *
	 * @param string $method Exact method.
	 * @param mixed  $body Original receipt form.
	 * @return array|false Private non-authorizing observation, no bytes/identities.
	 */
	private function resolve( $method, $body ) {
		if ( $this->used ) {
			return false;
		}
		$this->used    = true;
		$this->started = hrtime( true );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact original method only.
		if ( 'POST' !== $method || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? null ) ) {
			return false;
		}
		$f = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::parse( $body );
		if ( false === $f || 'receipt_read' !== $f['envelope']['purpose'] || ! $this->budget( $f['envelope'] ) ) {
			return false;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Stack-local, restored unconditionally, private errors withheld.
		set_error_handler(
			static function () {
				throw new RuntimeException( 'Native receipt unavailable.' );
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
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Pin only; never export cookie.
			$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
			$e      = $f['envelope'];
			$action = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::action( $e );
			$c      = compact( 'frame', 'auth', 'before', 'actor', 'cookie', 'e', 'f', 'action' );
			if ( false === $before || false === $action || ! $this->continuation_auth( $c ) || ! $this->open_writer( $auth, $before ) || ! $this->metadata( $frame['options'] ) ) {
				return false;
			}
			if ( ! $this->continuation_auth( $c ) ) {
				return false;
			}
			$rows = $this->resolution_barrier( $frame, $e );
			if ( false === $rows || ! $this->continuation_auth( $c ) ) {
				return false;
			}
			// Validators can reach WordPress hooks: only after locks are released.
			$result = $this->receipt_fact( $rows[0], $rows[1], $e );
			if ( false === $result || ! $this->continuation_auth( $c ) ) {
				return false;
			}
			$this->finish();
			return 'committed' === $this->phase && $this->budget( $e ) ? $result : false;
		} catch ( Throwable $error ) {
			return false;
		} finally {
			$this->finish();
			restore_error_handler();
		}
	}

	/**
	 * Both locking reads must precede interpretation, including absence.
	 *
	 * @param array $frame Pinned table.
	 * @param array $e Fresh read intent.
	 * @return array|false Exact private rows, never authority or a public response.
	 */
	private function resolution_barrier( array $frame, array $e ) {
		if ( 'idle' !== $this->phase || ! $this->session( false ) || ! $this->budget( $e ) ) {
			return false;
		}
		$this->phase = 'begin_sent';
		if ( true !== $this->reader->query( 'START TRANSACTION READ WRITE' ) || ! $this->session( true ) ) {
			return false;
		}
		$this->phase = 'transaction_owned';
		$a           = $this->row( $frame['options'], true, true );
		if ( false === $a || ! $this->metadata( $frame['options'] ) ) {
			return false;
		}
		$b = $this->row( $frame['options'], false, true );
		if ( false === $b || ! $this->session( true ) || ! $this->budget( $e ) ) {
			return false;
		}
		$this->phase = 'commit_sent';
		if ( true !== $this->reader->query( 'COMMIT AND NO CHAIN NO RELEASE' ) || ! $this->session( false ) ) {
			return false;
		}
		$this->phase = 'committed';
		return array( $a, $b );
	}

	/**
	 * Validate the full retained real chain outside locks; current bytes separate.
	 * Absence, pending and domain conflict never imply rollback/no effect.
	 *
	 * @param array $a Barrier safety tuple.
	 * @param array $b Barrier target tuple.
	 * @param array $e Read intent.
	 * @return array|false Non-authorizing, support-safe private facts only.
	 */
	private function receipt_fact( array $a, array $b, array $e ) {
		$s = Alynt_Drime_Backups_Uploader_Closed_Writer_Json_Contract::decode( $a['value'] );
		if ( false === $s || 8 !== $s['schema_version'] || ! Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::supported( $s, $e['site_uuid'] ) ) {
			return false;
		}
		$t = Alynt_Drime_Backups_Uploader_Serialized_Settings_Target::inspect( $b['value'], $e['site_uuid'], $b['autoload'] );
		if ( false === $t ) {
			return false;
		}
		$status = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::lookup( $s, $e['observed_command_id'], $e['site_uuid'] );
		$result = array(
			'historical'          => 'unresolved',
			'target'              => 'unknown',
			'admission_proven'    => false,
			'execution_supported' => false,
		);
		if ( 'domain_conflict' === $status ) {
			$result['historical'] = 'domain_conflict';
			return $result;
		}
		$records = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::records( $s );
		foreach ( $records as $record ) {
			if ( $e['observed_command_id'] === $record['command']['command_id'] && ( 'begin_disable' !== $record['purpose'] || Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::AUTH !== $record['domain'] ) ) {
				$result['historical'] = 'domain_conflict';
				return $result;
			}
		}
		foreach ( $records as $begin ) {
			if ( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::AUTH !== $begin['domain'] || 'begin_disable' !== $begin['purpose'] || $e['observed_command_id'] !== $begin['command']['command_id'] ) {
				continue;
			}
			$result['historical'] = 'begin_only_unresolved';
			foreach ( $records as $end ) {
				if ( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::INTERNAL === $end['domain'] && 'complete_disable' === $end['purpose'] && $begin['completion_id'] === $end['command']['command_id'] && $begin['command']['command_id'] === $end['command']['operation_id'] ) {
					$result['historical'] = 'completed';
					$result['target']     = $t['before_hash'] === $end['command']['observed_hash'] ? 'matching_bytes' : 'superseded_bytes';
					return $result;
				}
			}
		}
		return $result;
	}
}
