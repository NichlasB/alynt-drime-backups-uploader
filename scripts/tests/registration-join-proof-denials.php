<?php
/** Genuine guards and owned SQL failures; explicit reflection faults never pretend to be provenance. */
function alynt_join_denials() {
    $values = alynt_registration_values(); alynt_scan_seed( $values ); $db = alynt_scan_db();
    try {
        $original = alynt_registration_frame( $db ); alynt_core_login(); $form = alynt_registration_form();
        foreach ( array( array(), array_merge( $form, array( 'nonce' => 'bad' ) ), array_merge( $form, array( 'provider' => 'external' ) ) ) as $bad ) {
            $o = alynt_admit( $bad, false, 0, 0, 'POST', true ); alynt_join_erased( $o, $bad );
        }
        $bad = $form; $bad['nonce'] = wp_create_nonce( Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( get_current_blog_id(), 'reserve_wpvivid_intent' ) );
        alynt_join_erased( alynt_admit( $bad, false, 0, 0, 'POST', true ), $bad );
        alynt_join_erased( alynt_admit( $form, false, 0, 0, 'GET', true ), $form );
        alynt_core_login( 90000002 ); $bad = alynt_registration_form();
        alynt_join_erased( alynt_admit( $bad, false, 0, 0, 'POST', true ), $bad );
        alynt_core_done( 'joined_malformed_intents_zero_protected_io_erased_consumed' );
        foreach ( range( 1, 10 ) as $gate ) {
            alynt_scan_seed( $values ); alynt_core_login(); $form = alynt_registration_form(); $seen = 0; $fired = false;
            $probe = function ( $life, $action, $verify ) use ( $gate, &$seen, &$fired ) { if ( $verify && $gate === ++$seen ) { $fired = true; return 1; } return $life; };
            $o = alynt_join_run( $form, false, 1 === $gate ? 0 : ( $gate > 8 ? 40 : 20 ), $gate > 8 ? 1 : 0, $probe );
            alynt_core_assert( $fired && $gate === $seen, 'Required genuine guard did not independently reject stale nonce.' );
            if ( $gate > 8 ) alynt_admission_receipt( $db, $original );
            else alynt_core_assert( $original === alynt_registration_frame( $db ), 'Guard refusal wrote protected ledger.' );
        }
        alynt_core_done( 'all_ten_genuine_nonce_guards_independent_stale_refusals_history_not_acceptance' );
        foreach ( range( 0, 9 ) as $id ) {
            alynt_scan_seed( $values ); alynt_core_login(); $form = alynt_registration_form(); $n = 0; $fired = false;
            $probe = function ( $life, $action, $verify, $o ) use ( $id, &$n, &$fired ) {
                if ( $verify && 7 === ++$n ) { $ids = alynt_core_property( $o, 'admission_ids' ); array_splice( $ids, count( $ids ) - 10 + $id, 1 ); alynt_ledger_set( $o, 'admission_ids', $ids ); $fired = true; }
                return $life;
            };
            $o = alynt_join_run( $form, false, 20, 0, $probe );
            alynt_core_assert( $fired && 'idle' === alynt_core_property( $o, 'phase' ) && $original === alynt_registration_frame( $db ), 'Instrumented original-ID loss reached BEGIN or changed ledger.' );
        }
        alynt_core_done( 'explicit_each_original_id_loss_before_begin_no_remint_or_write' );
        foreach ( array( 1, 6, 7, 9, 10 ) as $gate ) {
            alynt_scan_seed( $values ); alynt_core_login(); $form = alynt_registration_form(); $n = 0; $nested = null;
            $probe = function ( $life, $action, $verify, $o ) use ( $gate, $form, &$n, &$nested ) {
                if ( $verify && $gate === ++$n ) $nested = alynt_core_private( $o, 'admit', array( 'POST', $form ) );
                return $life;
            };
            $o = alynt_join_run( $form, false, 1 === $gate ? 0 : ( $gate > 8 ? 40 : 20 ), $gate > 8 ? 1 : 0, $probe );
            alynt_core_assert( false === $nested && true === alynt_core_property( $o, 'admission_join_interrupted' ), 'Cross-entry reentry did not permanently poison original root.' );
            if ( $gate > 8 ) alynt_admission_receipt( $db, $original );
            else alynt_core_assert( $original === alynt_registration_frame( $db ), 'Reentered root wrote a ledger.' );
        }
        alynt_core_done( 'genuine_cross_entry_reentry_pre_capture_pre_retention_pre_begin_postrelease_refusals' );
        alynt_scan_seed( $values ); alynt_core_login(); $form = alynt_registration_form(); $n = 0;
        $probe = function ( $life, $action, $verify, $o ) use ( &$n ) { if ( $verify && 1 === ++$n ) alynt_ledger_set( $o, 'started', hrtime( true ) - 10000000001 ); return $life; };
        alynt_join_run( $form, false, 0, 0, $probe ); alynt_core_assert( 1 === $n, 'Original budget fault did not occur.' );
        alynt_core_done( 'explicit_original_root_expiry_before_capture_no_new_budget' );
        $index = 0;
        foreach ( array_keys( $values ) as $role ) {
            alynt_scan_seed( $values ); alynt_core_login(); $form = alynt_registration_form(); $n = 0; $fired = false;
            $probe = function ( $life, $action, $verify ) use ( $role, $values, &$n, &$fired ) { if ( $verify && 8 === ++$n ) { alynt_scan_seed( array( $role => $values[$role] ), 'off' ); $fired = true; } return $life; };
            $o = alynt_join_run( $form, false, 20 + ++$index, 0, $probe );
            alynt_core_assert( $fired && 'rolled_back' === alynt_core_property( $o, 'phase' ), 'Actual joined stale dependency did not acknowledge rollback.' );
        }
        alynt_core_done( 'all_ten_joined_real_locked_stale_dependencies_rollback_without_dml' );
        alynt_scan_seed( $values ); alynt_core_login(); $form = alynt_registration_form();
        $db->query( 'START TRANSACTION' ); $name = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows()['failed'][0];
        $stmt = $db->prepare( "SELECT option_id FROM {$GLOBALS['wpdb']->options} WHERE option_name=? FOR UPDATE" ); $stmt->bind_param( 's', $name ); $stmt->execute(); $stmt->get_result()->free(); $stmt->close();
        try { $o = alynt_join_run( $form, false, 30, 0, function ( $life ) { return $life; } ); }
        finally { $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
        alynt_core_assert( 'rolled_back' === alynt_core_property( $o, 'phase' ) && $original === alynt_registration_frame( $db ), 'Joined real contention retried or left changes.' );
        alynt_core_done( 'joined_actual_last_dependency_lock_timeout_rollback_cleanup_no_retry' );
    } finally { $db->close(); }
}
