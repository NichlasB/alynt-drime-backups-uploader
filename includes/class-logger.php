<?php
/**
 * Structured diagnostics log.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores a bounded redacted diagnostics event log.
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_Logger {
	const OPTION_NAME = 'alynt_drime_backups_logs';

	/**
	 * Settings service.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Settings $settings Settings.
	 *
	 * @since 0.1.0
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Backward-compatible logging method.
	 *
	 * @param string              $level Level.
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @return bool Whether the event was stored or intentionally skipped.
	 *
	 * @since 0.1.0
	 */
	public function log( $level, $message, array $context = array() ) {
		return $this->event( 'general', $level, 'event', $message, $context );
	}

	/**
	 * Adds a structured event if diagnostics is enabled.
	 *
	 * @param string              $category Category.
	 * @param string              $level Level.
	 * @param string              $code Event code.
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @return bool Whether the event was stored or intentionally skipped.
	 *
	 * @since 0.1.0
	 */
	public function event( $category, $level, $code, $message, array $context = array() ) {
		$settings = $this->settings->get();

		if ( empty( $settings['diagnostics_enabled'] ) ) {
			return true;
		}

		$level = $this->normalize_level( $level );
		if ( ! $this->passes_threshold( $level, (string) $settings['diagnostics_min_level'] ) ) {
			return true;
		}

		$events = $this->get_events();
		array_unshift(
			$events,
			array(
				'timestamp' => gmdate( 'c' ),
				'time'      => time(),
				'level'     => $level,
				'category'  => sanitize_key( $category ),
				'code'      => sanitize_key( $code ),
				'message'   => sanitize_text_field( $message ),
				'context'   => $this->redact_context( $context ),
			)
		);

		$retention = isset( $settings['diagnostics_retention'] ) ? absint( $settings['diagnostics_retention'] ) : 100;
		$events    = array_slice( $events, 0, max( 25, min( 500, $retention ) ) );

		return $this->persist_events( $events );
	}

	/**
	 * Gets events.
	 *
	 * @return array<int,array<string,mixed>>
	 *
	 * @since 0.1.0
	 */
	public function get_events() {
		$events = get_option( self::OPTION_NAME, array() );

		return is_array( $events ) ? $events : array();
	}

	/**
	 * Clears all diagnostics events.
	 *
	 * @return bool Whether diagnostics were cleared.
	 *
	 * @since 0.1.0
	 */
	public function clear() {
		delete_option( self::OPTION_NAME );
		$this->flush_option_cache();

		return array() === get_option( self::OPTION_NAME, array() );
	}

	/**
	 * Persists diagnostics events and verifies the stored value.
	 *
	 * @param array<int,array<string,mixed>> $events Events.
	 * @return bool
	 */
	private function persist_events( array $events ) {
		update_option( self::OPTION_NAME, $events, false );
		$this->sync_option_cache( $events );

		return get_option( self::OPTION_NAME, array() ) === $events;
	}

	/**
	 * Syncs the diagnostics option cache after mutation.
	 *
	 * @param array<int,array<string,mixed>> $events Events.
	 * @return void
	 */
	private function sync_option_cache( array $events ) {
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::OPTION_NAME, 'options' );
		}
		if ( function_exists( 'wp_cache_set' ) ) {
			wp_cache_set( self::OPTION_NAME, $events, 'options' );
		}
	}

	/**
	 * Clears the diagnostics option cache after deletion.
	 *
	 * @return void
	 */
	private function flush_option_cache() {
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::OPTION_NAME, 'options' );
		}
	}

	/**
	 * Builds a safe diagnostics export payload.
	 *
	 * @return array<string,mixed>
	 *
	 * @since 0.1.0
	 */
	public function export_payload() {
		$settings = $this->settings->get();

		return array(
			'generated_at' => gmdate( 'c' ),
			'plugin'       => array(
				'name'    => 'Alynt Drime Backups Uploader',
				'version' => ALYNT_DRIME_BACKUPS_UPLOADER_VERSION,
			),
			'environment'  => array(
				'wordpress_version' => get_bloginfo( 'version' ),
				'php_version'       => PHP_VERSION,
			),
			'diagnostics'  => array(
				'enabled'   => ! empty( $settings['diagnostics_enabled'] ),
				'min_level' => $settings['diagnostics_min_level'],
				'retention' => $settings['diagnostics_retention'],
			),
			'events'       => $this->get_events(),
		);
	}

	/**
	 * Returns basic diagnostics stats.
	 *
	 * @return array<string,mixed>
	 *
	 * @since 0.1.0
	 */
	public function stats() {
		$events = $this->get_events();
		$last   = isset( $events[0]['timestamp'] ) ? (string) $events[0]['timestamp'] : '';

		return array(
			'count'      => count( $events ),
			'last_event' => $last,
		);
	}

	/**
	 * Removes sensitive context fields and truncates oversized values.
	 *
	 * @param array<string,mixed> $context Context.
	 * @return array<string,mixed>
	 */
	private function redact_context( array $context ) {
		$redacted = array();

		foreach ( $context as $key => $value ) {
			$key = sanitize_key( $key );
			if ( preg_match( '/(api_token|authorization|bearer|cookie|nonce|password|secret|token|presigned|url|raw_body|body)/', $key ) ) {
				$redacted[ $key ] = '[redacted]';
				continue;
			}

			if ( is_scalar( $value ) ) {
				$value            = $this->redact_scalar_value( sanitize_text_field( (string) $value ) );
				$redacted[ $key ] = strlen( $value ) > 300 ? substr( $value, 0, 300 ) . '...' : $value;
				continue;
			}

			$redacted[ $key ] = '[complex]';
		}

		return $redacted;
	}

	/**
	 * Redacts sensitive substrings that may be embedded in otherwise safe fields.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function redact_scalar_value( $value ) {
		$value = preg_replace( '/Bearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer [redacted]', $value );
		$value = preg_replace( '#https?://\S+#i', '[redacted-url]', (string) $value );

		return (string) $value;
	}

	/**
	 * Normalizes severity level.
	 *
	 * @param string $level Level.
	 * @return string
	 */
	private function normalize_level( $level ) {
		$level = sanitize_key( $level );

		return array_key_exists( $level, Alynt_Drime_Backups_Uploader_Settings::severity_levels() ) ? $level : 'info';
	}

	/**
	 * Checks minimum threshold.
	 *
	 * @param string $level Level.
	 * @param string $minimum Minimum level.
	 * @return bool
	 */
	private function passes_threshold( $level, $minimum ) {
		$levels  = Alynt_Drime_Backups_Uploader_Settings::severity_levels();
		$minimum = array_key_exists( $minimum, $levels ) ? $minimum : 'warning';

		return $levels[ $level ] >= $levels[ $minimum ];
	}
}
