<?php
/** Single native connection, fixed state/target lock order; no auth hooks under locks. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_admission_transaction( $f, $continuation ) {
	$f->transactions++; $f->retained = $continuation;
	if ( in_array( $f->fault, array( 'paused', 'expired', 'lock' ), true ) ) $f->pause( 'before_transaction' );
	$auth = $f->auth_checks; $f->start();
	try {
		$row = $f->read( true ); $f->phase = 'admission'; $pair = $continuation->enter_fixture( $f->db, $row['state'], $row['target'], time() );
		if ( false === $pair ) { $f->db->exec( 'ROLLBACK' ); return false; }
		if ( 'before_target' === $f->fault ) $f->db->exec( 'UPDATE ' . $f->db->table . ' SET missing_admission_column=1 WHERE option_name=?', array( 'probe_settings' ) );
		alynt_lifecycle_check( 1 === $f->db->exec( 'UPDATE ' . $f->db->table . ' SET option_value=? WHERE option_name=? AND BINARY option_value=BINARY ?', array( $pair['target'], 'probe_settings', $row['target'] ) ), 'Admission target comparison failed.' );
		if ( 'after_target' === $f->fault ) $f->db->exec( 'UPDATE ' . $f->db->table . ' SET missing_admission_column=1 WHERE option_name=?', array( alynt_writer_state_name() ) );
		alynt_lifecycle_check( 1 === $f->db->exec( 'UPDATE ' . $f->db->table . ' SET option_value=? WHERE option_name=? AND BINARY option_value=BINARY ?', array( wp_json_encode( $pair['state'] ), alynt_writer_state_name(), wp_json_encode( $row['state'] ) ) ), 'Admission terminal comparison failed.' );
		if ( 'before_commit' === $f->fault ) $f->pause( 'before_commit' );
		alynt_lifecycle_check( $pair === $continuation->confirm_fixture( $f->db, $row['state'], $row['target'], time() ), 'Admission final pure guard failed.' );
		alynt_lifecycle_check( $auth === $f->auth_checks, 'Authentication callback invoked under fixture locks.' );
		$f->db->exec( 'COMMIT' );
	} catch ( Throwable $error ) { $f->db->exec( 'ROLLBACK' ); throw $error; }
	if ( 'after_commit' === $f->fault ) $f->pause( 'after_commit' );
	if ( 'revoke_after_commit' === $f->fault ) WP_Session_Tokens::get_instance( 90000001 )->destroy_all();
	return true;
}
