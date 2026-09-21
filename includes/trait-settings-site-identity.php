<?php
/**
 * Settings site identity helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages local site UUID generation and settings option cache sync.
 *
 * @since 0.5.22
 */
trait Alynt_Drime_Backups_Uploader_Settings_Site_Identity {
	/**
	 * Syncs the settings option cache after mutation.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private function sync_option_cache( array $settings ) {
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::OPTION_NAME, 'options' );
		}
		if ( function_exists( 'wp_cache_set' ) ) {
			wp_cache_set( self::OPTION_NAME, $settings, 'options' );
		}
	}

	/**
	 * Sanitizes a UUID v4 style identifier.
	 *
	 * @param string $uuid UUID.
	 * @return string
	 */
	private function sanitize_uuid( $uuid ) {
		$uuid = strtolower( trim( (string) $uuid ) );

		return preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $uuid ) ? $uuid : '';
	}

	/**
	 * Generates a UUID.
	 *
	 * @return string
	 */
	private function generate_site_uuid() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			$uuid = $this->sanitize_uuid( wp_generate_uuid4() );
			if ( '' !== $uuid ) {
				return $uuid;
			}
		}

		if ( function_exists( 'random_bytes' ) ) {
			$data    = random_bytes( 16 );
			$data[6] = chr( ( ord( $data[6] ) & 0x0f ) | 0x40 );
			$data[8] = chr( ( ord( $data[8] ) & 0x3f ) | 0x80 );
			$hex     = bin2hex( $data );

			return sprintf(
				'%s-%s-%s-%s-%s',
				substr( $hex, 0, 8 ),
				substr( $hex, 8, 4 ),
				substr( $hex, 12, 4 ),
				substr( $hex, 16, 4 ),
				substr( $hex, 20, 12 )
			);
		}

		$hash = md5( uniqid( 'alynt-drime-backups-', true ) );

		return sprintf(
			'%s-%s-4%s-%s-%s',
			substr( $hash, 0, 8 ),
			substr( $hash, 8, 4 ),
			substr( $hash, 13, 3 ),
			substr( '89ab', absint( hexdec( substr( $hash, 16, 1 ) ) ) % 4, 1 ) . substr( $hash, 17, 3 ),
			substr( $hash, 20, 12 )
		);
	}
}
