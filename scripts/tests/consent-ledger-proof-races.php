<?php
/** Independent-process receipt/epoch races and terminated acknowledgements on owned rows. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_ledger_race_cases( $root, $table, $token, &$children, &$receipt, $model, $backend, $reset, $seed, $prepared, $granted, $now ) {
	global $wpdb; $name = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION; $binding = str_repeat( 'e', 64 );
	foreach ( array( 'migrate', 'race', 'ordered', 'revoke_ordered', 'duplicate' ) as $case ) {
		$reset( 'migrate' === $case ? $seed : $prepared ); $indexes = array();
		for ( $actor = 1; $actor <= 2; $actor++ ) $indexes[] = alynt_ledger_spawn( $root, $table, $token, $children, $actor, 'migrate' === $case ? 'migrate' : ( 1 === $actor || 'duplicate' === $case ? 'grant' : ( 'revoke_ordered' === $case ? 'revoke' : 'rebind' ) ), $now, $case );
		for ( $actor = 1; $actor <= 2; $actor++ ) alynt_lifecycle_wait( $table, 'probe_ledger_' . $case . '_ready_' . $actor );
		alynt_lifecycle_check( 1 === $wpdb->insert( $table, array( 'option_name' => 'probe_ledger_' . $case . '_go', 'option_value' => 'go', 'autoload' => 'no' ) ), 'Ledger parent barrier release failed.' );
		$results = array(); foreach ( $indexes as $index ) $results[] = alynt_lifecycle_join( $children[$index] ); $s = $model->snapshot();
		alynt_lifecycle_check( is_array( $s ) && ! $s['authority']['enabled'], 'Ledger race enabled or corrupted authority.' );
		if ( 'duplicate' === $case ) {
			$codes = array_column( $results, 'code' ); sort( $codes );
			alynt_lifecycle_check( array( 'committed', 'observed_current' ) === $codes && 2 === $s['lifecycle']['permission_generation'] && $prepared['generation'] + 1 === $s['generation'] && empty( $s['ledger']['receipts'] ), 'Duplicate race made multiple transitions.' );
		} else {
			alynt_lifecycle_check( 1 === count( array_filter( $results, function ( $r ) { return $r['ok']; } ) ) && ( 'migrate' === $case ? 1 : 2 ) === $s['lifecycle']['permission_generation'] && ( 'migrate' === $case ? $seed['generation'] : $prepared['generation'] ) + 1 === $s['generation'], 'Ledger race admitted multiple writers.' );
			if ( in_array( $case, array( 'ordered', 'revoke_ordered' ), true ) ) alynt_lifecycle_check( ! $results[0]['ok'] && 'schedule_safety_ledger_stale' === $results[0]['code'] && $results[1]['ok'] && ! $s['lifecycle']['consent'] && ( 'ordered' === $case ? str_repeat( 'f', 64 ) : $binding ) === $s['enrollment_binding'], 'Ordered closure did not fence stale grant.' );
		}
		$receipt['two_process_' . $case . '_protected'] = true;
	}
	foreach ( array( 'lost_ack_grant', 'lost_ack_close' ) as $case ) {
		$is_grant = 'lost_ack_grant' === $case; $reset( $is_grant ? $prepared : $granted );
		$actor = $is_grant ? 1 : 2; $purpose = $is_grant ? 'grant' : 'revoke'; $generation = $is_grant ? 1 : 2;
		$index = alynt_ledger_spawn( $root, $table, $token, $children, $actor, $purpose, $now, $case );
		alynt_lifecycle_wait( $table, 'probe_ledger_' . $case . '_done_' . $actor ); alynt_lifecycle_join( $children[$index], true );
		$restart = alynt_ledger_model( new Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS( $wpdb ), $now ); $s = $restart->snapshot(); $before = $backend->read( $name ); $queries = $wpdb->num_queries;
		$r = $restart->command( alynt_ledger_intent( $purpose, $actor, $now, $generation ) );
		alynt_lifecycle_check( is_array( $s ) && $generation + 1 === $s['lifecycle']['permission_generation'] && $is_grant === $s['lifecycle']['consent'] && ! $s['authority']['enabled'] && is_array( $r ) && 'observed_current' === $r['outcome'] && 1 === $wpdb->num_queries - $queries && $before === $backend->read( $name ), 'Lost acknowledgement replayed or lost durable command.' );
		if ( ! $is_grant ) { $r = $restart->command( alynt_ledger_intent( 'grant', 1, $now ) ); alynt_lifecycle_check( is_array( $r ) && 'observed_superseded' === $r['outcome'] && $before === $backend->read( $name ), 'Lost closure acknowledgement allowed regrant.' ); }
		$receipt[$case . '_reconciled_without_replay'] = true;
	}
}
