<?php
/**
 * Explicit closed upgrade and pure fixture barrier transformations.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No target option, identity, authenticated consent or execution mutation. */
trait Alynt_Drime_Backups_Uploader_Closed_Writer_Barrier_Transitions {
	/**
	 * Upgrade only an existing immutable schema-5 checkpoint, never bootstrap.
	 *
	 * @param string   $binding Original binding.
	 * @param int      $expected_generation Original permission epoch.
	 * @param callable $guard Literal fixture admission before every read/write.
	 * @return array|WP_Error Closed private snapshot, not permission.
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
				if ( ! is_array( $old ) || ( ! $this->valid_state( $old ) && ! $this->valid_checkpoint( $old ) ) ) {
					return $this->error( 'unavailable' );
				}
				$now = $this->barrier_now( $old );
				if ( is_wp_error( $now ) ) {
					return $now;
				}
				if ( $old['enrollment_binding'] !== $binding || $old['lifecycle']['permission_generation'] !== $expected_generation ) {
					return $this->error( 'stale' );
				}
				if ( 6 === $old['schema_version'] ) {
					return $old; // Observation only: never reset a pending writer or history.
				}
				if ( ! $this->headroom( $old, 4 ) ) {
					return $this->error( 'capacity' );
				}
				if ( true !== $guard() ) {
					return $this->error( 'denied' );
				}
				$write_now = $this->barrier_now( $old );
				if ( is_wp_error( $write_now ) || $write_now < $now ) {
					return $this->error( 'clock' );
				}
				$s   = $this->upgrade_barrier( $old, $write_now );
				$raw = wp_json_encode( $s );
				if ( ! $this->valid_state( $s ) || ! is_string( $raw ) || strlen( $raw ) > self::MAX_BYTES - self::CLOSURE_BYTES ) {
					return $this->error( 'capacity' );
				}
				$final_now = $this->barrier_now( $s );
				if ( is_wp_error( $final_now ) || $final_now !== $write_now ) {
					return $this->error( 'clock' );
				}
				$saved = $this->backend->swap( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION, $row['option_value'], $raw );
				if ( is_wp_error( $saved ) || ! is_bool( $saved ) ) {
					return $this->error( 'unresolved' );
				}
				if ( $saved ) {
					return $s;
				}
			}
			return $this->error( 'contention' );
		} catch ( Throwable $error ) {
			return $this->error( 'unresolved' );
		}
	}

	/**
	 * Build a strict closed successor with one nonrecursive frozen checkpoint.
	 *
	 * @param array $old Valid checkpoint.
	 * @param int   $now UTC.
	 * @return array
	 */
	private function upgrade_barrier( array $old, $now ) {
		$s = $old;
		unset( $s['provenance'] );
		$s['schema_version'] = 6;
		++$s['generation'];
		++$s['schedule_revision'];
		++$s['lifecycle']['permission_generation'];
		$s['lifecycle']['changed_at'] = $now;
		$s['barrier']                 = array(
			'site_uuid'              => $this->site_uuid,
			'source'                 => self::BARRIER_SOURCE,
			'checkpoint'             => $old,
			'upgrade'                => array(
				'checkpoint_digest'   => $this->checkpoint_digest( $old ),
				'expected_generation' => $old['lifecycle']['permission_generation'],
				'result_generation'   => $s['lifecycle']['permission_generation'],
				'committed_at'        => $now,
			),
			'authenticated_grant'    => null,
			'authenticated_receipts' => array(),
			'pending'                => null,
			'closure'                => null,
			'completion'             => null,
			'receipts'               => array(),
		);
		return $s;
	}

	/**
	 * Pure closed-only transformation. A finish is only a fixture observation.
	 *
	 * @param array  $s State copy.
	 * @param array  $c Fixture command.
	 * @param string $purpose Begin, finish or abandon.
	 * @param int    $now UTC.
	 * @return array|WP_Error
	 */
	private function change_barrier( array $s, array $c, $purpose, $now ) {
		$b = $s['barrier'];
		$r = array(
			'purpose'           => $purpose,
			'command'           => $c,
			'request_hash'      => $this->barrier_digest( $c, $purpose ),
			'result_generation' => $c['expected_generation'] + 1,
			'committed_at'      => $now,
		);
		if ( ! $this->headroom( $s, 'begin' === $purpose ? 3 : 2 ) ) {
			return $this->error( 'capacity' );
		}
		if ( 'begin' === $purpose ) {
			if ( null !== $b['pending'] ) {
				return $this->error( 'pending' );
			}
			foreach ( array( 'closure', 'completion' ) as $slot ) {
				if ( null !== $b[ $slot ] ) {
					$b['receipts'][ $b[ $slot ]['command']['command_id'] ] = $b[ $slot ];
				}
			}
			if ( count( $b['receipts'] ) + $this->archive_count( $s ) > self::MAX_RECEIPTS ) {
				return $this->error( 'capacity' );
			}
			$b['closure']    = $r;
			$b['completion'] = null;
			$b['pending']    = $c['command_id'];
		} else {
			if ( null === $b['pending'] || $b['pending'] !== $c['operation_id'] || ! $this->valid_barrier_completion( $r, $b['closure'] ) ) {
				return $this->error( 'context' );
			}
			$b['completion'] = $r;
			$b['pending']    = null;
		}
		$s['barrier'] = $b;
		++$s['generation'];
		++$s['schedule_revision'];
		++$s['lifecycle']['permission_generation'];
		$s['lifecycle']['changed_at'] = $now;
		return $s;
	}
}
