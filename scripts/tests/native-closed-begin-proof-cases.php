<?php
/** Genuine-core integrated positive/denials plus separately labeled SQL primitives. */
function alynt_native_begin_cases() {
    global $wpdb;
    $site = '11111111-1111-4111-8111-111111111111'; $six = alynt_native_six(); $now = time();
    $settings = serialize( array( 'site_uuid' => $site, 'auto_scan_enabled' => true, 'unknown' => array( "ø\0b:1;", 'fixture-private' ) ) );
    $upgrade = array( 'contract' => 'closed_writer_request_v1', 'purpose' => 'upgrade_closed', 'command_id' => '88888888-8888-4888-8888-000000000011', 'site_uuid' => $site, 'enrollment_binding' => $six['enrollment_binding'], 'expected_generation' => $six['lifecycle']['permission_generation'], 'issued_at' => $now, 'expires_at' => $now + 300, 'confirm' => 'close', 'origin_digest' => Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection::hash( $six ) );
    $p = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::plan( $six, $upgrade, $settings, $now, 1, 'no', null, true );
    alynt_core_assert( is_array( $p ) && 8 === $p['state']['schema_version'], 'Synthetic schema eight fixture failed.' );
    $eight = $p['state']; $safety = json_encode( $eight );
    $values = array( 'alynt_drime_backups_schedule_safety_state' => $safety, 'alynt_drime_backups_settings' => $settings );
    alynt_native_seed_rows( $values ); alynt_core_login(); $form = alynt_native_begin_form( $eight, $settings );
    $db = alynt_native_begin_db(); $table = $wpdb->options;
    try {
        alynt_native_begin_run( array_merge( $form, array( 'nonce' => 'bad' ) ), false, 0 );
        $original = alynt_native_begin_rows( $db ); $auth = $wpdb->dbh;
        $facts = $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row();
        $events = array();
        $trap = function () use ( &$events, $db, $table ) {
            // If any WP callback runs under a native row lock, this independent
            // locking read times out; never invoke callbacks from the writer itself.
            $db->query( 'SET SESSION innodb_lock_wait_timeout=1' ); $db->query( 'START TRANSACTION' );
            try {
                $result = $db->query( "SELECT option_id FROM {$table} WHERE option_name='alynt_drime_backups_schedule_safety_state' FOR UPDATE" );
                alynt_core_assert( $result instanceof mysqli_result && 1 === $result->num_rows, 'WordPress authentication callback encountered a native lock.' );
                $result->free(); $events[] = 'outside-lock';
            }
            finally { $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
        };
        add_action( 'auth_cookie_valid', $trap );
        try { $owner = alynt_native_begin_run( $form, true, 8 ); }
        finally { remove_action( 'auth_cookie_valid', $trap ); }
        $rows = alynt_native_begin_rows( $db ); $stored = Alynt_Drime_Backups_Uploader_Closed_Writer_Json_Contract::decode( $rows['alynt_drime_backups_schedule_safety_state']['value'] );
        alynt_core_assert( 'committed' === alynt_core_property( $owner, 'phase' ) && 1 === alynt_core_property( $owner, 'writes' ) && count( $events ) >= 6, 'Durable begin/guard lock control missing.' );
        alynt_core_assert( $original['alynt_drime_backups_settings'] === $rows['alynt_drime_backups_settings'], 'Settings bytes or metadata changed.' );
        alynt_core_assert( is_array( $stored ) && Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::supported( $stored, $site ) && null !== $stored['writer']['pending'] && null === $stored['writer']['authenticated_grant'] && $stored['writer']['closure']['command']['command_id'] === $form['command_id'] && $stored['lifecycle']['permission_generation'] === $eight['lifecycle']['permission_generation'] + 1, 'Persisted closed begin chain invalid.' );
        alynt_core_assert( $facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(), 'Borrowed authentication session changed.' );
        alynt_core_assert( false === $owner->disposition()['storage_supported'], 'Native private result advertised public support.' );
        alynt_core_done( 'genuine_guard_native_durable_closed_begin_exact_target_preserved_no_hooks_under_locks' );

        alynt_native_begin_run( $form, false, 2 ); // Stale original cannot repeat the committed command.
        alynt_core_assert( $rows === alynt_native_begin_rows( $db ), 'Repeated command changed durable rows.' );
        alynt_native_seed_rows( $values );
        foreach ( array( array(), array_merge( $form, array( 'nonce' => 'bad' ) ), array_merge( $form, array( 'extra' => 'x' ) ) ) as $bad ) alynt_native_begin_run( $bad, false, 0 );
        alynt_native_begin_run( alynt_native_begin_form( $eight, $settings, -301 ), false, 0 );
        foreach ( array( 'receipt_read', 'upgrade_closed', 'abandon_closed' ) as $purpose ) { list( $other ) = alynt_core_form( $purpose ); alynt_native_begin_run( $other, false, 0 ); $bad = $form; $bad['nonce'] = $other['nonce']; alynt_native_begin_run( $bad, false, 0 ); }
        alynt_core_login( 90000002 ); alynt_native_begin_run( alynt_native_begin_form( $eight, $settings ), false, 0 );
        $token = alynt_core_login(); $revoked = alynt_native_begin_form( $eight, $settings ); WP_Session_Tokens::get_instance( 90000001 )->destroy( $token ); alynt_native_begin_run( $revoked, false, 0 );
        alynt_core_login(); $form = alynt_native_begin_form( $eight, $settings );
        $auth->query( 'START TRANSACTION READ ONLY' );
        try { alynt_native_begin_run( $form, false, 0 ); alynt_core_assert( '1' === $auth->query( 'SELECT CAST(@@in_transaction+0 AS CHAR)' )->fetch_row()[0], 'Ambient auth transaction ended.' ); }
        finally { $auth->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
        alynt_core_assert( $original === alynt_native_begin_rows( $db ), 'Denial wrote protected rows.' );
        alynt_core_done( 'duplicate_stale_malformed_wrong_nonce_domain_subscriber_revoked_ambient_denials' );

        // A corrupt legacy origin takes a genuine WP_Error path in the existing
        // strict validator. Even that transitive WordPress hook must be outside SQL locks.
        $corrupt = $eight; $corrupt['legacy_writer']['checkpoint'] = null;
        alynt_native_seed_rows( array( 'alynt_drime_backups_schedule_safety_state' => json_encode( $corrupt ) ) );
        $validation_hooks = 0;
        $validation_trap = function () use ( &$validation_hooks, $db, $table ) {
            ++$validation_hooks; $db->query( 'START TRANSACTION' );
            try {
                $result = $db->query( "SELECT option_id FROM {$table} WHERE option_name='alynt_drime_backups_schedule_safety_state' FOR UPDATE NOWAIT" );
                alynt_core_assert( $result instanceof mysqli_result && 1 === $result->num_rows, 'Transitive validator WordPress hook ran under native locks.' ); $result->free();
            } finally { $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
        };
        add_action( 'is_wp_error_instance', $validation_trap );
        try { $owner = alynt_native_begin_run( $form, false, 2 ); }
        finally { remove_action( 'is_wp_error_instance', $validation_trap ); alynt_native_seed_rows( array( 'alynt_drime_backups_schedule_safety_state' => $safety ) ); }
        alynt_core_assert( $validation_hooks > 0 && 'idle' === alynt_core_property( $owner, 'phase' ) && 0 === alynt_core_property( $owner, 'writes' ), 'Malformed legacy validator did not refuse before BEGIN.' );
        alynt_core_done( 'genuine_corrupt_legacy_wordpress_error_hook_before_begin_no_native_locks' );

        // Real row lock timeout on a separate owned connection: no automatic retry.
        $db->query( 'START TRANSACTION' ); $db->query( "SELECT option_id FROM {$table} WHERE option_name='alynt_drime_backups_schedule_safety_state' FOR UPDATE" );
        try { $owner = alynt_native_begin_run( $form, false, 3 ); }
        finally { $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
        alynt_core_assert( 'rolled_back' === alynt_core_property( $owner, 'phase' ) && 0 === alynt_core_property( $owner, 'writes' ) && $original === alynt_native_begin_rows( $db ), 'Native timeout did not terminate owned transaction.' );
        alynt_core_done( 'genuine_lock_timeout_definite_owned_rollback_no_retry' );

        require __DIR__ . '/native-closed-begin-proof-failures.php';
        alynt_native_begin_failures( $db, $eight, $settings, $form, $original );
        alynt_core_assert( $facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(), 'Final authentication session changed.' );
    } finally { $db->close(); }
}
