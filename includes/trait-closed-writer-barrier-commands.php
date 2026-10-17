<?php
/**
 * Bounded guarded fixture mutations, never target writes or authenticated grants.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Every retry retains the original identity, epoch and deadline. */
trait Alynt_Drime_Backups_Uploader_Closed_Writer_Barrier_Commands {
	/**
	 * Begin a closed pending fixture operation.
	 *
	 * @param mixed $command Fixture intent.
	 * @param mixed $guard Scoped fixture guard, not real authorization.
	 * @return array|WP_Error
	 */
	public function begin( $command, $guard ) {
		return $this->mutate_barrier( $command, $guard, 'begin' );
	}

	/**
	 * Record a secondary-result hash observation in the model only.
	 *
	 * @param mixed $command Fixture terminal intent.
	 * @param mixed $guard Scoped fixture guard.
	 * @return array|WP_Error
	 */
	public function finish( $command, $guard ) {
		return $this->mutate_barrier( $command, $guard, 'finish' );
	}

	/**
	 * Explicitly abandon an exact pending fixture without replaying its write.
	 *
	 * @param mixed $command Fresh fixture terminal intent.
	 * @param mixed $guard Scoped fixture guard.
	 * @return array|WP_Error
	 */
	public function abandon( $command, $guard ) {
		return $this->mutate_barrier( $command, $guard, 'abandon' );
	}

	/**
	 * Apply one pure state CAS; callbacks/storage errors never report disablement.
	 *
	 * @param mixed  $c Intent.
	 * @param mixed  $guard Fixture guard.
	 * @param string $purpose Explicit method purpose.
	 * @return array|WP_Error
	 */
	private function mutate_barrier( $c, $guard, $purpose ) {
		if ( ! $this->enabled ) {
			return $this->error( 'disabled' );
		}
		if ( ! $this->valid_barrier_command( $c, $purpose ) || ! is_callable( $guard ) ) {
			return $this->error( 'invalid' );
		}
		try {
			for ( $attempt = 0; $attempt < 3; $attempt++ ) {
				if ( true !== $guard() ) {
					return $this->error( 'denied' );
				}
				$loaded = $this->load();
				if ( is_wp_error( $loaded ) ) {
					return $this->error( 'unavailable' );
				}
				$s   = $loaded['state'];
				$now = $this->barrier_now( $s );
				if ( is_wp_error( $now ) ) {
					return $now;
				}
				if ( $c['issued_at'] > $now || $now >= $c['expires_at'] ) {
					return $this->error( 'expired' );
				}
				if ( $this->archived_id( $s, $c['command_id'] ) ) {
					return $this->error( 'domain_conflict' );
				}
				$prior = $this->writer_receipt( $s, $c['command_id'] );
				if ( null !== $prior ) {
					return hash_equals( $prior['request_hash'], $this->barrier_digest( $c, $purpose ) )
						? $this->barrier_outcome( $prior['result_generation'] === $s['lifecycle']['permission_generation'] ? 'observed_current' : 'observed_superseded' ) : $this->error( 'conflict' );
				}
				if ( $s['enrollment_binding'] !== $c['enrollment_binding'] || $s['lifecycle']['permission_generation'] !== $c['expected_generation'] ) {
					return $this->error( 'stale' );
				}
				if ( true !== $guard() ) {
					return $this->error( 'denied' );
				}
				$write_now = $this->barrier_now( $s );
				if ( is_wp_error( $write_now ) || $write_now < $now ) {
					return $this->error( 'clock' );
				}
				if ( $write_now < $c['issued_at'] || $write_now >= $c['expires_at'] ) {
					return $this->error( 'expired' );
				}
				$s = $this->change_barrier( $s, $c, $purpose, $write_now );
				if ( is_wp_error( $s ) ) {
					return $s;
				}
				$raw   = wp_json_encode( $s );
				$limit = 'begin' === $purpose ? self::MAX_BYTES - self::CLOSURE_BYTES : self::MAX_BYTES;
				if ( ! $this->valid_state( $s ) || ! is_string( $raw ) || strlen( $raw ) > $limit ) {
					return $this->error( 'capacity' );
				}
				$final_now = $this->barrier_now( $s );
				if ( is_wp_error( $final_now ) ) {
					return $final_now;
				}
				if ( $final_now >= $c['expires_at'] ) {
					return $this->error( 'expired' );
				}
				if ( $final_now !== $write_now ) {
					return $this->error( 'clock' ); // Never commit a timestamp prepared before a clock change.
				}
				$saved = $this->backend->swap( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION, $loaded['raw'], $raw );
				if ( is_wp_error( $saved ) || ! is_bool( $saved ) ) {
					return $this->error( 'unresolved' );
				}
				if ( $saved ) {
					return $this->barrier_outcome( 'committed' );
				}
			}
			return $this->error( 'contention' );
		} catch ( Throwable $error ) {
			return $this->error( 'unresolved' );
		}
	}
}
