<?php
/** Independent processes share only the owned table; no epoch adoption or ambiguous replay. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_provenance_races( $root, $table, $token, &$children, &$receipt, $model, $backend, $reset, $seed, $granted, $now ) {
	global $wpdb; $name = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION; $binding = str_repeat( 'e', 64 );
	foreach ( array( 'migrate_three', 'migrate_four', 'ordered' ) as $case ) {
		$old = 'migrate_three' === $case ? $seed : $granted; $reset( $old ); $indexes = array();
		for ( $actor = 1; $actor <= 2; $actor++ ) $indexes[] = alynt_provenance_spawn( $root, $table, $token, $children, $actor, 'ordered' === $case && 2 === $actor ? 'revoke' : 'migrate', $now, $case );
		for ( $actor = 1; $actor <= 2; $actor++ ) alynt_lifecycle_wait( $table, 'probe_provenance_' . $case . '_ready_' . $actor );
		alynt_lifecycle_check( 1 === $wpdb->insert( $table, array( 'option_name' => 'probe_provenance_' . $case . '_go', 'option_value' => 'go', 'autoload' => 'no' ) ), 'Provenance parent barrier failed.' );
		$results = array(); foreach ( $indexes as $index ) $results[] = alynt_lifecycle_join( $children[$index] );
		alynt_lifecycle_check( 1 === count( array_filter( $results, function ( $r ) { return true === $r['ok']; } ) ), 'Migration race admitted multiple writers.' );
		if ( 'ordered' === $case ) {
			$s = alynt_ledger_model( $backend, $now )->snapshot();
			alynt_lifecycle_check( ! $results[0]['ok'] && 'schedule_safety_provenance_stale' === $results[0]['code'] && $results[1]['ok'] && is_array( $s ) && 4 === $s['schema_version'] && 3 === $s['lifecycle']['permission_generation'] && ! $s['lifecycle']['consent'] && ! $s['authority']['enabled'] && $granted['generation'] + 1 === $s['generation'], 'Competing fixture closure did not fence migration.' );
		} else {
			alynt_provenance_closed_check( $model->snapshot(), $old ); $codes = array_column( $results, 'code' ); sort( $codes );
			alynt_lifecycle_check( array( 'schedule_safety_provenance_stale', 'stored' ) === $codes, 'Migration loser adopted the winning epoch.' );
		}
		$receipt['two_process_' . $case . '_fenced'] = true;
	}
	foreach ( array( 'lost_ack_three' => $seed, 'lost_ack_four' => $granted ) as $case => $old ) {
		$reset( $old ); $index = alynt_provenance_spawn( $root, $table, $token, $children, 1, 'migrate', $now, $case );
		alynt_lifecycle_wait( $table, 'probe_provenance_' . $case . '_done_1' ); alynt_lifecycle_join( $children[$index], true );
		$restart = alynt_provenance_model( new Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS( $wpdb ), $now ); $s = $restart->snapshot(); alynt_provenance_closed_check( $s, $old ); $before = $backend->read( $name );
		$r = $restart->prepare_closed( $binding, $old['lifecycle']['permission_generation'], function () { return true; } );
		alynt_lifecycle_check( alynt_lifecycle_error( $r, 'schedule_safety_provenance_stale' ) && $before === $backend->read( $name ), 'Lost acknowledgement retried a stale migration.' );
		$queries = $wpdb->num_queries; $r = $restart->prepare_closed( $binding, $s['lifecycle']['permission_generation'], function () { return true; } );
		alynt_lifecycle_check( $s === $r && 1 === $wpdb->num_queries - $queries && $before === $backend->read( $name ), 'Post-restart observation issued a write.' ); $receipt[$case . '_reconciled_without_replay'] = true;
	}
}
