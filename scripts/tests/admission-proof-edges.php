<?php
/** Actual session/capability loss, acknowledgement ambiguity and native SQL faults. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_admission_edges( $f, &$receipt ) {
	foreach ( array( 'session', 'role', 'observation', 'revoke_after_begin', 'revoke_after_commit' ) as $kind ) {
		alynt_auth_login( 90000001 ); $f->fault = ''; $b = alynt_admission_upgrade( $f ); $form = $f->issue( $b, 'begin_disable' ); $writes = $f->writes;
		if ( 'session' === $kind ) {
			WP_Session_Tokens::get_instance( 90000001 )->destroy_all(); $e = Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::parse( $form )['envelope'];
			alynt_lifecycle_check( in_array( wp_verify_nonce( $form['nonce'], Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::action( $e ) ), array( 1, 2 ), true ), 'Session revocation test did not retain a valid nonce.' );
		}
		if ( 'role' === $kind ) ( new WP_User( 90000001 ) )->set_role( 'subscriber' );
		if ( 'observation' === $kind ) $f->revoke_on_read = true;
		if ( in_array( $kind, array( 'revoke_after_begin', 'revoke_after_commit' ), true ) ) $f->fault = $kind;
		alynt_admission_denied( $b->consume( 'POST', $form ) );
		if ( 'role' === $kind ) ( new WP_User( 90000001 ) )->set_role( 'administrator' );
		$pending = 'revoke_after_begin' === $kind; alynt_admission_closed( $f->db, $pending ); alynt_admission_target( $f->db, 'revoke_after_commit' !== $kind );
		if ( in_array( $kind, array( 'session', 'role', 'observation' ), true ) ) alynt_lifecycle_check( $writes === $f->writes, 'Lost authorization still wrote closure.' );
		alynt_auth_login( 90000003 ); $read = $f->issue( $b, 'receipt_read', $form['command_id'] ); $r = $b->consume( 'POST', $read );
		alynt_lifecycle_check( is_array( $r ) && ( 'revoke_after_commit' !== $kind || 'simulated_committed' === $r['secondary_outcome'] ), 'Fresh authorized receipt reconciliation failed.' );
	}
	$receipt['session_nonce_independence_role_loss_and_post_observation_guard'] = true; $receipt['loss_after_begin_keeps_pending_after_commit_redacts_without_erasure'] = true;
	foreach ( array( 'begin_false_ack', 'begin_throw_ack', 'before_target', 'after_target' ) as $kind ) {
		alynt_auth_login( 90000001 ); $f->fault = ''; $b = alynt_admission_upgrade( $f ); $form = $f->issue( $b, 'begin_disable' ); $tx = $f->transactions; $f->fault = $kind;
		$r = $b->consume( 'POST', $form );
		if ( 'begin_false_ack' === $kind ) alynt_lifecycle_check( is_array( $r ) && 'unresolved' === $r['secondary_outcome'], 'False begin acknowledgement not reconciled closed.' ); else alynt_admission_denied( $r );
		alynt_admission_closed( $f->db, true ); alynt_admission_target( $f->db, true );
		alynt_lifecycle_check( $tx + ( in_array( $kind, array( 'before_target', 'after_target' ), true ) ? 1 : 0 ) === $f->transactions, 'Ambiguous begin replayed a target transaction.' );
		$f->fault = ''; $read = $f->issue( $b, 'receipt_read', $form['command_id'] ); $r = $b->consume( 'POST', $read ); alynt_lifecycle_check( is_array( $r ) && 'unresolved' === $r['secondary_outcome'], 'Pending acknowledgement observation was lost.' );
	}
	$receipt['false_throwing_begin_ack_never_replays_target'] = true; $receipt['sql_failure_before_or_after_target_rolls_back_pair_keeps_begin'] = true;
	alynt_auth_login( 90000001 ); $b = alynt_admission_upgrade( $f ); $form = $f->issue( $b, 'begin_disable' ); $raw = $f->db->value( alynt_writer_state_name() ); $target = $f->db->value( 'probe_settings' );
	foreach ( array( 'missing', 'corrupt', 'autoload', 'real_domain' ) as $kind ) {
		if ( 'missing' === $kind ) $f->db->exec( 'DELETE FROM ' . $f->db->table . ' WHERE option_name=?', array( alynt_writer_state_name() ) );
		if ( 'corrupt' === $kind ) $f->db->replace( alynt_writer_state_name(), '{bad' );
		if ( 'autoload' === $kind ) $f->db->exec( 'UPDATE ' . $f->db->table . ' SET autoload=? WHERE option_name=?', array( 'yes', alynt_writer_state_name() ) );
		if ( 'real_domain' === $kind ) { $s = json_decode( $raw, true ); $s['writer']['source'] = 'closed_writer_admission_v1'; $f->db->replace( alynt_writer_state_name(), wp_json_encode( $s ) ); }
		$bad = $f->db->value( alynt_writer_state_name() ); $writes = $f->writes; alynt_admission_denied( $b->consume( 'POST', $form ) );
		alynt_lifecycle_check( $writes === $f->writes && $bad === $f->db->value( alynt_writer_state_name() ) && $target === $f->db->value( 'probe_settings' ), 'Unsupported storage was repaired or changed target.' );
		if ( 'missing' === $kind ) $f->db->exec( 'INSERT INTO ' . $f->db->table . ' (option_name,option_value,autoload) VALUES (?,?,?)', array( alynt_writer_state_name(), $raw, 'no' ) );
		else { $f->db->replace( alynt_writer_state_name(), $raw ); $f->db->exec( 'UPDATE ' . $f->db->table . ' SET autoload=? WHERE option_name=?', array( 'no', alynt_writer_state_name() ) ); }
	}
	$receipt['missing_corrupt_autoload_reserved_domain_denied_without_repair'] = true;
	$f->db->exec( 'START TRANSACTION' ); try { alynt_admission_denied( $b->consume( 'POST', $form ) ); } finally { $f->db->exec( 'ROLLBACK' ); }
	alynt_lifecycle_check( $raw === $f->db->value( alynt_writer_state_name() ) && $target === $f->db->value( 'probe_settings' ), 'Nested transaction escaped ownership.' ); $receipt['nested_transaction_denied'] = true;
	wp_cache_set( alynt_writer_state_name(), '{stale}', 'options' ); wp_cache_set( 'probe_settings', '{stale}', 'options' );
	$r = $b->consume( 'POST', $form ); alynt_lifecycle_check( is_array( $r ) && 'simulated_committed' === $r['secondary_outcome'], 'Native admission read trusted stale process cache.' );
	alynt_admission_closed( $f->db, false ); alynt_admission_target( $f->db, false ); $receipt['native_raw_rows_bypass_planted_process_cache_only'] = true;
}
