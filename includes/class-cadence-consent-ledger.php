<?php
/**
 * Dormant fixture-only consent ledger; no authenticated permission or execution.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Test-loaded successor model, deliberately incompatible with production readers. */
final class Alynt_Drime_Backups_Uploader_Cadence_Consent_Ledger extends Alynt_Drime_Backups_Uploader_Schedule_Safety_Store {
	use Alynt_Drime_Backups_Uploader_Cadence_Authority_Validation, Alynt_Drime_Backups_Uploader_Consent_Ledger_Validation {
		Alynt_Drime_Backups_Uploader_Consent_Ledger_Validation::valid_state insteadof Alynt_Drime_Backups_Uploader_Cadence_Authority_Validation;
		Alynt_Drime_Backups_Uploader_Cadence_Authority_Validation::valid_state as private valid_authority_state;
	}
	use Alynt_Drime_Backups_Uploader_Consent_Ledger_Commands;
	use Alynt_Drime_Backups_Uploader_Consent_Ledger_Migration;
	use Alynt_Drime_Backups_Uploader_Consent_Ledger_API_Boundary;

	const MAX_RECEIPTS  = 50;
	const RECEIPT_BYTES = 1024;
	const CLOSURE_BYTES = 4096;

	/**
	 * Explicit construction scope, never discovered or generated.
	 *
	 * @var string
	 */
	private $site_uuid;
	/**
	 * Literal fixture switch, not authenticated authorization.
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * Construction performs no reads, writes, hooks or identity creation.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS $backend CAS backend.
	 * @param string                                                  $site_uuid Explicit scope.
	 * @param callable                                                $clock UTC fixture clock.
	 * @param bool                                                    $enabled Literal fixture switch.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS $backend, $site_uuid, callable $clock, $enabled = false ) {
		parent::__construct( $backend, $clock );
		$this->site_uuid = $site_uuid;
		$this->enabled   = true === $enabled && $this->valid_uuid( $site_uuid );
	}

	/**
	 * Internal fixture snapshot only; never an execution/status projection.
	 *
	 * @return array|WP_Error
	 */
	public function snapshot() {
		if ( ! $this->enabled ) {
			return $this->error( 'ledger_disabled' );
		}
		try {
			$result = parent::snapshot();
			return is_wp_error( $result ) ? $this->error( 'ledger_unavailable' ) : $result;
		} catch ( Throwable $error ) {
			return $this->error( 'ledger_unresolved' );
		}
	}

	/**
	 * Apply one synthetic command. Caller input is not administrator authorization.
	 *
	 * @param mixed $command Exact fixture intent, never a request body.
	 * @return array|WP_Error Fixed non-executing outcome; failure may be ambiguous.
	 */
	public function command( $command ) {
		if ( ! $this->enabled ) {
			return $this->error( 'ledger_disabled' );
		}
		if ( ! $this->valid_command( $command ) || $command['site_uuid'] !== $this->site_uuid ) {
			return $this->error( 'ledger_invalid_command' );
		}
		try {
			for ( $attempt = 0; $attempt < 3; $attempt++ ) {
				$loaded = $this->load();
				if ( is_wp_error( $loaded ) ) {
					return $this->error( 'ledger_unavailable' );
				}
				$state = $loaded['state'];
				$now   = $this->ledger_now( $state );
				if ( is_wp_error( $now ) ) {
					return $now;
				}
				$digest = $this->command_digest( $command );
				$prior  = $this->prior_receipt( $state, $command['command_id'] );
				if ( null !== $prior ) {
					return hash_equals( $prior['request_hash'], $digest )
						? $this->outcome( $prior['result_generation'] === $state['lifecycle']['permission_generation'] ? 'observed_current' : 'observed_superseded' )
						: $this->error( 'ledger_conflict' );
				}
				if ( $command['enrollment_binding'] !== $state['enrollment_binding'] || $command['expected_generation'] !== $state['lifecycle']['permission_generation'] ) {
					return $this->error( 'ledger_stale' );
				}
				$result = $this->change_command( $state, $command, $digest, $now );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				$raw = wp_json_encode( $state );
				if ( ! $this->valid_state( $state ) || ! is_string( $raw ) || strlen( $raw ) > self::MAX_BYTES ) {
					return $this->error( 'ledger_capacity' );
				}
				$saved = $this->backend->swap( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION, $loaded['raw'], $raw );
				if ( is_wp_error( $saved ) || ! is_bool( $saved ) ) {
					return $this->error( 'ledger_unresolved' );
				}
				if ( $saved ) {
					return $this->outcome( 'committed' );
				}
			}
			return $this->error( 'ledger_contention' );
		} catch ( Throwable $error ) {
			return $this->error( 'ledger_unresolved' );
		}
	}

	/**
	 * Validate current UTC before arithmetic and against persisted transition time.
	 *
	 * @param array $state Model.
	 * @return int|WP_Error
	 */
	private function ledger_now( array $state ) {
		$now = call_user_func( $this->clock );
		return is_int( $now ) && $now > 0 && $now <= PHP_INT_MAX - 3600 && $now >= $state['lifecycle']['changed_at'] ? $now : $this->error( 'ledger_clock' );
	}

	/**
	 * Check all monotonic counters before increment, reserving closure headroom.
	 *
	 * @param array $state Model.
	 * @param int   $steps Required increments.
	 * @return bool
	 */
	private function headroom( array $state, $steps ) {
		return max( $state['generation'], $state['schedule_revision'], $state['lifecycle']['permission_generation'] ) <= PHP_INT_MAX - 1 - $steps;
	}

	/**
	 * Return no permission, owner, context, receipt or raw input.
	 *
	 * @param string $outcome Fixed result.
	 * @return array
	 */
	private function outcome( $outcome ) {
		return array(
			'outcome'             => $outcome,
			'dispatch_supported'  => false,
			'execution_supported' => false,
		);
	}
}
