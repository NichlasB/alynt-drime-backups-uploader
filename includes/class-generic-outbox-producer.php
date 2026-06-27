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
	 * @return string
	 */
	public function key() {
		return self::KEY;
	}

	/**
	 * Returns the human-readable producer label.
	 *
	 * @return string
	 */
	public function label() {
		return self::LABEL;
	}

	/**
	 * Scans the configured outbox for stable archive packages.
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
	 * Scans archive files and updates stability snapshots.
	 *
	 * @param string              $directory Directory.
	 * @param array<string,mixed> $settings Settings.
	 * @return array{file_infos:array<int,array<string,mixed>>,errors:array<int,string>}
	 */
	private function scan_file_infos( $directory, array $settings ) {
		$snapshots     = $this->get_snapshots();
		$new_snapshots = array();
		$minimum_age   = max( 60, absint( $settings['min_file_age_seconds'] ) );
		$now           = time();
		$file_infos    = array();
		$errors        = array();

		foreach ( $this->list_archives( $directory ) as $file ) {
			$info = $this->scan_file_info( $file, $snapshots, $minimum_age, $now );
			if ( empty( $info ) ) {
				continue;
			}

			$new_snapshots[ $info['snapshot_key'] ] = array(
				'size'  => $info['size'],
				'mtime' => $info['mtime'],
			);
			$file_infos[]                           = $info;
		}

		if ( ! $this->persist_array_option( self::SNAPSHOT_OPTION, $new_snapshots ) ) {
			$errors[] = __( 'The backup outbox scan state could not be saved. Confirm the site database is writable, then try again.', 'alynt-drime-backups-uploader' );
			$this->diagnostic( 'error', 'outbox_snapshot_save_failed', 'The backup outbox scan state could not be saved.' );
		}

		return array(
			'file_infos' => $file_infos,
			'errors'     => $errors,
		);
	}

	/**
	 * Lists top-level completed archive files.
	 *
	 * @param string $directory Directory.
	 * @return array<int,string>
	 */
	private function list_archives( $directory ) {
		$files = array();

		try {
			$iterator = new DirectoryIterator( $directory );
		} catch ( UnexpectedValueException $e ) {
			$this->diagnostic( 'error', 'outbox_directory_scan_failed', 'The backup outbox directory could not be scanned.', array( 'reason' => $e->getMessage() ) );
			return $files;
		}

		foreach ( $iterator as $file_info ) {
			try {
				if ( ! $file_info->isFile() ) {
					continue;
				}

				$path = $file_info->getPathname();
				$name = $file_info->getFilename();
				if ( $this->looks_temporary( $name ) || ! $this->is_supported_archive( $name ) || ! is_readable( $path ) ) {
					continue;
				}

				$files[] = $path;
			} catch ( RuntimeException $e ) {
				$this->diagnostic( 'warning', 'outbox_file_scan_skipped', 'A backup outbox file could not be inspected during scan.', array( 'reason' => $e->getMessage() ) );
			}
		}

		sort( $files );

		return $files;
	}

	/**
	 * Builds one scanned file info entry.
	 *
	 * @param string                          $file File.
	 * @param array<string,array<string,int>> $snapshots Snapshots.
	 * @param int                             $minimum_age Minimum age.
	 * @param int                             $now Current timestamp.
	 * @return array<string,mixed>
	 */
	private function scan_file_info( $file, array $snapshots, $minimum_age, $now ) {
		$size  = filesize( $file );
		$mtime = filemtime( $file );
		if ( false === $size || false === $mtime || $size <= 0 ) {
			return array();
		}

		$snapshot_key = $this->snapshot_key( $file );
		$previous     = isset( $snapshots[ $snapshot_key ] ) && is_array( $snapshots[ $snapshot_key ] ) ? $snapshots[ $snapshot_key ] : array();
		$stable       = isset( $previous['size'] ) && (int) $previous['size'] === (int) $size && ( $now - (int) $mtime ) >= $minimum_age;

		return array(
			'snapshot_key' => $snapshot_key,
			'signature'    => $this->package_signature( $file, $size, $mtime ),
			'path'         => $file,
			'name'         => basename( $file ),
			'size'         => $size,
			'mtime'        => $mtime,
			'stable'       => $stable,
		);
	}

	/**
	 * Adds producer-neutral package fields.
	 *
	 * @param array<string,mixed> $info Scanned file info.
	 * @return array<string,mixed>
	 */
	private function normalize_package( array $info ) {
		$manifest          = $this->read_manifest( (string) $info['path'] );
		$checksum          = $this->read_checksum( (string) $info['path'] );
		$remote_index      = $this->read_remote_index( (string) $info['path'] );
		$manifest_path     = isset( $manifest['_path'] ) ? (string) $manifest['_path'] : '';
		$checksum_path     = isset( $checksum['path'] ) ? (string) $checksum['path'] : '';
		$remote_index_path = isset( $remote_index['path'] ) ? (string) $remote_index['path'] : '';
		$package_id        = isset( $manifest['package_id'] ) && '' !== (string) $manifest['package_id'] ? (string) $manifest['package_id'] : (string) $info['signature'];

		$info['package_id']         = $package_id;
		$info['producer_key']       = $this->key();
		$info['producer_label']     = $this->label();
		$info['filename']           = (string) $info['name'];
		$info['modified_time']      = isset( $info['mtime'] ) ? (int) $info['mtime'] : 0;
		$info['backup_set_id']      = isset( $manifest['backup_set_id'] ) ? (string) $manifest['backup_set_id'] : $package_id;
		$info['backup_set_part']    = '';
		$info['backup_set_total']   = 1;
		$info['manifest_path']      = $manifest_path;
		$info['checksum_path']      = $checksum_path;
		$info['remote_index_path']  = $remote_index_path;
		$info['checksum_algorithm'] = isset( $checksum['algorithm'] ) ? (string) $checksum['algorithm'] : '';
		$info['checksum_value']     = isset( $checksum['value'] ) ? (string) $checksum['value'] : '';
		$info['site_url']           = isset( $manifest['site_url'] ) ? (string) $manifest['site_url'] : '';
		$info['created_at']         = isset( $manifest['created_at'] ) ? $this->normalize_timestamp( $manifest['created_at'] ) : 0;
		$info['metadata']           = array(
			'generic_outbox' => array(
				'manifest'     => $this->manifest_metadata( $manifest ),
				'checksum'     => $checksum,
				'remote_index' => $remote_index,
			),
		);

		return $info;
	}

	/**
	 * Filters scanned files down to stable upload candidates.
	 *
	 * @param array<int,array<string,mixed>> $file_infos File info.
	 * @return array<int,array<string,mixed>>
	 */
	private function filter_complete_candidates( array $file_infos ) {
		$candidates = array();

		foreach ( $file_infos as $info ) {
			if ( empty( $info['stable'] ) ) {
				continue;
			}

			$info = $this->normalize_package( $info );
			unset( $info['stable'], $info['snapshot_key'] );
			$candidates[] = $info;
		}

		return $candidates;
	}

	/**
	 * Reads package manifest sidecar metadata.
	 *
	 * @param string $file Archive path.
	 * @return array<string,mixed>
	 */
	private function read_manifest( $file ) {
		foreach ( $this->sidecar_candidates( $file, 'manifest.json' ) as $candidate ) {
			if ( ! is_readable( $candidate ) ) {
				continue;
			}

			$contents = file_get_contents( $candidate );
			$decoded  = false !== $contents ? json_decode( $contents, true ) : null;
			if ( is_array( $decoded ) ) {
				$decoded['_path'] = $candidate;
				return $decoded;
			}
		}

		return array();
	}

	/**
	 * Reads checksum sidecar metadata.
	 *
	 * @param string $file Archive path.
	 * @return array<string,string>
	 */
	private function read_checksum( $file ) {
		foreach ( array( 'sha256', 'sha256sum' ) as $suffix ) {
			foreach ( $this->sidecar_candidates( $file, $suffix ) as $candidate ) {
				if ( ! is_readable( $candidate ) ) {
					continue;
				}

				$contents = trim( (string) file_get_contents( $candidate ) );
				if ( preg_match( '/^([a-fA-F0-9]{64})(?:\s+\*?.*)?$/', $contents, $matches ) ) {
					return array(
						'path'      => $candidate,
						'algorithm' => 'sha256',
						'value'     => strtolower( $matches[1] ),
					);
				}
			}
		}

		return array();
	}

	/**
	 * Reads package remote index sidecar metadata.
	 *
	 * @param string $file Archive path.
	 * @return array<string,mixed>
	 */
	private function read_remote_index( $file ) {
		foreach ( $this->sidecar_candidates( $file, 'remote-index.json' ) as $candidate ) {
			if ( ! is_readable( $candidate ) ) {
				continue;
			}

			$contents = file_get_contents( $candidate );
			$decoded  = false !== $contents ? json_decode( $contents, true ) : null;
			if ( is_array( $decoded ) ) {
				return array(
					'path'           => $candidate,
					'schema_version' => isset( $decoded['schema_version'] ) ? (int) $decoded['schema_version'] : 0,
					'index_type'     => isset( $decoded['index_type'] ) && is_scalar( $decoded['index_type'] ) ? (string) $decoded['index_type'] : '',
					'package_count'  => isset( $decoded['package_count'] ) ? (int) $decoded['package_count'] : 0,
				);
			}
		}

		return array();
	}

	/**
	 * Builds possible sidecar paths.
	 *
	 * @param string $file Archive path.
	 * @param string $suffix Sidecar suffix.
	 * @return array<int,string>
	 */
	private function sidecar_candidates( $file, $suffix ) {
		$stem = $this->archive_stem( $file );

		return array_values(
			array_unique(
				array(
					$file . '.' . $suffix,
					$stem . '.' . $suffix,
				)
			)
		);
	}

	/**
	 * Returns an archive path without its known archive extension.
	 *
	 * @param string $file Archive path.
	 * @return string
	 */
	private function archive_stem( $file ) {
		foreach ( $this->archive_extensions() as $extension ) {
			if ( $this->ends_with( strtolower( $file ), $extension ) ) {
				return substr( $file, 0, -strlen( $extension ) );
			}
		}

		return $file;
	}

	/**
	 * Returns whether a file is a supported package archive.
	 *
	 * @param string $name File name.
	 * @return bool
	 */
	private function is_supported_archive( $name ) {
		$lower = strtolower( $name );

		foreach ( $this->archive_extensions() as $extension ) {
			if ( $this->ends_with( $lower, $extension ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns supported archive extensions.
	 *
	 * @return array<int,string>
	 */
	private function archive_extensions() {
		return array( '.tar.zst', '.tar.gz', '.tgz', '.zip', '.tar' );
	}

	/**
	 * Determines whether a filename looks incomplete.
	 *
	 * @param string $name File name.
	 * @return bool
	 */
	private function looks_temporary( $name ) {
		$lower = strtolower( basename( $name ) );

		return (bool) preg_match( '/(?:\.tmp|\.part|\.partial|temp|partial|incomplete)(?:$|\.)/', $lower );
	}

	/**
	 * Builds a stable snapshot key for file stability tracking.
	 *
	 * @param string $file File path.
	 * @return string
	 */
	private function snapshot_key( $file ) {
		return hash( 'sha256', wp_normalize_path( $file ) );
	}

	/**
	 * Builds a package signature that changes when a path is replaced.
	 *
	 * @param string $file File path.
	 * @param int    $size File size.
	 * @param int    $mtime Modified timestamp.
	 * @return string
	 */
	private function package_signature( $file, $size, $mtime ) {
		return hash( 'sha256', wp_normalize_path( $file ) . '|' . (int) $size . '|' . (int) $mtime );
	}

	/**
	 * Normalizes a filesystem path.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function normalize_path( $path ) {
		return untrailingslashit( wp_normalize_path( trim( $path ) ) );
	}

	/**
	 * Normalizes manifest timestamps.
	 *
	 * @param mixed $value Timestamp value.
	 * @return int
	 */
	private function normalize_timestamp( $value ) {
		if ( is_numeric( $value ) ) {
			return absint( $value );
		}

		$timestamp = strtotime( (string) $value );

		return false === $timestamp ? 0 : (int) $timestamp;
	}

	/**
	 * Returns manifest metadata without internal helper keys.
	 *
	 * @param array<string,mixed> $manifest Manifest.
	 * @return array<string,mixed>
	 */
	private function manifest_metadata( array $manifest ) {
		unset( $manifest['_path'] );

		return $manifest;
	}

	/**
	 * Checks whether a string ends with a suffix.
	 *
	 * @param string $value Value.
	 * @param string $suffix Suffix.
	 * @return bool
	 */
	private function ends_with( $value, $suffix ) {
		return substr( $value, -strlen( $suffix ) ) === $suffix;
	}

	/**
	 * Writes a scanner diagnostic event.
	 *
	 * @param string              $level Level.
	 * @param string              $code Event code.
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @return void
	 */
	private function diagnostic( $level, $code, $message, array $context = array() ) {
		if ( $this->logger instanceof Alynt_Drime_Backups_Uploader_Logger ) {
			$this->logger->event( 'filesystem', $level, $code, $message, $context );
		}
	}

	/**
	 * Returns snapshots.
	 *
	 * @return array<string,array<string,int>>
	 */
	private function get_snapshots() {
		$snapshots = get_option( self::SNAPSHOT_OPTION, array() );

		return is_array( $snapshots ) ? $snapshots : array();
	}
}
