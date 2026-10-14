<?php
/** Real SQL model proof on owned synthetic rows; never an operational recovery API. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_lifecycle_cases( $root, $table, $token, &$children, &$receipt ) {
	global $wpdb; alynt_lifecycle_owned( $table, $token );
	$name = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION; $binding = str_repeat( 'e', 64 ); $now = time();
	$backend = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS( $wpdb );
	$model = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Lifecycle_Store( $backend, function () use ( &$now ) { return $now; } );
	$legacy = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Store( $backend, function () use ( &$now ) { return $now; } );
	$seed = array( 'schema_version' => 1, 'generation' => 0, 'enrollment_binding' => $binding, 'schedule_revision' => 0, 'claims' => array() );
	alynt_lifecycle_check( is_wp_error( $model->snapshot() ) && null === $backend->read( $name ), 'Read implicitly created lifecycle state.' );
	$suppression = $wpdb->suppress_errors( true );
	try {
		alynt_lifecycle_check( false !== $wpdb->query( 'START TRANSACTION READ ONLY' ), 'Create fault transaction failed.' );
		alynt_lifecycle_check( alynt_lifecycle_error( $model->prepare_lifecycle( $binding, 'every_15_minutes', $now + 900 ), 'schedule_safety_contention' ), 'Failed option creation claimed success.' );
	} finally { $wpdb->query( 'ROLLBACK' ); $wpdb->suppress_errors( $suppression ); }
	alynt_lifecycle_check( null === $backend->read( $name ), 'Failed create persisted state.' ); $receipt['failed_create_no_state_or_success'] = true;
	$new = $model->prepare_lifecycle( $binding, 'every_15_minutes', $now + 900 );
	alynt_lifecycle_check( is_array( $new ) && 'new_model' === $new['lifecycle']['history'] && ! $new['authority']['enabled'] && ! $new['lifecycle']['consent'], 'Explicit new model creation failed.' ); $receipt['new_model_creation_disabled'] = true;
	alynt_lifecycle_check( 1 === $wpdb->update( $table, array( 'option_value' => wp_json_encode( $seed ) ), array( 'option_name' => $name ) ), 'Independent legacy seed failed.' );
	alynt_lifecycle_check( is_array( $legacy->initialize( $binding ) ), 'Legacy fixture creation failed.' );
	// Only synthetic fixture rows are reset between independent cases. No actual option is redirected hereafter.
	$reset = function ( $state ) use ( $wpdb, $table, $token, $name ) {
		alynt_lifecycle_owned( $table, $token ); alynt_lifecycle_check( $wpdb->options === $table, 'Fixture scope lost.' );
		alynt_lifecycle_check( false !== $wpdb->update( $table, array( 'option_value' => wp_json_encode( $state ), 'autoload' => 'no' ), array( 'option_name' => $name ) ), 'Fixture seed failed.' );
		foreach ( array( 'alloptions', 'notoptions', $name ) as $key ) wp_cache_delete( $key, 'options' );
	};
	$queries = $wpdb->num_queries; $s = $model->prepare_lifecycle( $binding, 'every_15_minutes', $now + 900 );
	alynt_lifecycle_check( is_array( $s ) && 3 === $s['schema_version'] && ! $s['authority']['enabled'] && ! $s['lifecycle']['consent'] && 'unknown' === $s['lifecycle']['history'] && 2 === $wpdb->num_queries - $queries, 'Migration did not use one read and one CAS.' );
	alynt_lifecycle_check( in_array( $backend->read( $name )['autoload'], array( 'no', 'off', 'auto-off' ), true ), 'Fixture state autoloaded.' );
	$prepared = $s; $receipt['schema_one_migration_atomic_disabled'] = true;
	wp_cache_set( $name, 'stale', 'options' ); wp_cache_set( 'alloptions', array( $name => 'stale' ), 'options' ); wp_cache_set( 'notoptions', array( $name => true ), 'options' );
	alynt_lifecycle_check( $s === $model->snapshot(), 'Lifecycle read used stale cache.' );
	$queries = $wpdb->num_queries; alynt_lifecycle_check( 1 === $model->record_consent( $binding, 0, true ) && 2 === $wpdb->num_queries - $queries, 'Consent was not one read plus one CAS.' );
	alynt_lifecycle_check( $backend->read( $name )['option_value'] === get_option( $name ) && ! $model->snapshot()['authority']['enabled'], 'Cache invalidation or disabled invariant failed.' ); $receipt['consent_atomic_and_cache_invalidated'] = true;
	$granted = $model->snapshot();

	foreach ( array( 'migration', 'grant', 'revoke' ) as $fault ) {
		$reset( 'migration' === $fault ? $seed : ( 'revoke' === $fault ? $granted : $prepared ) ); $before = $backend->read( $name ); $suppression = $wpdb->suppress_errors( true );
		try {
			alynt_lifecycle_check( false !== $wpdb->query( 'START TRANSACTION READ ONLY' ), 'Read-only fault transaction failed.' );
			$r = 'migration' === $fault ? $model->prepare_lifecycle( $binding, 'every_15_minutes', $now + 900 ) : $model->record_consent( $binding, 'revoke' === $fault ? 1 : 0, 'grant' === $fault, 'revoke' === $fault ? str_repeat( 'f', 64 ) : null );
			alynt_lifecycle_check( alynt_lifecycle_error( $r, 'schedule_safety_write_failed' ), 'Failed SQL claimed lifecycle success.' );
		} finally { $wpdb->query( 'ROLLBACK' ); $wpdb->suppress_errors( $suppression ); }
		alynt_lifecycle_check( $before === $backend->read( $name ), 'Failed SQL partially changed lifecycle/binding.' );
	}
	$receipt['real_sql_migration_grant_revoke_failures_closed'] = true;
	$reset( $seed ); $older = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Store( $backend, function () use ( $now ) { return $now; } );
	$older->initialize_authority( $binding, 'every_15_minutes', $now ); $old = $older->snapshot(); $owner = '77777777-7777-4777-8777-777777777777'; $hash = str_repeat( 'a', 64 );
	$older->claim_dispatch( $binding, $old['schedule_revision'], $older->authority_fingerprint( $old ), $owner, $hash ); $older->start_dispatch( $binding, $owner, $hash ); $older->complete_dispatch( $binding, $owner, $hash ); $old = $older->snapshot();
	alynt_lifecycle_check( 'completed' === $old['dispatch']['phase'], 'Older terminal receipt fixture failed.' );
	$migrated = $model->prepare_lifecycle( $binding, 'every_15_minutes', $old['authority']['next_due_at'] );
	alynt_lifecycle_check( is_array( $migrated ) && $old['dispatch'] === $migrated['dispatch'] && $old['claims'] === $migrated['claims'] && ! $migrated['authority']['enabled'] && ! $migrated['lifecycle']['consent'] && 'unknown' === $migrated['lifecycle']['history'], 'Schema-two migration altered receipt or inherited permission.' );
	$before = $backend->read( $name ); alynt_lifecycle_check( alynt_lifecycle_error( $model->start_dispatch( $binding, $owner, $hash ), 'schedule_safety_dispatch_phase_invalid' ) && $before === $backend->read( $name ), 'Migrated receipt restarted dispatch.' ); $receipt['schema_two_receipt_migration_no_replay'] = true;
	foreach ( array( 'migrate', 'race', 'ordered', 'revoke_ordered' ) as $case ) {
		$reset( 'migrate' === $case ? $seed : $prepared ); $indexes = array();
		for ( $actor = 1; $actor <= 2; $actor++ ) $indexes[] = alynt_lifecycle_spawn( $root, $table, $token, $children, $actor, 'migrate' === $case ? 'migrate' : ( 1 === $actor ? 'grant' : ( 'revoke_ordered' === $case ? 'revoke' : 'rebind' ) ), $now, $case );
		for ( $actor = 1; $actor <= 2; $actor++ ) alynt_lifecycle_wait( $table, 'probe_' . $case . '_ready_' . $actor );
		alynt_lifecycle_check( 1 === $wpdb->insert( $table, array( 'option_name' => 'probe_' . $case . '_go', 'option_value' => 'go', 'autoload' => 'no' ) ), 'Parent barrier release failed.' );
		$results = array(); foreach ( $indexes as $index ) $results[] = alynt_lifecycle_join( $children[$index] ); $s = $model->snapshot();
		alynt_lifecycle_check( is_array( $s ) && ! $s['authority']['enabled'], 'Race activated or corrupted model.' );
		if ( 'migrate' === $case ) alynt_lifecycle_check( $results[0]['ok'] && $results[1]['ok'] && 1 === $s['generation'] && 1 === $s['schedule_revision'] && 0 === $s['lifecycle']['permission_generation'], 'Migration race repeated/reset generation.' );
		else {
			alynt_lifecycle_check( 1 === count( array_filter( $results, function ( $r ) { return $r['ok']; } ) ) && 1 === $s['lifecycle']['permission_generation'] && $prepared['generation'] + 1 === $s['generation'], 'Epoch race had multiple accepted writes.' );
			if ( 'ordered' === $case ) alynt_lifecycle_check( ! $results[0]['ok'] && 'schedule_safety_binding_changed' === $results[0]['code'] && $results[1]['ok'] && str_repeat( 'f', 64 ) === $s['enrollment_binding'] && ! $s['lifecycle']['consent'], 'Rebinding winner did not fence stale grant.' );
			if ( 'revoke_ordered' === $case ) alynt_lifecycle_check( ! $results[0]['ok'] && 'schedule_safety_lifecycle_stale' === $results[0]['code'] && $results[1]['ok'] && $binding === $s['enrollment_binding'] && ! $s['lifecycle']['consent'] && 'disabled' === $s['lifecycle']['phase'], 'Revocation winner did not fence stale grant.' );
		}
		$receipt['two_process_' . $case . '_protected'] = true;
	}
	$reset( $prepared ); $index = alynt_lifecycle_spawn( $root, $table, $token, $children, 1, 'rebind', $now, 'lost_ack' );
	alynt_lifecycle_wait( $table, 'probe_lost_ack_done_1' ); alynt_lifecycle_join( $children[$index], true );
	$restart = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Lifecycle_Store( new Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS( $wpdb ), function () use ( $now ) { return $now; } ); $s = $restart->snapshot();
	alynt_lifecycle_check( 1 === $s['lifecycle']['permission_generation'] && str_repeat( 'f', 64 ) === $s['enrollment_binding'] && ! $s['lifecycle']['consent'] && ! $s['authority']['enabled'], 'Lost acknowledgement lost durable rebind.' );
	$before = $backend->read( $name ); alynt_lifecycle_check( alynt_lifecycle_error( $restart->record_consent( $binding, 0, true ), 'schedule_safety_binding_changed' ) && alynt_lifecycle_error( $restart->record_consent( str_repeat( 'f', 64 ), 0, true ), 'schedule_safety_lifecycle_stale' ) && $before === $backend->read( $name ), 'Lost acknowledgement allowed blind old-epoch replay.' );
	$receipt['terminated_after_commit_rebind_reconciled_without_replay'] = true;
	$reset( $seed ); $e = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $now ); $source = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE;
	$legacy->claim( $binding, $source, $e ); $legacy->transition( $binding, $source, $e['action_id'], $e['request_hash'], 'claimed', 'write_started' ); $legacy->transition( $binding, $source, $e['action_id'], $e['request_hash'], 'write_started', 'recovery_required' );
	$before = $backend->read( $name ); $now += 90000;
	alynt_lifecycle_check( alynt_lifecycle_error( $model->prepare_lifecycle( $binding, 'every_15_minutes', $now + 900 ), 'schedule_safety_lifecycle_recovery_pending' ) && $before === $backend->read( $name ), 'Expired migration erased uncertainty.' );
	$protected = $prepared; $protected['claims'] = $legacy->snapshot()['claims']; $protected['lifecycle']['phase'] = 'draining'; $reset( $protected ); $claims = $model->snapshot()['claims'];
	alynt_lifecycle_check( 1 === $model->record_consent( $binding, 0, false, str_repeat( 'f', 64 ) ) && $claims === $model->snapshot()['claims'] && 'draining' === $model->snapshot()['lifecycle']['phase'], 'Revocation/rebind discarded retained journal.' );
	alynt_lifecycle_check( alynt_lifecycle_error( $model->record_consent( str_repeat( 'f', 64 ), 1, true ), 'schedule_safety_lifecycle_recovery_pending' ), 'Uncertainty granted consent.' ); $receipt['expired_uncertainty_retained_across_rebind'] = true;
	$reset( $prepared ); $before = $backend->read( $name );
	alynt_lifecycle_check( true === $backend->swap( $name, $before['option_value'], '{' ), 'Corruption fixture failed.' ); $corrupt = $backend->read( $name );
	alynt_lifecycle_check( is_wp_error( $model->prepare_lifecycle( $binding, 'every_15_minutes', $prepared['authority']['next_due_at'] ) ) && is_wp_error( $model->record_consent( $binding, 0, true ) ) && $corrupt === $backend->read( $name ), 'Corrupt fixture was silently repaired.' ); $receipt['corruption_preserved_without_repair'] = true;
}
