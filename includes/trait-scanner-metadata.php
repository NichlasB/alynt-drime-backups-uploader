<?php
/**
 * Scanner WPvivid metadata helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scanner WPvivid metadata helpers.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Scanner_Metadata {
	/**
	 * Reads completed backup metadata from WPvivid's local backup list.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_backup_list_metadata() {
		$list = get_option( 'wpvivid_backup_list', array() );
		if ( ! is_array( $list ) ) {
			return array();
		}

		$metadata = array();
		foreach ( $list as $backup_id => $backup ) {
			if ( is_array( $backup ) ) {
				$this->merge_backup_metadata( $metadata, (string) $backup_id, $backup );
			}
		}

		return $metadata;
	}

	/**
	 * Merges one WPvivid backup list item into metadata.
	 *
	 * @param array<string,array<string,mixed>> $metadata Metadata.
	 * @param string                            $backup_id Backup ID.
	 * @param array<string,mixed>               $backup Backup item.
	 * @return void
	 */
	private function merge_backup_metadata( array &$metadata, $backup_id, array $backup ) {
		if ( empty( $backup['backup']['files'] ) || ! is_array( $backup['backup']['files'] ) ) {
			return;
		}

		$set_files  = $this->backup_set_files( $backup['backup']['files'] );
		$file_count = count( $set_files );

		foreach ( $backup['backup']['files'] as $file ) {
			if ( ! is_array( $file ) || empty( $file['file_name'] ) ) {
				continue;
			}

			$file_name              = basename( (string) $file['file_name'] );
			$metadata[ $file_name ] = array(
				'backup_id'      => $backup_id,
				'backup_type'    => isset( $file['type'] ) ? (string) $file['type'] : '',
				'set_signature'  => hash( 'sha256', $backup_id ),
				'set_file_count' => $file_count,
				'set_files'      => $set_files,
				'from_list'      => true,
			);
		}
	}

	/**
	 * Returns the file basenames in one WPvivid backup set.
	 *
	 * @param array<int,array<string,mixed>> $files Files.
	 * @return array<int,string>
	 */
	private function backup_set_files( array $files ) {
		$set_files = array();

		foreach ( $files as $file ) {
			if ( is_array( $file ) && ! empty( $file['file_name'] ) ) {
				$set_files[] = basename( (string) $file['file_name'] );
			}
		}

		return $set_files;
	}

	/**
	 * Builds best-effort WPvivid metadata for a file.
	 *
	 * @param string                            $name Backup filename.
	 * @param array<string,array<string,mixed>> $backup_metadata Backup list metadata.
	 * @return array<string,mixed>
	 */
	private function metadata_for_file( $name, array $backup_metadata ) {
		if ( isset( $backup_metadata[ $name ] ) ) {
			return $backup_metadata[ $name ];
		}

		$backup_id = $this->extract_backup_id( $name );

		return array(
			'backup_id'      => $backup_id,
			'backup_type'    => $this->extract_backup_type( $name ),
			'set_signature'  => '' === $backup_id ? '' : hash( 'sha256', $backup_id ),
			'set_file_count' => 0,
			'set_files'      => array(),
			'from_list'      => false,
		);
	}

	/**
	 * Extracts WPvivid's stable backup id from common Free/Pro filenames.
	 *
	 * @param string $name Backup filename.
	 * @return string
	 */
	private function extract_backup_id( $name ) {
		if ( preg_match( '/^(?:wpvivid|[^_]+-wpvivid)-([^_]+)_/', $name, $matches ) ) {
			return $matches[1];
		}

		if ( preg_match( '/(?:^[^_]+_)?([^_]+)_\d{4}-\d{2}-\d{2}-\d{2}-\d{2}/', $name, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * Determines whether a filename is a numbered WPvivid split part.
	 *
	 * @param string $name Filename.
	 * @return bool
	 */
	private function is_split_part( $name ) {
		return (bool) preg_match( '/\\.part\\d+\\.zip$/i', $name );
	}

	/**
	 * Extracts a WPvivid backup content type from the filename.
	 *
	 * @param string $name Backup filename.
	 * @return string
	 */
	private function extract_backup_type( $name ) {
		if ( preg_match( '/_(backup_[a-z0-9_]+)(?:\\.part\\d+)?\\.zip$/i', $name, $matches ) ) {
			return strtolower( $matches[1] );
		}

		return '';
	}
}
