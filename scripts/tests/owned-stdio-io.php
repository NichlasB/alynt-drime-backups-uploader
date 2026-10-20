<?php
/**
 * Fixed standalone CLI pipe helpers. No WordPress, files, sockets or subprocesses.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

/**
 * Write in deliberately small slices; a short/failed write never means success.
 *
 * @param resource $stream Standard output/error only.
 * @param string   $bytes Compiled synthetic bytes.
 * @param int      $calls Successful write count.
 * @return void
 */
function alynt_stdio_probe_write( $stream, $bytes, &$calls ) {
	$offset = 0;
	while ( $offset < strlen( $bytes ) ) {
		$written = fwrite( $stream, substr( $bytes, $offset, 5 ) );
		if ( false === $written || 0 === $written ) {
			exit( 29 );
		}
		$offset += $written;
		++$calls;
	}
	if ( ! fflush( $stream ) ) {
		exit( 29 );
	}
}

/**
 * Read one byte per call: Windows fread(7) may wait past a short message tail.
 *
 * @param int $calls Successful read count.
 * @return string Complete line without LF, or exits with a compiled refusal code.
 */
function alynt_stdio_probe_read( &$calls ) {
	$line = '';
	while ( true ) {
		$bytes = fread( STDIN, 1 );
		if ( false === $bytes || '' === $bytes ) {
			exit( '' === $line ? 23 : 24 );
		}
		++$calls;
		if ( strlen( $line ) + strlen( $bytes ) > 4096 ) {
			exit( 26 );
		}
		$line .= $bytes;
		$end   = strpos( $line, "\n" );
		if ( false !== $end ) {
			if ( $end !== strlen( $line ) - 1 ) {
				exit( 25 );
			}
			return substr( $line, 0, -1 );
		}
	}
}
