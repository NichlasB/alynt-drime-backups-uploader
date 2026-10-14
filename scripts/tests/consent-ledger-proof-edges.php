<?php
/** Capacity, retained uncertainty, corruption and closure-first secondary failure on owned fixtures. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_ledger_edge_cases( $table, $token, &$receipt, $model, $backend, $reset, $prepared, $now ) {
	global $wpdb; alynt_lifecycle_owned( $table, $token ); $name = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION;
	$reset( $prepared );
	for ( $id = 1; $id <= 50; $id++ ) { $r = $model->command( alynt_ledger_intent( $id % 2 ? 'grant' : 'revoke', $id, $now, $id ) ); alynt_lifecycle_check( is_array( $r ) && 'committed' === $r['outcome'], 'Receipt history population failed.' ); }
	$before = $backend->read( $name ); alynt_lifecycle_check( alynt_lifecycle_error( $model->command( alynt_ledger_intent( 'grant', 51, $now, 51 ) ), 'schedule_safety_ledger_capacity' ) && $before === $backend->read( $name ), 'Capacity admitted grant or changed receipt.' );
	$r = $model->command( alynt_ledger_intent( 'revoke', 52, $now, 51 ) ); $s = $model->snapshot(); $history = $s['ledger']['receipts'];
	alynt_lifecycle_check( is_array( $r ) && 'committed' === $r['outcome'] && 50 === count( $history ) && ! $s['lifecycle']['consent'], 'History capacity blocked closure.' );
	$r = $model->command( alynt_ledger_intent( 'revoke', 53, $now, 52 ) ); $s = $model->snapshot();
	alynt_lifecycle_check( is_array( $r ) && 'committed' === $r['outcome'] && 53 === $s['lifecycle']['permission_generation'] && $history === $s['ledger']['receipts'] && strlen( wp_json_encode( $s ) ) <= 131072 && strlen( wp_json_encode( $s['ledger']['last_receipt'] ) ) <= 1024, 'Full history discarded evidence or blocked latest closure.' ); $receipt['history_capacity_retains_closure_receipt'] = true;
	$bounded = $prepared; $bounded['generation'] = PHP_INT_MAX - 2; $reset( $bounded ); $before = $backend->read( $name );
	alynt_lifecycle_check( alynt_lifecycle_error( $model->command( alynt_ledger_intent( 'grant', 1, $now ) ), 'schedule_safety_ledger_counter' ) && $before === $backend->read( $name ), 'Grant consumed counter closure reserve.' );
	$r = $model->command( alynt_ledger_intent( 'revoke', 2, $now ) ); $s = $model->snapshot();
	alynt_lifecycle_check( is_array( $r ) && 'committed' === $r['outcome'] && PHP_INT_MAX - 1 === $s['generation'] && ! $s['lifecycle']['consent'], 'Counter headroom did not permit final closure.' ); $receipt['counter_headroom_reserved_for_closure'] = true;

	$reset( $prepared ); $close = alynt_ledger_intent( 'revoke', 2, $now ); $model->command( $close ); $protected = $model->snapshot();
	$protected['dispatch'] = array( 'owner' => '77777777-7777-4777-8777-777777777777', 'request_hash' => str_repeat( 'a', 64 ), 'enrollment_binding' => str_repeat( 'e', 64 ), 'schedule_revision' => 0, 'due_at' => $now - 900, 'claimed_at' => $now - 900, 'lease_deadline' => $now - 600, 'phase' => 'recovery_required', 'completed_at' => 0 );
	$protected['lifecycle']['phase'] = 'draining'; $reset( $protected ); $later = alynt_ledger_model( $backend, $now + 90000 ); $before = $backend->read( $name );
	alynt_lifecycle_check( alynt_lifecycle_error( $later->command( alynt_ledger_intent( 'grant', 3, $now + 90000, 2 ) ), 'schedule_safety_ledger_grant_blocked' ) && $before === $backend->read( $name ), 'Expired uncertainty admitted grant.' );
	$r = $later->command( alynt_ledger_intent( 'rebind', 4, $now + 90000, 2 ) ); $end = $later->snapshot();
	alynt_lifecycle_check( is_array( $r ) && 'committed' === $r['outcome'] && $protected['dispatch'] === $end['dispatch'] && $protected['authority'] === $end['authority'] && 'draining' === $end['lifecycle']['phase'] && ! $end['lifecycle']['consent'], 'Closure erased expired ownership.' ); $receipt['expired_uncertainty_retained_across_closure'] = true;
	$reset( $prepared ); $model->command( alynt_ledger_intent( 'revoke', 2, $now ) ); $closed_seed = $model->snapshot();
	$legacy_seed = array( 'schema_version' => 1, 'generation' => 0, 'enrollment_binding' => str_repeat( 'e', 64 ), 'schedule_revision' => 0, 'claims' => array() ); $reset( $legacy_seed );
	$legacy = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Store( $backend, function () use ( $now ) { return $now; } ); $e = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $now ); $source = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE;
	alynt_lifecycle_check( is_array( $legacy->claim( str_repeat( 'e', 64 ), $source, $e ) ) && is_array( $legacy->transition( str_repeat( 'e', 64 ), $source, $e['action_id'], $e['request_hash'], 'claimed', 'write_started' ) ) && is_array( $legacy->transition( str_repeat( 'e', 64 ), $source, $e['action_id'], $e['request_hash'], 'write_started', 'recovery_required' ) ), 'Journal recovery fixture failed.' );
	$protected = $closed_seed; $protected['claims'] = $legacy->snapshot()['claims']; $protected['lifecycle']['phase'] = 'draining';
	$unmigrated = $protected; unset( $unmigrated['ledger'] ); $unmigrated['schema_version'] = 3; $reset( $unmigrated ); $before = $backend->read( $name );
	alynt_lifecycle_check( alynt_lifecycle_error( $later->prepare_ledger( str_repeat( 'e', 64 ), 2 ), 'schedule_safety_ledger_migration_blocked' ) && $before === $backend->read( $name ), 'Expired journal migrated or disappeared.' );
	$reset( $protected ); $r = $later->command( alynt_ledger_intent( 'rebind', 4, $now + 90000, 2 ) ); $end = $later->snapshot();
	alynt_lifecycle_check( is_array( $r ) && 'committed' === $r['outcome'] && $protected['claims'] === $end['claims'] && 'draining' === $end['lifecycle']['phase'], 'Journal closure lost old ownership/evidence.' ); $receipt['expired_journal_migration_blocked_and_closure_preserves_it'] = true;
	$terminal = $unmigrated; $terminal['lifecycle']['phase'] = 'disabled'; $terminal['lifecycle']['history'] = 'unknown';
	$terminal['claims'][$source]['phase'] = 'succeeded'; $terminal['claims'][$source]['completed_at'] = $now; $terminal['claims'][$source]['authority_evidence_version'] = 1; $reset( $terminal );
	$r = $model->prepare_ledger( str_repeat( 'e', 64 ), 2 ); alynt_lifecycle_check( is_array( $r ) && $terminal['claims'] === $r['claims'] && $terminal['authority'] === $r['authority'] && 'unknown' === $r['lifecycle']['history'] && ! $r['lifecycle']['consent'], 'Terminal native journal migration changed provenance/due/history.' ); $receipt['terminal_native_journal_preserved_on_migration'] = true;

	$reset( $prepared ); $model->command( alynt_ledger_intent( 'grant', 1, $now ) ); $r = $model->command( alynt_ledger_intent( 'revoke', 2, $now, 2 ) );
	alynt_lifecycle_check( is_array( $r ) && 'committed' === $r['outcome'], 'Pre-secondary closure failed.' );
	alynt_lifecycle_check( 1 === $wpdb->insert( $table, array( 'option_name' => 'probe_secondary_setting', 'option_value' => 'unchanged', 'autoload' => 'no' ) ), 'Secondary synthetic row failed.' ); $suppression = $wpdb->suppress_errors( true );
	try { alynt_lifecycle_check( false !== $wpdb->query( 'START TRANSACTION READ ONLY' ) && false === $wpdb->update( $table, array( 'option_value' => 'changed' ), array( 'option_name' => 'probe_secondary_setting' ) ), 'Secondary SQL did not fail as expected.' ); }
	finally { $wpdb->query( 'ROLLBACK' ); $wpdb->suppress_errors( $suppression ); }
	$s = $model->snapshot(); alynt_lifecycle_check( ! $s['lifecycle']['consent'] && 'disabled' === $s['lifecycle']['phase'] && 'unchanged' === $wpdb->get_var( "SELECT option_value FROM {$table} WHERE option_name='probe_secondary_setting'" ), 'Secondary failure reopened ledger or changed its row.' ); $receipt['closure_survives_secondary_fixture_write_failure'] = true;

	$reset( $prepared ); $before = $backend->read( $name ); alynt_lifecycle_check( true === $backend->swap( $name, $before['option_value'], '{' ), 'Ledger corruption fixture failed.' ); $corrupt = $backend->read( $name );
	alynt_lifecycle_check( is_wp_error( $model->snapshot() ) && is_wp_error( $model->prepare_ledger( str_repeat( 'e', 64 ), 1 ) ) && is_wp_error( $model->command( alynt_ledger_intent( 'grant', 1, $now ) ) ) && $corrupt === $backend->read( $name ), 'Corrupt ledger repaired or admitted.' ); $receipt['corruption_not_repaired_or_admitted'] = true;
}
