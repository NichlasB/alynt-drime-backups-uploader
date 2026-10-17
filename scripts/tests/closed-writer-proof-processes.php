<?php
/** Bounded independent processes; only fixed owned-table commands and markers cross processes. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_writer_spawn( $root, $db, $token, &$children, $case, array $command, $id, $purpose = 'finish', $fault = 'none' ) {
	alynt_lifecycle_check( 1 === preg_match( '/^case_[a-z0-9_]+$/D', $case ), 'Invalid child scenario.' );
	$payload = wp_json_encode( array( $command, $id, $purpose, $fault ) );
	$db->exec( 'INSERT INTO ' . $db->control . ' (option_name,option_value,autoload) VALUES (?,?,?)', array( $case . '_command', $payload, 'no' ) );
	$argv = array( PHP_BINARY, '-c', get_cfg_var( 'cfg_file_path' ), __DIR__ . '/closed-writer-local-proof.php', $root, '--child', $db->table, $token, $case );
	$p = proc_open( $argv, array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
	alynt_lifecycle_check( is_resource( $p ), 'Writer child launch failed.' ); fclose( $pipes[0] );
	$children[] = array( 'process' => $p, 'pipes' => $pipes ); $db->wait( $case . '_ready' ); return count( $children ) - 1;
}
function alynt_writer_child( $db, $token, $case ) {
	alynt_lifecycle_check( 1 === preg_match( '/^case_[a-z0-9_]+$/D', $case ), 'Invalid child marker.' );
	$r = $db->one( 'SELECT option_value FROM ' . $db->control . ' WHERE option_name=?', array( $case . '_command' ) );
	$p = is_array( $r ) ? json_decode( $r['option_value'], true, 16 ) : null;
	alynt_lifecycle_check( is_array( $p ) && count( $p ) === 4 && is_array( $p[0] ), 'Missing owned child descriptor.' );
	$tx = new Alynt_Closed_Writer_Proof_Transaction( $db, $token ); $db->marker( $case . '_ready' ); $db->wait( $case . '_go' ); $db->marker( $case . '_attempt' );
	$r = $tx->run( $p[0], $p[1], $p[2], $p[3], $case ); echo json_encode( $r ) . "\n";
}
function alynt_writer_join( &$child, $terminate = false ) {
	if ( $terminate ) alynt_lifecycle_check( proc_terminate( $child['process'] ), 'Owned writer termination failed.' );
	$until = microtime( true ) + 15;
	do { $status = proc_get_status( $child['process'] ); if ( ! $status['running'] ) break; usleep( 20000 ); } while ( microtime( true ) < $until );
	alynt_lifecycle_check( ! $status['running'], 'Writer child failed to stop.' );
	$out = stream_get_contents( $child['pipes'][1] ); fclose( $child['pipes'][1] ); stream_get_contents( $child['pipes'][2] ); fclose( $child['pipes'][2] );
	$closed = proc_close( $child['process'] ); $child['process'] = null;
	$receipt = null; foreach ( explode( "\n", trim( $out ) ) as $line ) { $r = json_decode( $line, true ); if ( is_array( $r ) && array_key_exists( 'ok', $r ) ) $receipt = $r; }
	if ( $terminate ) { alynt_lifecycle_check( null === $receipt, 'Interrupted writer unexpectedly acknowledged.' ); return; }
	alynt_lifecycle_check( ( 0 === $status['exitcode'] || 0 === $closed ) && is_array( $receipt ), 'Writer child missing bounded receipt.' ); return $receipt;
}
