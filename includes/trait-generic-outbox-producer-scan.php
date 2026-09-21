<?php
/**
 * Generic outbox scan helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans outbox archive files and filters stable upload candidates.
 *
 * @since 0.5.22
 */
trait Alynt_Drime_Backups_Uploader_Generic_Outbox_Producer_Scan {
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
}
