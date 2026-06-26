<?php
/**
 * Uploader WPvivid set cleanup helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles registry context and set-aware local deletion.
 *
 * @since 0.5.1
 */
trait Alynt_Drime_Backups_Uploader_Uploader_WPvivid_Set_Cleanup {
	/**
	 * Builds registry context for a queue item.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param int                 $attempts Attempts.
	 * @return array<string,mixed>
	 */
	private function registry_item_context( array $item, $attempts = 0 ) {
		$path = isset( $item['path'] ) ? (string) $item['path'] : '';
		$name = isset( $item['name'] ) ? (string) $item['name'] : basename( $path );

		$context = array(
			'signature' => isset( $item['signature'] ) ? (string) $item['signature'] : '',
			'path'      => $path,
			'name'      => $name,
			'attempts'  => absint( $attempts ),
		);

		if ( is_file( $path ) ) {
			$size = filesize( $path );
			if ( false !== $size ) {
				$context['size'] = (int) $size;
			}
		}

		if ( isset( $item['wpvivid'] ) && is_array( $item['wpvivid'] ) ) {
			$context['wpvivid'] = $item['wpvivid'];
		}

		foreach ( $this->producer_context_string_keys() as $key ) {
			if ( isset( $item[ $key ] ) && is_scalar( $item[ $key ] ) ) {
				$context[ $key ] = (string) $item[ $key ];
			}
		}

		foreach ( array( 'backup_set_index', 'backup_set_total' ) as $key ) {
			if ( isset( $item[ $key ] ) ) {
				$context[ $key ] = absint( $item[ $key ] );
			}
		}

		if ( isset( $item['metadata'] ) && is_array( $item['metadata'] ) ) {
			$context['metadata'] = $item['metadata'];
		}

		return $context;
	}

	/**
	 * Returns normalized producer context keys that can be copied as strings.
	 *
	 * @return array<int,string>
	 */
	private function producer_context_string_keys() {
		return array(
			'producer_key',
			'producer_label',
			'package_id',
			'filename',
			'backup_set_id',
			'manifest_path',
			'checksum_path',
			'checksum_algorithm',
			'checksum',
		);
	}

	/**
	 * Checks whether a queue item belongs to a listed multi-file WPvivid set.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @return bool
	 */
	private function is_wpvivid_listed_multi_file_item( array $item ) {
		$metadata  = isset( $item['wpvivid'] ) && is_array( $item['wpvivid'] ) ? $item['wpvivid'] : array();
		$set_files = isset( $metadata['set_files'] ) && is_array( $metadata['set_files'] ) ? $metadata['set_files'] : array();

		return ! empty( $metadata['from_list'] ) && count( $set_files ) > 1;
	}

	/**
	 * Deletes a WPvivid split backup set only after every listed file is uploaded.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @return void
	 */
	private function maybe_delete_wpvivid_set_files( array $item ) {
		$metadata      = isset( $item['wpvivid'] ) && is_array( $item['wpvivid'] ) ? $item['wpvivid'] : array();
		$set_files     = $this->wpvivid_set_file_names( $metadata );
		$set_signature = isset( $metadata['set_signature'] ) ? (string) $metadata['set_signature'] : '';
		$records       = $this->uploaded_wpvivid_set_records( $set_signature, $set_files );

		if ( count( $records ) < count( $set_files ) ) {
			$this->logger->event(
				'filesystem',
				'info',
				'local_delete_waiting_for_wpvivid_set',
				'Local backup deletion is waiting until all WPvivid set files are uploaded.',
				array(
					'uploaded' => count( $records ),
					'total'    => count( $set_files ),
				)
			);
			return;
		}

		foreach ( $records as $record ) {
			$this->delete_uploaded_wpvivid_set_file( $record, $set_files );
		}
	}

	/**
	 * Returns uploaded records that match a WPvivid backup set.
	 *
	 * @param string            $set_signature Set signature.
	 * @param array<int,string> $set_files Set files.
	 * @return array<int,array<string,mixed>>
	 */
	private function uploaded_wpvivid_set_records( $set_signature, array $set_files ) {
		$records = array();
		$seen    = array();

		foreach ( $this->registry->get_uploaded() as $record ) {
			if ( ! is_array( $record ) || ! $this->uploaded_record_matches_wpvivid_set( $record, $set_signature, $set_files ) ) {
				continue;
			}

			$name = $this->uploaded_record_basename( $record );
			if ( '' === $name || isset( $seen[ $name ] ) ) {
				continue;
			}

			$seen[ $name ] = true;
			$records[]     = $record;
		}

		return $records;
	}

	/**
	 * Checks whether an uploaded record belongs to a WPvivid backup set.
	 *
	 * @param array<string,mixed> $record Uploaded record.
	 * @param string              $set_signature Set signature.
	 * @param array<int,string>   $set_files Set files.
	 * @return bool
	 */
	private function uploaded_record_matches_wpvivid_set( array $record, $set_signature, array $set_files ) {
		$metadata = isset( $record['wpvivid'] ) && is_array( $record['wpvivid'] ) ? $record['wpvivid'] : array();
		if ( '' !== $set_signature && isset( $metadata['set_signature'] ) && (string) $metadata['set_signature'] === $set_signature ) {
			return true;
		}

		$name = $this->uploaded_record_basename( $record );

		return '' !== $name && in_array( $name, $set_files, true );
	}

	/**
	 * Deletes one uploaded WPvivid set file if it still exists locally.
	 *
	 * @param array<string,mixed> $record Uploaded record.
	 * @param array<int,string>   $set_files Set files.
	 * @return void
	 */
	private function delete_uploaded_wpvivid_set_file( array $record, array $set_files ) {
		$path = isset( $record['path'] ) ? (string) $record['path'] : '';
		$name = $this->uploaded_record_basename( $record );

		if ( '' === $path || '' === $name || ! in_array( $name, $set_files, true ) || ! is_file( $path ) ) {
			return;
		}

		if ( ! wp_delete_file( $path ) ) {
			$this->logger->event( 'filesystem', 'error', 'local_delete_failed', 'Local backup deletion failed after upload.', array( 'file' => $name ) );
			return;
		}

		$this->logger->event( 'filesystem', 'info', 'local_delete_succeeded', 'Local backup file deleted after upload.', array( 'file' => $name ) );
	}

	/**
	 * Returns normalized WPvivid set filenames.
	 *
	 * @param array<string,mixed> $metadata WPvivid metadata.
	 * @return array<int,string>
	 */
	private function wpvivid_set_file_names( array $metadata ) {
		$files = isset( $metadata['set_files'] ) && is_array( $metadata['set_files'] ) ? $metadata['set_files'] : array();

		return array_values(
			array_filter(
				array_map(
					function ( $file ) {
						return is_scalar( $file ) ? basename( (string) $file ) : '';
					},
					$files
				)
			)
		);
	}

	/**
	 * Returns a stable basename from an uploaded registry record.
	 *
	 * @param array<string,mixed> $record Uploaded record.
	 * @return string
	 */
	private function uploaded_record_basename( array $record ) {
		foreach ( array( 'name', 'remote_name', 'path' ) as $key ) {
			if ( ! empty( $record[ $key ] ) && is_scalar( $record[ $key ] ) ) {
				return basename( (string) $record[ $key ] );
			}
		}

		return '';
	}
}
