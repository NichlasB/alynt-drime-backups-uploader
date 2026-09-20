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
	use Alynt_Drime_Backups_Uploader_Remote_Action_Store_Sanitizers;
	use Alynt_Drime_Backups_Uploader_Remote_Action_Store_Schedule_Sanitizers;

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
	 * @param array<string,mixed> $schedule_preview Safe schedule preview details.
	 * @param array<string,mixed> $schedule_apply Safe schedule apply details.
	 * @param array<string,mixed> $schedule_rollback_preview Safe schedule rollback preview details.
	 * @return array<string,mixed>
	 */
	public function upsert_action( array $intent, $state, $code, $summary, array $counts = array(), $retry_after = 0, array $schedule_preview = array(), array $schedule_apply = array(), array $schedule_rollback_preview = array() ) {
		$stored     = $this->get();
		$action_id  = isset( $intent['action_id'] ) ? $this->sanitize_uuid( (string) $intent['action_id'] ) : '';
		$created_at = isset( $stored['records'][ $action_id ]['created_at'] ) ? absint( $stored['records'][ $action_id ]['created_at'] ) : time();

		if ( '' === $action_id ) {
			return array();
		}

		$record = array(
			'action_id'                 => $action_id,
			'action_type'               => isset( $intent['action_type'] ) ? sanitize_key( (string) $intent['action_type'] ) : '',
			'dashboard_site_public_id'  => isset( $intent['dashboard_site_public_id'] ) ? $this->sanitize_identifier( (string) $intent['dashboard_site_public_id'] ) : '',
			'idempotency_key'           => isset( $intent['idempotency_key'] ) ? $this->sanitize_identifier( (string) $intent['idempotency_key'] ) : '',
			'request_hash'              => isset( $intent['request_hash'] ) ? $this->sanitize_hash( (string) $intent['request_hash'] ) : '',
			'state'                     => $this->sanitize_state_key( $state ),
			'code'                      => sanitize_key( (string) $code ),
			'summary'                   => $this->safe_summary( $summary ),
			'counts'                    => $this->safe_counts( $counts ),
			'schedule_preview'          => $this->safe_schedule_preview( ! empty( $schedule_preview ) ? $schedule_preview : ( isset( $intent['schedule_preview'] ) && is_array( $intent['schedule_preview'] ) ? $intent['schedule_preview'] : array() ) ),
			'schedule_apply'            => $this->safe_schedule_apply( ! empty( $schedule_apply ) ? $schedule_apply : ( isset( $intent['schedule_apply'] ) && is_array( $intent['schedule_apply'] ) ? $intent['schedule_apply'] : array() ) ),
			'schedule_rollback_preview' => $this->safe_schedule_rollback_preview( ! empty( $schedule_rollback_preview ) ? $schedule_rollback_preview : ( isset( $intent['schedule_rollback_preview'] ) && is_array( $intent['schedule_rollback_preview'] ) ? $intent['schedule_rollback_preview'] : array() ) ),
			'created_at'                => $created_at,
			'updated_at'                => time(),
			'retry_after'               => max( 0, absint( $retry_after ) ),
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
