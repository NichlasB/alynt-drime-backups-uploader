<?php
/** Genuine core users/roles/sessions in owned tables only; no shims/true ports. */
function alynt_core_assert( $ok, $label ) { $GLOBALS['alynt_core_proof']['checks']++; alynt_core_check( $ok, $label ); }
function alynt_core_done( $label ) { $GLOBALS['alynt_core_proof']['cases'][] = $label; }
function alynt_core_private( $owner, $method, array $args = array() ) {
    $m = new ReflectionMethod( $owner, $method ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true );
    return $m->invokeArgs( $owner, $args ); // Observe an existing private boolean; no production success port.
}
function alynt_core_property( $owner, $name ) {
    $p = new ReflectionProperty( $owner, $name ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true );
    return $p->getValue( $owner );
}
function alynt_core_seed() {
    global $wpdb;
    $roles = array( 'administrator' => array( 'name' => 'Disposable administrator', 'capabilities' => array( 'read' => true, 'manage_options' => true ) ), 'subscriber' => array( 'name' => 'Disposable subscriber', 'capabilities' => array( 'read' => true ) ) );
    alynt_core_assert( add_option( $wpdb->prefix . 'user_roles', $roles, '', false ), 'Owned roles seed failed.' ); $GLOBALS['wp_roles'] = new WP_Roles();
    foreach ( array( 90000001 => 'administrator', 90000002 => 'subscriber', 90000003 => 'administrator' ) as $id => $role ) {
        alynt_core_assert( 1 === $wpdb->insert( $wpdb->users, array( 'ID' => $id, 'user_login' => 'alynt-core-fixture-' . $id, 'user_pass' => wp_hash_password( bin2hex( random_bytes( 24 ) ) ), 'user_nicename' => 'alynt-core-fixture-' . $id, 'user_email' => 'fixture-' . $id . '@example.invalid', 'display_name' => 'Disposable guard fixture', 'user_registered' => gmdate( 'Y-m-d H:i:s' ) ) ), 'Owned user seed failed.' );
        ( new WP_User( $id ) )->set_role( $role );
    }
    foreach ( array( 'alynt_drime_backups_settings', 'alynt_drime_backups_schedule_safety_state' ) as $name ) add_filter( 'pre_option_' . $name, function () { $GLOBALS['alynt_core_proof']['protected_reads']++; throw new RuntimeException( 'Protected option API attempted.' ); }, PHP_INT_MAX );
    add_action( 'auth_cookie_valid', function () { $GLOBALS['alynt_core_proof']['events'][] = 'authenticated'; } );
    add_filter( 'user_has_cap', function ( $all, $caps, $args ) { if ( 'manage_options' === $args[0] ) $GLOBALS['alynt_core_proof']['events'][] = 'capability'; return $all; }, 10, 3 );
}
function alynt_core_login( $id = 90000001, $expiration = null ) {
    alynt_core_assert( in_array( $id, array( 90000001, 90000002, 90000003 ), true ), 'Actual user refused.' );
    $expires = null === $expiration ? time() + 900 : $expiration;
    $token = WP_Session_Tokens::get_instance( $id )->create( $expires );
    $_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie( $id, $expires, 'logged_in', $token );
    wp_set_current_user( $id ); $_SERVER['REQUEST_METHOD'] = 'POST';
    return $token; // Never emit raw cookies/tokens/nonces or create browser headers.
}
function alynt_core_form( $purpose = 'upgrade_closed', $offset = 0 ) {
    $now = time() + $offset;
    $form = array( 'contract' => 'closed_writer_request_v1', 'purpose' => $purpose, 'command_id' => '88888888-8888-4888-8888-000000000001', 'site_uuid' => '11111111-1111-4111-8111-111111111111', 'enrollment_binding' => str_repeat( 'a', 64 ), 'expected_generation' => '2', 'issued_at' => (string) $now, 'expires_at' => (string) ( $now + 300 ), 'confirm' => 'close', 'origin_digest' => str_repeat( 'b', 64 ), 'nonce' => 'pending' );
    if ( 'receipt_read' === $purpose ) { unset( $form['enrollment_binding'], $form['expected_generation'], $form['origin_digest'] ); $form['confirm'] = 'observe'; $form['observed_command_id'] = '88888888-8888-4888-8888-000000000002'; }
    if ( in_array( $purpose, array( 'begin_disable', 'abandon_closed' ), true ) ) {
        unset( $form['origin_digest'] ); $form['context_digest'] = str_repeat( 'b', 64 );
        if ( 'begin_disable' === $purpose ) { $form['confirm'] = 'disable'; $form['before_hash'] = str_repeat( 'c', 64 ); $form['after_hash'] = str_repeat( 'd', 64 ); }
        else { $form['confirm'] = 'abandon'; $form['operation_id'] = '88888888-8888-4888-8888-000000000002'; $form['closure_generation'] = '3'; $form['observed_hash'] = str_repeat( 'c', 64 ); }
    }
    $parsed = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::parse( $form );
    alynt_core_assert( is_array( $parsed ), 'Real form must parse.' ); $e = $parsed['envelope'];
    $action = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::action( $e ); $form['nonce'] = wp_create_nonce( $action );
    return array( $form, $e, $action );
}
function alynt_core_decision( $nonce, $action, $e = null ) {
    $owner = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Owner::create();
    $frame = alynt_core_private( $owner, 'frame' ); alynt_core_assert( is_array( $frame ), 'Native frame must be admitted for guard inspection.' );
    $before = $GLOBALS['wp_roles']; $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { return alynt_core_private( $owner, 'guard', array( $frame, $nonce, $action, $e ) ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; alynt_core_assert( $before === $GLOBALS['wp_roles'], 'Prior roles must be restored.' ); }
}
function alynt_core_public( $owner, $method, $form ) {
    $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { $result = $owner->$method( 'POST', $form ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; }
    alynt_core_assert( array( 'outcome' => 'unavailable', 'source' => 'closed_writer_construction_v1', 'storage_supported' => false, 'dispatch_supported' => false, 'execution_supported' => false ) === $result, 'Public request must expose no handle or storage success.' );
    return $result;
}
