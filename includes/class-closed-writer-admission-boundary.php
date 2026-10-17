<?php
/**
 * Default-off fixture request coordinator. Never a production auth factory.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Test seams simulate closed admission only; no real authenticated receipts. */
final class Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Boundary {
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Request;

	/**
	 * Fixed fixture ports, site, identity, switch and monotonic clock.
	 *
	 * @var mixed
	 */
	private $ports;
	/**
	 * Fixed existing UUID.
	 *
	 * @var string
	 */
	private $site;
	/**
	 * Simulated transaction identity.
	 *
	 * @var object
	 */
	private $connection;
	/**
	 * Literal test-only switch.
	 *
	 * @var bool
	 */
	private $enabled;
	/**
	 * Last observed nonregressing fixture clock.
	 *
	 * @var int
	 */
	private $last_clock = 0;

	/**
	 * No IO/hooks during construction. Literal enablement is not permission.
	 *
	 * @param array  $ports In-memory test seams only.
	 * @param string $site Fixed existing UUID.
	 * @param object $connection Fixed simulated transaction identity.
	 * @param bool   $enabled Default off.
	 */
	public function __construct( array $ports, $site, $connection, $enabled = false ) {
		$this->ports      = $ports;
		$this->site       = $site;
		$this->connection = $connection;
		$this->enabled    = true === $enabled && is_object( $connection ) && Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::uuid( $site )
			&& Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::keys( $ports, array( 'authorize', 'verify', 'nonce', 'clock', 'uuid', 'read', 'compare', 'transaction' ) );
		foreach ( $ports as $port ) {
			$this->enabled = $this->enabled && is_callable( $port );
		}
	}

	/**
	 * Consume exact original fixture intent. Never accept a finish or hash sink.
	 *
	 * @param string $method POST only.
	 * @param mixed  $body Original scalar-string command form.
	 * @return array|WP_Error Redacted observation, not physical target success.
	 */
	public function consume( $method, $body ) {
		if ( ! $this->enabled || 'POST' !== $method ) {
			return $this->error();
		}
		try {
			$f = Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::parse( $body );
			if ( false === $f || $f['envelope']['site_uuid'] !== $this->site || ! $this->guard( $f['envelope'], $f['nonce'] ) ) {
				return $this->error();
			}
			return $this->consume_verified( $f['envelope'], $f['nonce'] );
		} catch ( Throwable $error ) {
			return $this->error();
		}
	}

	/**
	 * Three actual upgrade/begin writes, not three guard invocations.
	 * Abandon has exactly one simulated original-epoch transaction attempt.
	 *
	 * @param array  $e Original command, never rewritten on conflict.
	 * @param string $nonce Original nonce.
	 * @return array|WP_Error
	 */
	private function consume_verified( array $e, $nonce ) {
		$reserved = null;
		$limit    = 'abandon_closed' === $e['purpose'] ? 1 : 3;
		for ( $attempt = 0; $attempt < $limit; ++$attempt ) {
			if ( ! $this->guard( $e, $nonce ) ) {
				return $this->error();
			}
			$row = $this->snapshot();
			if ( false === $row || ! $this->guard( $e, $nonce ) ) {
				return $this->error();
			}
			$s = $row['state'];
			if ( 'receipt_read' === $e['purpose'] ) {
				return $this->respond( $e, $nonce, $s, Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Model::lookup( $s, $e['observed_command_id'] ) );
			}
			$prior = Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Model::observe( $s, $e );
			if ( 'not_found' !== $prior ) {
				return $this->respond( $e, $nonce, $s, $prior );
			}
			$c = Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::context( $s, $row['target'], $this->site );
			if ( false === $c || ! $this->guard( $e, $nonce ) ) {
				return $this->error();
			}
			if ( 'begin_disable' === $e['purpose'] && true === $c['enabled'] && null === $reserved ) {
				$reserved = ( $this->ports['uuid'] )();
			}
			$next = Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Model::propose( $s, $e, $c, $this->now(), $reserved );
			if ( false === $next ) {
				return $this->error();
			}
			if ( $next === $s ) {
				return $this->respond( $e, $nonce, $s, 'observed_noop' );
			}
			if ( ! $this->guard( $e, $nonce ) || $this->last_clock !== $next['lifecycle']['changed_at'] ) {
				return $this->error();
			}
			$ack = ( $this->ports['compare'] )( $row, $next );
			if ( true === $ack ) {
				return $this->confirmed( $e, $nonce, $next, $row['target'] );
			}
			if ( false !== $ack ) {
				return $this->error();
			}
		}
		// Final lost acknowledgement may already have committed. Observe, never replay.
		return $this->reconcile_original( $e, $nonce );
	}

	/**
	 * Read back exact strict candidate; truthy generic sink results are not proof.
	 *
	 * @param array  $e Original command.
	 * @param string $nonce Nonce.
	 * @param array  $next Proposed closed state.
	 * @param string $target Original bytes.
	 * @return array|WP_Error
	 */
	private function confirmed( array $e, $nonce, array $next, $target ) {
		if ( ! $this->guard( $e, $nonce ) ) {
			return $this->error();
		}
		$row = $this->snapshot();
		if ( false === $row || $row['state'] !== $next || $row['target'] !== $target || ! $this->guard( $e, $nonce ) ) {
			return $this->error();
		}
		if ( 'begin_disable' !== $e['purpose'] ) {
			return $this->respond( $e, $nonce, $next, 'observed_current' );
		}
		// Last fresh authentication before entry; no auth hooks under simulated locks.
		if ( ! $this->guard( $e, $nonce ) ) {
			return $this->error();
		}
		$pair = Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Continuation::within_fixture( $e, $next, $this->connection, $this->ports['transaction'] );
		if ( ! $this->guard( $e, $nonce ) ) {
			return $this->error();
		}
		$row = $this->snapshot();
		if ( false === $row || false === $pair || $row['state'] !== $pair['state'] || $row['target'] !== $pair['target'] ) {
			return $this->guard( $e, $nonce ) ? $this->outcome( 'closure_observed', 'unresolved' ) : $this->error();
		}
		return $this->respond( $e, $nonce, $row['state'], 'observed_superseded' );
	}

	/**
	 * Fresh guarded final observation after an exhausted write budget.
	 *
	 * @param array  $e Original intent.
	 * @param string $nonce Nonce.
	 * @return array|WP_Error
	 */
	private function reconcile_original( array $e, $nonce ) {
		if ( ! $this->guard( $e, $nonce ) ) {
			return $this->error();
		}
		$row = $this->snapshot();
		if ( false === $row ) {
			return $this->error();
		}
		$prior = Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Model::observe( $row['state'], $e );
		return $this->respond( $e, $nonce, $row['state'], 'not_found' === $prior ? 'unresolved' : $prior );
	}

	/**
	 * Recheck after all private observations; never export private state.
	 *
	 * @param array  $e Intent.
	 * @param string $nonce Nonce.
	 * @param array  $s Strict state.
	 * @param string $outcome Fixed disposition.
	 * @return array|WP_Error
	 */
	private function respond( array $e, $nonce, array $s, $outcome ) {
		$secondary = 'not_attempted';
		if ( 7 === $s['schema_version'] ) {
			$id  = $e['observed_command_id'] ?? $e['command_id'];
			$w   = $s['writer'];
			$all = array_merge( array_values( $w['fixture_receipts'] ), array( $w['completion'] ) );
			foreach ( $all as $r ) {
				if ( null !== $r && in_array( $r['purpose'], array( 'complete_fixture', 'abandon_closed' ), true ) && ( $r['command']['operation_id'] === $id || $r['command']['command_id'] === $id ) ) {
					$secondary = 'complete_fixture' === $r['purpose'] ? 'simulated_committed' : 'abandoned';
				}
			}
			if ( null !== $w['pending'] && $w['pending']['operation_id'] === $id ) {
				$secondary = 'unresolved';
			}
		}
		return $this->guard( $e, $nonce ) ? $this->outcome( $outcome, $secondary ) : $this->error();
	}

	/**
	 * Bounded fixture-only dispositions never certify physical target effects.
	 *
	 * @param string $outcome Closure/observation disposition.
	 * @param string $secondary Distinct secondary disposition.
	 * @return array
	 */
	private function outcome( $outcome, $secondary ) {
		return array(
			'outcome'                        => $outcome,
			'secondary_outcome'              => $secondary,
			'source'                         => 'closed_writer_admission_fixture_v1',
			'physical_target_effects_proven' => false,
			'dispatch_supported'             => false,
			'execution_supported'            => false,
		);
	}

	/**
	 * No provider/auth/storage data in errors.
	 *
	 * @return WP_Error
	 */
	private function error() {
		return new WP_Error( 'closed_writer_admission_unavailable', 'Closed admission fixture is unavailable.' );
	}
}
