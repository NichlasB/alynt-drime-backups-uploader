<?php
/** Genuine entry positives, complete locked dependencies and owned failure proof. */
function alynt_admission_cases() {
    global $wpdb;
    $values = alynt_registration_values(); alynt_scan_seed( $values ); alynt_core_login(); $form = alynt_registration_form(); $db = alynt_scan_db();
    try {
        $original = alynt_registration_frame( $db ); $auth = $wpdb->dbh;
        $bad = $form; $bad['nonce'] = 'bad'; alynt_admit( $bad, false, 0 );
        // A full-command nonce created under a different lifetime must actually
        // fail command verification BEFORE BEGIN, even with valid issuance.
        $command_calls = 0; $bad_command = function ( $life, $action ) use ( &$command_calls ) { return 0 === strpos( $action, 'alynt_drime_backups_scan_registration_command_v2:' ) ? ( 1 === ++$command_calls ? 900 : 1 ) : $life; };
        add_filter( 'nonce_life', $bad_command, 10, 2 );
        try { $denied = alynt_admit( $form, false, 20 ); } finally { remove_filter( 'nonce_life', $bad_command, 10 ); }
        alynt_core_assert( $command_calls >= 2 && 'idle' === alynt_core_property( $denied, 'phase' ) && $original === alynt_registration_frame( $db ), 'Bad full command reached BEGIN or changed ledger.' );
        alynt_core_done( 'genuine_issuance_before_reads_and_independent_command_nonce_before_begin' );

        $events = 0; $facts = $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row();
        $original_start = null; $capture_thread = null; $fresh_writer = false;
        $lifetime = function ( $life, $action ) use ( &$original_start, &$capture_thread, &$fresh_writer, $auth ) {
            $owner = $GLOBALS['alynt_admission_owner']; $start = alynt_core_property( $owner, 'started' );
            if ( null === $original_start ) $original_start = $start;
            alynt_core_assert( $start === $original_start, 'Admission reset its original real monotonic start.' );
            $phase = alynt_core_property( $owner, 'phase' ); $reader = alynt_core_property( $owner, 'reader' );
            if ( 'capturing' === $phase && $reader instanceof mysqli ) { alynt_core_assert( '1' === $reader->query( 'SELECT CAST(@@tx_read_only+0 AS CHAR)' )->fetch_row()[0] && $reader !== $auth, 'Capture was not a separate server-read-only session.' ); $capture_thread = $reader->thread_id; }
            if ( 'idle' === $phase ) { alynt_core_assert( $reader instanceof mysqli && $reader !== $auth && $reader->thread_id !== $capture_thread && '0' === $reader->query( 'SELECT CAST(@@tx_read_only+0 AS CHAR)' )->fetch_row()[0], 'Writer adopted the capture/auth connection.' ); $fresh_writer = true; }
            if ( 'committed' === $phase ) alynt_core_assert( null === $reader, 'Post-commit core callback preceded native close.' );
            return $life;
        };
        $trap = function () use ( $db, &$events ) { foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $def ) alynt_ledger_nowait( $db, $def[0], false ); ++$events; };
        add_action( 'auth_cookie_valid', $trap ); add_filter( 'nonce_life', $lifetime, 10, 2 );
        try { $o = alynt_admit( $form, true, 40, 1 ); } finally { remove_action( 'auth_cookie_valid', $trap ); remove_filter( 'nonce_life', $lifetime, 10 ); }
        alynt_core_assert( $fresh_writer && null !== $capture_thread && 'committed' === alynt_core_property( $o, 'phase' ) && $events >= 12, 'Acknowledged admission or genuine unlocked auth callbacks missing.' );
        foreach ( array( 'wp_create_nonce', 'wp_generate_uuid4' ) as $function ) alynt_core_assert( 0 === strpos( realpath( ( new ReflectionFunction( $function ) )->getFileName() ), realpath( ABSPATH . 'wp-includes' ) . DIRECTORY_SEPARATOR ), 'Construction API not genuine core.' );
        alynt_admission_receipt( $db, $original );
        alynt_core_assert( $facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(), 'Borrowed authentication session changed.' );
        alynt_admit( $form, false, 20 ); // Existing pending registration cannot be reminted/replayed.
        alynt_admission_receipt( $db, $original ); alynt_scan_seed( $values );
        alynt_core_done( 'genuine_forty_read_one_ledger_cas_commit_exact_receipt_no_callbacks_under_locks_no_replay' );

        $index = 0;
        foreach ( array_keys( $values ) as $role ) {
            ++$index; $changed = false;
            $stale = function ( $life, $action ) use ( $role, $values, &$changed ) { if ( ! $changed && 0 === strpos( $action, 'alynt_drime_backups_scan_registration_command_v2:' ) ) { $changed = true; alynt_scan_seed( array( $role => $values[$role] ), 'off' ); } return $life; };
            add_filter( 'nonce_life', $stale, 10, 2 );
            try { $o = alynt_admit( $form, false, 20 + $index ); } finally { remove_filter( 'nonce_life', $stale, 10 ); alynt_scan_seed( $values ); }
            alynt_core_assert( $changed && 'rolled_back' === alynt_core_property( $o, 'phase' ) && $original === alynt_registration_frame( $db ), 'Stale role was rebased/committed or left data: ' . $role );
        }
        alynt_core_done( 'all_ten_post_capture_nonce_time_changes_refuse_in_fixed_lock_order_before_update' );

        $db->query( 'START TRANSACTION' ); $name = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows()['failed'][0];
        $db->query( "SELECT option_id FROM {$wpdb->options} WHERE option_name='{$name}' FOR UPDATE" );
        try { $o = alynt_admit( $form, false, 30 ); } finally { $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
        alynt_core_assert( 'rolled_back' === alynt_core_property( $o, 'phase' ) && $original === alynt_registration_frame( $db ), 'Last-row timeout retried or changed a ledger.' );
        alynt_core_done( 'genuine_last_dependency_contention_definite_rollback_no_update_or_retry' );
        require __DIR__ . '/registration-admission-proof-denials.php'; alynt_admission_denials( $db, $values, $original );
        require __DIR__ . '/registration-admission-proof-failures.php'; $original = alynt_admission_failures( $db, $values, $original );
        alynt_scan_seed( $values ); alynt_core_login(); alynt_admit( alynt_registration_form(), true, 40, 1 ); alynt_admission_receipt( $db, $original ); alynt_scan_seed( $values );
        alynt_core_assert( $original === alynt_registration_frame( $db ), 'Final exact protected fixture reconciliation failed.' );
        alynt_core_done( 'final_genuine_registration_recovery_and_exact_owned_frame_reconciliation' );
    } finally { $db->close(); }
}
