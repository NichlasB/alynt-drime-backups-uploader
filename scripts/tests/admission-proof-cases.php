<?php
/** Genuine-core request denial and native durable closed-pair observations. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_admission_denied( $r ) { alynt_lifecycle_check( is_wp_error( $r ) && 'closed_writer_admission_unavailable' === $r->get_error_code() && 'Closed admission fixture is unavailable.' === $r->get_error_message(), 'Request did not return the fixed redacted denial.' ); }
function alynt_admission_cases( $f, &$receipt ) {
	global $wpdb, $actual;
	$blocked = false; try { $wpdb->query( 'UPDATE ' . $actual['options'] . ' SET option_value=option_value WHERE 1=0' ); } catch ( RuntimeException $error ) { $blocked = true; }
	alynt_lifecycle_check( $blocked, 'Actual-table write fence not installed.' ); $receipt['actual_table_write_fence_before_sql'] = true;
	alynt_auth_login( 90000001 ); alynt_admission_seed( $f->db ); $off = $f->boundary( false ); $reads = $f->reads; $queries = $f->db->queries;
	alynt_admission_denied( $off->consume( 'POST', array() ) ); alynt_admission_denied( $off->issue( 'POST', array() ) );
	alynt_lifecycle_check( $reads === $f->reads && $queries === $f->db->queries, 'Default-off admission performed native IO.' ); $receipt['default_off_zero_native_io'] = true;
	$b = $f->boundary(); $form = $f->issue( $b, 'upgrade_closed' );
	foreach ( array( 'guest', 'subscriber', 'get', 'nonce', 'purpose', 'site' ) as $kind ) {
		alynt_auth_login( 90000001 ); $body = $f->issue( $b, 'upgrade_closed' ); $method = 'POST';
		if ( 'subscriber' === $kind ) alynt_auth_login( 90000002 );
		if ( 'guest' === $kind ) { $_COOKIE = array(); wp_set_current_user( 0 ); }
		if ( 'get' === $kind ) $method = 'GET'; if ( 'nonce' === $kind ) $body['nonce'] = 'invalid';
		if ( 'purpose' === $kind ) $body['purpose'] = 'rollback'; if ( 'site' === $kind ) $body['site_uuid'] = alynt_writer_id( 999 );
		$reads = $f->reads; $writes = $f->writes; alynt_admission_denied( $b->consume( $method, $body ) );
		alynt_lifecycle_check( $reads === $f->reads && $writes === $f->writes, 'Unauthorized form observed private state.' );
	}
	$receipt['guest_subscriber_get_nonce_purpose_site_denied_before_private_read'] = true;
	alynt_auth_login( 90000001 ); $b = alynt_admission_upgrade( $f ); $form = $f->issue( $b, 'begin_disable' ); $r = $b->consume( 'POST', $form );
	alynt_lifecycle_check( is_array( $r ) && 'simulated_committed' === $r['secondary_outcome'] && false === $r['physical_target_effects_proven'] && false === $r['dispatch_supported'] && false === $r['execution_supported'], 'Fixture response domain/limits changed.' );
	$s = alynt_admission_closed( $f->db, false ); alynt_admission_target( $f->db, false );
	alynt_lifecycle_check( false === $f->retained->enter_fixture( $f->db, $s, $f->db->value( 'probe_settings' ), time() ) && false === $f->retained->confirm_fixture( $f->db, $s, $f->db->value( 'probe_settings' ), time() ), 'Retained continuation remained usable.' );
	$receipt['genuine_admin_upgrade_frozen_legacy_and_atomic_fixture_pair'] = true; $receipt['continuation_invalid_after_stack'] = true;
	$receipt['no_request_authorization_callback_under_target_locks'] = true;
	$bytes = array( $f->db->value( alynt_writer_state_name() ), $f->db->value( 'probe_settings' ) ); $writes = $f->writes; $tx = $f->transactions;
	$r = $b->consume( 'POST', $form ); alynt_lifecycle_check( is_array( $r ) && 'observed_superseded' === $r['outcome'] && $writes === $f->writes && $tx === $f->transactions, 'Duplicate command replayed target work.' );
	$noop = $f->issue( $b, 'begin_disable' ); $r = $b->consume( 'POST', $noop ); alynt_lifecycle_check( is_array( $r ) && 'observed_noop' === $r['outcome'] && $writes === $f->writes, 'Already-disabled request wrote state.' );
	$read = $f->issue( $b, 'receipt_read', $form['command_id'] ); $r = $b->consume( 'POST', $read );
	alynt_lifecycle_check( is_array( $r ) && 'simulated_committed' === $r['secondary_outcome'] && $bytes === array( $f->db->value( alynt_writer_state_name() ), $f->db->value( 'probe_settings' ) ), 'Fresh receipt read changed retained evidence.' );
	$receipt['duplicates_noop_fresh_receipt_do_not_replay'] = true;
	// A genuinely signed new command cannot reuse an existing ID with new intent.
	$conflict = $f->issue( $b, 'begin_disable' ); $conflict['command_id'] = $form['command_id']; $e = $conflict; unset( $e['nonce'] );
	foreach ( array( 'expected_generation', 'issued_at', 'expires_at' ) as $key ) $e[$key] = (int) $e[$key];
	$conflict['nonce'] = wp_create_nonce( Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract::action( $e ) );
	$r = $b->consume( 'POST', $conflict ); alynt_lifecycle_check( is_array( $r ) && 'domain_conflict' === $r['outcome'] && $writes === $f->writes && $bytes === array( $f->db->value( alynt_writer_state_name() ), $f->db->value( 'probe_settings' ) ), 'Signed identity collision mutated evidence.' );
	$receipt['genuine_nonce_does_not_admit_changed_retained_identity'] = true;
}
