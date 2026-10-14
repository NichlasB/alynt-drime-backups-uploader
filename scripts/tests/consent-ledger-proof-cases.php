<?php
/** Real ledger SQL proof using only owned synthetic rows, never actual settings/enrollment. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_ledger_cases( $root, $table, $token, &$children, &$receipt ) {
	global $wpdb; alynt_lifecycle_owned( $table, $token );
	$name = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION; $binding = str_repeat( 'e', 64 ); $now = time();
	$backend = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS( $wpdb ); $model = alynt_ledger_model( $backend, $now );
	alynt_lifecycle_check( is_wp_error( $model->snapshot() ) && null === $backend->read( $name ), 'Ledger read created missing storage.' );
	alynt_lifecycle_check( alynt_lifecycle_error( $model->prepare_ledger( $binding, 0 ), 'schedule_safety_ledger_unavailable' ) && null === $backend->read( $name ), 'Ledger migration created missing storage.' );
	$receipt['missing_state_not_created'] = true;
	$legacy = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Lifecycle_Store( $backend, function () use ( $now ) { return $now; } );
	$seed = $legacy->prepare_lifecycle( $binding, 'every_15_minutes', $now + 900 ); alynt_lifecycle_check( is_array( $seed ), 'Schema-3 seed failed.' );
	$reset = function ( $state ) use ( $wpdb, $table, $token, $name ) {
		alynt_lifecycle_owned( $table, $token ); alynt_lifecycle_check( $wpdb->options === $table, 'Ledger fixture scope lost.' );
		alynt_lifecycle_check( false !== $wpdb->update( $table, array( 'option_value' => wp_json_encode( $state ), 'autoload' => 'no' ), array( 'option_name' => $name ) ), 'Ledger fixture seed failed.' );
		foreach ( array( 'alloptions', 'notoptions', $name ) as $key ) wp_cache_delete( $key, 'options' );
	};
	$queries = $wpdb->num_queries; $prepared = $model->prepare_ledger( $binding, 0 );
	alynt_lifecycle_check( is_array( $prepared ) && 2 === $wpdb->num_queries - $queries && 4 === $prepared['schema_version'] && 1 === $prepared['lifecycle']['permission_generation'] && ! $prepared['lifecycle']['consent'] && ! $prepared['authority']['enabled'] && $seed['authority'] === $prepared['authority'] && $seed['lifecycle']['history'] === $prepared['lifecycle']['history'], 'Ledger migration not atomic/closed/preserving.' );
	alynt_lifecycle_check( in_array( $backend->read( $name )['autoload'], array( 'no', 'off', 'auto-off' ), true ), 'Ledger fixture is autoloaded.' ); $receipt['schema_four_migration_atomic_closed'] = true;

	wp_cache_set( $name, 'stale', 'options' ); wp_cache_set( 'alloptions', array( $name => 'stale' ), 'options' ); wp_cache_set( 'notoptions', array( $name => true ), 'options' );
	alynt_lifecycle_check( $prepared === $model->snapshot(), 'Ledger authoritative read used stale cache.' );
	$grant = alynt_ledger_intent( 'grant', 1, $now ); $queries = $wpdb->num_queries; $r = $model->command( $grant );
	alynt_lifecycle_check( is_array( $r ) && 'committed' === $r['outcome'] && 2 === $wpdb->num_queries - $queries && $backend->read( $name )['option_value'] === get_option( $name ), 'Ledger grant/CAS/cache proof failed.' );
	$granted = $model->snapshot(); $before = $backend->read( $name ); $queries = $wpdb->num_queries; $r = $model->command( array_reverse( $grant, true ) );
	alynt_lifecycle_check( is_array( $r ) && 'observed_current' === $r['outcome'] && 1 === $wpdb->num_queries - $queries && $before === $backend->read( $name ), 'Duplicate issued another write.' ); $receipt['grant_cache_and_duplicate_observation'] = true;
	$r = $model->command( alynt_ledger_intent( 'rebind', 2, $now, 2 ) ); $closed = $model->snapshot(); $before = $backend->read( $name );
	alynt_lifecycle_check( is_array( $r ) && 'committed' === $r['outcome'] && ! $closed['lifecycle']['consent'] && ! $closed['authority']['enabled'] && 'fixture_intent_v1' === $closed['ledger']['source'] && str_repeat( 'f', 64 ) === $closed['enrollment_binding'], 'Rebind failed to close ledger.' );
	$r = $model->command( $grant ); alynt_lifecycle_check( is_array( $r ) && 'observed_superseded' === $r['outcome'] && $before === $backend->read( $name ), 'Old grant replayed after closure.' ); $receipt['rebind_supersedes_without_regrant'] = true;
	foreach ( array( 'migration', 'grant', 'revoke' ) as $fault ) {
		$reset( 'migration' === $fault ? $seed : ( 'grant' === $fault ? $prepared : $granted ) ); $before = $backend->read( $name ); $suppression = $wpdb->suppress_errors( true );
		try {
			alynt_lifecycle_check( false !== $wpdb->query( 'START TRANSACTION READ ONLY' ), 'Ledger failed-write transaction setup failed.' );
			$r = 'migration' === $fault ? $model->prepare_ledger( $binding, 0 ) : $model->command( 'grant' === $fault ? $grant : alynt_ledger_intent( 'revoke', 2, $now, 2 ) );
			alynt_lifecycle_check( alynt_lifecycle_error( $r, 'schedule_safety_ledger_unresolved' ), 'Failed SQL reported ledger success.' );
		} finally { $wpdb->query( 'ROLLBACK' ); $wpdb->suppress_errors( $suppression ); }
		alynt_lifecycle_check( $before === $backend->read( $name ), 'Failed SQL altered ledger/receipt/binding.' );
	}
	$receipt['real_failed_migration_grant_closure_preserve_bytes'] = true;
	alynt_ledger_race_cases( $root, $table, $token, $children, $receipt, $model, $backend, $reset, $seed, $prepared, $granted, $now );
	alynt_ledger_edge_cases( $table, $token, $receipt, $model, $backend, $reset, $prepared, $now );
}
require_once __DIR__ . '/consent-ledger-proof-races.php';
require_once __DIR__ . '/consent-ledger-proof-edges.php';
