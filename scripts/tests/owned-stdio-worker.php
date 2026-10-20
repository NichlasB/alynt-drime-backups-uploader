<?php
/**
 * Explicit standalone owned-child fixture. Never bootstrap WordPress or a native client.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

$alynt_stdio_modes = array( 'roundtrip', 'stderr', 'eof', 'partial', 'malformed', 'oversize', 'output-overflow', 'output-partial', 'exit', 'stall-read', 'stall-write', 'no-read', 'late-ack', 'tail', 'stderr-overflow' );
if ( 'cli' !== PHP_SAPI || defined( 'ABSPATH' ) || 2 !== $argc || ! in_array( $argv[1], $alynt_stdio_modes, true ) ) {
	exit( 64 );
}
require __DIR__ . '/owned-stdio-io.php';
$alynt_stdio_mode   = $argv[1];
$alynt_stdio_reads  = 0;
$alynt_stdio_writes = 0;
alynt_stdio_probe_write( STDOUT, "{\"v\":1,\"kind\":\"ready\",\"seq\":1}\n", $alynt_stdio_writes );

if ( 'stall-write' === $alynt_stdio_mode ) {
	alynt_stdio_probe_write( STDERR, "writing\n", $alynt_stdio_writes );
	for ( $alynt_stdio_i = 0; $alynt_stdio_i < 64; ++$alynt_stdio_i ) {
		$alynt_stdio_result = fwrite( STDOUT, str_repeat( 'X', 65536 ) );
		if ( false === $alynt_stdio_result || 0 === $alynt_stdio_result ) {
			exit( 29 );
		}
	}
	if ( ! fflush( STDOUT ) ) {
		exit( 29 );
	}
	alynt_stdio_probe_write( STDERR, "completed\n", $alynt_stdio_writes );
	exit( 30 );
}
if ( 'no-read' === $alynt_stdio_mode ) {
	sleep( 20 ); // Deliberate owned stalled peer, independently terminated by parent.
	exit( 30 );
}
if ( 'output-overflow' === $alynt_stdio_mode ) {
	alynt_stdio_probe_write( STDOUT, str_repeat( 'X', 8192 ) . "\n", $alynt_stdio_writes );
	alynt_stdio_probe_read( $alynt_stdio_reads );
	exit( 30 );
}
if ( 'output-partial' === $alynt_stdio_mode ) {
	alynt_stdio_probe_write( STDOUT, '{"v":1,"kind":"final"', $alynt_stdio_writes );
	exit( 0 );
}
if ( 'exit' === $alynt_stdio_mode ) {
	exit( 27 );
}
if ( in_array( $alynt_stdio_mode, array( 'stderr', 'stderr-overflow' ), true ) ) {
	// Larger than a typical pipe buffer; the parent must drain this while dialoguing.
	$alynt_stdio_chunks = 'stderr' === $alynt_stdio_mode ? 128 : 512;
	for ( $alynt_stdio_i = 0; $alynt_stdio_i < $alynt_stdio_chunks; ++$alynt_stdio_i ) {
		$alynt_stdio_result = fwrite( STDERR, str_repeat( 'E', 1024 ) );
		if ( 1024 !== $alynt_stdio_result ) {
			exit( 29 );
		}
	}
	if ( ! fflush( STDERR ) ) {
		exit( 29 );
	}
}
$alynt_stdio_probe = '{"v":1,"kind":"probe","seq":1,"value":"' . str_repeat( 'A', 1024 ) . '"}';
if ( alynt_stdio_probe_read( $alynt_stdio_reads ) !== $alynt_stdio_probe ) {
	exit( 25 );
}
alynt_stdio_probe_write( STDOUT, "{\"v\":1,\"kind\":\"reply\",\"seq\":2}\n", $alynt_stdio_writes );
$alynt_stdio_start = hrtime( true );
if ( '{"v":1,"kind":"ack","seq":2}' !== alynt_stdio_probe_read( $alynt_stdio_reads ) ) {
	exit( 25 );
}
if ( hrtime( true ) - $alynt_stdio_start > 1000000000 ) {
	exit( 28 ); // Acceptance after blocking I/O, not a portable one-second cancellation.
}
$alynt_stdio_final = array( 'v' => 1, 'kind' => 'final', 'seq' => 3, 'reads' => $alynt_stdio_reads, 'writes_before_final' => $alynt_stdio_writes );
alynt_stdio_probe_write( STDOUT, json_encode( $alynt_stdio_final ) . "\n", $alynt_stdio_writes );
if ( '' !== fread( STDIN, 1 ) || ! feof( STDIN ) ) {
	exit( 25 );
}
exit( 0 );
