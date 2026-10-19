<?php
/** Non-vacuous genuine auth, one proposal, exact original retention and cleanup. */
function alynt_lifecycle_positive() {
    foreach ( array( 'wp_generate_uuid4', 'wp_create_nonce', 'wp_verify_nonce', 'wp_validate_auth_cookie', 'user_can' ) as $fn ) alynt_core_assert( 0 === strpos( realpath( ( new ReflectionFunction( $fn ) )->getFileName() ), realpath( ABSPATH . 'wp-includes' ) . DIRECTORY_SEPARATOR ), 'Lifecycle proof requires genuine core APIs.' );
    list( $o, $frame, $i ) = alynt_lifecycle_fixture(); $facts = array(); $created = array(); $probe = alynt_lifecycle_probe( $facts, $created );
    add_filter( 'nonce_life', $probe, 10, 2 );
    try { alynt_lifecycle_run( $o, $frame, $i, true ); } finally { remove_filter( 'nonce_life', $probe, 10 ); }
    alynt_core_assert( 6 === count( $facts ) && 1 === count( $created ), 'Require six independent guards and one command nonce creation.' );
    $small = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( get_current_blog_id(), 'register_closed' );
    alynt_core_assert( 0 === strpos( $created[0], 'alynt_drime_backups_scan_registration_command_v2:' ) && $small !== $created[0], 'Separate issuance/full command domains required; no observation nonce.' );
    alynt_core_assert( array( $small, $small, $small, $created[0], $small, $created[0] ) === array_column( $facts, 'action' ), 'Exact six-checkpoint guard ordering differs.' );
    foreach ( $facts as $n => $f ) {
        alynt_core_assert( $f['started'] === $facts[0]['started'], 'Same root window renewed.' );
        if ( $n < 4 ) { alynt_core_assert( null === $f['origin'] && array() === $f['used'] && null === $f['clock'], 'Origin retained before own command authorization.' ); continue; }
        $d = $f['origin']; $ids = array_keys( $f['used'] );
        alynt_core_assert( is_array( $d ) && 90000001 === $d['actor_id'] && $d['mutation']['command_id'] !== $d['operation_id'] && $d['context_digest'] === $d['mutation']['context_digest'], 'Original genuine actor/command/context lost.' );
        alynt_core_assert( $f['clock']['started_ns'] === $facts[0]['started'] && $f['clock']['now_ns'] >= $f['clock']['started_ns'], 'Retained clock is not original root clock.' );
        $fresh = array_slice( $ids, -10 ); alynt_core_assert( 10 === count( array_unique( $fresh ) ) && in_array( $d['mutation']['command_id'], $fresh, true ) && in_array( $d['operation_id'], $fresh, true ), 'Complete ten original identities required.' );
        foreach ( $fresh as $id ) alynt_core_assert( 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $id ), 'Fresh identity not genuine UUIDv4 shape.' );
        foreach ( array( 'nonce', 'rows', 'db', 'cookie' ) as $key ) alynt_core_assert( ! array_key_exists( $key, $d ) && ! array_key_exists( $key, $d['installation'] ), 'Protected payload retained in minimal origin.' );
    }
    alynt_core_assert( $facts[4]['origin'] === $facts[5]['origin'] && $facts[4]['used'] === $facts[5]['used'] && $facts[4]['clock'] === $facts[5]['clock'], 'Original origin/identities/clock changed during final guards.' );
}
function alynt_lifecycle_cases() {
    alynt_lifecycle_positive(); alynt_core_done( 'genuine_positive_six_guards_original_provenance_ten_ids_one_command_nonce_cleanup' );
    foreach ( range( 1, 6 ) as $gate ) {
        list( $o, $frame, $i ) = alynt_lifecycle_fixture(); $facts = array(); $created = array();
        $probe = alynt_lifecycle_probe( $facts, $created, function ( $n, $life ) use ( $gate ) { return $gate === $n ? 1 : $life; } );
        add_filter( 'nonce_life', $probe, 10, 2 );
        try { alynt_lifecycle_run( $o, $frame, $i ); } finally { remove_filter( 'nonce_life', $probe, 10 ); }
        alynt_core_assert( $gate === count( $facts ), 'Independent genuine nonce refusal skipped or bypassed.' );
    }
    alynt_core_done( 'each_of_six_genuine_nonce_guards_independently_refuses_and_consumes' );
    foreach ( array( 'malformed', 'extra', 'read', 'wrong_domain', 'stale_session', 'other_actor', 'subscriber', 'forged_cookie', 'expired_cookie' ) as $change ) {
        list( $o, $frame, $i ) = alynt_lifecycle_fixture();
        if ( 'malformed' === $change ) $i['nonce'] = '';
        elseif ( 'extra' === $change ) $i['actor_id'] = '90000001';
        elseif ( 'read' === $change ) $i['purpose'] = 'observe_registration';
        elseif ( 'wrong_domain' === $change ) $i['nonce'] = wp_create_nonce( 'alynt_drime_backups_scan_registration_receipt_v2:wrong' );
        elseif ( 'stale_session' === $change ) alynt_core_login();
        elseif ( 'other_actor' === $change ) alynt_core_login( 90000003 );
        elseif ( 'subscriber' === $change ) { alynt_core_login( 90000002 ); $i = alynt_registration_form(); }
        elseif ( 'expired_cookie' === $change ) { alynt_core_login( 90000001, time() - 7200 ); $i = alynt_registration_form(); }
        else $_COOKIE[LOGGED_IN_COOKIE] .= 'forged';
        $facts = array(); $created = array(); $probe = alynt_lifecycle_probe( $facts, $created ); add_filter( 'nonce_life', $probe, 10, 2 );
        try { alynt_lifecycle_run( $o, $frame, $i ); } finally { remove_filter( 'nonce_life', $probe, 10 ); }
        alynt_core_assert( array() === $created, 'Initial malformed/auth denial minted command nonce.' );
    }
    alynt_core_done( 'malformed_read_cross_domain_stale_wrong_actor_capability_forged_expired_intents_refuse' );
    foreach ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) as $role ) {
        list( $o, $frame, $i ) = alynt_lifecycle_fixture(); $frame['rows'][$role]['octets'] = '00'; $facts = array(); $created = array(); $probe = alynt_lifecycle_probe( $facts, $created );
        add_filter( 'nonce_life', $probe, 10, 2 ); try { alynt_lifecycle_run( $o, $frame, $i ); } finally { remove_filter( 'nonce_life', $probe, 10 ); }
        alynt_core_assert( 1 === count( $facts ) && array() === $created, 'Malformed ten-row candidate bypassed first auth or reached command creation.' );
    }
    alynt_core_done( 'all_ten_synthetic_captured_tuple_guards_follow_auth_before_command_nonce' );
}
