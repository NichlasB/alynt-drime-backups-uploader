<?php
/**
 * TEST-ONLY cooperative cron writer. Never loaded by the plugin bootstrap.
 * Restricted fixture hooks; not an installable cron replacement or runtime lock.
 */
class Alynt_Drime_Backups_Uploader_Test_Cron_Writer_Prototype {
	private $backend;
	public function __construct( $backend ) { $this->backend = $backend; }

	/** Explicit fixture registration only; the caller must remove these hooks. */
	public function filters() {
		return array(
			'pre_schedule_event' => array( 'schedule', 3 ),
			'pre_unschedule_event' => array( 'unschedule', 5 ),
			'pre_clear_scheduled_hook' => array( 'clear', 4 ),
			'pre_unschedule_hook' => array( 'remove_hook', 3 ),
		);
	}

	public function schedule( $pre, $event, $wp_error ) {
		if ( null !== $pre ) return $pre;
		return $this->mutate( 'schedule', array( 'hook' => $event->hook, 'timestamp' => (int) $event->timestamp, 'args' => $event->args, 'event' => array_filter( (array) $event, function ( $key ) { return in_array( $key, array( 'schedule', 'args', 'interval' ), true ); }, ARRAY_FILTER_USE_KEY ) ) );
	}
	public function unschedule( $pre, $timestamp, $hook, $args, $wp_error ) {
		return null !== $pre ? $pre : $this->mutate( 'unschedule', array( 'timestamp' => (int) $timestamp, 'hook' => $hook, 'args' => $args ) );
	}
	public function clear( $pre, $hook, $args, $wp_error ) {
		return null !== $pre ? $pre : $this->mutate( 'clear', array( 'hook' => $hook, 'args' => $args ) );
	}
	public function remove_hook( $pre, $hook, $wp_error ) {
		return null !== $pre ? $pre : $this->mutate( 'remove_hook', array( 'hook' => $hook ) );
	}

	/** Rebase one event intent, not a stale whole-option replacement. */
	public function mutate( $operation, array $spec ) {
		if ( ! isset( $spec['hook'] ) || ! is_string( $spec['hook'] ) ) return $this->error( 'invalid' );
		if ( Alynt_Drime_Backups_Uploader_Schedule_Safety_Cron_CAS::HOOK === $spec['hook'] ) return $this->error( 'owned_event_protected' );
		if ( ! preg_match( '/^alynt_coordination_fixture_[a-z0-9_]+$/D', $spec['hook'] ) || ! in_array( $operation, array( 'schedule', 'unschedule', 'clear', 'remove_hook' ), true ) ) return $this->error( 'invalid' );
		$budget = 2048;
		if ( ! $this->bounded( $spec, $budget ) || ( 'remove_hook' !== $operation && ( ! isset( $spec['args'] ) || ! is_array( $spec['args'] ) ) ) ) return $this->error( 'invalid' );
		if ( in_array( $operation, array( 'schedule', 'unschedule' ), true ) && ( ! isset( $spec['timestamp'] ) || ! is_int( $spec['timestamp'] ) || $spec['timestamp'] <= 0 ) ) return $this->error( 'invalid' );
		if ( 'schedule' === $operation && ( ! isset( $spec['event'] ) || ! is_array( $spec['event'] ) || ! isset( $spec['event']['args'] ) || ! array_key_exists( 'schedule', $spec['event'] ) || $spec['event']['args'] !== $spec['args'] || ! in_array( $spec['event']['schedule'], array( false, 'hourly' ), true )
			|| ( 'hourly' === $spec['event']['schedule'] && ( ! isset( $spec['event']['interval'] ) || 3600 !== $spec['event']['interval'] ) ) ) ) return $this->error( 'invalid' );
		for ( $attempt = 0; $attempt < 3; $attempt++ ) {
			$row = $this->backend->read( 'cron' );
			if ( is_wp_error( $row ) ) return $row;
			if ( ! is_array( $row ) || ! isset( $row['option_value'] ) || ! is_string( $row['option_value'] ) || strlen( $row['option_value'] ) > 1048576 ) return $this->error( 'invalid' );
			$before = unserialize( $row['option_value'], array( 'allowed_classes' => false, 'max_depth' => 12 ) );
			$budget = 2048;
			if ( ! is_array( $before ) || ! isset( $before['version'] ) || 2 !== $before['version'] || ! $this->bounded( $before, $budget ) ) return $this->error( 'invalid' );
			foreach ( $before as $timestamp => $hooks ) {
				if ( 'version' === $timestamp ) continue;
				if ( ! is_int( $timestamp ) || $timestamp <= 0 || ! is_array( $hooks ) ) return $this->error( 'invalid' );
				foreach ( $hooks as $hook => $events ) {
					if ( ! is_string( $hook ) || ! is_array( $events ) ) return $this->error( 'invalid' );
					foreach ( $events as $event ) if ( ! is_array( $event ) ) return $this->error( 'invalid' );
				}
			}
			$after = $before;
			$count = 0;
			$key = 'remove_hook' === $operation ? null : md5( serialize( $spec['args'] ) );
			if ( 'schedule' === $operation ) {
				$after[ $spec['timestamp'] ][ $spec['hook'] ][ $key ] = $spec['event'];
			} else {
				foreach ( $after as $timestamp => &$hooks ) {
					if ( 'version' === $timestamp ) continue;
					if ( ! is_array( $hooks ) ) return $this->error( 'invalid' );
					if ( 'unschedule' === $operation && $timestamp !== $spec['timestamp'] ) continue;
					if ( ! isset( $hooks[ $spec['hook'] ] ) ) continue;
					if ( 'remove_hook' === $operation ) {
						$count += count( $hooks[ $spec['hook'] ] ); unset( $hooks[ $spec['hook'] ] );
					} elseif ( isset( $hooks[ $spec['hook'] ][ $key ] ) ) {
						$count++; unset( $hooks[ $spec['hook'] ][ $key ] );
						if ( empty( $hooks[ $spec['hook'] ] ) ) unset( $hooks[ $spec['hook'] ] );
					}
					if ( empty( $hooks ) ) unset( $after[ $timestamp ] );
				}
				unset( $hooks );
			}
			$result = in_array( $operation, array( 'schedule', 'unschedule' ), true ) ? true : $count;
			if ( $after === $before ) return $result;
			uksort( $after, 'strnatcasecmp' );
			$saved = $this->backend->swap( 'cron', $row['option_value'], serialize( $after ) );
			if ( is_wp_error( $saved ) || $saved ) return is_wp_error( $saved ) ? $saved : $result;
		}
		return $this->error( 'contention' );
	}
	private function bounded( $value, &$budget, $depth = 0 ) {
		if ( --$budget < 0 || $depth > 10 || is_object( $value ) || is_resource( $value ) ) return false;
		if ( is_string( $value ) && strlen( $value ) > 1024 ) return false;
		if ( is_array( $value ) ) foreach ( $value as $child ) if ( ! $this->bounded( $child, $budget, $depth + 1 ) ) return false;
		return true;
	}
	private function error( $suffix ) { return new WP_Error( 'coordination_prototype_' . $suffix, 'Test-only cron coordination operation unavailable.' ); }
}
