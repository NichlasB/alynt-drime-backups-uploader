<?php
/** Genuine positive auth with synthetic complete candidate history, no observer. */
function alynt_handoff_cases() {
    foreach ( array( 'wp_generate_uuid4', 'wp_create_nonce', 'wp_verify_nonce', 'wp_validate_auth_cookie', 'user_can' ) as $fn ) alynt_core_assert( 0 === strpos( realpath( ( new ReflectionFunction( $fn ) )->getFileName() ), realpath( ABSPATH . 'wp-includes' ) . DIRECTORY_SEPARATOR ), 'Handoff requires genuine core APIs.' );
    foreach ( array( 'registered' => 'registered_closed', 'reserved' => 'registered_closed', 'empty' => 'not_observed_in_candidate', 'conflict' => 'domain_conflict' ) as $phase => $classification ) {
        list( $o, $frame, $i, $d ) = alynt_handoff_fixture( $phase ); $facts = array(); $probe = alynt_handoff_probe( $facts );
        $db = alynt_scan_db(); try { $before = alynt_registration_frame( $db ); } finally { $db->close(); }
        add_filter( 'nonce_life', $probe, 10, 2 );
        try { alynt_handoff_run( $o, $frame, $i, $classification ); } finally { remove_filter( 'nonce_life', $probe, 10 ); }
        alynt_core_assert( 7 === count( $facts ), 'All seven genuine verifier checkpoints required.' );
        $small = Alynt_Drime_Backups_Uploader_Registration_Observation_Request::issue_action( get_current_blog_id(), $d['mutation']['command_id'] );
        foreach ( $facts as $n => $f ) {
            alynt_core_assert( $f['fenced'] && ( $n < 3 ) === $f['origin'] && in_array( $n, array( 3, 4 ), true ) === $f['staged'] && ( $n >= 5 ) === $f['finished'], 'Guard ordering/origin lifetime/fences differ.' );
            alynt_core_assert( $f['window'] === $facts[0]['window'], 'Inner handoff renewed original window.' );
            alynt_core_assert( in_array( $n, array( 0, 1, 3, 5 ), true ) ? $small === $f['action'] : 0 === strpos( $f['action'], 'alynt_drime_backups_scan_registration_receipt_v2:' ), 'Small/full read nonce domains not independently verified.' );
        }
        $db = alynt_scan_db(); try { alynt_core_assert( $before === alynt_registration_frame( $db ), 'Candidate changed owned protected rows.' ); } finally { $db->close(); }
    }
    alynt_core_done( 'genuine_seven_guards_four_candidate_classifications_original_window_and_owned_cleanup' );
    list( $o, $frame, $i, $d ) = alynt_handoff_fixture(); alynt_core_login( 90000003 ); $i = alynt_origin_intent( $o );
    alynt_handoff_run( $o, $frame, $i, 'registered_closed' ); alynt_core_assert( 90000001 === $d['actor_id'], 'Current observer replaced historical actor.' );
    alynt_core_done( 'different_current_genuine_administrator_preserves_original_actor' );
    // Genuine core uses CSPRNG UUIDs; observe full nonce creation, not mt_rand state.
    foreach ( array( 'bad_nonce', 'wrong_id', 'cross_domain', 'extra', 'stale', 'subscriber' ) as $change ) {
        list( $o, $frame, $i ) = alynt_handoff_fixture();
        if ( 'bad_nonce' === $change ) $i['nonce'] = 'bad';
        elseif ( 'wrong_id' === $change ) $i['observed_command_id'] = wp_generate_uuid4();
        elseif ( 'cross_domain' === $change ) $i['nonce'] = alynt_registration_form()['nonce'];
        elseif ( 'extra' === $change ) $i['actor_id'] = '90000001';
        elseif ( 'stale' === $change ) { alynt_core_login(); } // Old genuine intent bound to previous session.
        else { alynt_core_login( 90000002 ); $i = alynt_origin_intent( $o ); }
        $created = 0; $creation = function ( $life, $action ) use ( &$created ) { if ( 0 === strpos( $action, 'alynt_drime_backups_scan_registration_receipt_v2:' ) && in_array( 'wp_create_nonce', array_column( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 8 ), 'function' ), true ) ) ++$created; return $life; };
        add_filter( 'nonce_life', $creation, 10, 2 );
        try { alynt_handoff_run( $o, $frame, $i ); } finally { remove_filter( 'nonce_life', $creation, 10 ); }
        alynt_core_assert( 0 === $created, 'Invalid request reached full nonce creation before authorization.' );
    }
    alynt_core_done( 'malformed_wrong_domain_stale_session_and_subscriber_refuse_before_full_nonce_creation' );
    foreach ( range( 1, 7 ) as $phase ) {
        list( $o, $frame, $i ) = alynt_handoff_fixture(); $facts = array();
        $probe = alynt_handoff_probe( $facts, function ( $n, $life ) use ( $phase ) { return $n === $phase ? 1 : $life; } );
        add_filter( 'nonce_life', $probe, 10, 2 );
        try { alynt_handoff_run( $o, $frame, $i ); } finally { remove_filter( 'nonce_life', $probe, 10 ); }
        alynt_core_assert( $phase === count( $facts ), 'Independent genuine nonce denial bypassed or wrong phase.' );
        if ( $phase <= 3 ) foreach ( $facts as $f ) alynt_core_assert( ! $f['staged'], 'Failed genuine construction staged a handoff.' );
    }
    alynt_core_done( 'every_independent_genuine_nonce_verifier_denies_and_clears_without_retry' );
}
