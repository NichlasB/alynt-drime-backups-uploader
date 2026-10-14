<?php
/** SQL history/counter reserve, retained uncertainty and corrupt/provenance refusal. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_provenance_edges( &$receipt, $model, $backend, $reset, $seed, $granted, $now ) {
	global $wpdb; $name = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION; $binding = str_repeat( 'e', 64 ); $guard = function () { return true; };
	$reset( $seed ); $ledger = alynt_ledger_model( $backend, $now ); alynt_lifecycle_check( is_array( $ledger->prepare_ledger( $binding, 0 ) ), 'Capacity seed migration failed.' );
	for ( $id = 1; $id <= 51; $id++ ) alynt_lifecycle_check( is_array( $ledger->command( alynt_ledger_intent( 'revoke', $id, $now, $id ) ) ), 'Provenance history population failed.' ); $full = $ledger->snapshot();
	$reset( array( 'schema_version' => 1, 'generation' => 0, 'enrollment_binding' => $binding, 'schedule_revision' => 0, 'claims' => array() ) );
	$legacy = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Store( $backend, function () use ( $now ) { return $now; } ); $e = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $now ); $source = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE;
	alynt_lifecycle_check( is_array( $legacy->claim( $binding, $source, $e ) ) && is_array( $legacy->transition( $binding, $source, $e['action_id'], $e['request_hash'], 'claimed', 'write_started' ) ) && is_array( $legacy->transition( $binding, $source, $e['action_id'], $e['request_hash'], 'write_started', 'recovery_required' ) ), 'Native journal fixture setup failed.' );
	$pending = $seed; $pending['claims'] = $legacy->snapshot()['claims']; $pending['lifecycle']['phase'] = 'draining';
	$terminal = $full; $terminal['claims'] = $pending['claims']; $terminal['claims'][$source]['phase'] = 'succeeded'; $terminal['claims'][$source]['completed_at'] = $now; $terminal['claims'][$source]['authority_evidence_version'] = 1; $terminal['lifecycle']['history'] = 'unknown'; $reset( $terminal );
	alynt_lifecycle_check( is_array( $ledger->snapshot() ), 'Terminal full-history source is invalid.' ); $s = $model->prepare_closed( $binding, 52, $guard ); alynt_provenance_closed_check( $s, $terminal );
	alynt_lifecycle_check( 50 === count( $s['provenance']['legacy']['ledger']['receipts'] ) && strlen( wp_json_encode( $s ) ) <= 131072 - 4096 && 'unknown' === $s['lifecycle']['history'], 'Archive lost history/journal or closure reserve.' ); $receipt['full_history_native_journal_and_byte_reserve_preserved'] = true;
	foreach ( array( 'journal', 'dispatch' ) as $case ) {
		$old = 'journal' === $case ? $pending : $seed; $old['lifecycle']['phase'] = 'draining';
		if ( 'dispatch' === $case ) $old['dispatch'] = array( 'owner' => '77777777-7777-4777-8777-777777777777', 'request_hash' => str_repeat( 'a', 64 ), 'enrollment_binding' => $binding, 'schedule_revision' => 0, 'due_at' => $now - 900, 'claimed_at' => $now - 900, 'lease_deadline' => $now - 600, 'phase' => 'recovery_required', 'completed_at' => 0 );
		$reset( $old ); $before = $backend->read( $name ); $r = alynt_provenance_model( $backend, $now + 90000 )->prepare_closed( $binding, 0, $guard );
		alynt_lifecycle_check( alynt_lifecycle_error( $r, 'schedule_safety_provenance_blocked' ) && $before === $backend->read( $name ), 'Expired uncertainty migrated or disappeared.' ); $receipt['expired_' . $case . '_blocks_without_repair'] = true;
	}
	$bounded = $seed; foreach ( array( 'generation', 'schedule_revision' ) as $key ) $bounded[$key] = PHP_INT_MAX - 3; $bounded['lifecycle']['permission_generation'] = PHP_INT_MAX - 3; $reset( $bounded );
	$s = $model->prepare_closed( $binding, PHP_INT_MAX - 3, $guard ); alynt_provenance_closed_check( $s, $bounded );
	foreach ( array( 'generation', 'schedule_revision' ) as $key ) $bounded[$key]++; $bounded['lifecycle']['permission_generation']++; $reset( $bounded ); $before = $backend->read( $name );
	alynt_lifecycle_check( alynt_lifecycle_error( $model->prepare_closed( $binding, PHP_INT_MAX - 2, $guard ), 'schedule_safety_provenance_blocked' ) && $before === $backend->read( $name ), 'Migration spent counter closure reserve.' ); $receipt['counter_closure_reserve_not_reset'] = true;
	$reset( $granted ); $closed = $model->prepare_closed( $binding, 2, $guard ); alynt_provenance_closed_check( $closed, $granted );
	foreach ( array( 'corrupt', 'autoload', 'unsupported', 'authenticated_source', 'authenticated_grant', 'authenticated_receipt', 'archive_tamper' ) as $case ) {
		$bad = $closed;
		if ( 'unsupported' === $case ) $bad['schema_version'] = 6;
		elseif ( 'authenticated_source' === $case ) $bad['provenance']['source'] = 'authenticated_request_v1';
		elseif ( 'authenticated_grant' === $case ) $bad['provenance']['authenticated_grant'] = array( 'command_id' => alynt_ledger_intent( 'grant', 1, $now )['command_id'] );
		elseif ( 'authenticated_receipt' === $case ) $bad['provenance']['authenticated_receipts'] = array( $granted['ledger']['last_receipt'] );
		elseif ( 'archive_tamper' === $case ) $bad['provenance']['legacy']['lifecycle']['consent'] = false;
		$reset( $bad ); if ( 'corrupt' === $case ) alynt_lifecycle_check( false !== $wpdb->update( $wpdb->options, array( 'option_value' => '{' ), array( 'option_name' => $name ) ), 'Corruption fixture failed.' );
		if ( 'autoload' === $case ) alynt_lifecycle_check( false !== $wpdb->update( $wpdb->options, array( 'autoload' => 'yes' ), array( 'option_name' => $name ) ), 'Autoload fixture failed.' );
		$before = $backend->read( $name ); alynt_lifecycle_check( is_wp_error( $model->snapshot() ) && alynt_lifecycle_error( $model->prepare_closed( $binding, 3, $guard ), 'schedule_safety_provenance_unavailable' ) && $before === $backend->read( $name ), 'Invalid provenance was repaired or promoted.' );
	}
	$receipt['invalid_archive_and_authenticated_promotion_rejected'] = true;
}
