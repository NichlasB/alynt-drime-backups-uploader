<?php
/** Injected MOCK auth/nonce ports with real closed SQL observation; never actual WP sessions. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_provenance_requests( &$receipt, $model, $backend, $reset, $seed, $closed, $now ) {
	global $wpdb; $name = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION;
	$p = new Alynt_Test_Consent_Request_Ports(); $p->site = '99999999-9999-4999-8999-999999999999'; $p->now = $now; $ports = $p->ports();
	$ports['context'] = function () use ( $model, $p ) { $s = $model->snapshot(); return is_wp_error( $s ) ? $s : array( 'site_uuid' => $p->site, 'binding' => $s['enrollment_binding'], 'generation' => $s['lifecycle']['permission_generation'], 'eligible' => false, 'replacement_binding' => null ); };
	$ports['consume'] = function ( $command, $guard ) use ( $model, $p ) { $p->hit( 'closed_consume' ); return $model->command( $command, $guard ); };
	$ports['observe'] = function ( $site, $id ) use ( $model, $p ) { if ( $site !== $p->site ) return new WP_Error( 'fixture_scope', 'Unavailable.' ); $r = $model->receipt( 'fixture_request_v1', $id ); return is_wp_error( $r ) ? $r : $r['outcome']; };
	$adapter = new Alynt_Drime_Backups_Uploader_Consent_Request_Adapter( $ports, $p->site, true );
	$issue = function ( $purpose ) use ( $p, $adapter ) { return $adapter->issue( 'POST', array( 'purpose' => $purpose, 'confirm' => 'grant' === $purpose ? 'prepare' : 'close', 'nonce' => $p->create_nonce( 'alynt_drime_backups_consent_fixture_issue_v1:' . $purpose . ':' . $p->site ) ) ); };
	$before = $backend->read( $name );
	foreach ( array( 'logged_out', 'incapable', 'wrong_nonce' ) as $case ) {
		$p->logged_in = 'logged_out' !== $case; $p->capable = 'incapable' !== $case; $p->nonce_result = 'wrong_nonce' === $case ? false : null; $queries = $wpdb->num_queries;
		alynt_lifecycle_check( is_wp_error( $issue( 'revoke' ) ) && $queries === $wpdb->num_queries, 'Denied mock request read SQL.' );
	}
	$p->logged_in = true; $p->capable = true; $p->nonce_result = null; $receipt['denied_mock_requests_zero_sql'] = true;
	$queries = $wpdb->num_queries;
	alynt_lifecycle_check( is_wp_error( $adapter->issue( 'GET', array() ) ) && is_wp_error( $adapter->reconcile( 'GET', array() ) ) && $queries === $wpdb->num_queries, 'GET request reached SQL.' );
	alynt_lifecycle_check( is_wp_error( $issue( 'grant' ) ), 'Closed SQL context issued a grant.' ); $i = $issue( 'revoke' );
	alynt_lifecycle_check( is_array( $i ) && ! $i['dispatch_supported'] && ! $i['execution_supported'] && 'fixture_request_v1' === $i['source'], 'Fixture closure intent failed or became executing.' );
	$body = array_map( 'strval', $i['envelope'] ); $body['nonce'] = $i['nonce']; $queries = $wpdb->num_queries;
	alynt_lifecycle_check( alynt_lifecycle_error( $adapter->consume( 'POST', $body ), 'consent_request_unresolved' ) && $queries === $wpdb->num_queries && $before === $backend->read( $name ), 'Request executed a closed command or read SQL.' ); $receipt['grant_denied_and_closure_command_disabled'] = true;
	foreach ( array( 'expired', 'changed_session' ) as $case ) {
		$p->now = 'expired' === $case ? $now + 301 : $now; $p->session = 'changed_session' === $case ? 'fixture-session-b' : 'fixture-session-a'; $queries = $wpdb->num_queries; $calls = $p->calls['closed_consume'];
		alynt_lifecycle_check( is_wp_error( $adapter->consume( 'POST', $body ) ) && $queries === $wpdb->num_queries && $calls === $p->calls['closed_consume'], 'Expired or wrong-session request reached SQL or its sink.' );
	}
	$p->now = $now; $p->session = 'fixture-session-a'; $receipt['expired_and_session_changed_mock_requests_zero_sql'] = true;
	$legacy_id = $closed['provenance']['legacy']['ledger']['last_receipt']['command']['command_id']; $r = $model->receipt( 'fixture_intent_v1', $legacy_id );
	alynt_lifecycle_check( is_array( $r ) && 'observed_superseded' === $r['outcome'], 'Archived identity lost its original namespace.' );
	foreach ( array( 'legacy' => $legacy_id, 'unknown' => $p->site ) as $case => $id ) {
		$b = array( 'command_id' => $id, 'nonce' => $p->create_nonce( 'alynt_drime_backups_consent_fixture_receipt_v1:' . $p->site . ':' . $id ) ); $queries = $wpdb->num_queries; $r = $adapter->reconcile( 'POST', $b );
		alynt_lifecycle_check( 1 === $wpdb->num_queries - $queries && ( 'legacy' === $case ? alynt_lifecycle_error( $r, 'consent_request_unresolved' ) : array( 'outcome' => 'not_found', 'source' => 'fixture_request_v1', 'dispatch_supported' => false, 'execution_supported' => false ) === $r ) && $before === $backend->read( $name ), 'Request receipt promoted or rewrote a fixture identity.' );
	}
	$queries = $wpdb->num_queries; alynt_lifecycle_check( is_wp_error( $model->receipt( 'authenticated_request_v1', $legacy_id ) ) && $queries === $wpdb->num_queries, 'Authenticated namespace read or admitted.' ); $receipt['namespace_observation_cannot_promote_legacy_identity'] = true;
	foreach ( array( 'grant', 'revoke', 'rebind', 'activate', 'rollback' ) as $purpose ) { $queries = $wpdb->num_queries; alynt_lifecycle_check( alynt_lifecycle_error( $model->command( array( 'purpose' => $purpose ), function () { throw new RuntimeException( 'Guard must not run.' ); } ), 'schedule_safety_provenance_commands_disabled' ) && $queries === $wpdb->num_queries, 'Disabled command reached SQL or its guard.' ); } $receipt['all_commands_zero_sql_and_no_guard'] = true;
	$reset( $seed ); $before = $backend->read( $name ); alynt_lifecycle_check( is_wp_error( $issue( 'revoke' ) ) && $before === $backend->read( $name ), 'Unmigrated request prepared storage.' );
	alynt_lifecycle_check( 1 === $wpdb->delete( $wpdb->options, array( 'option_name' => $name ) ), 'Owned missing-state fixture failed.' );
	alynt_lifecycle_check( is_wp_error( $issue( 'revoke' ) ) && null === $backend->read( $name ), 'Missing request initialized storage.' ); $receipt['requests_never_initialize_or_migrate'] = true;
}
