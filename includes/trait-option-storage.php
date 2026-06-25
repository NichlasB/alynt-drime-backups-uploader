<?php
/**
 * Shared option storage helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides verified array option storage.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Option_Storage {
	/**
	 * Returns an array option value.
	 *
	 * @param string $option Option name.
	 * @return array<string,mixed>
	 */
	private function get_array_option( $option ) {
		$value = get_option( $option, array() );

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Persists an array option and verifies the stored value.
	 *
	 * @param string              $option Option name.
	 * @param array<string,mixed> $value Value.
	 * @return bool
	 */
	private function persist_array_option( $option, array $value ) {
		update_option( $option, $value, false );
		$this->sync_array_option_cache( $option, $value );

		return get_option( $option, array() ) === $value;
	}

	/**
	 * Deletes an array option and verifies the effective state is empty.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	private function delete_array_option( $option ) {
		delete_option( $option );
		$this->delete_array_option_cache( $option );

		return array() === get_option( $option, array() );
	}

	/**
	 * Syncs a cached option after mutation.
	 *
	 * @param string              $option Option name.
	 * @param array<string,mixed> $value Value.
	 * @return void
	 */
	private function sync_array_option_cache( $option, array $value ) {
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( $option, 'options' );
		}
		if ( function_exists( 'wp_cache_set' ) ) {
			wp_cache_set( $option, $value, 'options' );
		}
	}

	/**
	 * Clears a cached option after deletion.
	 *
	 * @param string $option Option name.
	 * @return void
	 */
	private function delete_array_option_cache( $option ) {
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( $option, 'options' );
		}
	}
}
