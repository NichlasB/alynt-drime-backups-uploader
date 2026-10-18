<?php
/** Positive controls, real nonce domains and public boundary ordering. */
function alynt_core_cases() {
    alynt_core_login();
    list( $form, $e, $action ) = alynt_core_form();
    alynt_core_assert( true === alynt_core_decision( $form['nonce'], $action, $e ), 'Positive native guard control must be true.' );
    alynt_core_done( 'native_admin_session_capability_nonce_positive_guard_not_admission' );
    $issue = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::issue_action( get_current_blog_id(), 'upgrade_closed' );
    list( $read, $read_e, $read_action ) = alynt_core_form( 'receipt_read' );
    $domains = array( $issue, $action, $read_action, 'alynt_drime_backups_closed_writer_fixture_command_v1:fake' );
    foreach ( $domains as $expected ) {
        $nonce = wp_create_nonce( $expected );
        foreach ( $domains as $actual ) alynt_core_assert( ( $expected === $actual ) === alynt_core_decision( $nonce, $actual ), 'Core nonce domains crossed.' );
    }
    alynt_core_done( 'real_issue_command_receipt_fixture_nonce_domains_disjoint' );
    $observer = function ( $life ) { $GLOBALS['alynt_core_proof']['events'][] = 'nonce'; return $life; };
    foreach ( array( 'upgrade_closed', 'begin_disable', 'abandon_closed', 'receipt_read' ) as $purpose ) {
        list( $body, $intent, $bound ) = alynt_core_form( $purpose );
        $owner = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Owner::create();
        $GLOBALS['alynt_core_proof']['events'] = array(); add_filter( 'nonce_life', $observer );
        try { alynt_core_public( $owner, 'consume', $body ); }
        finally { remove_filter( 'nonce_life', $observer ); }
        alynt_core_assert( array( 'authenticated', 'capability', 'nonce', 'authenticated', 'capability' ) === $GLOBALS['alynt_core_proof']['events'], 'Public consume must authenticate on both sides of its real nonce.' );
        alynt_core_assert( false === alynt_core_property( $owner, 'active' ), 'Public lifetime must close.' );
    }
    $owner = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Owner::create();
    $issue_form = array( 'purpose' => 'upgrade_closed', 'confirm' => 'close', 'nonce' => wp_create_nonce( $issue ) );
    $GLOBALS['alynt_core_proof']['events'] = array(); add_filter( 'nonce_life', $observer );
    try { alynt_core_public( $owner, 'issue', $issue_form ); } finally { remove_filter( 'nonce_life', $observer ); }
    alynt_core_assert( array( 'authenticated', 'capability', 'nonce', 'authenticated', 'capability' ) === $GLOBALS['alynt_core_proof']['events'], 'Issuance must use fixed guard ordering without issuing anything.' );
    alynt_core_done( 'all_public_purposes_guarded_twice_and_unavailable_without_handles' );
    foreach ( array( 'unknown_field', 'wrong_method', 'missing_nonce' ) as $case ) {
        $body = $form;
        if ( 'unknown_field' === $case ) $body['source'] = 'posted';
        if ( 'missing_nonce' === $case ) unset( $body['nonce'] );
        if ( 'wrong_method' === $case ) $_SERVER['REQUEST_METHOD'] = 'GET';
        $queries = $GLOBALS['alynt_core_proof']['queries']; $GLOBALS['alynt_core_proof']['events'] = array();
        alynt_core_public( $owner, 'consume', $body );
        alynt_core_assert( $queries === $GLOBALS['alynt_core_proof']['queries'] && array() === $GLOBALS['alynt_core_proof']['events'], 'Malformed intent reached core reads.' );
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }
    alynt_core_done( 'malformed_and_wrong_method_intent_rejected_before_auth_reads' );
    list( $stale, $stale_e, $stale_action ) = alynt_core_form( 'upgrade_closed', -400 );
    alynt_core_assert( in_array( wp_verify_nonce( $stale['nonce'], $stale_action ), array( 1, 2 ), true ), 'Stale intent control needs a still-valid core nonce.' );
    $queries = $GLOBALS['alynt_core_proof']['queries'];
    alynt_core_assert( false === alynt_core_decision( $stale['nonce'], $stale_action, $stale_e ) && $queries === $GLOBALS['alynt_core_proof']['queries'], 'Original stale intent must deny before auth reads despite valid nonce.' );
    $changed = $e; $changed['expected_generation']++;
    $changed_action = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::action( $changed );
    alynt_core_assert( false === alynt_core_decision( $form['nonce'], $changed_action, $changed ), 'Changed original intent reused an old core nonce.' );
    alynt_core_done( 'stale_and_changed_original_intent_denied_with_live_nonce_control' );
    $short = function () { return 2; }; add_filter( 'nonce_life', $short );
    try {
        $nonce = wp_create_nonce( $action ); $tick = wp_nonce_tick( $action ); $end = microtime( true ) + 5;
        alynt_core_assert( true === alynt_core_decision( $nonce, $action ), 'Short nonce positive control failed.' );
        while ( wp_nonce_tick( $action ) <= $tick + 1 && microtime( true ) < $end ) usleep( 50000 );
        alynt_core_assert( wp_nonce_tick( $action ) > $tick + 1 && false === wp_verify_nonce( $nonce, $action ), 'Native nonce did not expire in bounded window.' );
        alynt_core_assert( false === alynt_core_decision( $nonce, $action ), 'Expired native nonce admitted.' );
    } finally { remove_filter( 'nonce_life', $short ); }
    alynt_core_done( 'genuine_nonce_expiry_denied' );
    $serializable = false;
    try { serialize( $owner ); $serializable = true; } catch ( LogicException $error ) { alynt_core_assert( 'Closed request construction is unavailable.' === $error->getMessage(), 'Unexpected serialization refusal.' ); }
    alynt_core_assert( ! $serializable && array() === headers_list(), 'Owner escaped or browser session emitted.' );
    alynt_core_done( 'owner_serialization_and_browser_session_export_refused' );
}
