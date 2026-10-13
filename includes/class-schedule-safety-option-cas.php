<?php
/**
 * Conditional option writes for the dormant scheduling safety foundation.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Client-owned safety-state and cron compare-and-swap adapter. */
class Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS {
	const STATE_OPTION = 'alynt_drime_backups_schedule_safety_state';

	/**
	 * Database connection; integration tests use an isolated options table.
	 *
	 * @var wpdb
	 */
	private $database;

	/**
	 * Construct a dormant adapter.
	 *
	 * @param wpdb $database Current WordPress database connection.
	 */
	public function __construct( $database ) {
		$this->database = $database;
	}

	/**
	 * Read authoritative bytes, bypassing option caches.
	 *
	 * @param string $name Allowlisted option.
	 * @return array|null|WP_Error
	 */
	public function read( $name ) {
		$table = $this->table( $name );
		if ( is_wp_error( $table ) ) {
			return $table;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic state cannot use cached get_option reads.
		$row = $this->database->get_row( $this->database->prepare( "SELECT option_value, autoload FROM {$table} WHERE option_name = %s", $name ), 'ARRAY_A' );
		if ( '' !== $this->database->last_error ) {
			return new WP_Error( 'schedule_safety_read_failed', 'Schedule safety state could not be read.' );
		}
		return $row;
	}

	/**
	 * Create only the dedicated non-autoloaded state. Never creates cron.
	 *
	 * @param string $value Encoded state.
	 * @return bool|WP_Error
	 */
	public function create( $value ) {
		if ( ! is_string( $value ) || strlen( $value ) > 131072 ) {
			return new WP_Error( 'schedule_safety_invalid_state', 'Schedule safety state is invalid.' );
		}
		return add_option( self::STATE_OPTION, $value, '', false );
	}

	/**
	 * Replace an exact snapshot; false means another writer won.
	 *
	 * @param string $name Allowlisted option.
	 * @param string $expected Expected stored bytes.
	 * @param string $replacement Replacement bytes.
	 * @return bool|WP_Error
	 */
	public function swap( $name, $expected, $replacement ) {
		$table = $this->table( $name );
		if ( is_wp_error( $table ) ) {
			return $table;
		}
		if ( ! is_string( $expected ) || ! is_string( $replacement ) || $expected === $replacement || strlen( $replacement ) > 1048576 ) {
			return new WP_Error( 'schedule_safety_invalid_state', 'Schedule safety replacement is invalid.' );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Option API has no compare-and-swap operation.
		$changed = $this->database->query( $this->database->prepare( "UPDATE {$table} SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s", $replacement, $name, $expected ) );
		if ( false === $changed ) {
			return new WP_Error( 'schedule_safety_write_failed', 'Schedule safety state could not be saved.' );
		}
		if ( 1 === $changed ) {
			foreach ( array( $name, 'alloptions', 'notoptions' ) as $key ) {
				wp_cache_delete( $key, 'options' );
			}
		}
		return 1 === $changed;
	}

	/**
	 * Resolve only a trusted options-table identifier and fixed option names.
	 *
	 * @param string $name Option name.
	 * @return string|WP_Error
	 */
	private function table( $name ) {
		$table = $this->database->options;
		if ( ! in_array( $name, array( self::STATE_OPTION, 'cron' ), true ) || ! is_string( $table ) || ! preg_match( '/^[A-Za-z0-9_]+$/D', $table ) ) {
			return new WP_Error( 'schedule_safety_scope_invalid', 'Schedule safety storage scope is invalid.' );
		}
		return $table;
	}
}
