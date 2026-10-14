<?php
/** Core-auth request admission proof with grants disabled and no actual site/user mutation. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_auth_cases( $root, $owned, $token, &$children, &$receipt ) {
	global $wpdb; $now = time(); $backend = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS( $wpdb ); $binding = str_repeat( 'e', 64 );
	$legacy = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Lifecycle_Store( $backend, function () use ( $now ) { return $now; } ); $seed = $legacy->prepare_lifecycle( $binding, 'every_15_minutes', $now + 900 );
	alynt_lifecycle_check( is_array( $seed ), 'Owned closed-auth seed failed.' ); $ledger = alynt_ledger_model( $backend, $now ); alynt_lifecycle_check( is_array( $ledger->prepare_ledger( $binding, 0 ) ), 'Owned archive preparation failed.' );
	// Archive only a synthetic closure receipt, never even a synthetic consent grant in this proof.
	$intent = alynt_ledger_intent( 'revoke', 1, $now ); alynt_lifecycle_check( is_array( $ledger->command( $intent ) ), 'Owned archival closure receipt failed.' );
	$model = alynt_provenance_model( $backend, $now ); $closed = $model->prepare_closed( $binding, 2, function () { return true; } );
	alynt_lifecycle_check( is_array( $closed ) && ! $closed['lifecycle']['consent'] && null === $closed['provenance']['authenticated_grant'], 'Owned checkpoint is admitting.' ); $before = $backend->read( $backend::STATE_OPTION );
	$p = new Alynt_Auth_Proof_Ports( $owned, $now ); $adapter = $p->adapter();
	$trace = function ( $sql ) use ( $p ) { if ( 1 === preg_match( '/^\s*SELECT\b/i', $sql ) && false !== strpos( $sql, Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION ) ) $p->state_reads++; return $sql; }; add_filter( 'query', $trace );
	try {
		$queries = $wpdb->num_queries;
		foreach ( array( 'wp_options', 'wp_users', 'wp_usermeta' ) as $actual_table ) {
			$blocked = false; try { apply_filters( 'query', 'UPDATE ' . $actual_table . ' SET probe_forbidden=1' ); } catch ( RuntimeException $error ) { $blocked = 'SQL operation outside owned auth fixtures denied.' === $error->getMessage(); }
			alynt_lifecycle_check( $blocked && $queries === $wpdb->num_queries, 'Actual-table write fence did not deny before query submission.' );
		}
		$receipt['actual_table_write_fence_denies_without_submission'] = true;
		alynt_lifecycle_check( ! $p->authorize( $p::SITE ) && is_wp_error( $p->issue( $adapter ) ) && 0 === $p->state_reads && 0 === $p->sink_calls, 'Guest reached closed storage.' ); $receipt['guest_denied_before_state'] = true;
		alynt_auth_login( 90000002 ); alynt_lifecycle_check( false === $p->authorize( $p::SITE ) && is_user_logged_in() && ! current_user_can( 'manage_options' ), 'Subscriber core capability fixture invalid.' );
		alynt_lifecycle_check( is_wp_error( $p->issue( $adapter ) ) && 0 === $p->state_reads && 0 === $p->sink_calls, 'No-capability request reached state.' ); $receipt['real_capability_denial_before_state'] = true;
		alynt_auth_login( 90000001 ); alynt_lifecycle_check( $p->authorize( $p::SITE ) && current_user_can( 'manage_options' ), 'Real core admin authorization failed.' );
		$queries = $p->state_reads; alynt_lifecycle_check( is_wp_error( $adapter->issue( 'GET', array() ) ) && is_wp_error( $adapter->reconcile( 'GET', array() ) ) && $queries === $p->state_reads, 'GET read private state.' ); $receipt['get_denied_before_private_reads'] = true;
		$i = $p->issue( $adapter ); $body = alynt_auth_body( $i ); $reads = $p->state_reads; $calls = $p->sink_calls;
		alynt_lifecycle_check( 'fixture_request_v1' === $i['source'] && ! $i['dispatch_supported'] && ! $i['execution_supported'], 'Core auth promoted request provenance.' );
		alynt_lifecycle_check( alynt_lifecycle_error( $adapter->consume( 'POST', $body ), 'consent_request_unresolved' ) && $calls + 1 === $p->sink_calls && $reads === $p->state_reads, 'Closed command executed or read state.' );
		alynt_lifecycle_check( is_wp_error( $p->issue( $adapter, 'grant' ) ) && is_wp_error( $p->issue( $adapter, 'rebind' ) ), 'Real authorization enabled consent grant/rebind.' ); alynt_auth_unchanged( $backend, $before ); $receipt['real_auth_issues_only_non_admitting_fixture_intent'] = true;
		foreach ( array( 'nonce', 'purpose', 'site', 'generation', 'deadline', 'binding', 'extra' ) as $case ) {
			$bad = $body;
			if ( 'nonce' === $case ) $bad['nonce'] = 'invalid'; elseif ( 'purpose' === $case ) { $bad['purpose'] = 'grant'; $bad['confirm'] = 'prepare'; } elseif ( 'site' === $case ) $bad['site_uuid'] = '88888888-8888-4888-8888-888888888888';
			elseif ( 'generation' === $case ) $bad['expected_generation'] = '4'; elseif ( 'deadline' === $case ) $bad['expires_at'] = (string) ( $now + 299 ); elseif ( 'binding' === $case ) $bad['enrollment_binding'] = str_repeat( 'f', 64 ); else $bad['trusted'] = '1';
			$reads = $p->state_reads; $calls = $p->sink_calls; alynt_lifecycle_check( is_wp_error( $adapter->consume( 'POST', $bad ) ) && $reads === $p->state_reads && $calls === $p->sink_calls, 'Forged core-nonce envelope reached private state or sink.' );
		}
		$receipt['real_nonce_canonical_envelope_binding'] = true;
		$nonce = wp_create_nonce( 'alynt_drime_backups_consent_fixture_issue_v1:revoke:' . $p::SITE ); $reads = $p->state_reads;
		alynt_lifecycle_check( is_wp_error( $adapter->issue( 'POST', array( 'purpose' => 'grant', 'confirm' => 'prepare', 'nonce' => $nonce ) ) ) && $reads === $p->state_reads, 'Issuance nonce crossed purposes.' ); $receipt['real_issuance_nonce_purpose_binding'] = true;
		$nonce = wp_create_nonce( 'alynt_drime_backups_consent_fixture_issue_v1:revoke:88888888-8888-4888-8888-888888888888' );
		alynt_lifecycle_check( is_wp_error( $adapter->issue( 'POST', array( 'purpose' => 'revoke', 'confirm' => 'close', 'nonce' => $nonce ) ) ) && $reads === $p->state_reads, 'Issuance nonce crossed site scope.' ); $receipt['real_issuance_nonce_site_binding'] = true;
		alynt_auth_denial_cases( $p, $adapter, $body, $receipt, $backend, $before );
		alynt_auth_guard_cases( $p, $adapter, $receipt, $backend, $before, $closed, $seed );
		alynt_auth_receipt_cases( $root, $owned, $token, $children, $p, $adapter, $receipt, $backend, $before, $intent['command_id'] );
		$closed_state = $p->model->snapshot(); alynt_lifecycle_check( is_array( $closed_state ) && ! $closed_state['lifecycle']['consent'] && ! $closed_state['authority']['enabled'] && null === $closed_state['provenance']['authenticated_grant'] && array() === $closed_state['provenance']['authenticated_receipts'], 'Core-auth proof enabled permission.' ); $receipt['grants_activation_rollback_remain_disabled'] = true;
	} finally { remove_filter( 'query', $trace ); }
}
require_once __DIR__ . '/consent-auth-proof-denials.php'; require_once __DIR__ . '/consent-auth-proof-guards.php'; require_once __DIR__ . '/consent-auth-proof-receipts.php';
