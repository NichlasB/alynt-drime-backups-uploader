<?php
/**
 * Generic backup outbox producer.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans completed archive packages from a configured outbox directory.
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_Generic_Outbox_Producer implements Alynt_Drime_Backups_Uploader_Producer_Interface {
	use Alynt_Drime_Backups_Uploader_Option_Storage;
	use Alynt_Drime_Backups_Uploader_Generic_Outbox_Producer_Scan;
	use Alynt_Drime_Backups_Uploader_Generic_Outbox_Producer_Normalization;
	use Alynt_Drime_Backups_Uploader_Generic_Outbox_Producer_Sidecars;
	use Alynt_Drime_Backups_Uploader_Generic_Outbox_Producer_Archive_Helpers;
	use Alynt_Drime_Backups_Uploader_Generic_Outbox_Producer_Diagnostics;

	const KEY             = 'generic_outbox';
	const LABEL           = 'Generic Outbox';
	const SNAPSHOT_OPTION = 'alynt_drime_backups_outbox_file_snapshots';

	/**
	 * Settings.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Settings
	 */
	private $settings;

	/**
	 * Logger.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Logger|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Settings    $settings Settings.
	 * @param Alynt_Drime_Backups_Uploader_Logger|null $logger Logger.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Settings $settings, ?Alynt_Drime_Backups_Uploader_Logger $logger = null ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Returns the stable producer key.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function key() {
		return self::KEY;
	}

	/**
	 * Returns the human-readable producer label.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function label() {
		return self::LABEL;
	}

	/**
	 * Scans the configured outbox for stable archive packages.
	 *
	 * @since 0.1.0
	 *
	 * @return array{directory:string,candidates:array<int,array<string,mixed>>,errors:array<int,string>}
	 */
	public function scan() {
		$settings  = $this->settings->get();
		$directory = isset( $settings['server_outbox_path'] ) ? $this->normalize_path( (string) $settings['server_outbox_path'] ) : '';
		$result    = $this->empty_scan_result( $directory );

		if ( '' === $directory ) {
			return $result;
		}

		if ( ! is_dir( $directory ) || ! is_readable( $directory ) ) {
			$result['errors'][] = __( 'The backup outbox directory is not readable.', 'alynt-drime-backups-uploader' );
			$this->diagnostic( 'error', 'outbox_directory_unreadable', 'The backup outbox directory is not readable.', array( 'directory' => $directory ) );

			return $result;
		}

		$scan = $this->scan_file_infos( $directory, $settings );

		if ( ! empty( $scan['errors'] ) ) {
			$result['errors'] = array_merge( $result['errors'], $scan['errors'] );
		}

		$result['candidates'] = $this->filter_complete_candidates( $scan['file_infos'] );

		return $result;
	}
}
