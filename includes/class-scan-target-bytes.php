<?php
/**
 * Internal spans over already validated plain serialization; no unserialization.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-serialized-settings-parser.php';

/** Reuses the unchanged primitive grammar; not the settings disable contract. */
final class Alynt_Drime_Backups_Uploader_Scan_Target_Bytes {
	/**
	 * Internal private payload representation, never a response or authority.
	 *
	 * @param mixed $raw At most one MiB of plain root-array bytes.
	 * @return array|false Validated value and root entry/value byte spans.
	 */
	public static function parse( $raw ) {
		$parsed = Alynt_Drime_Backups_Uploader_Serialized_Settings_Parser::parse( $raw );
		if ( false === $parsed ) {
			return false;
		}
		$position = strpos( $raw, '{' ) + 1;
		$spans    = array();
		foreach ( $parsed['value'] as $key => $value ) {
			$start = $position;
			self::skip( $raw, $position );
			$value_start = $position;
			self::skip( $raw, $position );
			$spans[ $key ] = array( $start, $value_start, $position );
		}
		return array(
			'value' => $parsed['value'],
			'spans' => $spans,
		);
	}

	/**
	 * Skip VALIDATED tokens only. Grammar, lengths, node/depth bounds checked first.
	 *
	 * @param string $raw Immutable bounded bytes.
	 * @param int    $position Private cursor.
	 * @return void
	 */
	private static function skip( $raw, &$position ) {
		$type = $raw[ $position ];
		if ( 'N' === $type ) {
			$position += 2;
			return;
		}
		$position += 2;
		if ( 'i' === $type || 'b' === $type ) {
			$position = strpos( $raw, ';', $position ) + 1;
			return;
		}
		$end      = strpos( $raw, ':', $position );
		$count    = (int) substr( $raw, $position, $end - $position );
		$position = $end + 1;
		if ( 's' === $type ) {
			$position += $count + 3;
			return;
		}
		++$position;
		for ( $i = 0; $i < $count * 2; ++$i ) {
			self::skip( $raw, $position );
		}
		++$position;
	}

	/**
	 * Preserve exact unrelated spans when rebuilding a root array.
	 *
	 * @param array $entries Ordered exact encoded entries.
	 * @return string
	 */
	public static function root( $entries ) {
		return 'a:' . count( $entries ) . ':{' . implode( '', $entries ) . '}';
	}
}
