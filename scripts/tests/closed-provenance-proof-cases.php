<?php
/** Schema-5 SQL/process proof on the parent-owned empty table, with untouched actual users. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_provenance_users_digest() {
	global $wpdb; $digests = array();
	foreach ( array( array( $wpdb->users, 'ID', 'ID,user_login,user_pass,user_nicename,user_email,user_url,user_registered,user_activation_key,user_status,display_name' ), array( $wpdb->usermeta, 'umeta_id', 'umeta_id,user_id,meta_key,meta_value' ) ) as $spec ) {
		list( $table, $order, $columns ) = $spec; alynt_lifecycle_check( 1 === preg_match( '/^[A-Za-z0-9_]+$/D', $table ), 'Invalid actual user table identifier.' );
		$rows = $wpdb->get_col( "SELECT SHA2(JSON_ARRAY({$columns}),256) FROM {$table} ORDER BY {$order}" );
		alynt_lifecycle_check( '' === $wpdb->last_error && is_array( $rows ), 'Actual-user digest read failed.' );
		$digests[] = hash( 'sha256', wp_json_encode( $rows ) ); // Never emit private fields or row hashes.
	}
	return $digests;
}
function alynt_provenance_closed_check( $s, $old ) {
	alynt_lifecycle_check( is_array( $s ) && 5 === $s['schema_version'] && ! $s['lifecycle']['consent'] && 'disabled' === $s['lifecycle']['phase'] && ! $s['authority']['enabled'] && null === $s['provenance']['authenticated_grant'] && array() === $s['provenance']['authenticated_receipts'] && 'closed_migration_fixture_v1' === $s['provenance']['source'], 'Migration admitted consent or authenticated provenance.' );
	foreach ( array( 'generation', 'schedule_revision' ) as $key ) alynt_lifecycle_check( $old[$key] + 1 === $s[$key], 'Migration counter did not advance once.' );
	alynt_lifecycle_check( $old['lifecycle']['permission_generation'] + 1 === $s['lifecycle']['permission_generation'] && $old['lifecycle'] === $s['provenance']['legacy']['lifecycle'] && ( $old['ledger'] ?? null ) === $s['provenance']['legacy']['ledger'], 'Migration changed archived evidence.' );
	foreach ( array( 'authority', 'claims', 'dispatch', 'enrollment_binding' ) as $key ) alynt_lifecycle_check( $old[$key] === $s[$key], 'Migration changed protected work or binding.' );
}
function alynt_provenance_cases( $root, $table, $token, &$children, &$receipt ) {
	global $wpdb; alynt_lifecycle_owned( $table, $token ); $name = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION; $binding = str_repeat( 'e', 64 ); $now = time();
	$backend = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS( $wpdb ); $model = alynt_provenance_model( $backend, $now ); $guard = function () { return true; };
	$queries = $wpdb->num_queries; $off = alynt_provenance_model( $backend, $now, false );
	alynt_lifecycle_check( is_wp_error( $off->snapshot() ) && is_wp_error( $off->prepare_closed( $binding, 0, $guard ) ) && $queries === $wpdb->num_queries, 'Dormant model read SQL.' ); $receipt['default_off_zero_sql'] = true;
	alynt_lifecycle_check( is_wp_error( $model->snapshot() ) && alynt_lifecycle_error( $model->prepare_closed( $binding, 0, $guard ), 'schedule_safety_provenance_unavailable' ) && null === $backend->read( $name ), 'Missing state created or repaired.' ); $receipt['missing_state_not_created'] = true;
	$legacy = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Lifecycle_Store( $backend, function () use ( $now ) { return $now; } );
	$seed = $legacy->prepare_lifecycle( $binding, 'every_15_minutes', $now + 900 ); alynt_lifecycle_check( is_array( $seed ), 'Provenance schema-3 seed failed.' );
	$reset = function ( $state ) use ( $wpdb, $table, $token, $name ) {
		alynt_lifecycle_owned( $table, $token ); alynt_lifecycle_check( $wpdb->options === $table, 'Provenance fixture scope lost.' );
		alynt_lifecycle_check( false !== $wpdb->update( $table, array( 'option_value' => wp_json_encode( $state ), 'autoload' => 'no' ), array( 'option_name' => $name ) ), 'Provenance fixture seed failed.' );
		foreach ( array( 'alloptions', 'notoptions', $name ) as $key ) wp_cache_delete( $key, 'options' );
	};
	$ledger = alynt_ledger_model( $backend, $now ); alynt_lifecycle_check( is_array( $ledger->prepare_ledger( $binding, 0 ) ) && is_array( $ledger->command( alynt_ledger_intent( 'grant', 1, $now ) ) ), 'Untrusted schema-4 archive seed failed.' ); $granted = $ledger->snapshot();
	foreach ( array( 'three' => $seed, 'four' => $granted ) as $label => $old ) {
		$reset( $old ); wp_cache_set( $name, 'stale', 'options' ); wp_cache_set( 'alloptions', array( $name => 'stale' ), 'options' ); wp_cache_set( 'notoptions', array( $name => true ), 'options' );
		$queries = $wpdb->num_queries; $s = $model->prepare_closed( $binding, $old['lifecycle']['permission_generation'], $guard ); alynt_lifecycle_check( 2 === $wpdb->num_queries - $queries, 'Migration was not one read and one CAS.' ); alynt_provenance_closed_check( $s, $old );
		alynt_lifecycle_check( $backend->read( $name )['option_value'] === get_option( $name ) && 'no' === $backend->read( $name )['autoload'], 'Migration cache or autoload isolation failed.' );
		$before = $backend->read( $name ); $queries = $wpdb->num_queries; $r = $model->prepare_closed( $binding, $s['lifecycle']['permission_generation'], $guard );
		alynt_lifecycle_check( $s === $r && 1 === $wpdb->num_queries - $queries && $before === $backend->read( $name ), 'Closed observation rewrote state.' ); $receipt['schema_' . $label . '_atomic_closed_cache_observation'] = true;
		$reset( $old ); $before = $backend->read( $name ); $suppression = $wpdb->suppress_errors( true );
		try { alynt_lifecycle_check( false !== $wpdb->query( 'START TRANSACTION READ ONLY' ), 'Provenance read-only transaction setup failed.' ); $r = $model->prepare_closed( $binding, $old['lifecycle']['permission_generation'], $guard ); alynt_lifecycle_check( alynt_lifecycle_error( $r, 'schedule_safety_provenance_unresolved' ), 'Failed SQL reported closure success.' ); }
		finally { $wpdb->query( 'ROLLBACK' ); $wpdb->suppress_errors( $suppression ); }
		alynt_lifecycle_check( $before === $backend->read( $name ), 'Failed SQL altered source bytes or synthetic consent.' ); $receipt['schema_' . $label . '_failed_sql_preserves_bytes'] = true;
	}
	$reset( $granted ); $before = $backend->read( $name ); $calls = 0; $r = $model->prepare_closed( $binding, 2, function () use ( &$calls ) { return 1 === ++$calls; } );
	alynt_lifecycle_check( 2 === $calls && alynt_lifecycle_error( $r, 'schedule_safety_provenance_denied' ) && $before === $backend->read( $name ), 'Final guard loss wrote a closure.' ); $receipt['final_guard_loss_preserves_source'] = true;
	alynt_provenance_races( $root, $table, $token, $children, $receipt, $model, $backend, $reset, $seed, $granted, $now );
	alynt_provenance_edges( $receipt, $model, $backend, $reset, $seed, $granted, $now );
	$reset( $granted ); $closed = $model->prepare_closed( $binding, 2, $guard ); alynt_provenance_closed_check( $closed, $granted );
	alynt_provenance_requests( $receipt, $model, $backend, $reset, $seed, $closed, $now );
}
require_once __DIR__ . '/closed-provenance-proof-races.php';
require_once __DIR__ . '/closed-provenance-proof-edges.php';
require_once __DIR__ . '/closed-provenance-proof-requests.php';
