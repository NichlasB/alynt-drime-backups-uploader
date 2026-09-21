<?php
/**
 * WPvivid backup producer.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans completed WPvivid local backups and returns normalized packages.
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_WPvivid_Producer implements Alynt_Drime_Backups_Uploader_Producer_Interface {
	use Alynt_Drime_Backups_Uploader_Scanner_Metadata;
	use Alynt_Drime_Backups_Uploader_Option_Storage;
	use Alynt_Drime_Backups_Uploader_WPvivid_Producer_Scan_Helpers;

	const KEY             = 'wpvivid';
	const LABEL           = 'WPvivid';
	const SNAPSHOT_OPTION = 'alynt_drime_backups_file_snapshots';

	/**
	 * Settings.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Settings
	 */
	private $settings;

	/**
	 * Detector.
	 *
	 * @var Alynt_Drime_Backups_Uploader_WPvivid_Detector
	 */
	private $detector;

	/**
	 * Logger.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Logger|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Settings         $settings Settings.
	 * @param Alynt_Drime_Backups_Uploader_WPvivid_Detector $detector Detector.
	 * @param Alynt_Drime_Backups_Uploader_Logger|null      $logger Logger.
	 *
	 * @since 0.1.0
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Settings $settings, Alynt_Drime_Backups_Uploader_WPvivid_Detector $detector, ?Alynt_Drime_Backups_Uploader_Logger $logger = null ) {
		$this->settings = $settings;
		$this->detector = $detector;
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
	 * Scans for stable ZIP backups.
	 *
	 * @return array{directory:string,candidates:array<int,array<string,mixed>>,errors:array<int,string>}
	 *
	 * @since 0.1.0
	 */
	public function scan() {
		$settings  = $this->settings->get();
		$directory = $this->detector->get_backup_dir( $settings );
		$result    = $this->empty_scan_result( $directory );

		if ( ! is_dir( $directory ) || ! is_readable( $directory ) ) {
			return $this->unreadable_directory_result( $directory, $result );
		}

		$files = glob( trailingslashit( $directory ) . '*.zip' );
		if ( ! is_array( $files ) ) {
			$this->diagnostic( 'warning', 'backup_glob_failed', 'The backup directory scan did not return a file list.', array( 'directory' => $directory ) );
			return $result;
		}

		$scan = $this->scan_file_infos( $files, $settings );
		if ( ! empty( $scan['errors'] ) ) {
			$result['errors'] = array_merge( $result['errors'], $scan['errors'] );
		}

		$result['candidates'] = $this->filter_complete_candidates( $scan['file_infos'] );

		return $result;
	}

	/**
	 * Builds a stable local signature.
	 *
	 * @param string $file File path.
	 * @return string
	 *
	 * @since 0.1.0
	 */
	public function signature( $file ) {
		return hash( 'sha256', wp_normalize_path( $file ) );
	}

	/**
	 * Returns an empty scan result.
	 *
	 * @param string $directory Directory.
	 * @return array{directory:string,candidates:array<int,array<string,mixed>>,errors:array<int,string>}
	 */
	private function empty_scan_result( $directory ) {
		return array(
			'directory'  => $directory,
			'candidates' => array(),
			'errors'     => array(),
		);
	}

	/**
	 * Builds a scan result for an unreadable directory.
	 *
	 * @param string              $directory Directory.
	 * @param array<string,mixed> $result Result.
	 * @return array<string,mixed>
	 */
	private function unreadable_directory_result( $directory, array $result ) {
		$result['errors'][] = __( 'The WPvivid backup directory is not readable.', 'alynt-drime-backups-uploader' );
		$this->diagnostic( 'error', 'backup_directory_unreadable', 'The WPvivid backup directory is not readable.', array( 'directory' => $directory ) );

		return $result;
	}

	/**
	 * Builds scanned file info.
	 *
	 * @param array<int,string>   $files Files.
	 * @param array<string,mixed> $settings Settings.
	 * @return array{file_infos:array<string,array<string,mixed>>,errors:array<int,string>}
	 */
	private function scan_file_infos( array $files, array $settings ) {
		$snapshots       = $this->get_snapshots();
		$new_snapshots   = array();
		$minimum_age     = max( 60, absint( $settings['min_file_age_seconds'] ) );
		$now             = time();
		$backup_metadata = $this->get_backup_list_metadata();
		$file_infos      = array();
		$errors          = array();

		foreach ( $files as $file ) {
			$info = $this->scan_file_info( $file, $snapshots, $minimum_age, $now, $backup_metadata );
			if ( empty( $info ) ) {
				continue;
			}

			$new_snapshots[ $info['signature'] ] = array(
				'size'  => $info['size'],
				'mtime' => $info['mtime'],
			);
			$file_infos[ $info['name'] ]         = $info;
		}

		if ( ! $this->persist_array_option( self::SNAPSHOT_OPTION, $new_snapshots ) ) {
			$errors[] = __( 'The WPvivid backup scan state could not be saved. Confirm the site database is writable, then try again.', 'alynt-drime-backups-uploader' );
			$this->diagnostic( 'error', 'wpvivid_snapshot_save_failed', 'The WPvivid backup scan state could not be saved.' );
		}

		return array(
			'file_infos' => $file_infos,
			'errors'     => $errors,
		);
	}

	/**
	 * Builds one scanned file info entry.
	 *
	 * @param string                            $file File.
	 * @param array<string,array<string,int>>   $snapshots Snapshots.
	 * @param int                               $minimum_age Minimum age.
	 * @param int                               $now Current timestamp.
	 * @param array<string,array<string,mixed>> $backup_metadata Backup metadata.
	 * @return array<string,mixed>
	 */
	private function scan_file_info( $file, array $snapshots, $minimum_age, $now, array $backup_metadata ) {
		if ( ! is_file( $file ) || ! is_readable( $file ) || $this->looks_temporary( $file ) ) {
			return array();
		}

		$name  = basename( $file );
		$size  = filesize( $file );
		$mtime = filemtime( $file );
		if ( false === $size || false === $mtime || $size <= 0 ) {
			return array();
		}

		$key      = $this->signature( $file );
		$previous = isset( $snapshots[ $key ] ) && is_array( $snapshots[ $key ] ) ? $snapshots[ $key ] : array();

		return $this->normalize_package(
			array(
				'signature' => $key,
				'path'      => $file,
				'name'      => $name,
				'size'      => $size,
				'mtime'     => $mtime,
				'stable'    => isset( $previous['size'] ) && (int) $previous['size'] === (int) $size && ( $now - (int) $mtime ) >= $minimum_age,
				'wpvivid'   => $this->metadata_for_file( $name, $backup_metadata ),
			)
		);
	}

	/**
	 * Adds normalized producer-neutral package fields while preserving legacy item fields.
	 *
	 * @param array<string,mixed> $info Scanned file info.
	 * @return array<string,mixed>
	 */
	private function normalize_package( array $info ) {
		$metadata = isset( $info['wpvivid'] ) && is_array( $info['wpvivid'] ) ? $info['wpvivid'] : array();

		$info['package_id']         = (string) $info['signature'];
		$info['producer_key']       = $this->key();
		$info['producer_label']     = $this->label();
		$info['filename']           = (string) $info['name'];
		$info['modified_time']      = isset( $info['mtime'] ) ? (int) $info['mtime'] : 0;
		$info['backup_set_id']      = isset( $metadata['set_signature'] ) ? (string) $metadata['set_signature'] : '';
		$info['backup_set_part']    = '';
		$info['backup_set_total']   = isset( $metadata['set_file_count'] ) ? absint( $metadata['set_file_count'] ) : 0;
		$info['manifest_path']      = '';
		$info['checksum_path']      = '';
		$info['checksum_algorithm'] = '';
		$info['checksum_value']     = '';
		$info['site_url']           = '';
		$info['created_at']         = 0;
		$info['metadata']           = array(
			'wpvivid' => $metadata,
		);

		return $info;
	}
}
