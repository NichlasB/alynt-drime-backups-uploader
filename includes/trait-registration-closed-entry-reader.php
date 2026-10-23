<?php
/**
 * Fixed one-open input reader. Completion is not inferred from a short read.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Unavailable in production until an exact server/SAPI profile is proved. */
trait Alynt_Drime_Backups_Uploader_Registration_Closed_Entry_Reader {
	/**
	 * At most 17 reads / 16385 bytes, no seek, reopen, spill or caller path.
	 *
	 * The extra byte detects overflow, not an accepted capped prefix. Metadata
	 * and declared length are consistency checks, not proof of HTTP framing.
	 *
	 * @return string|false Complete original bytes only after clean owned close.
	 */
	private function read_body() {
		$stream = null;
		$result = false;
		try {
			if ( ! $this->reader_scope() ) {
				return false;
			}
			$stream = fopen( 'php://input', 'rb' );
			if ( ! is_resource( $stream ) || ! $this->reader_scope() ) {
				return false;
			}
			$raw = '';
			for ( $calls = 0; $calls < 17; ++$calls ) {
				if ( ! $this->reader_scope() || ! $this->input_metadata( $stream ) ) {
					break;
				}
				$chunk = fread( $stream, min( 1024, 16385 - strlen( $raw ) ) );
				if ( ! is_string( $chunk ) || ! $this->reader_scope() || ! $this->input_metadata( $stream ) || strlen( $chunk ) > min( 1024, 16385 - strlen( $raw ) ) ) {
					break;
				}
				$raw .= $chunk;
				if ( strlen( $raw ) > 16384 ) {
					break;
				}
				if ( feof( $stream ) ) {
					if ( strlen( $raw ) === (int) $this->state['transport']['length'] && $this->reader_scope() ) {
						$result = $raw;
					}
					break;
				}
				if ( '' === $chunk ) {
					break;
				}
			}
		} finally {
			if ( is_resource( $stream ) && ( true !== fclose( $stream ) || ! $this->reader_scope() ) ) {
				$result = false;
			}
		}
		return $result;
	}

	/**
	 * Refuse unknown wrappers, timeout, mode/buffering state and conflicting EOF.
	 *
	 * @param resource $stream Only the owned input stream.
	 * @return bool
	 */
	private function input_metadata( $stream ) {
		$meta = stream_get_meta_data( $stream );
		return is_array( $meta ) && 'PHP' === ( $meta['wrapper_type'] ?? null ) && 'Input' === ( $meta['stream_type'] ?? null ) && 'rb' === ( $meta['mode'] ?? null ) && 'php://input' === ( $meta['uri'] ?? null ) && false === ( $meta['timed_out'] ?? null ) && true === ( $meta['blocked'] ?? null ) && false === ( $meta['seekable'] ?? null ) && is_bool( $meta['eof'] ?? null ) && feof( $stream ) === $meta['eof'] && 0 === ( $meta['unread_bytes'] ?? null );
	}

	/**
	 * Original deadline and unchanged bounded transport, never a fresh read root.
	 *
	 * @return bool
	 */
	private function reader_scope() {
		return $this->within_root() && $this->post_transport() === ( $this->state['transport'] ?? null );
	}
}
