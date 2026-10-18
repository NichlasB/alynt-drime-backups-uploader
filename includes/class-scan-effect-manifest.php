<?php
/**
 * Fixed hypothetical row manifest; no reader, SQL or native owner.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Test-loaded data comparison only. */
final class Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest {
	/**
	 * Fixed role/name/byte-cap order, never caller-selected targets.
	 *
	 * @return array
	 */
	public static function rows() {
		return array(
			'safety'           => array( 'alynt_drime_backups_schedule_safety_state', 131072 ),
			'settings'         => array( 'alynt_drime_backups_settings', 1048576 ),
			'connection'       => array( 'alynt_drime_backups_dashboard_connection', 131072 ),
			'ledger'           => array( 'alynt_drime_backups_scan_effect_state', 131072 ),
			'wpvivid_snapshot' => array( 'alynt_drime_backups_file_snapshots', 524288 ),
			'outbox_snapshot'  => array( 'alynt_drime_backups_outbox_file_snapshots', 524288 ),
			'queue'            => array( 'alynt_drime_backups_upload_queue', 1048576 ),
			'active'           => array( 'alynt_drime_backups_active_upload', 262144 ),
			'uploaded'         => array( 'alynt_drime_backups_uploaded_files', 1048576 ),
			'failed'           => array( 'alynt_drime_backups_failed_uploads', 1048576 ),
		);
	}

	/**
	 * Digest describes this static manifest, not actual participant discovery.
	 *
	 * @return string
	 */
	public static function digest() {
		return hash( 'sha256', json_encode( self::rows() ) );
	}

	/**
	 * Fixed non-authorizing output shared by the dormant data contracts.
	 *
	 * @return array
	 */
	public static function disposition() {
		return array(
			'candidate_only'      => true,
			'storage_supported'   => false,
			'dispatch_supported'  => false,
			'execution_supported' => false,
		);
	}

	/**
	 * Compare synthetic exact tuples; never certify a query or acquire a lock.
	 *
	 * @param mixed $before Original scope and ordered rows.
	 * @param mixed $after Re-observed scope and ordered rows.
	 * @return array Redacted comparison with no transferable data/handle.
	 */
	public static function compare( $before, $after ) {
		return self::disposition() + array( 'same_candidate' => 2 === func_num_args() && self::valid( $before ) && self::valid( $after ) && $before === $after );
	}

	/**
	 * Fixed scope and bounded raw tuples only, not trusted provenance.
	 *
	 * @param mixed $frame Synthetic observation.
	 * @return bool
	 */
	private static function valid( $frame ) {
		if ( ! is_array( $frame ) || array_keys( $frame ) !== array( 'blog_id', 'prefix', 'database', 'rows' ) || ! is_int( $frame['blog_id'] ) || $frame['blog_id'] < 1 || ! is_string( $frame['prefix'] ) || 1 !== preg_match( '/^[A-Za-z0-9_]{1,64}$/D', $frame['prefix'] ) || ! is_string( $frame['database'] ) || 1 !== preg_match( '/^[A-Za-z0-9_]{1,64}$/D', $frame['database'] ) || ! is_array( $frame['rows'] ) || array_keys( $frame['rows'] ) !== array_keys( self::rows() ) ) {
			return false;
		}
		$ids   = array();
		$bytes = 0;
		foreach ( self::rows() as $role => $definition ) {
			$row = $frame['rows'][ $role ];
			if ( ! is_array( $row ) || array_keys( $row ) !== array( 'option_id', 'option_name', 'autoload', 'octets', 'value' ) || ! is_string( $row['option_id'] ) || strlen( $row['option_id'] ) > 20 || 1 !== preg_match( '/^[1-9][0-9]*$/D', $row['option_id'] ) || isset( $ids[ $row['option_id'] ] ) || $row['option_name'] !== $definition[0] || ! in_array( $row['autoload'], array( 'no', 'off', 'auto-off' ), true ) || ! is_string( $row['value'] ) || '' === $row['value'] || strlen( $row['value'] ) > $definition[1] || (string) strlen( $row['value'] ) !== $row['octets'] ) {
				return false;
			}
			$max = (string) ( PHP_INT_MAX - 1 );
			if ( strlen( $row['option_id'] ) > strlen( $max ) || ( strlen( $row['option_id'] ) === strlen( $max ) && strcmp( $row['option_id'], $max ) > 0 ) ) {
				return false;
			}
			$ids[ $row['option_id'] ] = true;
			$bytes                   += strlen( $row['value'] );
		}
		return $bytes <= 6291456;
	}
}
