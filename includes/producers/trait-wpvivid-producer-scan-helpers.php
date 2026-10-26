<?php
/**
 * WPvivid producer scan helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPvivid producer scan helpers.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_WPvivid_Producer_Scan_Helpers {
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

	/**
	 * Determines whether a filename looks incomplete.
	 *
	 * @param string $file File path.
	 * @return bool
	 */
	private function looks_temporary( $file ) {
		$name = strtolower( basename( $file ) );

		if ( $this->is_split_part( $name ) ) {
			return false;
		}

		return (bool) preg_match( '/(\.tmp|\.part|temp|partial|incomplete)/', $name );
	}

	/**
	 * Filters scanned files down to complete, stable upload candidates.
	 *
	 * @param array<string,array<string,mixed>> $file_infos File info keyed by basename.
	 * @return array<int,array<string,mixed>>
	 */
	private function filter_complete_candidates( array $file_infos ) {
		$candidates   = array();
		$blocked_sets = array();

		foreach ( $file_infos as $name => $info ) {
			if ( empty( $info['stable'] ) ) {
				continue;
			}

			$metadata = isset( $info['wpvivid'] ) && is_array( $info['wpvivid'] ) ? $info['wpvivid'] : array();

			if ( ! empty( $metadata['from_list'] ) ) {
				if ( ! $this->is_listed_set_complete( $metadata, $file_infos ) ) {
					$set_signature = isset( $metadata['set_signature'] ) ? (string) $metadata['set_signature'] : (string) $name;
					if ( ! isset( $blocked_sets[ $set_signature ] ) ) {
						$this->diagnostic( 'warning', 'wpvivid_backup_set_incomplete', 'A WPvivid backup set was not queued because not all listed files are present and stable.', array( 'backup_id' => isset( $metadata['backup_id'] ) ? (string) $metadata['backup_id'] : '' ) );
						$blocked_sets[ $set_signature ] = true;
					}
					continue;
				}
			} elseif ( $this->is_split_part( $name ) ) {
				$this->diagnostic( 'warning', 'wpvivid_split_file_without_list', 'A split WPvivid backup part was not queued because no completed backup-list entry was found.', array( 'file' => $name ) );
				continue;
			}

			unset( $info['stable'] );
			$candidates[] = $info;
		}

		return $candidates;
	}

	/**
	 * Determines whether all files in a WPvivid-listed backup set are stable.
	 *
	 * @param array<string,mixed>               $metadata Backup metadata.
	 * @param array<string,array<string,mixed>> $file_infos File info keyed by basename.
	 * @return bool
	 */
	private function is_listed_set_complete( array $metadata, array $file_infos ) {
		$set_files = isset( $metadata['set_files'] ) && is_array( $metadata['set_files'] ) ? $metadata['set_files'] : array();

		if ( empty( $set_files ) ) {
			return true;
		}

		foreach ( $set_files as $set_file ) {
			$name = basename( (string) $set_file );
			if ( empty( $file_infos[ $name ]['stable'] ) ) {
				return false;
			}
		}

		return true;
	}
}
