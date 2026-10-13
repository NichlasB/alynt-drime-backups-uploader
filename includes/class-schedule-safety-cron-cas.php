<?php
/**
 * Dormant conditional replacement of one client-owned scan event.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Rejects stale snapshots; does not coordinate unguarded core cron writers. */
class Alynt_Drime_Backups_Uploader_Schedule_Safety_Cron_CAS {
	const HOOK = 'alynt_drime_backups_scan_event';

	/**
	 * Conditional option storage.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS
	 */
	private $backend;

	/**
	 * Construct a dormant cron guard.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS $backend Storage.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS $backend ) {
		$this->backend = $backend;
	}

	/**
	 * Change only one allowlisted scan event, atomically against exact bytes.
	 *
	 * @param string $expected Raw snapshot bytes.
	 * @param array  $before Complete prior cron option.
	 * @param array  $after Complete proposed cron option.
	 * @return true|WP_Error
	 */
	public function replace( $expected, array $before, array $after ) {
		if ( ! is_string( $expected ) || ! $this->safe_values( $before ) || ! $this->safe_values( $after ) || maybe_serialize( $before ) !== $expected
			|| ! isset( $before['version'], $after['version'] ) || 2 !== $before['version'] || 2 !== $after['version'] ) {
			return new WP_Error( 'schedule_safety_cron_invalid', 'Schedule safety cron evidence is invalid.' );
		}
		$before_rest = $this->without_scan( $before );
		$after_rest  = $this->without_scan( $after );
		if ( is_wp_error( $before_rest ) || is_wp_error( $after_rest ) || $before_rest !== $after_rest || $before === $after ) {
			return new WP_Error( 'schedule_safety_cron_scope_invalid', 'Schedule safety replacement must change only the owned scan event.' );
		}
		$result = $this->backend->swap( 'cron', $expected, maybe_serialize( $after ) );
		return is_wp_error( $result ) ? $result : ( $result ? true : new WP_Error( 'schedule_safety_cron_stale', 'The cron snapshot changed before replacement.' ) );
	}

	/**
	 * Validate exactly one owned event, then return unrelated events unchanged.
	 *
	 * @param array $cron Complete cron option.
	 * @return array|WP_Error
	 */
	private function without_scan( array $cron ) {
		$count     = 0;
		$intervals = array(
			'fifteen_minutes' => 900,
			'thirty_minutes'  => 1800,
			'hourly'          => 3600,
		);
		foreach ( $cron as $timestamp => &$hooks ) {
			if ( 'version' === $timestamp ) {
				continue;
			}
			if ( ! is_int( $timestamp ) || $timestamp <= 0 || ! is_array( $hooks ) ) {
				return new WP_Error( 'schedule_safety_cron_invalid', 'Schedule safety cron evidence is invalid.' );
			}
			if ( isset( $hooks[ self::HOOK ] ) ) {
				$events = $hooks[ self::HOOK ];
				if ( ! is_array( $events ) || 1 !== count( $events ) ) {
					return new WP_Error( 'schedule_safety_cron_invalid', 'The owned scan event is ambiguous.' );
				}
				$event = reset( $events );
				if ( ! is_array( $event ) || ! isset( $event['schedule'], $event['interval'], $event['args'] ) || count( $event ) !== 3 || ! is_string( $event['schedule'] ) || ! is_int( $event['interval'] )
					|| ! isset( $intervals[ $event['schedule'] ] ) || $event['interval'] !== $intervals[ $event['schedule'] ]
					|| array() !== $event['args'] || key( $events ) !== md5( maybe_serialize( array() ) ) ) {
					return new WP_Error( 'schedule_safety_cron_invalid', 'The owned scan event is unsupported.' );
				}
				++$count;
				unset( $hooks[ self::HOOK ] );
				if ( empty( $hooks ) ) {
					unset( $cron[ $timestamp ] );
				}
			}
		}
		unset( $hooks );
		ksort( $cron );
		return 1 === $count ? $cron : new WP_Error( 'schedule_safety_cron_invalid', 'Exactly one owned scan event is required.' );
	}

	/**
	 * Reject objects/resources before serialization; limit traversal and bytes.
	 *
	 * @param mixed $value Cron value.
	 * @param int   $depth Nesting depth.
	 * @return bool
	 */
	private function safe_values( $value, $depth = 0 ) {
		if ( $depth > 12 || is_object( $value ) || is_resource( $value ) ) {
			return false;
		}
		if ( is_array( $value ) ) {
			if ( count( $value ) > 1000 ) {
				return false;
			}
			foreach ( $value as $child ) {
				if ( ! $this->safe_values( $child, $depth + 1 ) ) {
					return false;
				}
			}
		}
		return true;
	}
}
