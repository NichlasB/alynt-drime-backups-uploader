<?php
/** Independent core-auth workers; no cookie, nonce or session token crosses argv/IPC. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_admission_spawn( $root, $f, &$children, $case ) {
	alynt_lifecycle_check( in_array( $case, array( 'paused', 'expired', 'lock', 'before_commit', 'after_commit' ), true ), 'Unknown admission child case.' );
	$f->db->exec( 'DELETE FROM ' . $f->db->control . ' WHERE option_name<>?', array( 'never_seeded' ) );
	$argv = array( PHP_BINARY, '-c', get_cfg_var( 'cfg_file_path' ), __DIR__ . '/admission-local-proof.php', $root, '--child', $f->base, $f->token, $case );
	$p = proc_open( $argv, array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
	alynt_lifecycle_check( is_resource( $p ), 'Admission child launch failed.' ); fclose( $pipes[0] ); $children[] = array( 'process' => $p, 'pipes' => $pipes ); return count( $children ) - 1;
}
function alynt_admission_child( $base, $token, $owned, $case ) {
	alynt_lifecycle_check( in_array( $case, array( 'paused', 'expired', 'lock', 'before_commit', 'after_commit' ), true ), 'Invalid owned child mode.' );
	$f = new Alynt_Admission_Proof_Factory( $base, $token, $owned ); alynt_auth_login( 90000003 ); $b = $f->boundary(); $form = $f->issue( $b, 'begin_disable' );
	if ( 'expired' === $case ) {
		// Deliberately short server-side proof issuer; never renew the original command.
		$form['expires_at'] = (string) ( (int) $form['issued_at'] + 2 ); $e = $form; unset( $e['nonce'] );
		foreach ( array( 'expected_generation', 'issued_at', 'expires_at' ) as $key ) $e[$key] = (int) $e[$key];
		$form['nonce'] = wp_create_nonce( Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::action( $e ) );
	}
	$f->fault = $case; $r = $b->consume( 'POST', $form );
	echo json_encode( array( 'ok' => is_array( $r ) && 'simulated_committed' === $r['secondary_outcome'], 'phase' => $f->phase, 'denied_or_unresolved' => is_wp_error( $r ) || ( is_array( $r ) && 'unresolved' === $r['secondary_outcome'] ) ) ) . "\n"; $f->db->close();
}
