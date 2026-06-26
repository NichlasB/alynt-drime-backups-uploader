<?php
/**
 * Uploader multipart part transfer helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uploader multipart part transfer helpers.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Uploader_Multipart_Parts {
	/**
	 * Uploads missing multipart parts.
	 *
	 * @param string                                       $path Path.
	 * @param string                                       $remote_name Remote name.
	 * @param array<string,mixed>                          $item Queue item.
	 * @param array{key:string,upload_id:string,total:int} $session Multipart session.
	 * @param array<int,array<string,mixed>>               $parts Completed parts.
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	private function upload_multipart_parts( $path, $remote_name, array $item, array $session, array $parts ) {
		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) {
			return new WP_Error( 'alynt_drime_file_open_failed', __( 'Could not open the backup file for upload.', 'alynt-drime-backups-uploader' ) );
		}

		$chunk_size = $this->multipart_chunk_size();
		$memory_ok  = $this->validate_multipart_chunk_memory( $chunk_size );
		if ( is_wp_error( $memory_ok ) ) {
			fclose( $handle );
			return $memory_ok;
		}

		$sign_response = $this->sign_missing_multipart_parts( $session, $parts );
		if ( is_wp_error( $sign_response ) ) {
			fclose( $handle );
			return $sign_response;
		}

		for ( $part_number = 1; $part_number <= $session['total']; $part_number++ ) {
			if ( isset( $parts[ $part_number ] ) ) {
				if ( ! $this->store_active_upload_state( $path, $remote_name, $session['key'], $session['upload_id'], (string) $item['signature'], $parts, $session['total'] ) ) {
					fclose( $handle );
					return $this->state_persistence_error();
				}

				continue;
			}

			$part = $this->upload_multipart_part( $handle, $sign_response, $part_number, $chunk_size );
			if ( is_wp_error( $part ) ) {
				fclose( $handle );
				return $part;
			}

			$parts[ $part_number ] = $part;
			if ( ! $this->store_active_upload_state( $path, $remote_name, $session['key'], $session['upload_id'], (string) $item['signature'], $parts, $session['total'] ) ) {
				fclose( $handle );
				return $this->state_persistence_error();
			}
		}

		fclose( $handle );
		ksort( $parts );

		return $parts;
	}

	/**
	 * Signs all missing multipart part URLs for this upload pass.
	 *
	 * @param array{key:string,upload_id:string,total:int} $session Multipart session.
	 * @param array<int,array<string,mixed>>               $parts Completed parts.
	 * @return array<string,mixed>|WP_Error
	 */
	private function sign_missing_multipart_parts( array $session, array $parts ) {
		$part_numbers = array();

		for ( $part_number = 1; $part_number <= $session['total']; $part_number++ ) {
			if ( ! isset( $parts[ $part_number ] ) ) {
				$part_numbers[] = $part_number;
			}
		}

		if ( empty( $part_numbers ) ) {
			return array( 'urls' => array() );
		}

		return $this->client->sign_part_urls( $session['key'], $session['upload_id'], $part_numbers );
	}

	/**
	 * Uploads one multipart part.
	 *
	 * @param resource            $handle File handle.
	 * @param array<string,mixed> $sign_response Sign response.
	 * @param int                 $part_number Part number.
	 * @param int                 $chunk_size Chunk size in bytes.
	 * @return array{PartNumber:int,ETag:string}|WP_Error
	 */
	private function upload_multipart_part( $handle, array $sign_response, $part_number, $chunk_size ) {
		$url = $this->extract_signed_url( $sign_response, $part_number );
		if ( is_wp_error( $url ) ) {
			return $url;
		}

		$data = $this->read_multipart_part( $handle, $part_number, $chunk_size );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$etag = $this->client->upload_part( $url, $data );
		if ( is_wp_error( $etag ) ) {
			return $etag;
		}

		return array(
			'PartNumber' => $part_number,
			'ETag'       => $etag,
		);
	}

	/**
	 * Reads one multipart part from disk.
	 *
	 * @param resource $handle File handle.
	 * @param int      $part_number Part number.
	 * @param int      $chunk_size Chunk size in bytes.
	 * @return string|WP_Error
	 */
	private function read_multipart_part( $handle, $part_number, $chunk_size ) {
		if ( 0 !== fseek( $handle, ( $part_number - 1 ) * $chunk_size ) ) {
			return new WP_Error( 'alynt_drime_file_seek_failed', __( 'Could not seek to the next backup chunk.', 'alynt-drime-backups-uploader' ) );
		}

		$data = fread( $handle, $chunk_size );

		return false === $data ? new WP_Error( 'alynt_drime_file_read_failed', __( 'Could not read the next backup chunk.', 'alynt-drime-backups-uploader' ) ) : $data;
	}

	/**
	 * Ensures the configured chunk size can be held safely in memory.
	 *
	 * @param int $chunk_size Chunk size in bytes.
	 * @return true|WP_Error
	 */
	private function validate_multipart_chunk_memory( $chunk_size ) {
		$memory_limit = $this->memory_limit_bytes( ini_get( 'memory_limit' ) );
		if ( $memory_limit <= 0 ) {
			return true;
		}

		$reserved_bytes = 32 * 1048576;
		$needed_bytes   = memory_get_usage( true ) + absint( $chunk_size ) + $reserved_bytes;
		if ( $needed_bytes <= $memory_limit ) {
			return true;
		}

		return new WP_Error(
			'alynt_drime_chunk_exceeds_memory',
			__( 'The configured multipart chunk size is too large for the current PHP memory limit. Reduce the chunk size or raise the PHP memory limit before uploading.', 'alynt-drime-backups-uploader' )
		);
	}

	/**
	 * Parses a PHP memory limit shorthand value into bytes.
	 *
	 * @param string|false $value Memory limit.
	 * @return int
	 */
	private function memory_limit_bytes( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value || '-1' === $value ) {
			return 0;
		}

		$unit   = strtolower( substr( $value, -1 ) );
		$number = (float) $value;

		switch ( $unit ) {
			case 'g':
				$number *= 1024;
				// Fall through.
			case 'm':
				$number *= 1024;
				// Fall through.
			case 'k':
				$number *= 1024;
				break;
		}

		return (int) $number;
	}
}
