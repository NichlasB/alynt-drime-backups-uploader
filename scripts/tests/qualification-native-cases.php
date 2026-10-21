<?php
/** Genuine denials on fresh fixed owners; never seed an observer or native success. */
function alynt_qualification_cases() {
    alynt_scan_seed( alynt_registration_values() ); alynt_core_login(); $form = alynt_registration_form();
    $db = alynt_scan_db(); $before = alynt_registration_frame( $db );
    try {
        $command_domain = 'not-an-issuance-domain';
        foreach ( array( array(), array_merge( $form, array( 'nonce' => 'bad' ) ), array_merge( $form, array( 'nonce' => wp_create_nonce( $command_domain ) ) ) ) as $bad ) {
            $w = Alynt_Test_Qualification_Native_Worker::create(); alynt_core_assert( $w instanceof Alynt_Test_Qualification_Native_Worker, 'qualification_fixed_factory' );
            $owner = alynt_core_property( $w, 'owner' );
            alynt_core_assert( false === alynt_core_private( $w, 'enter', array( 'POST', $bad ) ), 'qualification_denial' );
            alynt_core_assert( 0 === alynt_core_property( $owner, 'protected_reads' ) && 0 === alynt_core_property( $owner, 'writes' ) && null === alynt_core_property( $owner, 'qualification_observation' ) && false === alynt_core_property( $owner, 'qualification_observation_spent' ), 'qualification_denial_no_observation' );
            alynt_core_assert( false === alynt_core_private( $w, 'enter', array( 'POST', $form ) ) && 0 === alynt_core_property( $owner, 'protected_reads' ), 'qualification_denial_no_rearm' );
        }
        alynt_core_assert( $before === alynt_registration_frame( $db ), 'qualification_denial_changed_ledger' );
        alynt_core_done( 'qualification_genuine_early_denials_zero_protected_io_or_observation' );
    } finally { $db->close(); }
    $w = Alynt_Test_Qualification_Native_Worker::create(); alynt_core_assert( $w instanceof Alynt_Test_Qualification_Native_Worker, 'qualification_positive_factory' );
    alynt_core_private( $w, 'run', array( $form ) );
}
