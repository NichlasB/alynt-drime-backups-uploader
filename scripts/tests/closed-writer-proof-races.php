<?php
/** Independent writers and bounded real InnoDB locks, not mocked interleavings. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_writer_races( $root, $db, $token, &$children, &$receipt ) {
	$tx = new Alynt_Closed_Writer_Proof_Transaction( $db, $token ); alynt_writer_seed( $db ); $c = alynt_writer_begin( $db, 30 );
	$a = alynt_writer_spawn( $root, $db, $token, $children, 'case_race_a', $c, alynt_writer_id( 31 ) );
	$b = alynt_writer_spawn( $root, $db, $token, $children, 'case_race_b', $c, alynt_writer_id( 32 ) );
	$db->marker( 'case_race_a_go' ); $db->marker( 'case_race_b_go' );
	$x = alynt_writer_join( $children[$a] ); $y = alynt_writer_join( $children[$b] );
	alynt_lifecycle_check( 1 === (int) $x['ok'] + (int) $y['ok'] && hash( 'sha256', $db->value( 'probe_settings' ) ) === $c['after_hash'] && $c['expected_generation'] + 2 === alynt_writer_closed( $db )['lifecycle']['permission_generation'], 'Two-process exclusion failed.' );
	$receipt['two_process_same_pending_exactly_one_target_completion'] = true;
	alynt_writer_seed( $db ); $c = alynt_writer_begin( $db, 250 );
	$a = alynt_writer_spawn( $root, $db, $token, $children, 'case_finish_race', $c, alynt_writer_id( 251 ) );
	$b = alynt_writer_spawn( $root, $db, $token, $children, 'case_abandon_race', $c, alynt_writer_id( 252 ), 'abandon' );
	$db->marker( 'case_finish_race_go' ); $db->marker( 'case_abandon_race_go' );
	$x = alynt_writer_join( $children[$a] ); $y = alynt_writer_join( $children[$b] ); $s = alynt_writer_closed( $db );
	alynt_lifecycle_check( 1 === (int) $x['ok'] + (int) $y['ok'] && null === $s['barrier']['pending'] && ( $x['ok'] ? 'finish' : 'abandon' ) === $s['barrier']['completion']['purpose'] && hash( 'sha256', $db->value( 'probe_settings' ) ) === ( $x['ok'] ? $c['after_hash'] : $c['before_hash'] ), 'Finish/abandon race split target and completion.' );
	$receipt['two_process_finish_abandon_share_lock_protocol'] = true;
	alynt_writer_seed( $db ); $old = alynt_writer_begin( $db, 40 );
	$a = alynt_writer_spawn( $root, $db, $token, $children, 'case_delayed', $old, alynt_writer_id( 41 ) );
	alynt_lifecycle_check( true === $tx->run( $old, alynt_writer_id( 42 ), 'abandon' )['ok'], 'Serialized abandon failed.' );
	$new = alynt_writer_begin( $db, 43 ); $bytes = array( $db->value( alynt_writer_state_name() ), $db->value( 'probe_settings' ) );
	$db->marker( 'case_delayed_go' ); $r = alynt_writer_join( $children[$a] );
	alynt_lifecycle_check( false === $r['ok'] && $bytes === array( $db->value( alynt_writer_state_name() ), $db->value( 'probe_settings' ) ), 'Late abandoned writer mutated successor target.' );
	alynt_lifecycle_check( true === $tx->run( $new, alynt_writer_id( 44 ) )['ok'], 'Successor writer failed.' );
	$back = alynt_writer_begin( $db, 45 ); alynt_lifecycle_check( true === $tx->run( $back, alynt_writer_id( 46 ) )['ok'], 'Participating ABA return failed.' );
	$bytes = array( $db->value( alynt_writer_state_name() ), $db->value( 'probe_settings' ) );
	alynt_lifecycle_check( $old['before_hash'] === hash( 'sha256', $bytes[1] ) && false === $tx->run( $old, alynt_writer_id( 47 ) )['ok'] && $bytes === array( $db->value( alynt_writer_state_name() ), $db->value( 'probe_settings' ) ), 'Participating ABA revived old operation.' );
	$receipt['abandon_new_begin_blocks_paused_writer_and_participating_aba'] = true;
	foreach ( array( 'safety', 'target', 'expiry' ) as $i => $kind ) {
		alynt_writer_seed( $db ); $c = alynt_writer_begin( $db, 50 + $i * 2, 'expiry' === $kind ? 1 : 300 );
		$case = 'case_lock_' . $kind; $a = alynt_writer_spawn( $root, $db, $token, $children, $case, $c, alynt_writer_id( 51 + $i * 2 ) );
		$holder = new Alynt_Closed_Writer_Proof_DB( $db->table ); $holder->exec( 'START TRANSACTION' );
		try {
			$holder->value( 'target' === $kind ? 'probe_settings' : alynt_writer_state_name(), true );
			$db->marker( $case . '_go' ); $db->wait( $case . '_attempt' ); $wait_started = microtime( true );
			if ( 'expiry' === $kind ) usleep( 1150000 );
			else { $r = alynt_writer_join( $children[$a] ); alynt_lifecycle_check( false === $r['ok'] && ( 'target' === $kind ? 'target_lock' : 'safety_lock' ) === $r['phase'] && microtime( true ) - $wait_started >= 1.5, 'Held lock did not fail closed on timeout.' ); }
		} finally { $holder->exec( 'ROLLBACK' ); $holder->close(); }
		if ( 'expiry' === $kind ) { $r = alynt_writer_join( $children[$a] ); alynt_lifecycle_check( false === $r['ok'] && 'deadline' === $r['phase'], 'Expired writer proceeded after lock wait.' ); }
		alynt_lifecycle_check( $c['command_id'] === alynt_writer_closed( $db )['barrier']['pending'] && $c['before_hash'] === hash( 'sha256', $db->value( 'probe_settings' ) ), 'Lock abort changed target or pending.' );
	}
	$receipt['safety_and_target_lock_timeouts_and_post_wait_original_expiry'] = true;
}
function alynt_writer_interruptions( $root, $db, $token, &$children, &$receipt ) {
	foreach ( array( 'pause_before_commit', 'pause_after_commit' ) as $i => $fault ) {
		alynt_writer_seed( $db ); $c = alynt_writer_begin( $db, 70 + $i * 2 ); $id = alynt_writer_id( 71 + $i * 2 ); $case = 'case_' . $fault;
		$bytes = array( $db->value( alynt_writer_state_name() ), $db->value( 'probe_settings' ) );
		$a = alynt_writer_spawn( $root, $db, $token, $children, $case, $c, $id, 'finish', $fault ); $db->marker( $case . '_go' ); $db->wait( $case . '_staged' );
		if ( 0 === $i ) alynt_lifecycle_check( $bytes === array( $db->value( alynt_writer_state_name() ), $db->value( 'probe_settings' ) ), 'Observer saw uncommitted target/completion.' );
		alynt_writer_join( $children[$a], true );
		// A locking transaction fences asynchronous server-side disconnect rollback before reconciliation.
		$db->exec( 'START TRANSACTION' ); try { $db->value( alynt_writer_state_name(), true ); $db->value( 'probe_settings', true ); } finally { $db->exec( 'ROLLBACK' ); }
		$s = alynt_writer_closed( $db ); $r = alynt_writer_model( $db )->receipt( 'closed_writer_fixture_v1', $id );
		if ( 0 === $i ) alynt_lifecycle_check( $bytes === array( $db->value( alynt_writer_state_name() ), $db->value( 'probe_settings' ) ) && 'not_found' === $r['outcome'], 'Killed uncommitted writer lost pending or leaked target.' );
		else alynt_lifecycle_check( null === $s['barrier']['pending'] && 'observed_current' === $r['outcome'] && hash( 'sha256', $db->value( 'probe_settings' ) ) === $c['after_hash'], 'Lost commit acknowledgement could not be reconciled without replay.' );
	}
	$receipt['killed_before_commit_rolls_back_after_commit_reconciles_without_ack_or_replay'] = true;
}
