<?php
/** Real transaction assertions; reset only owned synthetic rows between independent cases. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_writer_cases( $root, $db, $token, &$children, &$receipt ) {
	$tx = new Alynt_Closed_Writer_Proof_Transaction( $db, $token ); $off = alynt_writer_model( $db, false ); $queries = $db->queries;
	alynt_lifecycle_check( is_wp_error( $off->snapshot() ) && is_wp_error( $off->command( array() ) ) && $queries === $db->queries, 'Dormant boundary accessed SQL.' );
	$model = alynt_writer_model( $db );
	alynt_lifecycle_check( is_wp_error( $model->snapshot() ) && is_wp_error( $model->prepare_closed( str_repeat( 'e', 64 ), 0, function () { return true; } ) ) && null === $db->value( alynt_writer_state_name() ), 'Missing state implicitly created.' );
	$queries = $db->queries;
	foreach ( array( 'grant', 'activate', 'dispatch', 'rollback' ) as $kind ) alynt_lifecycle_check( is_wp_error( $model->command( array( 'kind' => $kind ) ) ), 'Execution or grant command accepted.' );
	alynt_lifecycle_check( $queries === $db->queries, 'Disabled grant/execution command accessed SQL.' ); $initial = alynt_writer_seed( $db );
	$receipt['default_off_missing_state_and_grant_execution_boundaries'] = true;
	$db->exec( 'START TRANSACTION' ); $nested_denied = false;
	try { $tx->run( alynt_writer_prepare( $db, 240 ), alynt_writer_id( 241 ) ); }
	catch ( RuntimeException $error ) { $nested_denied = 'Nested transaction denied; never implicitly commit caller writes.' === $error->getMessage(); }
	finally { $active = $db->one( 'SELECT @@in_transaction AS active' ); $db->exec( 'ROLLBACK' ); }
	alynt_lifecycle_check( $nested_denied && '1' === (string) $active['active'], 'Nested prototype implicitly committed a caller transaction.' );
	$receipt['nested_transaction_rejected_without_implicit_commit'] = true;
	$c = alynt_writer_begin( $db, 1 ); $pending = alynt_writer_closed( $db ); $target = $db->value( 'probe_settings' );
	alynt_lifecycle_check( $target === wp_json_encode( array( 'auto_scan_enabled' => false, 'polled_at' => 0 ) ) && $c['command_id'] === $pending['barrier']['pending'], 'Begin changed target or omitted durable pending.' );
	wp_cache_set( 'probe_settings', 'stale', 'options' ); wp_cache_set( alynt_writer_state_name(), 'stale', 'options' );
	$r = $tx->run( $c, alynt_writer_id( 2 ) ); $done = alynt_writer_closed( $db );
	alynt_lifecycle_check( true === $r['ok'] && hash( 'sha256', $db->value( 'probe_settings' ) ) === $c['after_hash'] && $initial['barrier']['checkpoint'] === $done['barrier']['checkpoint'] && $pending['barrier']['closure'] === $done['barrier']['closure'], 'Target/completion did not commit together.' );
	alynt_lifecycle_check( 'stale' === wp_cache_get( 'probe_settings', 'options' ) && 'stale' === wp_cache_get( alynt_writer_state_name(), 'options' ), 'Prototype published speculative cache data.' );
	$bytes = array( $db->value( alynt_writer_state_name() ), $db->value( 'probe_settings' ) ); $r = $tx->run( $c, alynt_writer_id( 2 ) );
	alynt_lifecycle_check( false === $r['ok'] && $bytes === array( $db->value( alynt_writer_state_name() ), $db->value( 'probe_settings' ) ) && 'observed_current' === alynt_writer_model( $db )->receipt( 'closed_writer_fixture_v1', alynt_writer_id( 2 ) )['outcome'], 'Committed write was replayed rather than observed.' );
	$receipt['separate_begin_atomic_target_completion_duplicate_observation_cache_bypass'] = true;
	foreach ( array( 'target_sql', 'terminal_sql' ) as $i => $fault ) {
		alynt_writer_seed( $db ); $c = alynt_writer_begin( $db, 10 + $i * 2 ); $bytes = array( $db->value( alynt_writer_state_name() ), $db->value( 'probe_settings' ) );
		$r = $tx->run( $c, alynt_writer_id( 11 + $i * 2 ), 'finish', $fault );
		alynt_lifecycle_check( false === $r['ok'] && ( 'target_sql' === $fault ? 'target_write' : 'terminal_write' ) === $r['phase'] && $bytes === array( $db->value( alynt_writer_state_name() ), $db->value( 'probe_settings' ) ), 'SQL abort lost durable pending or partially committed target.' );
	}
	$receipt['real_target_sql_and_terminal_sql_failures_rollback_both_rows'] = true;
	foreach ( array( 'missing_target', 'corrupt_state', 'autoloaded_state' ) as $i => $fault ) {
		alynt_writer_seed( $db ); $c = alynt_writer_begin( $db, 220 + $i * 2 );
		if ( 'missing_target' === $fault ) $db->exec( 'DELETE FROM ' . $db->table . ' WHERE option_name=?', array( 'probe_settings' ) );
		elseif ( 'corrupt_state' === $fault ) $db->replace( alynt_writer_state_name(), '{invalid-owned-fixture' );
		else $db->exec( 'UPDATE ' . $db->table . ' SET autoload=? WHERE option_name=?', array( 'yes', alynt_writer_state_name() ) );
		$rows = $db->query( 'SELECT option_name,option_value,autoload FROM ' . $db->table . ' ORDER BY option_name' );
		alynt_lifecycle_check( false === $tx->run( $c, alynt_writer_id( 221 + $i * 2 ) )['ok'] && $rows === $db->query( 'SELECT option_name,option_value,autoload FROM ' . $db->table . ' ORDER BY option_name' ), 'Missing/corrupt/autoloaded fixture was implicitly repaired or written.' );
	}
	$receipt['missing_target_corrupt_and_autoloaded_state_reject_without_repair'] = true;
	alynt_writer_seed( $db ); $c = alynt_writer_begin( $db, 20 ); $copy = $db->value( 'probe_settings' );
	$db->replace( 'probe_settings', wp_json_encode( array( 'auto_scan_enabled' => true, 'polled_at' => 0 ) ) ); $state = $db->value( alynt_writer_state_name() );
	$r = $tx->run( $c, alynt_writer_id( 21 ) ); alynt_lifecycle_check( false === $r['ok'] && $state === $db->value( alynt_writer_state_name() ), 'Changed target precondition accepted.' );
	// Restore only this test's synthetic row; not evidence of fencing an out-of-band ABA.
	$db->replace( 'probe_settings', $copy ); alynt_lifecycle_check( true === $tx->run( $c, alynt_writer_id( 22 ), 'timestamp' )['ok'], 'Fresh narrow timestamp patch failed.' );
	alynt_lifecycle_check( false === alynt_writer_target( $db->value( 'probe_settings' ) )['auto_scan_enabled'] && $state === $db->value( alynt_writer_state_name() ), 'Timestamp patch resurrected semantics or rewrote pending.' );
	$r = $tx->run( $c, alynt_writer_id( 23 ) ); alynt_lifecycle_check( false === $r['ok'] && $state === $db->value( alynt_writer_state_name() ), 'Stale whole-target writer bypassed changed timestamp precondition.' );
	$receipt['locked_target_precondition_and_fresh_narrow_timestamp_preserve_semantics'] = true;
	alynt_writer_races( $root, $db, $token, $children, $receipt );
	alynt_writer_interruptions( $root, $db, $token, $children, $receipt );
	alynt_writer_seed( $db );
	for ( $i = 0; $i < 26; $i++ ) {
		$c = alynt_writer_begin( $db, 100 + $i * 2 ); alynt_lifecycle_check( true === $tx->run( $c, alynt_writer_id( 101 + $i * 2 ) )['ok'], 'Reserved terminal capacity unavailable.' );
	}
	$bytes = array( $db->value( alynt_writer_state_name() ), $db->value( 'probe_settings' ) );
	$c = alynt_writer_prepare( $db, 200 ); $r = alynt_writer_model( $db )->begin( $c, function () { return true; } );
	alynt_lifecycle_check( is_wp_error( $r ) && 50 === count( alynt_writer_closed( $db )['barrier']['receipts'] ) && $bytes === array( $db->value( alynt_writer_state_name() ), $db->value( 'probe_settings' ) ), 'Full history pruned evidence or admitted another begin.' );
	$receipt['sql_history_capacity_reserves_terminal_and_rejects_without_pruning'] = true;
	alynt_lifecycle_check( 'preserved' === $db->value( 'probe_unrelated' ) && 'fixture-only-cron' === $db->value( 'cron' ), 'Unrelated owned rows changed.' );
	alynt_writer_closed( $db ); $receipt['closed_only_and_unrelated_fixture_rows_preserved'] = true;
}
