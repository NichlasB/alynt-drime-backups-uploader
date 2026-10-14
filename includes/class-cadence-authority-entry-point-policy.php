<?php
/**
 * Dormant pure admission classifier; no production loading or execution rights.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Internal projection only. Results are not permission, a lease or an execution token. */
final class Alynt_Drime_Backups_Uploader_Cadence_Authority_Entry_Point_Policy {
	/** Fixed internal entry-point vocabulary, not a remote action allowlist. */
	private const ENTRY_POINTS = array(
		'scheduled_scan',
		'admin_scan',
		'cli_scan',
		'cli_run',
		'remote_scan',
		'direct_scan',
		'queue_write',
		'failed_requeue',
		'schedule_apply',
		'upload_next',
		'upload_abort',
		'schedule_rollback',
	);

	/** Exact synthetic policy projection. This is not an option or wire schema. */
	private const FIELDS = array(
		'policy_version',
		'mode',
		'lifecycle',
		'ever_activated',
		'handoff_verified',
		'consent',
		'auto_scan_enabled',
		'permission_generation',
		'current_permission_generation',
		'binding',
		'current_binding',
		'model_enabled',
		'pending',
		'next_due_at',
		'observed_at',
		'expires_at',
	);

	/**
	 * Classify a pre-admission observation; never read state, invoke work or issue permission.
	 *
	 * Only an explicitly injected trusted local adapter may supply this projection.
	 * It authenticates nothing; callers still need existing authorization and atomic guards.
	 * Do not use this pre-claim result as the dispatcher's post-start context reader.
	 *
	 * @param mixed $entry_point Internal entry-point label.
	 * @param mixed $snapshot Exact local projection; absent policy never infers legacy.
	 * @param mixed $now Explicit UTC seconds; no ambient clock or WordPress reads.
	 * @return array<string,mixed> Redacted disposition; all execution flags remain false.
	 */
	public static function evaluate( $entry_point, $snapshot = null, $now = null ) {
		if ( ! is_string( $entry_point ) || ! in_array( $entry_point, self::ENTRY_POINTS, true ) ) {
			return self::result( 'blocked', 'entry_point_unknown' );
		}
		if ( in_array( $entry_point, array( 'upload_next', 'upload_abort' ), true ) ) {
			// Neutral classification, not approval to upload, abort, retry or perform retention.
			return self::result( 'outside_scope', 'existing_upload_policy_required' );
		}
		if ( 'schedule_rollback' === $entry_point ) {
			return self::result( 'blocked', 'rollback_disabled' );
		}
		if ( ! self::valid_snapshot( $snapshot ) || ! is_int( $now ) || $now <= 0 || $now > PHP_INT_MAX - 300 ) {
			return self::result( 'blocked', 'policy_invalid' );
		}
		if ( $snapshot['observed_at'] > $now || $snapshot['expires_at'] <= $now ) {
			return self::result( 'blocked', 'policy_stale' );
		}
		if ( 'none' !== $snapshot['pending'] ) {
			return self::result( 'blocked', 'inspection_required' );
		}
		if ( 'legacy' === $snapshot['mode'] ) {
			if ( ! self::valid_legacy( $snapshot ) ) {
				return self::result( 'blocked', 'legacy_handoff_required' );
			}
			return 'scheduled_scan' === $entry_point && ! $snapshot['auto_scan_enabled']
				? self::result( 'blocked', 'automatic_scan_disabled' )
				: self::result( 'legacy_policy_required', 'legacy_policy_required' );
		}
		if ( 'active' !== $snapshot['lifecycle'] ) {
			return self::result( 'blocked', 'lifecycle_blocked' );
		}
		if ( ! $snapshot['ever_activated'] || $snapshot['handoff_verified'] || ! $snapshot['consent']
			|| ! $snapshot['auto_scan_enabled'] || ! $snapshot['model_enabled']
			|| $snapshot['permission_generation'] < 1 || $snapshot['permission_generation'] !== $snapshot['current_permission_generation']
			|| '' === $snapshot['binding'] || $snapshot['binding'] !== $snapshot['current_binding'] || $snapshot['next_due_at'] < 1 ) {
			return self::result( 'blocked', 'local_context_invalid' );
		}
		if ( 'failed_requeue' === $entry_point ) {
			return self::result( 'queue_policy_required', 'existing_queue_policy_required' );
		}
		if ( 'scheduled_scan' !== $entry_point ) {
			return self::result( 'blocked', 'entry_point_unsupported' );
		}
		return $snapshot['next_due_at'] > $now
			? self::result( 'blocked', 'not_due' )
			: self::result( 'guarded_dispatch_required', 'guarded_dispatch_required' );
	}

	/**
	 * Validate without coercion, recovery, normalization or private state traversal.
	 *
	 * @param mixed $s Projection.
	 * @return bool
	 */
	private static function valid_snapshot( $s ) {
		if ( ! is_array( $s ) || count( self::FIELDS ) !== count( $s ) || array_diff( self::FIELDS, array_keys( $s ) )
			|| 1 !== $s['policy_version'] || ! in_array( $s['mode'], array( 'legacy', 'client_owned_v1' ), true )
			|| ! in_array( $s['lifecycle'], array( 'legacy', 'preparing', 'active', 'draining', 'disabled' ), true )
			|| ! in_array( $s['pending'], array( 'none', 'claimed', 'started', 'recovery_required' ), true ) ) {
			return false;
		}
		foreach ( array( 'ever_activated', 'handoff_verified', 'consent', 'auto_scan_enabled', 'model_enabled' ) as $field ) {
			if ( ! is_bool( $s[ $field ] ) ) {
				return false;
			}
		}
		foreach ( array( 'permission_generation', 'current_permission_generation', 'next_due_at', 'observed_at', 'expires_at' ) as $field ) {
			if ( ! is_int( $s[ $field ] ) || $s[ $field ] < 0 ) {
				return false;
			}
		}
		foreach ( array( 'binding', 'current_binding' ) as $field ) {
			if ( ! is_string( $s[ $field ] ) || ( '' !== $s[ $field ] && ( 64 !== strlen( $s[ $field ] ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $s[ $field ] ) ) ) ) {
				return false;
			}
		}
		return ( ( 'legacy' === $s['mode'] ) === ( 'legacy' === $s['lifecycle'] ) )
			&& $s['observed_at'] > 0 && $s['expires_at'] > $s['observed_at'] && $s['expires_at'] - $s['observed_at'] <= 300;
	}

	/**
	 * Explicit legacy observation or verified handoff; absence/disable is not handoff.
	 *
	 * @param array $s Validated local projection.
	 * @return bool
	 */
	private static function valid_legacy( array $s ) {
		return ! $s['consent'] && ! $s['model_enabled'] && 0 === $s['next_due_at']
			&& $s['permission_generation'] === $s['current_permission_generation']
			&& ( $s['ever_activated']
				? $s['handoff_verified'] && $s['permission_generation'] > 0
				: ! $s['handoff_verified'] && 0 === $s['permission_generation'] );
	}

	/**
	 * Return only allowlisted codes, never the supplied policy or execution permission.
	 *
	 * @param string $disposition Next policy category.
	 * @param string $code Reason.
	 * @return array<string,mixed>
	 */
	private static function result( $disposition, $code ) {
		return array(
			'disposition'         => $disposition,
			'code'                => $code,
			'dispatch_supported'  => false,
			'execution_supported' => false,
		);
	}
}
