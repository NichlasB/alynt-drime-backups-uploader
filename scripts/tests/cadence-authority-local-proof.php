<?php
/** Synthetic authority proof inside the existing hard-targeted owned-table harness. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );

function alynt_cadence_authority_local_proof( $backend, $site_root, $table, &$children, &$receipt, $binding, &$now, $legacy ) {
	global $wpdb;
	$cron_before = $backend->read( 'cron' )['option_value'];
	$model = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Store( $backend, function () use ( &$now ) { return $now; } );
	$legacy_before = $legacy->snapshot();
	$error = $model->initialize_authority( $binding, 'every_30_minutes', $now );
	alynt_safety_proof_check( is_wp_error( $error ) && 'schedule_safety_migration_recovery_pending' === $error->get_error_code() && $legacy_before === $legacy->snapshot(), 'Migration erased/ignored unfinished journals.' );
	// Only these never-executed synthetic journals are explicitly resolved for migration proof.
	foreach ( $legacy_before['claims'] as $source => $record ) {
		alynt_safety_proof_check( in_array( $record['phase'], array( 'claimed', 'recovery_required' ), true ), 'Unexpected synthetic journal phase.' );
		alynt_safety_proof_check( is_array( $legacy->transition( $binding, $source, $record['action_id'], $record['request_hash'], $record['phase'], 'failed_closed' ) ), 'Synthetic journal closure failed.' );
	}
	$claims = $legacy->snapshot()['claims'];
	$old_suppression = $wpdb->suppress_errors( true );
	try {
		alynt_safety_proof_check( false !== $wpdb->query( 'START TRANSACTION READ ONLY' ), 'Migration fault transaction failed.' );
		$failure = $model->initialize_authority( $binding, 'every_30_minutes', $now );
		alynt_safety_proof_check( is_wp_error( $failure ) && 'schedule_safety_write_failed' === $failure->get_error_code(), 'Failed migration claimed success.' );
	} finally { $wpdb->query( 'ROLLBACK' ); $wpdb->suppress_errors( $old_suppression ); }
	alynt_safety_proof_check( $claims === $legacy->snapshot()['claims'] && 1 === $legacy->snapshot()['schema_version'], 'Failed migration changed legacy state.' );
	alynt_safety_proof_check( is_array( $model->initialize_authority( $binding, 'every_30_minutes', $now ) ) && $claims === $model->snapshot()['claims'], 'Migration discarded final journals.' );
	alynt_safety_proof_check( is_wp_error( $legacy->snapshot() ), 'Old schema API accepted schema 2.' );
	$receipt['authority_migration_blocks_uncertainty_and_retains_journals'] = true;

	$s = $model->snapshot(); $fp = $model->authority_fingerprint( $s ); $start = count( $children );
	for ( $i = 1; $i <= 2; $i++ ) {
		$command = array( PHP_BINARY, '-c', get_cfg_var( 'cfg_file_path' ), __DIR__ . '/schedule-safety-local-proof.php', $site_root, '--authority-child', $table, (string) $i, (string) $now, (string) $s['schedule_revision'], $fp );
		$process = proc_open( $command, array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
		alynt_safety_proof_check( is_resource( $process ), 'Authority child failed to start.' ); fclose( $pipes[0] );
		$children[] = array( 'process' => $process, 'pipes' => $pipes, 'output' => '' );
	}
	$deadline = microtime( true ) + 15;
	do {
		$ready = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE option_name IN ('probe_authority_ready_1','probe_authority_ready_2')" );
		if ( 2 === $ready ) break; usleep( 20000 );
	} while ( microtime( true ) < $deadline );
	alynt_safety_proof_check( 2 === $ready, 'Authority readers did not reach the shared snapshot barrier.' );
	$wpdb->insert( $table, array( 'option_name' => 'probe_authority_go', 'option_value' => 'go', 'autoload' => 'no' ) );
	$wins = 0;
	for ( $i = $start; $i < count( $children ); $i++ ) {
		$child = &$children[$i]; $limit = microtime( true ) + 15;
		do { $status = proc_get_status( $child['process'] ); if ( ! $status['running'] ) break; usleep( 20000 ); } while ( microtime( true ) < $limit );
		alynt_safety_proof_check( ! $status['running'], 'Authority child timed out.' );
		$output = stream_get_contents( $child['pipes'][1] ); fclose( $child['pipes'][1] ); stream_get_contents( $child['pipes'][2] ); fclose( $child['pipes'][2] );
		$closed = proc_close( $child['process'] ); $child['process'] = null;
		alynt_safety_proof_check( 0 === $status['exitcode'] || 0 === $closed, 'Authority child failed.' );
		$lines = explode( "\n", trim( $output ) ); $result = json_decode( end( $lines ), true );
		alynt_safety_proof_check( is_array( $result ), 'Authority result missing.' ); $wins += $result['won'] ? 1 : 0; unset( $child );
	}
	alynt_safety_proof_check( 1 === $wins, 'Competing model dispatch claims did not have one winner.' ); $receipt['authority_two_process_dispatch_single_winner'] = true;
	$d = $model->snapshot()['dispatch']; $hash = str_repeat( 'f', 64 );
	alynt_safety_proof_check( 'started' === $model->start_dispatch( $binding, $d['owner'], $hash )['phase'], 'Model start failed.' );
	alynt_safety_proof_check( is_wp_error( $model->start_dispatch( $binding, $d['owner'], $hash ) ), 'Duplicate model start granted permission.' );
	$before = $model->snapshot(); $old_suppression = $wpdb->suppress_errors( true );
	try {
		alynt_safety_proof_check( false !== $wpdb->query( 'START TRANSACTION READ ONLY' ), 'Fault transaction failed.' );
		$failure = $model->complete_dispatch( $binding, $d['owner'], $hash );
		alynt_safety_proof_check( is_wp_error( $failure ) && 'schedule_safety_write_failed' === $failure->get_error_code(), 'Completion reported success after failed SQL.' );
	} finally { $wpdb->query( 'ROLLBACK' ); $wpdb->suppress_errors( $old_suppression ); }
	alynt_safety_proof_check( $before === $model->snapshot(), 'Failed completion changed due time or journal.' );
	$queries = $wpdb->num_queries;
	alynt_safety_proof_check( 'completed' === $model->complete_dispatch( $binding, $d['owner'], $hash )['phase'] && $wpdb->num_queries - $queries === 2, 'Completion did not use one authoritative read plus one conditional update.' );
	alynt_safety_proof_check( $now + 1800 === $model->snapshot()['authority']['next_due_at'], 'Completion failed to advance model due time.' );
	$receipt['authority_dispatch_completion_atomic_and_failure_closed'] = true;

	$s = $model->snapshot(); $candidate = $s; ++$candidate['schedule_revision']; $candidate['authority']['cadence'] = 'every_15_minutes'; $candidate['authority']['next_due_at'] = $now + 900;
	$source = '66666666-6666-4666-8666-666666666666';
	$e = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $now, 10 ); $e['before_fingerprint'] = $model->authority_fingerprint( $s ); $e['intended_fingerprint'] = $model->authority_fingerprint( $candidate );
	alynt_safety_proof_check( is_array( $model->reserve_change( $binding, $source, $e ) ), 'Model cadence reservation failed.' );
	$before = $model->snapshot(); $old_suppression = $wpdb->suppress_errors( true );
	try {
		alynt_safety_proof_check( false !== $wpdb->query( 'START TRANSACTION READ ONLY' ), 'Fault transaction failed.' );
		$failure = $model->commit_change( $binding, $source, $e['action_id'], $e['request_hash'], $now + 900 );
		alynt_safety_proof_check( is_wp_error( $failure ) && 'schedule_safety_write_failed' === $failure->get_error_code(), 'Model cadence acknowledged failed SQL.' );
	} finally { $wpdb->query( 'ROLLBACK' ); $wpdb->suppress_errors( $old_suppression ); }
	alynt_safety_proof_check( $before === $model->snapshot(), 'Failed model change partially committed.' );
	$queries = $wpdb->num_queries; $changed = $model->commit_change( $binding, $source, $e['action_id'], $e['request_hash'], $now + 900 );
	alynt_safety_proof_check( is_array( $changed ) && $changed['changed'] && $wpdb->num_queries - $queries === 2, 'Journal and cadence were not committed in one CAS.' );
	alynt_safety_proof_check( $model->commit_change( $binding, $source, $e['action_id'], $e['request_hash'], $now + 900 )['observed'], 'Duplicate commit was not observation-only.' );
	$receipt['authority_cadence_journal_atomic_and_failure_closed'] = true;

	$now += 900; $s = $model->snapshot(); $owner = '88888888-8888-4888-8888-888888888888';
	alynt_safety_proof_check( is_array( $model->claim_dispatch( $binding, $s['schedule_revision'], $model->authority_fingerprint( $s ), $owner, $hash ) ), 'Next model due claim failed.' );
	$model->start_dispatch( $binding, $owner, $hash ); $now += 300;
	$restart = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Store( $backend, function () use ( &$now ) { return $now; } );
	alynt_safety_proof_check( is_wp_error( $restart->complete_dispatch( $binding, $owner, $hash ) ) && 'started' === $restart->snapshot()['dispatch']['phase'], 'Restart/expiry lost uncertain model work.' );
	alynt_safety_proof_check( is_wp_error( $restart->claim_dispatch( $binding, $s['schedule_revision'], $restart->authority_fingerprint( $restart->snapshot() ), $d['owner'], $hash ) ), 'Expired owner was replaced without inspection.' );
	alynt_safety_proof_check( is_int( $restart->invalidate( $binding ) ) && ! $restart->snapshot()['authority']['enabled'] && 'recovery_required' === $restart->snapshot()['dispatch']['phase'], 'Invalidation erased uncertainty.' );
	$receipt['authority_expired_restart_and_invalidation_protected'] = true;
	alynt_safety_proof_check( $cron_before === $backend->read( 'cron' )['option_value'], 'Authority model wrote the fixture cron option.' );
	$receipt['authority_never_writes_fixture_cron'] = true;
}
