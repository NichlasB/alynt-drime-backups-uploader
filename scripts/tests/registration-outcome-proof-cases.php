<?php
/** Full genuine entry: observe through existing core callbacks only, then verify erasure. */
function alynt_outcome_cases() {
    global $wpdb;
    $values = alynt_registration_values(); $db = alynt_scan_db(); $auth = $wpdb->dbh;
    try {
        foreach ( array( 'positive', 'revoke_after_commit', 'throw_after_commit' ) as $case ) {
            alynt_scan_seed( $values ); $token = alynt_core_login(); $form = alynt_registration_form(); $before = alynt_registration_frame( $db );
            $observed = null; $gates = array(); $fired = false;
            $probe = function ( $life, $action ) use ( &$observed, &$gates, &$fired, $case, $token, $db, $auth ) {
                if ( ! in_array( 'wp_verify_nonce', array_column( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 8 ), 'function' ), true ) ) return $life;
                $o = $GLOBALS['alynt_admission_owner']; $phase = alynt_core_property( $o, 'phase' );
                $gates[] = array( $phase, $action, alynt_core_property( $o, 'protected_reads' ), alynt_core_property( $o, 'writes' ) );
                alynt_core_assert( $GLOBALS['wpdb']->dbh === $auth, 'Borrowed auth handle replaced.' );
                if ( 'committed' !== $phase ) return $life;
                $observed = alynt_outcome_snapshot( $o, alynt_outcome_commit_order() );
                alynt_core_assert( null === alynt_core_property( $o, 'reader' ) && null === alynt_core_property( $o, 'native_outcome_writer' ), 'Postcommit auth callback preceded owned release.' );
                foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $def ) alynt_ledger_nowait( $db, $def[0], false );
                if ( ! $fired && 'positive' !== $case ) {
                    $fired = true;
                    if ( 'throw_after_commit' === $case ) throw new RuntimeException( 'Owned postcommit callback failure.' );
                    alynt_core_mutate( function () use ( $token ) { WP_Session_Tokens::get_instance( 90000001 )->destroy( $token ); } );
                }
                return $life;
            };
            add_filter( 'nonce_life', $probe, 10, 2 );
            try { $o = alynt_admit( $form, 'positive' === $case, 40, 1 ); } finally { remove_filter( 'nonce_life', $probe, 10 ); }
            alynt_core_assert( is_array( $observed ), 'Positive native history never reached genuine post-close callback.' ); alynt_outcome_commit( $observed );
            alynt_core_assert( 0 === $gates[0][2] && 0 === $gates[0][3], 'First genuine nonce guard followed protected reads/DML.' );
            $small = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( get_current_blog_id(), 'register_closed' );
            foreach ( array( 'idle', 'committed' ) as $phase ) {
                $actions = array_column( array_values( array_filter( $gates, function ( $g ) use ( $phase ) { return $phase === $g[0]; } ) ), 1 );
                alynt_core_assert( isset( $actions[0] ) && $small === $actions[0], 'Renewed issuance guard missing before command domain.' );
                if ( 'committed' !== $phase || 'positive' === $case ) alynt_core_assert( isset( $actions[1] ) && 0 === strpos( $actions[1], 'alynt_drime_backups_scan_registration_command_v2:' ) && $small !== $actions[1], 'Independent full-command guard missing.' );
            }
            alynt_admission_receipt( $db, $before ); alynt_outcome_erased( $o );
            alynt_core_assert( 'committed' === alynt_core_property( $o, 'phase' ) && ( 'positive' === $case || $fired ), 'Late refusal erased historical commit or fault did not run.' );
            alynt_scan_seed( $values );
            alynt_core_assert( $before === alynt_registration_frame( $db ), 'Owned postcommit fixture reconciliation failed.' );
            alynt_core_done( 'genuine_fixed_native_capture_' . $case . '_commit_release_unknown_acceptance_outer_erase' );
        }
        alynt_core_login(); $form = alynt_registration_form();
        foreach ( array( array(), array_merge( $form, array( 'nonce' => 'bad' ) ), array_merge( $form, array( 'actor_id' => '90000001' ) ) ) as $bad ) alynt_outcome_erased( alynt_admit( $bad, false, 0 ) );
        alynt_core_done( 'genuine_initial_refusals_zero_protected_reads_no_capture_reopen' );
    } finally { $db->close(); }
}
