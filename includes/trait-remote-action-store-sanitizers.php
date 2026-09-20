<?php
/**
 * Remote action store sanitization helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizes bounded, redacted remote-action store state.
 *
 * @since 0.5.22
 */
trait Alynt_Drime_Backups_Uploader_Remote_Action_Store_Sanitizers {
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
			'action_id'                 => isset( $record['action_id'] ) ? $this->sanitize_uuid( (string) $record['action_id'] ) : '',
			'action_type'               => isset( $record['action_type'] ) ? sanitize_key( (string) $record['action_type'] ) : '',
			'dashboard_site_public_id'  => isset( $record['dashboard_site_public_id'] ) ? $this->sanitize_identifier( (string) $record['dashboard_site_public_id'] ) : '',
			'idempotency_key'           => isset( $record['idempotency_key'] ) ? $this->sanitize_identifier( (string) $record['idempotency_key'] ) : '',
			'request_hash'              => isset( $record['request_hash'] ) ? $this->sanitize_hash( (string) $record['request_hash'] ) : '',
			'state'                     => isset( $record['state'] ) ? $this->sanitize_state_key( (string) $record['state'] ) : '',
			'code'                      => isset( $record['code'] ) ? sanitize_key( (string) $record['code'] ) : '',
			'summary'                   => isset( $record['summary'] ) ? $this->safe_summary( (string) $record['summary'] ) : '',
			'counts'                    => isset( $record['counts'] ) && is_array( $record['counts'] ) ? $this->safe_counts( $record['counts'] ) : array(),
			'schedule_preview'          => isset( $record['schedule_preview'] ) && is_array( $record['schedule_preview'] ) ? $this->safe_schedule_preview( $record['schedule_preview'] ) : array(),
			'schedule_apply'            => isset( $record['schedule_apply'] ) && is_array( $record['schedule_apply'] ) ? $this->safe_schedule_apply( $record['schedule_apply'] ) : array(),
			'schedule_rollback_preview' => isset( $record['schedule_rollback_preview'] ) && is_array( $record['schedule_rollback_preview'] ) ? $this->safe_schedule_rollback_preview( $record['schedule_rollback_preview'] ) : array(),
			'created_at'                => isset( $record['created_at'] ) ? max( 0, absint( $record['created_at'] ) ) : 0,
			'updated_at'                => isset( $record['updated_at'] ) ? max( 0, absint( $record['updated_at'] ) ) : 0,
			'retry_after'               => isset( $record['retry_after'] ) ? max( 0, absint( $record['retry_after'] ) ) : 0,
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
			'action_id'                 => $record['action_id'],
			'action_type'               => $record['action_type'],
			'state'                     => $record['state'],
			'code'                      => $record['code'],
			'summary'                   => $record['summary'],
			'counts'                    => $record['counts'],
			'schedule_preview'          => isset( $record['schedule_preview'] ) && is_array( $record['schedule_preview'] ) ? $this->safe_schedule_preview( $record['schedule_preview'] ) : array(),
			'schedule_apply'            => isset( $record['schedule_apply'] ) && is_array( $record['schedule_apply'] ) ? $this->safe_schedule_apply( $record['schedule_apply'] ) : array(),
			'schedule_rollback_preview' => isset( $record['schedule_rollback_preview'] ) && is_array( $record['schedule_rollback_preview'] ) ? $this->safe_schedule_rollback_preview( $record['schedule_rollback_preview'] ) : array(),
			'updated_at'                => $record['updated_at'],
			'retry_after'               => $record['retry_after'],
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
			'action_id'                 => isset( $summary['action_id'] ) ? $this->sanitize_uuid( (string) $summary['action_id'] ) : '',
			'action_type'               => isset( $summary['action_type'] ) ? sanitize_key( (string) $summary['action_type'] ) : '',
			'state'                     => isset( $summary['state'] ) ? $this->sanitize_state_key( (string) $summary['state'] ) : '',
			'code'                      => isset( $summary['code'] ) ? sanitize_key( (string) $summary['code'] ) : '',
			'summary'                   => isset( $summary['summary'] ) ? $this->safe_summary( (string) $summary['summary'] ) : '',
			'counts'                    => isset( $summary['counts'] ) && is_array( $summary['counts'] ) ? $this->safe_counts( $summary['counts'] ) : array(),
			'schedule_preview'          => isset( $summary['schedule_preview'] ) && is_array( $summary['schedule_preview'] ) ? $this->safe_schedule_preview( $summary['schedule_preview'] ) : array(),
			'schedule_apply'            => isset( $summary['schedule_apply'] ) && is_array( $summary['schedule_apply'] ) ? $this->safe_schedule_apply( $summary['schedule_apply'] ) : array(),
			'schedule_rollback_preview' => isset( $summary['schedule_rollback_preview'] ) && is_array( $summary['schedule_rollback_preview'] ) ? $this->safe_schedule_rollback_preview( $summary['schedule_rollback_preview'] ) : array(),
			'updated_at'                => isset( $summary['updated_at'] ) ? max( 0, absint( $summary['updated_at'] ) ) : 0,
			'retry_after'               => isset( $summary['retry_after'] ) ? max( 0, absint( $summary['retry_after'] ) ) : 0,
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
}
