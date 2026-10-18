<?php
/** Genuine core/native boundaries; only owned fixtures may be modified. */
function alynt_native_cases() {
    global $wpdb;
    $site = '11111111-1111-4111-8111-111111111111'; $six = alynt_native_six();
    $settings = serialize( array( 'site_uuid' => $site, 'auto_scan_enabled' => true, 'unknown' => array( "ø\0b:1;", 'fixture-private' ) ) );
    $safety = wp_json_encode( $six ); $values = array( 'alynt_drime_backups_schedule_safety_state' => $safety, 'alynt_drime_backups_settings' => $settings ); alynt_native_seed_rows( $values );
    alynt_core_login(); list( $form ) = alynt_core_form( 'receipt_read' );
    $auth = $wpdb->dbh; $facts = $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row();
    $o = alynt_native_observe( $form, 4, true ); $context = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::context( $six, $settings, $site, 1, 'no' );
    alynt_core_assert( is_array( $context ) && $o->matches_context( $context['context_digest'] ), 'Native exact primitive context did not match.' );
    alynt_core_assert( $facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(), 'Authentication session was configured or changed.' );
    $owner = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Native_Observer::create();
    $public = $owner->inspect( 'POST', $form ); alynt_core_assert( array( 'outcome' => 'unavailable', 'source' => 'closed_native_observation_v1', 'storage_supported' => false, 'dispatch_supported' => false, 'execution_supported' => false ) === $public && 4 === alynt_core_property( $owner, 'protected_reads' ), 'Public refusal masked an untested observation.' );
    try { serialize( $owner ); alynt_core_assert( false, 'Native owner serialized.' ); } catch ( LogicException $e ) { alynt_core_assert( true, 'Native transfer refused.' ); }
    alynt_core_done( 'positive_schema6_exact_bytes_four_reads_separate_closed_readonly_session_public_unavailable' );

    $e = array( 'contract' => 'closed_writer_request_v1', 'purpose' => 'upgrade_closed', 'command_id' => '88888888-8888-4888-8888-000000000011', 'site_uuid' => $site, 'enrollment_binding' => $six['enrollment_binding'], 'expected_generation' => $six['lifecycle']['permission_generation'], 'issued_at' => time(), 'expires_at' => time() + 300, 'confirm' => 'close', 'origin_digest' => Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::hash( $six ) );
    $planned = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::plan( $six, $e, $settings, time(), 1, 'no', null, true ); alynt_core_assert( is_array( $planned ) && 8 === $planned['state']['schema_version'], 'Synthetic real-shaped schema eight failed.' );
    $eight = wp_json_encode( $planned['state'] ); alynt_native_seed_rows( array( 'alynt_drime_backups_schedule_safety_state' => $eight ) ); alynt_native_observe( $form, 4, true ); alynt_native_seed_rows( $values );
    alynt_core_done( 'synthetic_schema8_native_read_only_not_authenticated_provenance' );

    alynt_native_seed_rows( $values, 'off' ); alynt_native_observe( $form, 4, true );
    alynt_native_seed_rows( $values, 'yes' ); alynt_native_observe( $form, 4 ); alynt_native_seed_rows( $values );
    alynt_core_done( 'genuine_native_nonautoload_metadata_accepts_off_refuses_yes' );

    foreach ( array( array(), array_merge( $form, array( 'extra' => 'x' ) ), array_merge( $form, array( 'nonce' => 'bad' ) ) ) as $bad ) alynt_native_observe( $bad, 0 );
    list( $stale ) = alynt_core_form( 'receipt_read', -301 ); alynt_native_observe( $stale, 0 );
    foreach ( array( 'upgrade_closed', 'begin_disable', 'abandon_closed' ) as $purpose ) { list( $other ) = alynt_core_form( $purpose ); alynt_native_observe( $other, 0 ); $bad = $form; $bad['nonce'] = $other['nonce']; alynt_native_observe( $bad, 0 ); }
    alynt_core_login( 90000002 ); list( $subscriber ) = alynt_core_form( 'receipt_read' ); alynt_native_observe( $subscriber, 0 );
    $token = alynt_core_login(); list( $revoked ) = alynt_core_form( 'receipt_read' ); WP_Session_Tokens::get_instance( 90000001 )->destroy( $token ); alynt_native_observe( $revoked, 0 );
    alynt_core_login(); list( $form ) = alynt_core_form( 'receipt_read' );
    alynt_core_done( 'malformed_stale_nonce_domain_other_purpose_subscriber_revoked_before_protected_reads' );

    $nonce_calls = 0; $token = alynt_core_login(); list( $form ) = alynt_core_form( 'receipt_read' );
    $revoke = function ( $life ) use ( &$nonce_calls, $token ) { if ( 2 === ++$nonce_calls ) alynt_core_mutate( function () use ( $token ) { WP_Session_Tokens::get_instance( 90000001 )->destroy( $token ); } ); return $life; };
    add_filter( 'nonce_life', $revoke );
    try { alynt_native_observe( $form, 4 ); alynt_core_assert( 2 === $nonce_calls, 'Post-observation revocation control did not fire.' ); }
    finally { remove_filter( 'nonce_life', $revoke ); }
    alynt_core_login(); list( $form ) = alynt_core_form( 'receipt_read' );
    alynt_core_done( 'genuine_revocation_during_post_read_nonce_denies_after_four_reads' );

    $auth->query( 'START TRANSACTION READ ONLY' );
    try { alynt_native_observe( $form, 0 ); alynt_core_assert( '1' === $auth->query( 'SELECT CAST(@@in_transaction AS CHAR)' )->fetch_row()[0], 'Ambient auth transaction was ended.' ); }
    finally { $auth->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
    $auth->autocommit( false );
    try { alynt_native_observe( $form, 0 ); alynt_core_assert( '0' === $auth->query( 'SELECT CAST(@@autocommit+0 AS CHAR)' )->fetch_row()[0], 'Autocommit was repaired.' ); }
    finally { $auth->autocommit( true ); }
    $inject = function ( $life ) use ( $auth ) { if ( '0' === $auth->query( 'SELECT CAST(@@in_transaction AS CHAR)' )->fetch_row()[0] ) $auth->query( 'START TRANSACTION READ ONLY' ); return $life; };
    add_filter( 'nonce_life', $inject );
    try { alynt_native_observe( $form, 0 ); alynt_core_assert( '1' === $auth->query( 'SELECT CAST(@@in_transaction AS CHAR)' )->fetch_row()[0], 'In-auth ambient transaction was ended.' ); }
    finally { remove_filter( 'nonce_life', $inject ); $auth->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
    alynt_core_done( 'ambient_and_during_nonce_transactions_autocommit_refused_without_repair' );

    $changed = false; $swap = function ( $life ) use ( &$changed, $wpdb ) { if ( ! $changed ) { $changed = true; $wpdb->db_connect( false ); } return $life; };
    add_filter( 'nonce_life', $swap );
    try { alynt_native_observe( $form, 0 ); alynt_core_assert( $changed && $wpdb->dbh !== $auth, 'Owned auth swap control did not fire.' ); }
    finally { remove_filter( 'nonce_life', $swap ); }
    $auth = $wpdb->dbh;
    $auth->close(); alynt_native_observe( $form, 0 ); $wpdb->db_connect( false ); $auth = $wpdb->dbh;
    $final_facts = $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row();
    alynt_core_done( 'same_wpdb_native_handle_swap_and_closed_handle_refuse_no_auto_reconnect' );

    foreach ( array( 'alynt_drime_backups_schedule_safety_state' => array( str_repeat( 'x', 131073 ), '{"schema_version":6,"schema_version":8}', ' ' . $safety ), 'alynt_drime_backups_settings' => array( str_repeat( 'x', 1048577 ), 'O:8:"stdClass":0:{}', serialize( array( 'site_uuid' => '22222222-2222-4222-8222-222222222222', 'auto_scan_enabled' => true ) ) ) ) as $name => $raws ) foreach ( $raws as $raw ) {
        alynt_native_seed_rows( array( $name => $raw ) );
        if ( ' ' . $safety === $raw ) { $other = alynt_native_observe( $form, 4, true ); alynt_core_assert( $other->matches_context( $context['context_digest'] ) && ! $other->matches( $o ), 'Raw/session binding was not private and distinct.' ); }
        else alynt_native_observe( $form, strlen( $raw ) > ( 'alynt_drime_backups_settings' === $name ? 1048576 : 131072 ) ? ( 'alynt_drime_backups_settings' === $name ? 2 : 1 ) : 4 );
        alynt_native_seed_rows( $values );
    }
    alynt_native_observe( $form, 4, true );
    alynt_core_assert( $final_facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(), 'Final auth state check failed.' );
    alynt_core_done( 'sql_prefetch_oversize_refusal_duplicate_json_primitive_uuid_negative_and_recovered_positive' );
}
