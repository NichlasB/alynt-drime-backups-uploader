<?php
/** Genuine core/native positive and negative owner decisions, no real application writes. */
function alynt_scan_cases() {
    global $wpdb;
    $values = alynt_scan_values(); alynt_scan_seed( $values ); alynt_core_login(); $form = alynt_scan_form();
    $auth = $wpdb->dbh; $facts = $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row();
    $o = alynt_scan_observe( $form, 20, true );
    alynt_core_assert( false === alynt_core_private( $o, 'scan_row', array( $wpdb->options, 'queue' ) ), 'Twenty-read ceiling was bypassed.' );
    alynt_core_assert( $facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(), 'Borrowed auth session changed.' );
    $public = $o->inspect( 'POST', $form );
    alynt_core_assert( array( 'outcome' => 'unavailable', 'source' => 'closed_scan_native_observation_v1', 'storage_supported' => false, 'dispatch_supported' => false, 'execution_supported' => false ) === $public && 20 === alynt_core_property( $o, 'protected_reads' ), 'Public result masked untested positive work or exported authority.' );
    alynt_core_done( 'genuine_positive_twenty_reads_noop_codecs_readonly_owner_public_unavailable' );

    $calls = 0; $observed = false;
    $readonly = function ( $life ) use ( &$calls, &$observed, $auth ) {
        if ( 2 === ++$calls ) {
            $owner = $GLOBALS['alynt_scan_current_owner']; $reader = alynt_core_property( $owner, 'reader' );
            alynt_core_assert( $reader instanceof mysqli && $reader !== $auth && $reader->thread_id !== $auth->thread_id, 'Owned observer borrowed authentication connection.' );
            alynt_core_assert( '1' === $reader->query( 'SELECT CAST(@@tx_read_only+0 AS CHAR)' )->fetch_row()[0], 'Observer connection is not server-read-only.' );
            $table = $GLOBALS['alynt_core_proof']['prefix'] . 'options'; $write = false;
            try { $write = $reader->query( "UPDATE {$table} SET option_value=option_value WHERE option_name='alynt_drime_backups_upload_queue'" ); }
            catch ( Throwable $error ) { $write = 1792 === $reader->errno ? false : null; } // Accept only the exact native denial, never arbitrary exceptions.
            $denied = false === $write && 1792 === $reader->errno;
            alynt_core_assert( $denied, 'Server did not reject owned-fixture write on reader.' ); $observed = true;
        }
        return $life;
    };
    $probe = Alynt_Drime_Backups_Uploader_Scan_Native_Observer::create(); $GLOBALS['alynt_scan_current_owner'] = $probe; add_filter( 'nonce_life', $readonly );
    try { $GLOBALS['alynt_core_proof']['guard_readonly'] = true; alynt_core_assert( true === alynt_core_private( $probe, 'observe', array( 'POST', $form ) ) && $observed, 'Genuine read-only owner proof failed.' ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; remove_filter( 'nonce_life', $readonly ); unset( $GLOBALS['alynt_scan_current_owner'] ); }
    alynt_core_done( 'private_separate_native_reader_server_rejects_fixture_write_no_public_handle' );

    foreach ( array( 'off', 'auto-off' ) as $meta ) { alynt_scan_seed( $values, $meta ); alynt_scan_observe( $form, 20, true ); }
    alynt_scan_seed( $values );
    foreach ( $values as $role => $value ) { alynt_scan_seed( array( $role => $value ), 'yes' ); alynt_scan_observe( $form, 20 ); alynt_scan_seed( array( $role => $value ) ); }
    alynt_core_done( 'every_fixed_role_autoload_metadata_refuses_without_default_repair' );

    $position = 0;
    foreach ( $values as $role => $value ) { ++$position; alynt_scan_delete( $role ); alynt_scan_observe( $form, $position ); alynt_scan_seed( array( $role => $value ) ); }
    alynt_scan_observe( $form, 20, true );
    alynt_core_done( 'each_missing_role_refuses_at_exact_native_read_without_creating_rows' );

    foreach ( array( 'connection' => array( 3, 131073 ), 'ledger' => array( 4, 131073 ), 'queue' => array( 7, 1048577 ) ) as $role => $case ) {
        alynt_scan_seed( array( $role => str_repeat( 'x', $case[1] ) ) ); alynt_scan_observe( $form, $case[0] ); alynt_scan_seed( array( $role => $values[$role] ) );
    }
    $payload = ''; for ( $i = 0; $i < 3; ++$i ) $payload = str_repeat( 'x', strlen( $payload ) + 131072 - strlen( serialize( array( 'bounded' => $payload ) ) ) );
    $raw = serialize( array( 'bounded' => $payload ) ); alynt_core_assert( 131072 === strlen( $raw ), 'Exact connection byte cap fixture differs.' );
    alynt_scan_seed( array( 'connection' => $raw ) ); alynt_scan_observe( $form, 20, true ); alynt_scan_seed( array( 'connection' => $values['connection'] ) );
    alynt_core_done( 'native_sql_prefetch_caps_and_exact_boundary_positive' );

    foreach ( array( 'ledger' => '{}', 'safety' => '{}', 'settings' => 'a:0:{}', 'connection' => 'O:8:"stdClass":0:{}', 'wpvivid_snapshot' => 'a:1:{i:0;b:1;}', 'outbox_snapshot' => 'a:1:{i:0;b:1;}', 'queue' => 'a:1:{i:0;b:1;}', 'failed' => 'a:1:{i:0;b:1;}', 'uploaded' => 'a:1:{i:0;b:1;}', 'active' => 'O:8:"stdClass":0:{}' ) as $role => $bad ) { alynt_scan_seed( array( $role => $bad ) ); alynt_scan_observe( $form, 20 ); alynt_scan_seed( array( $role => $values[$role] ) ); }
    alynt_scan_observe( $form, 20, true ); alynt_core_done( 'native_rows_do_not_bypass_closed_ledger_scope_or_fixed_codecs' );

    foreach ( array( array(), array_merge( $form, array( 'provider' => 'x' ) ), array_merge( $form, array( 'nonce' => 'bad' ) ), alynt_scan_form( -301 ) ) as $bad ) alynt_scan_observe( $bad, 0 );
    foreach ( array( 'receipt_read', 'upgrade_closed', 'begin_disable', 'abandon_closed' ) as $purpose ) { list( $old ) = alynt_core_form( $purpose ); alynt_scan_observe( $old, 0 ); $bad = $form; $bad['nonce'] = $old['nonce']; alynt_scan_observe( $bad, 0 ); }
    $typed = Alynt_Drime_Backups_Uploader_Scan_Observation_Request::parse( $form );
    foreach ( array( 'alynt_drime_backups_closed_writer_receipt_v1:', 'alynt_drime_backups_closed_writer_command_v1:' ) as $domain ) { $bad = $form; $bad['nonce'] = wp_create_nonce( $domain . hash( 'sha256', json_encode( $typed['envelope'] ) ) ); alynt_scan_observe( $bad, 0 ); }
    $original_cookie = $_COOKIE[LOGGED_IN_COOKIE]; unset( $_COOKIE[LOGGED_IN_COOKIE] ); alynt_scan_observe( $form, 0 ); $_COOKIE[LOGGED_IN_COOKIE] = $original_cookie;
    $_SERVER['REQUEST_METHOD'] = 'GET'; alynt_scan_observe( $form, 0 ); $_SERVER['REQUEST_METHOD'] = 'POST';
    $bad = $form; $bad['site_uuid'] = '22222222-2222-4222-8222-222222222222'; alynt_scan_observe( $bad, 0 );
    $p = Alynt_Drime_Backups_Uploader_Scan_Observation_Request::parse( $bad ); $bad['nonce'] = wp_create_nonce( $p['action'] ); alynt_scan_observe( $bad, 20 );
    alynt_core_login( 90000002 ); alynt_scan_observe( alynt_scan_form(), 0 );
    $token = alynt_core_login(); $revoked = alynt_scan_form(); WP_Session_Tokens::get_instance( 90000001 )->destroy( $token ); alynt_scan_observe( $revoked, 0 );
    alynt_core_login(); $form = alynt_scan_form(); alynt_core_done( 'malformed_stale_changed_intent_all_nonce_domains_subscriber_revoked_zero_reads' );

    $calls = 0; $token = alynt_core_login(); $form = alynt_scan_form();
    $revoke = function ( $life ) use ( &$calls, $token ) { if ( 2 === ++$calls ) alynt_core_mutate( function () use ( $token ) { WP_Session_Tokens::get_instance( 90000001 )->destroy( $token ); } ); return $life; };
    add_filter( 'nonce_life', $revoke );
    try { alynt_scan_observe( $form, 20 ); alynt_core_assert( 2 === $calls, 'Post-read revocation did not fire.' ); }
    finally { remove_filter( 'nonce_life', $revoke ); }
    alynt_core_login(); $form = alynt_scan_form(); alynt_core_done( 'genuine_revocation_during_second_nonce_denies_after_twenty_reads' );

    $auth->query( 'START TRANSACTION READ ONLY' );
    try { alynt_scan_observe( $form, 0 ); alynt_core_assert( '1' === $auth->query( 'SELECT CAST(@@in_transaction+0 AS CHAR)' )->fetch_row()[0], 'Borrowed ambient transaction was repaired.' ); }
    finally { $auth->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
    $changed = false; $swap = function ( $life ) use ( &$changed, $wpdb ) { if ( ! $changed ) { $changed = true; $wpdb->db_connect( false ); } return $life; };
    add_filter( 'nonce_life', $swap );
    try { alynt_scan_observe( $form, 0 ); alynt_core_assert( $changed && $wpdb->dbh !== $auth, 'Owned auth-handle swap control did not fire.' ); }
    finally { remove_filter( 'nonce_life', $swap ); }
    $auth = $wpdb->dbh; $auth->close(); alynt_scan_observe( $form, 0 ); $wpdb->db_connect( false ); $auth = $wpdb->dbh;
    alynt_core_done( 'ambient_transaction_handle_swap_and_disconnect_refuse_no_repair' );

    alynt_scan_case_name();
    try { alynt_scan_observe( $form, 7 ); } finally { alynt_scan_case_name( true ); }
    alynt_core_done( 'binary_option_name_mismatch_not_accepted_by_collation_match' );

    $db = alynt_scan_db(); $table = $GLOBALS['alynt_core_proof']['prefix'] . 'options';
    try { $db->query( "ALTER TABLE {$table} DROP INDEX option_name" ); alynt_scan_observe( $form, 0 ); }
    finally { $db->query( "ALTER TABLE {$table} ADD UNIQUE KEY option_name(option_name)" ); $db->close(); }
    alynt_scan_observe( $form, 20, true ); alynt_core_done( 'owned_missing_full_unique_index_refusal_and_recovered_positive' );
}
