<?php
/**
 * Dormant guarded scan-callback boundary; no production activation or rollback.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Executes one explicitly injected callback only after a durable single-use start. */
class Alynt_Drime_Backups_Uploader_Cadence_Authority_Scan_Dispatcher {
	/**
	 * Strict model store.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Cadence_Authority_Store
	 */
	private $store;
	/**
	 * Trusted local policy reader, not request/status evidence.
	 *
	 * @var callable
	 */
	private $context_reader;
	/**
	 * Explicit construction gate; default false, never set by plugin loading.
	 *
	 * @var bool
	 */
	private $enabled;
	/**
	 * UTC seconds clock.
	 *
	 * @var callable
	 */
	private $clock;
	/**
	 * Internal UUID issuer.
	 *
	 * @var callable
	 */
	private $owner_factory;

	/**
	 * No hooks, option creation, migration or activation setting.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Cadence_Authority_Store $store Model store.
	 * @param callable                                             $context_reader Trusted local binding/scan-permission reader.
	 * @param bool                                                 $enabled Explicit fixture/future factory gate, default false.
	 * @param callable|null                                        $clock UTC clock.
	 * @param callable|null                                        $owner_factory Internal UUID issuer.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Cadence_Authority_Store $store, callable $context_reader, $enabled = false, $clock = null, $owner_factory = null ) {
		$this->store          = $store;
		$this->context_reader = $context_reader;
		$this->enabled        = true === $enabled;
		$this->clock          = null === $clock ? 'time' : $clock;
		$this->owner_factory  = null === $owner_factory ? 'wp_generate_uuid4' : $owner_factory;
	}

	/**
	 * Run one local callback. Never resumes observed ownership or returns raw results.
	 *
	 * @param callable $scan Trusted scan-and-queue callback, never a request argument.
	 * @return array|WP_Error
	 */
	public function run( callable $scan ) {
		if ( ! $this->enabled ) {
			return $this->error( 'disabled' );
		}
		$started = false;
		$binding = '';
		$owner   = '';
		$hash    = '';
		try {
			$context = $this->context();
			$state   = $this->store->snapshot();
			if ( is_wp_error( $context ) || is_wp_error( $state ) ) {
				return $this->error( 'unavailable' );
			}
			$binding = $context['binding'];
			$owner   = call_user_func( $this->owner_factory );
			$fp      = $this->store->authority_fingerprint( $state );
			$hash    = hash(
				'sha256',
				wp_json_encode(
					array(
						'kind'        => 'authority_scan_v1',
						'binding'     => $binding,
						'owner'       => $owner,
						'revision'    => $state['schedule_revision'],
						'fingerprint' => $fp,
					)
				)
			);
			$claim   = $this->store->claim_dispatch( $binding, $state['schedule_revision'], $fp, $owner, $hash );
			if ( is_wp_error( $claim ) || ! isset( $claim['observed'] ) || false !== $claim['observed'] ) {
				return $this->error( 'claim_blocked' );
			}
			if ( $context !== $this->context() ) {
				return $this->error( 'context_changed' ); // Retain the unstarted claim for explicit inspection.
			}
			$start = $this->store->start_dispatch( $binding, $owner, $hash );
			if ( ! is_array( $start ) || ! isset( $start['phase'] ) || 'started' !== $start['phase'] ) {
				return $this->error( 'start_blocked' );
			}
			$started = true;
			if ( ! $this->owned_context( $context, $owner, $hash, $fp ) ) {
				return $this->recovery( $binding, $owner, $hash );
			}
			$result = $scan();
			if ( ! $this->successful_scan( $result ) || ! $this->owned_context( $context, $owner, $hash, $fp ) ) {
				return $this->recovery( $binding, $owner, $hash );
			}
			$completed = $this->store->complete_dispatch( $binding, $owner, $hash );
			if ( ! is_array( $completed ) || ! isset( $completed['phase'] ) || 'completed' !== $completed['phase'] ) {
				return $this->recovery( $binding, $owner, $hash );
			}
			return array(
				'outcome'          => 'completed',
				'callback_invoked' => true,
			);
		} catch ( Throwable $failure ) {
			// Never expose callback exceptions, paths, queue records or secret-bearing results.
			return $started ? $this->recovery( $binding, $owner, $hash ) : $this->error( 'internal_failure' );
		}
	}

	/**
	 * Require a current local binding and explicit boolean scan permission.
	 *
	 * @return array|WP_Error
	 */
	private function context() {
		$value = call_user_func( $this->context_reader );
		return is_array( $value ) && 2 === count( $value ) && isset( $value['binding'], $value['scan_enabled'] )
			&& is_string( $value['binding'] ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $value['binding'] ) && true === $value['scan_enabled']
			? $value : $this->error( 'policy_unavailable' );
	}

	/**
	 * Recheck policy and persisted ownership immediately around the callback.
	 *
	 * @param array  $context Initial policy observation.
	 * @param string $owner Dispatch owner.
	 * @param string $hash Dispatch hash.
	 * @param string $fingerprint Observed authority fingerprint.
	 * @return bool
	 */
	private function owned_context( array $context, $owner, $hash, $fingerprint ) {
		if ( $context !== $this->context() ) {
			return false;
		}
		$state = $this->store->snapshot();
		$now   = call_user_func( $this->clock );
		if ( is_wp_error( $state ) || ! is_int( $now ) || $now <= 0 || $now > PHP_INT_MAX - 3600 || null === $state['dispatch'] ) {
			return false;
		}
		$d = $state['dispatch'];
		if ( ! $state['authority']['enabled'] || $state['enrollment_binding'] !== $context['binding'] || $fingerprint !== $this->store->authority_fingerprint( $state )
			|| 'started' !== $d['phase'] || $d['owner'] !== $owner || $d['request_hash'] !== $hash || $d['enrollment_binding'] !== $context['binding']
			|| $d['schedule_revision'] !== $state['schedule_revision'] || $d['due_at'] !== $state['authority']['next_due_at'] || $now < $d['claimed_at'] || $now >= $d['lease_deadline'] ) {
			return false;
		}
		foreach ( $state['claims'] as $record ) {
			if ( in_array( $record['phase'], array( 'write_started', 'recovery_required' ), true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Require the existing scan-and-queue success shape without exporting it.
	 *
	 * @param mixed $result Scanner result.
	 * @return bool
	 */
	private function successful_scan( $result ) {
		return is_array( $result ) && isset( $result['errors'], $result['candidates'], $result['queued'] )
			&& is_array( $result['errors'] ) && empty( $result['errors'] ) && is_array( $result['candidates'] )
			&& is_int( $result['queued'] ) && $result['queued'] >= 0 && $result['queued'] <= count( $result['candidates'] );
	}

	/**
	 * Protect uncertain work; failed marking must never free the started owner.
	 *
	 * @param string $binding Enrollment binding.
	 * @param string $owner Owner.
	 * @param string $hash Dispatch hash.
	 * @return WP_Error
	 */
	private function recovery( $binding, $owner, $hash ) {
		try {
			$result = $this->store->require_dispatch_recovery( $binding, $owner, $hash );
			return $this->error( is_wp_error( $result ) ? 'recovery_unconfirmed' : 'recovery_required' );
		} catch ( Throwable $failure ) {
			return $this->error( 'recovery_unconfirmed' );
		}
	}

	/**
	 * Return only stable support-safe errors.
	 *
	 * @param string $code Failure suffix.
	 * @return WP_Error
	 */
	private function error( $code ) {
		return new WP_Error( 'authority_scan_' . $code, 'Guarded scan did not complete; inspect protected dispatch evidence before retrying.' );
	}
}
