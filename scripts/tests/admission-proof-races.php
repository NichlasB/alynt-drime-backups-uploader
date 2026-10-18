<?php
/** Real independent processes, held InnoDB locks and before/after-COMMIT interruption. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_admission_races( $root, $f, $owned, &$children, &$receipt ) {
	alynt_auth_login( 90000001 ); $b = alynt_admission_upgrade( $f ); $a = alynt_admission_spawn( $root, $f, $children, 'paused' ); $f->db->wait( 'before_transaction' );
	$s = alynt_admission_closed( $f->db, true ); $old = $s['writer']['pending']['operation_id'];
	$abandon = $f->issue( $b, 'abandon_closed', $old ); $r = $b->consume( 'POST', $abandon ); alynt_lifecycle_check( is_array( $r ) && 'abandoned' === $r['secondary_outcome'], 'Fresh core-authorized abandon failed.' );
	// A new begin is durably closed but deliberately not followed by target execution.
	$f->fault = 'begin_false_ack'; $new = $f->issue( $b, 'begin_disable' ); $r = $b->consume( 'POST', $new ); $f->fault = '';
	alynt_lifecycle_check( is_array( $r ) && 'unresolved' === $r['secondary_outcome'], 'Replacement closed begin failed.' );
	$bytes = array( $f->db->value( alynt_writer_state_name() ), $f->db->value( 'probe_settings' ) ); $f->db->marker( 'release_before_transaction' ); $r = alynt_writer_join( $children[$a] );
	alynt_lifecycle_check( ! $r['ok'] && $r['denied_or_unresolved'] && $bytes === array( $f->db->value( alynt_writer_state_name() ), $f->db->value( 'probe_settings' ) ), 'Paused original writer escaped abandon/successor fencing.' );
	alynt_admission_closed( $f->db, true ); alynt_admission_target( $f->db, true ); $receipt['independent_original_writer_fenced_after_fresh_abandon_new_begin'] = true;
	foreach ( array( 'safety', 'target', 'expiry' ) as $kind ) {
		alynt_auth_login( 90000001 ); alynt_admission_upgrade( $f ); $a = alynt_admission_spawn( $root, $f, $children, 'expiry' === $kind ? 'expired' : 'lock' ); $f->db->wait( 'before_transaction' );
		$holder = new Alynt_Admission_Proof_DB( $f->base ); $holder->exec( 'START TRANSACTION' );
		try {
			$holder->value( 'target' === $kind ? 'probe_settings' : alynt_writer_state_name(), true ); $f->db->marker( 'release_before_transaction' ); $start = microtime( true );
			if ( 'expiry' === $kind ) { $until = alynt_admission_state( $f->db )['writer']['closure']['command']['expires_at']; while ( time() < $until ) usleep( 20000 ); }
			else { $r = alynt_writer_join( $children[$a] ); alynt_lifecycle_check( ! $r['ok'] && $r['denied_or_unresolved'] && ( 'target' === $kind ? 'target_lock' : 'safety_lock' ) === $r['phase'] && microtime( true ) - $start >= 1.5, 'Held native lock did not time out closed.' ); }
		} finally { $holder->exec( 'ROLLBACK' ); $holder->close(); }
		if ( 'expiry' === $kind ) { $r = alynt_writer_join( $children[$a] ); alynt_lifecycle_check( ! $r['ok'] && $r['denied_or_unresolved'] && 'admission' === $r['phase'], 'Original deadline survived actual wait.' ); }
		alynt_admission_closed( $f->db, true ); alynt_admission_target( $f->db, true );
	}
	$receipt['independent_safety_target_lock_timeouts_and_original_window_after_wait'] = true;
	foreach ( array( 'before_commit', 'after_commit' ) as $kind ) {
		alynt_auth_login( 90000001 ); $b = alynt_admission_upgrade( $f ); $a = alynt_admission_spawn( $root, $f, $children, $kind ); $f->db->wait( $kind );
		if ( 'before_commit' === $kind ) { alynt_admission_closed( $f->db, true ); alynt_admission_target( $f->db, true ); }
		alynt_writer_join( $children[$a], true );
		// Fence asynchronous server disconnect rollback before observing the committed pair.
		$f->db->exec( 'START TRANSACTION' ); try { $f->db->value( alynt_writer_state_name(), true ); $f->db->value( 'probe_settings', true ); } finally { $f->db->exec( 'ROLLBACK' ); }
		$s = alynt_admission_closed( $f->db, 'before_commit' === $kind ); alynt_admission_target( $f->db, 'before_commit' === $kind ); $id = $s['writer']['closure']['command']['command_id'];
		$read = $f->issue( $b, 'receipt_read', $id ); $tx = $f->transactions; $r = $b->consume( 'POST', $read );
		alynt_lifecycle_check( is_array( $r ) && ( 'before_commit' === $kind ? 'unresolved' : 'simulated_committed' ) === $r['secondary_outcome'] && $tx === $f->transactions, 'Interrupted commit acknowledgement replayed or lost evidence.' );
	}
	$receipt['independent_kill_before_commit_rolls_back_after_commit_reconciles_without_replay'] = true;
}
