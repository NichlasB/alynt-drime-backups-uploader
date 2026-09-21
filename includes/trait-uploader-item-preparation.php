<?php
/**
 * Uploader item preparation helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prepares queue items, source-specific paths, package folders, and file-state checks for upload.
 *
 * @since 0.5.22
 */
trait Alynt_Drime_Backups_Uploader_Uploader_Item_Preparation {
	/**
	 * Uploads one queued item.
	 *
	 * @param array<string,mixed> $item Item.
	 * @return array<string,mixed>|WP_Error
	 */
	private function upload_item( array $item ) {
		$path = isset( $item['path'] ) ? (string) $item['path'] : '';
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'alynt_drime_file_missing', __( 'The queued backup file is no longer readable.', 'alynt-drime-backups-uploader' ) );
		}

		$settings = $this->effective_upload_settings( $this->settings->get(), $item );
		if ( ! Alynt_Drime_Backups_Uploader_Settings::is_workspace_id_allowed( absint( $settings['workspace_id'] ) ) ) {
			return new WP_Error( 'alynt_drime_workspace_not_allowed', Alynt_Drime_Backups_Uploader_Settings::workspace_not_allowed_message() );
		}

		$size        = filesize( $path );
		$mtime       = filemtime( $path );
		$remote_name = basename( $path );
		$parent_id   = $this->prepare_upload_parent_id( $settings );

		if ( false === $size || false === $mtime || $size <= 0 ) {
			return new WP_Error( 'alynt_drime_empty_file', __( 'The queued backup file is empty.', 'alynt-drime-backups-uploader' ) );
		}

		if ( ! $this->queued_file_state_matches( $item, (int) $size, (int) $mtime ) ) {
			return new WP_Error( 'alynt_drime_file_changed', __( 'The queued backup file changed after it was scanned. Run a new scan before uploading it.', 'alynt-drime-backups-uploader' ) );
		}

		if ( is_wp_error( $parent_id ) ) {
			return $parent_id;
		}

		$remote_name = $this->preflight_remote_name( $remote_name, (int) $size, $settings, $parent_id );
		if ( is_wp_error( $remote_name ) ) {
			return $remote_name;
		}

		if ( false === $remote_name ) {
			$sidecars = $this->upload_package_sidecars( $item, $path, $settings, $parent_id );
			if ( is_wp_error( $sidecars ) ) {
				return $sidecars;
			}

			$result = array(
				'path'                      => $path,
				'remote_name'               => basename( $path ),
				'size'                      => (int) $size,
				'destination_relative_path' => (string) $settings['relative_path'],
				'drime'                     => array(
					'duplicate_skipped' => true,
				),
			);

			if ( ! empty( $sidecars ) ) {
				$result['sidecars'] = $sidecars;
			}

			return $result;
		}

		$result = $size < Alynt_Drime_Backups_Uploader_Drime_Client::MIN_MULTIPART_CHUNK_SIZE
			? $this->simple_upload_item( $path, $remote_name, (int) $size, $parent_id, $settings )
			: $this->multipart_upload( $path, $remote_name, (int) $size, $item, $parent_id, $settings );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$sidecars = $this->upload_package_sidecars( $item, $path, $settings, $parent_id );
		if ( is_wp_error( $sidecars ) ) {
			return $sidecars;
		}

		if ( ! empty( $sidecars ) ) {
			$result['sidecars'] = $sidecars;
		}

		$result['destination_relative_path'] = (string) $settings['relative_path'];

		return $result;
	}

	/**
	 * Returns settings with a producer-specific Drime relative path applied.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param array<string,mixed> $item Queue item.
	 * @return array<string,mixed>
	 */
	private function effective_upload_settings( array $settings, array $item ) {
		$relative_path = $this->source_relative_path( $settings, $item );

		if ( '' !== $relative_path ) {
			$settings['relative_path'] = $relative_path;
		}

		if ( $this->is_generic_outbox_item( $item ) ) {
			$package_folder = $this->generic_package_folder_name( $item );
			if ( '' !== $package_folder ) {
				$settings['relative_path'] = $this->append_upload_relative_segment( (string) $settings['relative_path'], $package_folder );
			}
		}

		return $settings;
	}

	/**
	 * Returns a source-specific Drime relative path when configured.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param array<string,mixed> $item Queue item.
	 * @return string
	 */
	private function source_relative_path( array $settings, array $item ) {
		$producer_key = isset( $item['producer_key'] ) ? (string) $item['producer_key'] : '';

		if ( 'generic_outbox' === $producer_key && ! empty( $settings['server_relative_path'] ) ) {
			return (string) $settings['server_relative_path'];
		}

		if ( 'wpvivid' === $producer_key && ! empty( $settings['wpvivid_relative_path'] ) ) {
			return (string) $settings['wpvivid_relative_path'];
		}

		return '';
	}

	/**
	 * Checks whether a queued item is a generic server outbox package.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @return bool
	 */
	private function is_generic_outbox_item( array $item ) {
		return isset( $item['producer_key'] ) && 'generic_outbox' === (string) $item['producer_key'];
	}

	/**
	 * Returns the Drime package folder name for a generic outbox package.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @return string
	 */
	private function generic_package_folder_name( array $item ) {
		$name = '';

		foreach ( array( 'package_id', 'backup_set_id' ) as $key ) {
			if ( ! empty( $item[ $key ] ) && is_scalar( $item[ $key ] ) ) {
				$name = (string) $item[ $key ];
				break;
			}
		}

		if ( '' === $name && ! empty( $item['path'] ) && is_scalar( $item['path'] ) ) {
			$name = basename( $this->package_archive_stem( (string) $item['path'] ) );
		}

		if ( '' === $name && ! empty( $item['name'] ) && is_scalar( $item['name'] ) ) {
			$name = basename( $this->package_archive_stem( (string) $item['name'] ) );
		}

		$name = trim( preg_replace( '/[^A-Za-z0-9._-]+/', '-', str_replace( array( '\\', '/' ), '-', $name ) ), '-._' );

		if ( '' === $name || false !== strpos( $name, '..' ) ) {
			return '';
		}

		return substr( $name, 0, Alynt_Drime_Backups_Uploader_Settings::MAX_RELATIVE_PATH_SEGMENT_CHARS );
	}

	/**
	 * Appends a safe folder segment to a Drime relative path.
	 *
	 * @param string $relative_path Existing relative path.
	 * @param string $segment Folder segment.
	 * @return string
	 */
	private function append_upload_relative_segment( $relative_path, $segment ) {
		$relative_path = '/' . trim( str_replace( '\\', '/', (string) $relative_path ), '/' );
		$segment       = trim( str_replace( array( '\\', '/' ), '-', (string) $segment ), '/' );

		if ( '' === $segment ) {
			return '/' === $relative_path ? '' : $relative_path;
		}

		$path = '/' . trim( trim( $relative_path, '/' ) . '/' . $segment, '/' );

		return '/' === $path ? '' : $path;
	}

	/**
	 * Checks whether a queued item still matches the scanned file state.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param int                 $size Current file size.
	 * @param int                 $mtime Current modified timestamp.
	 * @return bool
	 */
	private function queued_file_state_matches( array $item, $size, $mtime ) {
		$queued_size  = isset( $item['size'] ) ? absint( $item['size'] ) : 0;
		$queued_mtime = isset( $item['mtime'] ) ? absint( $item['mtime'] ) : 0;

		if ( $queued_size > 0 && $queued_size !== (int) $size ) {
			return false;
		}

		return 0 === $queued_mtime || $queued_mtime === (int) $mtime;
	}

	/**
	 * Returns the configured multipart chunk size in bytes.
	 *
	 * @return int
	 */
	private function multipart_chunk_size() {
		$settings = $this->settings->get();
		$mb       = isset( $settings['multipart_chunk_size_mb'] ) ? absint( $settings['multipart_chunk_size_mb'] ) : Alynt_Drime_Backups_Uploader_Drime_Client::DEFAULT_MULTIPART_SIZE_MB;
		$bytes    = $mb * 1048576;

		return max(
			Alynt_Drime_Backups_Uploader_Drime_Client::MIN_MULTIPART_CHUNK_SIZE,
			min( Alynt_Drime_Backups_Uploader_Drime_Client::MAX_MULTIPART_CHUNK_SIZE, $bytes )
		);
	}
}
