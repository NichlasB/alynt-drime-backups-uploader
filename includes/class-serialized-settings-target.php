<?php
/**
 * Pure disable-only byte-span codec; never a settings writer or permission.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Explicit test loading only. Private after bytes must never enter receipts. */
final class Alynt_Drime_Backups_Uploader_Serialized_Settings_Target {
	/**
	 * Preserve every unrelated byte and reject unsupported input without repair.
	 *
	 * @param mixed $raw Existing private settings bytes.
	 * @param mixed $site Existing canonical UUID, never generated here.
	 * @param mixed $autoload Existing non-autoload marker.
	 * @return array|false Private non-admitting data or fixed failure.
	 */
	public static function inspect( $raw, $site, $autoload ) {
		if ( ! is_string( $site ) || 1 !== preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $site ) || ! in_array( $autoload, array( 'no', 'off', 'auto-off' ), true ) ) {
			return false;
		}
		$parsed = Alynt_Drime_Backups_Uploader_Serialized_Settings_Parser::parse( $raw );
		if ( false === $parsed || ( $parsed['value']['site_uuid'] ?? null ) !== $site || ! isset( $parsed['value']['auto_scan_enabled'] ) || ! is_bool( $parsed['value']['auto_scan_enabled'] ) || ! is_int( $parsed['offset'] ) ) {
			return false;
		}
		$enabled                              = $parsed['value']['auto_scan_enabled'];
		$after                                = $enabled ? substr_replace( $raw, 'b:0;', $parsed['offset'], 4 ) : $raw;
		$check                                = Alynt_Drime_Backups_Uploader_Serialized_Settings_Parser::parse( $after );
		$parsed['value']['auto_scan_enabled'] = false;
		if ( false === $check || $check['value'] !== $parsed['value'] || $check['offset'] !== $parsed['offset'] ) {
			return false;
		}
		return array(
			'codec_version' => 'php_settings_disable_v1',
			'site_uuid'     => $site,
			'enabled'       => $enabled,
			'before_hash'   => hash( 'sha256', $raw ),
			'after_hash'    => hash( 'sha256', $after ),
			'after'         => $after,
		);
	}
}
