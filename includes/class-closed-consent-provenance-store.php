<?php
/**
 * Dormant closed provenance checkpoint; no authenticated grant or command writes.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Test-loaded schema 5; production and older readers remain unchanged. */
final class Alynt_Drime_Backups_Uploader_Closed_Consent_Provenance_Store extends Alynt_Drime_Backups_Uploader_Schedule_Safety_Store {
	use Alynt_Drime_Backups_Uploader_Cadence_Authority_Validation, Alynt_Drime_Backups_Uploader_Consent_Ledger_Validation, Alynt_Drime_Backups_Uploader_Closed_Consent_Provenance_Validation {
		Alynt_Drime_Backups_Uploader_Closed_Consent_Provenance_Validation::valid_state insteadof Alynt_Drime_Backups_Uploader_Cadence_Authority_Validation, Alynt_Drime_Backups_Uploader_Consent_Ledger_Validation;
		Alynt_Drime_Backups_Uploader_Cadence_Authority_Validation::valid_state as private valid_authority_state;
		Alynt_Drime_Backups_Uploader_Consent_Ledger_Validation::valid_state as private valid_fixture_state;
	}
	use Alynt_Drime_Backups_Uploader_Consent_Ledger_API_Boundary;

	const SOURCE        = 'closed_migration_fixture_v1';
	const MAX_RECEIPTS  = 50;
	const RECEIPT_BYTES = 1024;
	const CLOSURE_BYTES = 4096;

	/**
	 * Fixed scope; not read or generated from site settings.
	 *
	 * @var string
	 */
	private $site_uuid;
	/**
	 * Literal fixture enablement, never authenticated permission.
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * Construct without reads, writes, hooks, migration or identity creation.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS $backend In-memory test backend.
	 * @param string                                                  $site_uuid Fixed fixture scope.
	 * @param callable                                                $clock Fixture UTC clock.
	 * @param bool                                                    $enabled Literal switch.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS $backend, $site_uuid, callable $clock, $enabled = false ) {
		parent::__construct( $backend, $clock );
		$this->site_uuid = $site_uuid;
		$this->enabled   = true === $enabled && $this->valid_uuid( $site_uuid );
	}

	/**
	 * Internal archival snapshot only, never a status or permission projection.
	 *
	 * @return array|WP_Error
	 */
	public function snapshot() {
		if ( ! $this->enabled ) {
			return $this->error( 'disabled' );
		}
		try {
			$result = parent::snapshot();
			return is_wp_error( $result ) ? $this->error( 'unavailable' ) : $result;
		} catch ( Throwable $error ) {
			return $this->error( 'unresolved' );
		}
	}

	/**
	 * Explicit existing-row migration; guard is a fixture seam, not real auth.
	 *
	 * @param string   $binding Original context.
	 * @param int      $expected_generation Original permission epoch.
	 * @param callable $guard Scoped fixture admission check before each read/write.
	 * @return array|WP_Error Internal closed checkpoint, not executable consent.
	 */
	public function prepare_closed( $binding, $expected_generation, $guard ) {
		if ( ! $this->enabled ) {
			return $this->error( 'disabled' );
		}
		if ( ! $this->valid_hash( $binding ) || ! $this->valid_integer( $expected_generation ) || ! is_callable( $guard ) ) {
			return $this->error( 'invalid' );
		}
		try {
			for ( $attempt = 0; $attempt < 3; $attempt++ ) {
				if ( true !== $guard() ) {
					return $this->error( 'denied' );
				}
				$row = $this->backend->read( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION );
				if ( ! is_array( $row ) || ! isset( $row['option_value'], $row['autoload'] ) || ! is_string( $row['option_value'] ) || strlen( $row['option_value'] ) > self::MAX_BYTES || ! in_array( $row['autoload'], array( 'no', 'off', 'auto-off' ), true ) ) {
					return $this->error( 'unavailable' );
				}
				$old = json_decode( $row['option_value'], true, 16 );
				if ( ! is_array( $old ) || ( ! $this->valid_state( $old ) && ! $this->valid_origin( $old ) ) ) {
					return $this->error( 'unavailable' );
				}
				$now = call_user_func( $this->clock );
				if ( ! is_int( $now ) || $now <= 0 || $now > PHP_INT_MAX - 3600 || $now < $old['lifecycle']['changed_at'] ) {
					return $this->error( 'clock' );
				}
				if ( $old['enrollment_binding'] !== $binding || $old['lifecycle']['permission_generation'] !== $expected_generation ) {
					return $this->error( 'stale' );
				}
				if ( 5 === $old['schema_version'] ) {
					return $old; // Rerun observes; it never resets archive, epochs or provenance.
				}
				if ( $this->ledger_unfinished( $old ) || ! $this->headroom( $old, 2 ) ) {
					return $this->error( 'blocked' );
				}
				$state = $this->closed_state( $old, $now );
				$raw   = wp_json_encode( $state );
				if ( ! $this->valid_state( $state ) || ! is_string( $raw ) || strlen( $raw ) > self::MAX_BYTES - self::CLOSURE_BYTES ) {
					return $this->error( 'capacity' );
				}
				if ( true !== $guard() ) {
					return $this->error( 'denied' );
				}
				$write_now = call_user_func( $this->clock );
				if ( ! is_int( $write_now ) || $write_now > PHP_INT_MAX - 3600 || $write_now < $now ) {
					return $this->error( 'clock' );
				}
				if ( $write_now !== $now ) {
					$state['lifecycle']['changed_at']                 = $write_now;
					$state['provenance']['migration']['committed_at'] = $write_now;
					$raw = wp_json_encode( $state );
					if ( ! $this->valid_state( $state ) || ! is_string( $raw ) || strlen( $raw ) > self::MAX_BYTES - self::CLOSURE_BYTES ) {
						return $this->error( 'capacity' );
					}
				}
				$saved = $this->backend->swap( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION, $row['option_value'], $raw );
				if ( is_wp_error( $saved ) || ! is_bool( $saved ) ) {
					return $this->error( 'unresolved' );
				}
				if ( $saved ) {
					return $state;
				}
			}
			return $this->error( 'contention' );
		} catch ( Throwable $error ) {
			return $this->error( 'unresolved' );
		}
	}

	/**
	 * Build only a closed checkpoint while retaining old provenance as untrusted.
	 *
	 * @param array $old Strict source.
	 * @param int   $now Fixture commit time.
	 * @return array
	 */
	private function closed_state( array $old, $now ) {
		$state   = $old;
		$archive = array();
		foreach ( array( 'schema_version', 'generation', 'enrollment_binding', 'schedule_revision', 'lifecycle' ) as $key ) {
			$archive[ $key ] = $old[ $key ];
		}
		$archive['ledger'] = isset( $old['ledger'] ) ? $old['ledger'] : null;
		$archive['digest'] = $this->origin_digest( $old );
		unset( $state['ledger'] );
		$state['schema_version'] = 5;
		++$state['generation'];
		++$state['schedule_revision'];
		++$state['lifecycle']['permission_generation'];
		$state['lifecycle']['consent']    = false;
		$state['lifecycle']['phase']      = 'disabled';
		$state['lifecycle']['changed_at'] = $now;
		$state['provenance']              = array(
			'site_uuid'              => $this->site_uuid,
			'source'                 => self::SOURCE,
			'authenticated_grant'    => null,
			'authenticated_receipts' => array(),
			'legacy'                 => $archive,
			'migration'              => array(
				'expected_generation' => $old['lifecycle']['permission_generation'],
				'result_generation'   => $state['lifecycle']['permission_generation'],
				'committed_at'        => $now,
			),
		);
		return $state;
	}

	/**
	 * Observe only fixture namespaces, never promote legacy command identities.
	 *
	 * @param string $domain Fixture namespace.
	 * @param string $id Command identity.
	 * @return array|WP_Error Fixed non-permission observation.
	 */
	public function receipt( $domain, $id ) {
		if ( ! $this->enabled ) {
			return $this->error( 'disabled' );
		}
		if ( ! in_array( $domain, array( 'fixture_intent_v1', 'fixture_request_v1' ), true ) || ! $this->valid_uuid( $id ) ) {
			return $this->error( 'invalid' );
		}
		$s = $this->snapshot();
		if ( is_wp_error( $s ) ) {
			return $s;
		}
		$l     = $s['provenance']['legacy']['ledger'];
		$found = null !== $l && ( ( null !== $l['last_receipt'] && $id === $l['last_receipt']['command']['command_id'] ) || isset( $l['receipts'][ $id ] ) );
		if ( $found && 'fixture_intent_v1' !== $domain ) {
			return $this->error( 'domain_conflict' );
		}
		return array(
			'outcome'             => $found ? 'observed_superseded' : 'not_found',
			'source'              => $domain,
			'dispatch_supported'  => false,
			'execution_supported' => false,
		);
	}

	/**
	 * Reject ALL request commands without reads or invoking an admission guard.
	 *
	 * @param mixed $command Any request or synthetic command.
	 * @param mixed $guard Ignored fixture callback.
	 * @return WP_Error
	 */
	public function command( $command, $guard = null ) {
		unset( $command, $guard );
		return $this->error( 'commands_disabled' );
	}

	/**
	 * Redact provider/storage details; failed closure is never reported disabled.
	 *
	 * @param string $code Fixed suffix.
	 * @return WP_Error
	 */
	protected function error( $code ) {
		return new WP_Error( 'schedule_safety_provenance_' . $code, 'Schedule safety operation is unavailable.' );
	}
}
