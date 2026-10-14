<?php
/** Fresh real authorization observes only harmless owned receipts; no grant/replay or HTTP route. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_auth_receipt_cases( $root, $owned, $token, &$children, $p, $adapter, &$receipt, $backend, $before, $legacy_id ) {
	global $wpdb; $p->mode = 'receipt'; $i = $p->issue( $adapter ); $body = alynt_auth_body( $i );
	$r = $adapter->consume( 'POST', $body ); alynt_lifecycle_check( is_array( $r ) && 'committed' === $r['outcome'] && ! $r['dispatch_supported'] && ! $r['execution_supported'], 'Harmless guarded observation receipt failed.' );
	$r = $adapter->consume( 'POST', $body ); alynt_lifecycle_check( is_array( $r ) && 'observed_current' === $r['outcome'], 'Harmless observation duplicate did not reconcile.' ); alynt_auth_unchanged( $backend, $before ); $receipt['guarded_harmless_receipt_not_consent_and_no_replay'] = true;
	$id = $i['envelope']['command_id']; $p->now += 300; $calls = $p->sink_calls; alynt_lifecycle_check( is_wp_error( $adapter->consume( 'POST', $body ) ) && $calls === $p->sink_calls, 'Expired receipt command reached sink.' );
	alynt_auth_login( 90000001 ); $r = $p->reconcile( $adapter, $id ); alynt_lifecycle_check( is_array( $r ) && 'observed_current' === $r['outcome'] && 'fixture_request_v1' === $r['source'] && ! $r['execution_supported'], 'Fresh authorized receipt read renewed or lost its observation.' ); $p->now -= 300; $receipt['reauthenticated_receipt_read_does_not_renew_expired_command'] = true;
	$reads = $p->state_reads; $calls = $p->observe_calls; $read_body = array( 'command_id' => $id, 'nonce' => wp_create_nonce( 'alynt_drime_backups_consent_fixture_receipt_v1:' . $p::SITE . ':' . $id ) );
	$_COOKIE = array(); wp_set_current_user( 0 ); alynt_lifecycle_check( is_wp_error( $adapter->reconcile( 'POST', $read_body ) ) && $reads === $p->state_reads && $calls === $p->observe_calls, 'Guest read a private receipt.' );
	alynt_auth_login( 90000002 ); alynt_lifecycle_check( is_wp_error( $p->reconcile( $adapter, $id ) ) && $calls === $p->observe_calls, 'Subscriber read a private receipt.' ); $receipt['private_receipt_requires_real_current_capability_session'] = true;
	alynt_auth_login( 90000001 ); $r = $p->reconcile( $adapter, $legacy_id ); alynt_lifecycle_check( alynt_lifecycle_error( $r, 'consent_request_unresolved' ), 'Authenticated boundary relabeled archival fixture identity.' ); $receipt['real_auth_cannot_promote_legacy_receipt'] = true;
	$command = array( PHP_BINARY, '-c', get_cfg_var( 'cfg_file_path' ), __DIR__ . '/consent-auth-local-proof.php', $root, '--ack-child', substr( $owned['options'], 0, -8 ), $token );
	$process = proc_open( $command, array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes ); alynt_lifecycle_check( is_resource( $process ), 'Owned authenticated child failed to launch.' ); fclose( $pipes[0] ); $children[] = array( 'process' => $process, 'pipes' => $pipes ); $index = count( $children ) - 1;
	alynt_lifecycle_wait( $owned['options'], 'probe_auth_ack' ); alynt_lifecycle_join( $children[$index], true );
	$ack_id = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name='probe_auth_ack'" ); alynt_auth_login( 90000001 ); $fresh = new Alynt_Auth_Proof_Ports( $owned, $p->now );
	$r = $fresh->reconcile( $fresh->adapter(), $ack_id ); alynt_lifecycle_check( is_array( $r ) && 'observed_current' === $r['outcome'] && ! $r['dispatch_supported'] && ! $r['execution_supported'] && 0 === $fresh->sink_calls, 'Interrupted observation acknowledgement replayed an effect.' ); alynt_auth_unchanged( $backend, $before ); $receipt['interrupted_harmless_ack_reconciles_with_fresh_core_auth'] = true;
	$closed = $p->model->snapshot(); alynt_lifecycle_check( 1 === $wpdb->delete( $wpdb->options, array( 'option_name' => $backend::STATE_OPTION ) ), 'Owned missing-state fixture failed.' );
	foreach ( array( $backend::STATE_OPTION, 'alloptions', 'notoptions' ) as $key ) wp_cache_delete( $key, 'options' );
	alynt_lifecycle_check( is_wp_error( $p->issue( $adapter ) ) && null === $backend->read( $backend::STATE_OPTION ), 'Real auth initialized missing consent storage.' );
	alynt_lifecycle_check( 1 === $wpdb->insert( $wpdb->options, array( 'option_name' => $backend::STATE_OPTION, 'option_value' => wp_json_encode( $closed ), 'autoload' => $before['autoload'] ) ), 'Owned checkpoint restoration failed.' );
	alynt_auth_unchanged( $backend, $before ); $receipt['real_auth_does_not_initialize_missing_permission'] = true; $p->mode = 'closed';
}
function alynt_auth_ack_child( array $owned ) {
	$p = new Alynt_Auth_Proof_Ports( $owned, time() ); $p->mode = 'ack'; alynt_auth_login( 90000001 ); alynt_lifecycle_check( $p->authorize( $p::SITE ), 'Child core authentication failed.' );
	$adapter = $p->adapter(); $body = alynt_auth_body( $p->issue( $adapter ) ); $adapter->consume( 'POST', $body ); throw new RuntimeException( 'Child should wait at its post-commit acknowledgement barrier.' );
}
