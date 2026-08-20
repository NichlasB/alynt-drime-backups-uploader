<?php
/**
 * Remote action state storage.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.12
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores bounded, redacted V2 remote-action audit and idempotency state.
 *
 * @since 0.5.12
 */
class Alynt_Drime_Backups_Uploader_Remote_Action_Store {
	const OPTION_NAME     = 'alynt_drime_backups_remote_action_state';
	const MAX_RECORDS     = 50;
	const IDEMPOTENCY_TTL = 86400;
	const LOCK_TTL        = 900;

	/**
	 * Returns default remote action state.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'records'          => array(),
			'idempotency'      => array(),
			'running_lock'     => array(
				'action_id'  => '',
				'expires_at' => 0,
			),
			'last_accepted_at' => array(),
			'latest_action'    => array(),
		);
	}

	/**
	 * Returns stored state.
	 *
	 * @return array<string,mixed>
	 */
	public function get() {
		$state = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $state ) ) {
			$state = array();
		}

		return $this->sanitize_state( array_merge( self::defaults(), $state ) );
	}

	/**
	 * Finds an action by idempotency key.
	 *
	 * @param string $key Idempotency key.
	 * @return array<string,mixed>
	 */
	public function find_by_idempotency_key( $key ) {
		$state = $this->get();
		$key   = $this->sanitize_identifier( $key );

		if ( '' === $key || empty( $state['idempotency'][ $key ] ) || ! is_array( $state['idempotency'][ $key ] ) ) {
			return array();
		}

		$action_id = isset( $state['idempotency'][ $key ]['action_id'] ) ? $this->sanitize_uuid( (string) $state['idempotency'][ $key ]['action_id'] ) : '';

		return '' === $action_id ? array() : $this->record( $action_id );
	}

	/**
	 * Returns a record by action ID.
	 *
	 * @param string $action_id Action ID.
	 * @return array<string,mixed>
	 */
	public function record( $action_id ) {
		$state     = $this->get();
		$action_id = $this->sanitize_uuid( $action_id );

		return '' !== $action_id && isset( $state['records'][ $action_id ] ) && is_array( $state['records'][ $action_id ] ) ? $state['records'][ $action_id ] : array();
	}

	/**
	 * Records a safely bounded terminal/non-terminal action state.
	 *
	 * @param array<string,mixed> $intent  Verified intent.
	 * @param string              $state   State.
	 * @param string              $code    Safe code.
	 * @param string              $summary Safe summary.
	 * @param array<string,int>   $counts  Safe counts.
	 * @param int                 $retry_after Retry-after seconds.
	 * @return array<string,mixed>
	 */
	public function upsert_action( array $intent, $state, $code, $summary, array $counts = array(), $retry_after = 0 ) {
		$stored     = $this->get();
		$action_id  = isset( $intent['action_id'] ) ? $this->sanitize_uuid( (string) $intent['action_id'] ) : '';
		$created_at = isset( $stored['records'][ $action_id ]['created_at'] ) ? absint( $stored['records'][ $action_id ]['created_at'] ) : time();

		if ( '' === $action_id ) {
			return array();
		}

		$record = array(
			'action_id'                => $action_id,
			'action_type'              => isset( $intent['action_type'] ) ? sanitize_key( (string) $intent['action_type'] ) : '',
			'dashboard_site_public_id' => isset( $intent['dashboard_site_public_id'] ) ? $this->sanitize_identifier( (string) $intent['dashboard_site_public_id'] ) : '',
			'idempotency_key'          => isset( $intent['idempotency_key'] ) ? $this->sanitize_identifier( (string) $intent['idempotency_key'] ) : '',
			'request_hash'             => isset( $intent['request_hash'] ) ? $this->sanitize_hash( (string) $intent['request_hash'] ) : '',
			'state'                    => $this->sanitize_state_key( $state ),
			'code'                     => sanitize_key( (string) $code ),
			'summary'                  => $this->safe_summary( $summary ),
			'counts'                   => $this->safe_counts( $counts ),
			'created_at'               => $created_at,
			'updated_at'               => time(),
			'retry_after'              => max( 0, absint( $retry_after ) ),
		);

		$stored['records'][ $action_id ] = $record;
		if ( '' !== $record['idempotency_key'] ) {
			$stored['idempotency'][ $record['idempotency_key'] ] = array(
				'action_id'    => $action_id,
				'request_hash' => $record['request_hash'],
				'expires_at'   => time() + self::IDEMPOTENCY_TTL,
			);
		}
		if ( 'accepted' === $record['state'] ) {
			$stored['last_accepted_at'][ $record['action_type'] ] = time();
		}

		$stored['latest_action'] = $this->summary_from_record( $record );
		$stored                  = $this->prune( $stored );

		$this->persist( $stored );

		return $record;
	}

	/**
	 * Returns retry-after seconds for the minimum interval.
	 *
	 * @param string $action_type Action type.
	 * @param int    $minimum_interval Minimum interval.
	 * @return int
	 */
	public function retry_after_for_action( $action_type, $minimum_interval ) {
		$state       = $this->get();
		$action_type = sanitize_key( (string) $action_type );
		$last        = isset( $state['last_accepted_at'][ $action_type ] ) ? absint( $state['last_accepted_at'][ $action_type ] ) : 0;

		if ( ! $last ) {
			return 0;
		}

		$remaining = ( $last + absint( $minimum_interval ) ) - time();

		return max( 0, $remaining );
	}

	/**
	 * Returns whether another action lock is active.
	 *
	 * @return bool
	 */
	public function has_active_lock() {
		$state = $this->get();
		$lock  = isset( $state['running_lock'] ) && is_array( $state['running_lock'] ) ? $state['running_lock'] : array();

		return ! empty( $lock['action_id'] ) && ! empty( $lock['expires_at'] ) && absint( $lock['expires_at'] ) > time();
	}

	/**
	 * Acquires the single-action lock.
	 *
	 * @param string $action_id Action ID.
	 * @return bool
	 */
	public function acquire_lock( $action_id ) {
		$action_id = $this->sanitize_uuid( $action_id );
		if ( '' === $action_id ) {
			return false;
		}

		$state = $this->get();
		$lock  = isset( $state['running_lock'] ) && is_array( $state['running_lock'] ) ? $state['running_lock'] : array();
		if ( ! empty( $lock['action_id'] ) && ! empty( $lock['expires_at'] ) && absint( $lock['expires_at'] ) > time() && ! hash_equals( (string) $lock['action_id'], $action_id ) ) {
			return false;
		}

		$state['running_lock'] = array(
			'action_id'  => $action_id,
			'expires_at' => time() + self::LOCK_TTL,
		);

		$this->persist( $state );

		return true;
	}

	/**
	 * Releases the single-action lock.
	 *
	 * @param string $action_id Action ID.
	 * @return void
	 */
	public function release_lock( $action_id ) {
		$state     = $this->get();
		$action_id = $this->sanitize_uuid( $action_id );
		$lock_id   = isset( $state['running_lock']['action_id'] ) ? $this->sanitize_uuid( (string) $state['running_lock']['action_id'] ) : '';

		if ( '' !== $action_id && hash_equals( $lock_id, $action_id ) ) {
			$state['running_lock'] = array(
				'action_id'  => '',
				'expires_at' => 0,
			);
			$this->persist( $state );
		}
	}

	/**
	 * Returns latest action summary.
	 *
	 * @return array<string,mixed>
	 */
	public function latest_action() {
		$state = $this->get();

		return isset( $state['latest_action'] ) && is_array( $state['latest_action'] ) ? $state['latest_action'] : array();
	}

	/**
	 * Prunes old records and idempotency keys.
	 *
	 * @param array<string,mixed> $state State.
	 * @return array<string,mixed>
	 */
	private function prune( array $state ) {
		$now = time();

		foreach ( $state['idempotency'] as $key => $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['expires_at'] ) || absint( $entry['expires_at'] ) <= $now ) {
				unset( $state['idempotency'][ $key ] );
			}
		}

		uasort(
			$state['records'],
			function ( $a, $b ) {
				$a_time = is_array( $a ) && isset( $a['updated_at'] ) ? absint( $a['updated_at'] ) : 0;
				$b_time = is_array( $b ) && isset( $b['updated_at'] ) ? absint( $b['updated_at'] ) : 0;

				return $b_time <=> $a_time;
			}
		);

		$state['records'] = array_slice( $state['records'], 0, self::MAX_RECORDS, true );

		return $state;
	}

	/**
	 * Sanitizes stored state.
	 *
	 * @param array<string,mixed> $state State.
	 * @return array<string,mixed>
	 */
	private function sanitize_state( array $state ) {
		$defaults = self::defaults();
		$records  = array();

		if ( isset( $state['records'] ) && is_array( $state['records'] ) ) {
			foreach ( $state['records'] as $record ) {
				if ( ! is_array( $record ) ) {
					continue;
				}
				$action_id = isset( $record['action_id'] ) ? $this->sanitize_uuid( (string) $record['action_id'] ) : '';
				if ( '' === $action_id ) {
					continue;
				}
				$records[ $action_id ] = $this->sanitize_record( $record );
			}
		}

		return array(
			'records'          => array_slice( $records, 0, self::MAX_RECORDS, true ),
			'idempotency'      => $this->sanitize_idempotency( isset( $state['idempotency'] ) && is_array( $state['idempotency'] ) ? $state['idempotency'] : $defaults['idempotency'] ),
			'running_lock'     => $this->sanitize_lock( isset( $state['running_lock'] ) && is_array( $state['running_lock'] ) ? $state['running_lock'] : $defaults['running_lock'] ),
			'last_accepted_at' => $this->sanitize_last_accepted_at( isset( $state['last_accepted_at'] ) && is_array( $state['last_accepted_at'] ) ? $state['last_accepted_at'] : $defaults['last_accepted_at'] ),
			'latest_action'    => isset( $state['latest_action'] ) && is_array( $state['latest_action'] ) ? $this->sanitize_latest_action( $state['latest_action'] ) : $defaults['latest_action'],
		);
	}

	/**
	 * Sanitizes one record.
	 *
	 * @param array<string,mixed> $record Record.
	 * @return array<string,mixed>
	 */
	private function sanitize_record( array $record ) {
		return array(
			'action_id'                => isset( $record['action_id'] ) ? $this->sanitize_uuid( (string) $record['action_id'] ) : '',
			'action_type'              => isset( $record['action_type'] ) ? sanitize_key( (string) $record['action_type'] ) : '',
			'dashboard_site_public_id' => isset( $record['dashboard_site_public_id'] ) ? $this->sanitize_identifier( (string) $record['dashboard_site_public_id'] ) : '',
			'idempotency_key'          => isset( $record['idempotency_key'] ) ? $this->sanitize_identifier( (string) $record['idempotency_key'] ) : '',
			'request_hash'             => isset( $record['request_hash'] ) ? $this->sanitize_hash( (string) $record['request_hash'] ) : '',
			'state'                    => isset( $record['state'] ) ? $this->sanitize_state_key( (string) $record['state'] ) : '',
			'code'                     => isset( $record['code'] ) ? sanitize_key( (string) $record['code'] ) : '',
			'summary'                  => isset( $record['summary'] ) ? $this->safe_summary( (string) $record['summary'] ) : '',
			'counts'                   => isset( $record['counts'] ) && is_array( $record['counts'] ) ? $this->safe_counts( $record['counts'] ) : array(),
			'created_at'               => isset( $record['created_at'] ) ? max( 0, absint( $record['created_at'] ) ) : 0,
			'updated_at'               => isset( $record['updated_at'] ) ? max( 0, absint( $record['updated_at'] ) ) : 0,
			'retry_after'              => isset( $record['retry_after'] ) ? max( 0, absint( $record['retry_after'] ) ) : 0,
		);
	}

	/**
	 * Builds status-safe latest summary.
	 *
	 * @param array<string,mixed> $record Record.
	 * @return array<string,mixed>
	 */
	private function summary_from_record( array $record ) {
		return array(
			'action_id'   => $record['action_id'],
			'action_type' => $record['action_type'],
			'state'       => $record['state'],
			'code'        => $record['code'],
			'summary'     => $record['summary'],
			'counts'      => $record['counts'],
			'updated_at'  => $record['updated_at'],
			'retry_after' => $record['retry_after'],
		);
	}

	/**
	 * Sanitizes latest action summary.
	 *
	 * @param array<string,mixed> $summary Summary.
	 * @return array<string,mixed>
	 */
	private function sanitize_latest_action( array $summary ) {
		return array(
			'action_id'   => isset( $summary['action_id'] ) ? $this->sanitize_uuid( (string) $summary['action_id'] ) : '',
			'action_type' => isset( $summary['action_type'] ) ? sanitize_key( (string) $summary['action_type'] ) : '',
			'state'       => isset( $summary['state'] ) ? $this->sanitize_state_key( (string) $summary['state'] ) : '',
			'code'        => isset( $summary['code'] ) ? sanitize_key( (string) $summary['code'] ) : '',
			'summary'     => isset( $summary['summary'] ) ? $this->safe_summary( (string) $summary['summary'] ) : '',
			'counts'      => isset( $summary['counts'] ) && is_array( $summary['counts'] ) ? $this->safe_counts( $summary['counts'] ) : array(),
			'updated_at'  => isset( $summary['updated_at'] ) ? max( 0, absint( $summary['updated_at'] ) ) : 0,
			'retry_after' => isset( $summary['retry_after'] ) ? max( 0, absint( $summary['retry_after'] ) ) : 0,
		);
	}

	/**
	 * Sanitizes idempotency entries.
	 *
	 * @param array<string,mixed> $entries Entries.
	 * @return array<string,array<string,mixed>>
	 */
	private function sanitize_idempotency( array $entries ) {
		$clean = array();

		foreach ( $entries as $key => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$key       = $this->sanitize_identifier( $key );
			$action_id = isset( $entry['action_id'] ) ? $this->sanitize_uuid( (string) $entry['action_id'] ) : '';
			if ( '' === $key || '' === $action_id ) {
				continue;
			}
			$clean[ $key ] = array(
				'action_id'    => $action_id,
				'request_hash' => isset( $entry['request_hash'] ) ? $this->sanitize_hash( (string) $entry['request_hash'] ) : '',
				'expires_at'   => isset( $entry['expires_at'] ) ? max( 0, absint( $entry['expires_at'] ) ) : 0,
			);
		}

		return $clean;
	}

	/**
	 * Sanitizes lock state.
	 *
	 * @param array<string,mixed> $lock Lock.
	 * @return array<string,mixed>
	 */
	private function sanitize_lock( array $lock ) {
		return array(
			'action_id'  => isset( $lock['action_id'] ) ? $this->sanitize_uuid( (string) $lock['action_id'] ) : '',
			'expires_at' => isset( $lock['expires_at'] ) ? max( 0, absint( $lock['expires_at'] ) ) : 0,
		);
	}

	/**
	 * Sanitizes last accepted timestamps.
	 *
	 * @param array<string,mixed> $timestamps Timestamps.
	 * @return array<string,int>
	 */
	private function sanitize_last_accepted_at( array $timestamps ) {
		$clean = array();

		foreach ( $timestamps as $action => $timestamp ) {
			$action = sanitize_key( (string) $action );
			if ( '' !== $action ) {
				$clean[ $action ] = max( 0, absint( $timestamp ) );
			}
		}

		return $clean;
	}

	/**
	 * Sanitizes counts.
	 *
	 * @param array<string,mixed> $counts Counts.
	 * @return array<string,int>
	 */
	private function safe_counts( array $counts ) {
		$clean = array();

		foreach ( array( 'found', 'queued', 'already_known', 'upload_attempted', 'failed' ) as $key ) {
			if ( isset( $counts[ $key ] ) ) {
				$clean[ $key ] = max( 0, absint( $counts[ $key ] ) );
			}
		}

		return $clean;
	}

	/**
	 * Bounds a safe operator summary and strips forbidden content hints.
	 *
	 * @param string $summary Summary.
	 * @return string
	 */
	private function safe_summary( $summary ) {
		$summary = sanitize_text_field( (string) $summary );
		$summary = preg_replace( '/(secret|token|credential|password|authorization|cookie|signature|private|path|file|package|drime|url|sql)/i', '[redacted]', $summary );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $summary, 0, 220 );
		}

		return substr( $summary, 0, 220 );
	}

	/**
	 * Sanitizes action state.
	 *
	 * @param string $state State.
	 * @return string
	 */
	private function sanitize_state_key( $state ) {
		$state = sanitize_key( (string) $state );

		return in_array( $state, array( 'accepted', 'rejected', 'unsupported', 'rate_limited', 'busy', 'running', 'succeeded', 'failed', 'timed_out' ), true ) ? $state : 'rejected';
	}

	/**
	 * Sanitizes a UUID.
	 *
	 * @param string $uuid UUID.
	 * @return string
	 */
	private function sanitize_uuid( $uuid ) {
		$uuid = strtolower( trim( (string) $uuid ) );

		return preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $uuid ) ? $uuid : '';
	}

	/**
	 * Sanitizes an identifier.
	 *
	 * @param string $identifier Identifier.
	 * @return string
	 */
	private function sanitize_identifier( $identifier ) {
		return substr( preg_replace( '/[^A-Za-z0-9_\-\.]/', '', (string) $identifier ), 0, 128 );
	}

	/**
	 * Sanitizes a SHA-256 hash.
	 *
	 * @param string $hash Hash.
	 * @return string
	 */
	private function sanitize_hash( $hash ) {
		return preg_match( '/^[a-f0-9]{64}$/', (string) $hash ) ? (string) $hash : '';
	}

	/**
	 * Persists state and clears option cache.
	 *
	 * @param array<string,mixed> $state State.
	 * @return void
	 */
	private function persist( array $state ) {
		update_option( self::OPTION_NAME, $state, false );

		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::OPTION_NAME, 'options' );
		}
		if ( function_exists( 'wp_cache_set' ) ) {
			wp_cache_set( self::OPTION_NAME, $state, 'options' );
		}
	}
}
