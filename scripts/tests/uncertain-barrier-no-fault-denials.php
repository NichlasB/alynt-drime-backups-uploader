<?php
/** Genuine early refusals of THIS new terminal owner; no injected auth result. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) ) exit( 1 );
function alynt_uncertain_barrier_denial( $method, $body ) {
    $o = Alynt_Test_Uncertain_Barrier_No_Fault_Owner::create();
    $writes = $GLOBALS['alynt_core_proof']['fixture_writes']; $roles = $GLOBALS['wp_roles'];
    $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { $result = alynt_core_private( $o, 'lifecycle_root', array( $method, $body ) ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; }
    alynt_core_assert( false === $result && 0 === alynt_core_property( $o, 'protected_reads' ) && 0 === alynt_core_property( $o, 'writes' ) && array() === alynt_core_property( $o, 'observed_sessions' ), 'uncertain_barrier_denial_before_protected_factory_io' );
    alynt_core_assert( $writes === $GLOBALS['alynt_core_proof']['fixture_writes'] && $roles === $GLOBALS['wp_roles'], 'uncertain_barrier_denial_no_wp_write_or_role_leak' );
    alynt_core_assert( false === alynt_core_private( $o, 'take_uncertain_barrier_observation' ) && null === alynt_core_property( $o, 'terminal_event' ) && null === alynt_core_property( $o, 'qualification_observation' ), 'uncertain_barrier_denial_no_terminal_or_qualification' );
    alynt_core_assert( false === alynt_core_private( $o, 'lifecycle_root', array( 'POST', $body ) ), 'uncertain_barrier_denial_no_rearm' );
}
function alynt_uncertain_barrier_denials( $form ) {
    $count = 0;
    foreach ( array( array(), array_merge( $form, array( 'nonce' => 'bad' ) ), array_merge( $form, array( 'nonce' => wp_create_nonce( 'not-an-issuance-domain' ) ) ), array_merge( $form, array( 'provider' => 'untrusted' ) ), array_merge( $form, array( 'confirm' => 'enable' ) ) ) as $bad ) { alynt_uncertain_barrier_denial( 'POST', $bad ); $count++; }
    alynt_uncertain_barrier_denial( 'GET', $form ); $count++;
    $cookie = $_COOKIE[LOGGED_IN_COOKIE]; $_COOKIE[LOGGED_IN_COOKIE] = 'forged';
    try { alynt_uncertain_barrier_denial( 'POST', $form ); $count++; } finally { $_COOKIE[LOGGED_IN_COOKIE] = $cookie; }
    alynt_core_login( 90000003 ); alynt_uncertain_barrier_denial( 'POST', $form ); $count++;
    alynt_core_login( 90000002 ); alynt_uncertain_barrier_denial( 'POST', alynt_registration_form() ); $count++;
    alynt_core_login( 90000001, time() - 7200 ); alynt_uncertain_barrier_denial( 'POST', alynt_registration_form() ); $count++;
    alynt_core_login();
    alynt_core_assert( 10 === $count, 'uncertain_barrier_exact_genuine_denials' );
    return $count;
}
