<?php
/**
 * Health summary WPvivid activity helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans redacted WPvivid local activity evidence without exposing paths.
 *
 * @since 0.5.22
 */
trait Alynt_Drime_Backups_Uploader_Health_Summary_WPvivid_Activity {
	/**
	 * Returns redacted source-side activity evidence that is not equivalent to upload proof.
	 *
	 * @param string              $source_key Source key.
	 * @param array<string,mixed> $settings Settings.
	 * @return array{latest_source_activity_at:int,source_activity_evidence:string,local_candidate_count:int}
	 */
	private function source_activity_evidence( $source_key, array $settings ) {
		if ( 'wpvivid' !== $source_key ) {
			return $this->empty_source_activity();
		}

		$detector  = new Alynt_Drime_Backups_Uploader_WPvivid_Detector();
		$directory = $detector->get_backup_dir( $settings );

		if ( ! is_dir( $directory ) || ! is_readable( $directory ) ) {
			return $this->empty_source_activity();
		}

		$activity                 = $this->wpvivid_activity_scan( $directory );
		$latest_archive_time      = $activity['latest_archive_time'];
		$latest_log_time          = $activity['latest_log_time'];
		$latest_activity_time     = max( $latest_archive_time, $latest_log_time );
		$source_activity_evidence = '';

		if ( $latest_archive_time > 0 && $latest_archive_time >= $latest_log_time ) {
			$source_activity_evidence = 'wpvivid_local_archive';
		} elseif ( $latest_log_time > 0 ) {
			$source_activity_evidence = 'wpvivid_backup_log';
		}

		return array(
			'latest_source_activity_at' => $latest_activity_time,
			'source_activity_evidence'  => $source_activity_evidence,
			'local_candidate_count'     => $activity['archive_count'],
		);
	}

	/**
	 * Returns an empty source activity summary.
	 *
	 * @return array{latest_source_activity_at:int,source_activity_evidence:string,local_candidate_count:int}
	 */
	private function empty_source_activity() {
		return array(
			'latest_source_activity_at' => 0,
			'source_activity_evidence'  => '',
			'local_candidate_count'     => 0,
		);
	}

	/**
	 * Scans WPvivid activity evidence without allocating glob match arrays.
	 *
	 * @param string $directory WPvivid backup directory.
	 * @return array{latest_archive_time:int,latest_log_time:int,archive_count:int}
	 */
	private function wpvivid_activity_scan( $directory ) {
		$activity = array(
			'latest_archive_time' => 0,
			'latest_log_time'     => 0,
			'archive_count'       => 0,
		);

		$this->scan_wpvivid_activity_directory( $directory, false, $activity );

		$log_directory = trailingslashit( $directory ) . 'wpvivid_log';
		if ( is_dir( $log_directory ) && is_readable( $log_directory ) ) {
			$this->scan_wpvivid_activity_directory( $log_directory, true, $activity );
		}

		return $activity;
	}

	/**
	 * Scans one directory for WPvivid archive/log evidence.
	 *
	 * @param string            $directory Directory.
	 * @param bool              $logs_only Whether only log files should be considered.
	 * @param array<string,int> $activity Activity accumulator.
	 * @return void
	 */
	private function scan_wpvivid_activity_directory( $directory, $logs_only, array &$activity ) {
		try {
			$iterator = new DirectoryIterator( $directory );
		} catch ( Exception $exception ) {
			unset( $exception );
			return;
		}

		foreach ( $iterator as $file ) {
			if ( $file->isDot() || ! $file->isFile() ) {
				continue;
			}

			$name  = $file->getFilename();
			$mtime = $this->readable_non_empty_file_mtime( $file->getPathname() );

			if ( $mtime <= 0 ) {
				continue;
			}

			if ( ! $logs_only && $this->string_ends_with( $name, '.zip' ) ) {
				++$activity['archive_count'];
				$activity['latest_archive_time'] = max( $activity['latest_archive_time'], $mtime );
				continue;
			}

			if ( $this->string_ends_with( $name, 'backup_log.txt' ) ) {
				$activity['latest_log_time'] = max( $activity['latest_log_time'], $mtime );
			}
		}
	}

	/**
	 * Returns a readable non-empty file modification time.
	 *
	 * @param string $file File path.
	 * @return int
	 */
	private function readable_non_empty_file_mtime( $file ) {
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			return 0;
		}

		$size  = filesize( $file );
		$mtime = filemtime( $file );

		return false === $size || false === $mtime || $size <= 0 ? 0 : max( 0, (int) $mtime );
	}

	/**
	 * Returns whether a string ends with a suffix.
	 *
	 * @param string $value Value.
	 * @param string $suffix Suffix.
	 * @return bool
	 */
	private function string_ends_with( $value, $suffix ) {
		$value  = (string) $value;
		$suffix = (string) $suffix;

		return '' === $suffix || substr( $value, -strlen( $suffix ) ) === $suffix;
	}
}
