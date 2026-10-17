<?php
/**
 * Dormant closed-only writer barrier; no authenticated consent or target writes.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Test-loaded schema 6; not a production permission or transaction provider. */
final class Alynt_Drime_Backups_Uploader_Closed_Writer_Barrier_Store extends Alynt_Drime_Backups_Uploader_Schedule_Safety_Store {
	use Alynt_Drime_Backups_Uploader_Cadence_Authority_Validation, Alynt_Drime_Backups_Uploader_Consent_Ledger_Validation, Alynt_Drime_Backups_Uploader_Closed_Consent_Provenance_Validation, Alynt_Drime_Backups_Uploader_Closed_Writer_Barrier_Validation {
		Alynt_Drime_Backups_Uploader_Closed_Writer_Barrier_Validation::valid_state insteadof Alynt_Drime_Backups_Uploader_Cadence_Authority_Validation, Alynt_Drime_Backups_Uploader_Consent_Ledger_Validation, Alynt_Drime_Backups_Uploader_Closed_Consent_Provenance_Validation;
		Alynt_Drime_Backups_Uploader_Closed_Consent_Provenance_Validation::valid_state as private valid_checkpoint;
		Alynt_Drime_Backups_Uploader_Cadence_Authority_Validation::valid_state as private valid_authority_state;
		Alynt_Drime_Backups_Uploader_Consent_Ledger_Validation::valid_state as private valid_fixture_state;
	}
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Barrier_Records;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Barrier_Transitions;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Barrier_Commands;
	use Alynt_Drime_Backups_Uploader_Consent_Ledger_API_Boundary;

	// SOURCE is required only by the unchanged frozen schema-5 validator.
	const SOURCE         = 'closed_migration_fixture_v1';
	const BARRIER_SOURCE = 'closed_writer_fixture_v1';
	const MAX_RECEIPTS   = 50;
	const RECEIPT_BYTES  = 1024;
	const CLOSURE_BYTES  = 4096;

	/**
	 * Fixed existing fixture scope; never generated.
	 *
	 * @var string
	 */
	private $site_uuid;
	/**
	 * Literal test enablement, not authenticated permission.
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * Construct with no reads, writes, hooks or secondary effects.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS $backend In-memory fixture storage.
	 * @param string                                                  $site_uuid Fixed scope.
	 * @param callable                                                $clock UTC fixture clock.
	 * @param bool                                                    $enabled Literal switch, default off.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS $backend, $site_uuid, callable $clock, $enabled = false ) {
		parent::__construct( $backend, $clock );
		$this->site_uuid = $site_uuid;
		$this->enabled   = true === $enabled && $this->valid_uuid( $site_uuid );
	}

	/**
	 * Private model snapshot, never status, support output or admission.
	 *
	 * @return array|WP_Error
	 */
	public function snapshot() {
		if ( ! $this->enabled ) {
			return $this->error( 'disabled' );
		}
		try {
			$s = parent::snapshot();
			return is_wp_error( $s ) ? $this->error( 'unavailable' ) : $s;
		} catch ( Throwable $error ) {
			return $this->error( 'unresolved' );
		}
	}

	/**
	 * Deny all consent/execution commands, without reads or guards.
	 *
	 * @param mixed $command Any input.
	 * @param mixed $guard Never invoked.
	 * @return WP_Error
	 */
	public function command( $command, $guard = null ) {
		unset( $command, $guard );
		return $this->error( 'commands_disabled' );
	}

	/**
	 * Observe segregated fixture receipts only, never return a private record.
	 *
	 * @param string $domain Exact fixture namespace.
	 * @param string $id Command UUID.
	 * @return array|WP_Error
	 */
	public function receipt( $domain, $id ) {
		if ( ! $this->enabled ) {
			return $this->error( 'disabled' );
		}
		if ( ! in_array( $domain, array( self::BARRIER_SOURCE, 'fixture_intent_v1' ), true ) || ! $this->valid_uuid( $id ) ) {
			return $this->error( 'invalid' );
		}
		$s = $this->snapshot();
		if ( is_wp_error( $s ) ) {
			return $s;
		}
		$prior  = $this->writer_receipt( $s, $id );
		$legacy = $this->archived_id( $s, $id );
		if ( ( $legacy && self::BARRIER_SOURCE === $domain ) || ( null !== $prior && 'fixture_intent_v1' === $domain ) ) {
			return $this->error( 'domain_conflict' );
		}
		$outcome = $legacy ? 'observed_superseded' : 'not_found';
		if ( null !== $prior ) {
			$outcome = $prior['result_generation'] === $s['lifecycle']['permission_generation'] ? 'observed_current' : 'observed_superseded';
		}
		return $this->barrier_outcome( $outcome, $domain );
	}

	/**
	 * Find an immutable current or retained writer receipt.
	 *
	 * @param array  $s Validated state.
	 * @param string $id UUID.
	 * @return array|null
	 */
	private function writer_receipt( array $s, $id ) {
		$b = $s['barrier'];
		foreach ( array( 'closure', 'completion' ) as $slot ) {
			if ( null !== $b[ $slot ] && $b[ $slot ]['command']['command_id'] === $id ) {
				return $b[ $slot ];
			}
		}
		return isset( $b['receipts'][ $id ] ) ? $b['receipts'][ $id ] : null;
	}

	/**
	 * Check legacy identity without interpreting fixture provenance as authority.
	 *
	 * @param array  $s Validated state.
	 * @param string $id UUID.
	 * @return bool
	 */
	private function archived_id( array $s, $id ) {
		$l = $s['barrier']['checkpoint']['provenance']['legacy']['ledger'];
		return null !== $l && ( isset( $l['receipts'][ $id ] ) || ( null !== $l['last_receipt'] && $id === $l['last_receipt']['command']['command_id'] ) );
	}

	/**
	 * Bound and check nonregressing UTC without accepting posted clocks.
	 *
	 * @param array $s State.
	 * @return int|WP_Error
	 */
	private function barrier_now( array $s ) {
		$now = call_user_func( $this->clock );
		return is_int( $now ) && $now > 0 && $now <= PHP_INT_MAX - 3600 && $now >= $s['lifecycle']['changed_at'] ? $now : $this->error( 'clock' );
	}

	/**
	 * Fixed redacted non-permission disposition.
	 *
	 * @param string      $outcome Observation or fixture CAS outcome.
	 * @param string|null $source Fixture namespace.
	 * @return array
	 */
	private function barrier_outcome( $outcome, $source = null ) {
		return array(
			'outcome'             => $outcome,
			'source'              => $source ?? self::BARRIER_SOURCE,
			'dispatch_supported'  => false,
			'execution_supported' => false,
		);
	}

	/**
	 * Never leak provider/storage/authorization details or assert failed closure.
	 *
	 * @param string $code Fixed error suffix.
	 * @return WP_Error
	 */
	protected function error( $code ) {
		return new WP_Error( 'schedule_safety_barrier_' . $code, 'Closed writer fixture is unavailable.' );
	}
}
