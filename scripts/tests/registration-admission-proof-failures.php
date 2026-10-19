<?php
/** Owned SQL failure primitives, explicitly NOT real lost-wire/crash/auth admission proof. */
function alynt_admission_failures( $db, $values, $original ) {
    global $wpdb; $table = $wpdb->options;
    list( $o, $core ) = alynt_admission_primitive( $original );
    try { alynt_admission_locked( $o, $core ); foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $def ) alynt_ledger_nowait( $db, $def[0], true ); }
    finally { alynt_core_private( $o, 'finish' ); }
    alynt_core_assert( 'rolled_back' === alynt_core_property( $o, 'phase' ) && $original === alynt_registration_frame( $db ), 'All-ten lock exclusion/owned rollback primitive changed rows.' );
    foreach ( array( 'option_id', 'autoload', 'value', 'binary_name' ) as $field ) {
        list( $o, $core ) = alynt_admission_primitive( $original );
        try {
            alynt_admission_locked( $o, $core ); $before = $original;
            if ( 'binary_name' === $field ) {
                $writer = alynt_core_property( $o, 'reader' ); $id = $before['rows']['ledger']['option_id'];
                $stmt = $writer->prepare( "SELECT CAST(COUNT(*) AS CHAR) FROM {$table} WHERE option_id=?" ); $stmt->bind_param( 's', $id ); $stmt->execute(); alynt_core_assert( '1' === $stmt->get_result()->fetch_row()[0], 'Owned ledger-name control preview not exactly one row.' ); $stmt->close();
                $name = strtoupper( $before['rows']['ledger']['option_name'] ); $stmt = $writer->prepare( "UPDATE {$table} SET option_name=? WHERE option_id=?" ); $stmt->bind_param( 'ss', $name, $id ); $stmt->execute(); alynt_core_assert( 1 === $stmt->affected_rows, 'Owned ledger case mutation did not occur.' ); $stmt->close();
            } else {
                if ( 'option_id' === $field ) $before['rows']['ledger'][$field] = (string) ( (int) $before['rows']['ledger'][$field] + 100000 );
                elseif ( 'autoload' === $field ) $before['rows']['ledger'][$field] = 'NO'; else $before['rows']['ledger'][$field] .= ' ';
                alynt_ledger_set( $o, 'before', $before );
            }
            alynt_core_assert( false === alynt_core_private( $o, 'admission_write', array( $table ) ) && 1 === alynt_core_property( $o, 'writes' ), 'Exact closed-ledger CAS accepted conflicting ' . $field );
        } finally { alynt_core_private( $o, 'finish' ); }
        alynt_core_assert( 'rolled_back' === alynt_core_property( $o, 'phase' ) && $original === alynt_registration_frame( $db ), 'Failed CAS or binary metadata control changed durable fixture.' );
    }
    alynt_core_done( 'native_all_ten_lock_exclusion_and_exact_id_binary_name_autoload_old_value_cas_primitives' );

    list( $o, $core, $e ) = alynt_admission_primitive( $original );
    try {
        // Test-only cursor forces real UPDATE followed by a refused readback.
        // This is not a claim that this primitive performed thirty captures.
        alynt_ledger_set( $o, 'protected_reads', 30 );
        alynt_core_assert( false === alynt_core_private( $o, 'admission_transaction', array( $core, $e ) ) && 1 === alynt_core_property( $o, 'writes' ), 'Readback ceiling control did not reach actual UPDATE and refusal.' );
    } finally { alynt_core_private( $o, 'finish' ); }
    alynt_core_assert( 'rolled_back' === alynt_core_property( $o, 'phase' ) && $original === alynt_registration_frame( $db ), 'Unverified readback committed a ledger receipt.' );
    alynt_core_done( 'actual_update_then_instrumented_readback_ceiling_failure_acknowledged_rollback' );

    foreach ( array( false, true ) as $commit ) {
        list( $o, $core ) = alynt_admission_primitive( $original ); $writer = alynt_core_property( $o, 'reader' ); $after = alynt_core_property( $o, 'after' );
        try {
            alynt_admission_locked( $o, $core ); alynt_core_assert( alynt_core_private( $o, 'admission_write', array( $table ) ), 'Ambiguous-ack primitive never wrote owned ledger.' );
            alynt_ledger_set( $o, 'phase', 'commit_sent' );
            if ( $commit ) alynt_core_assert( true === $writer->query( 'COMMIT AND NO CHAIN NO RELEASE' ), 'Physical primitive COMMIT failed.' );
            // Deliberately omit acknowledgement transition on two real outcomes.
        } finally { alynt_core_private( $o, 'finish' ); }
        $expected = $original; if ( $commit ) { $expected['rows']['ledger']['value'] = $after; $expected['rows']['ledger']['octets'] = (string) strlen( $after ); }
        alynt_core_assert( 'poisoned' === alynt_core_property( $o, 'phase' ) && $expected === alynt_registration_frame( $db ), 'Unknown COMMIT guessed an outcome/reconnected.' );
        alynt_core_assert( false === alynt_core_private( $o, 'admit', array( 'POST', alynt_registration_form() ) ) && 1 === alynt_core_property( $o, 'writes' ), 'Unknown command replayed or reminted.' ); alynt_scan_seed( $values );
    }
    foreach ( array( false, true ) as $began ) {
        list( $o ) = alynt_admission_primitive( $original ); $writer = alynt_core_property( $o, 'reader' ); alynt_ledger_set( $o, 'phase', 'begin_sent' );
        try { if ( $began ) alynt_core_assert( true === $writer->query( 'START TRANSACTION READ WRITE' ), 'Physical BEGIN primitive failed.' ); }
        finally { alynt_core_private( $o, 'finish' ); }
        alynt_core_assert( 'poisoned' === alynt_core_property( $o, 'phase' ) && $original === alynt_registration_frame( $db ), 'Unknown BEGIN adopted ownership or changed rows.' );
    }
    foreach ( array( false, true ) as $rolled_back ) {
        list( $o, $core ) = alynt_admission_primitive( $original ); $writer = alynt_core_property( $o, 'reader' );
        try {
            alynt_admission_locked( $o, $core ); alynt_core_assert( alynt_core_private( $o, 'admission_write', array( $table ) ), 'Rollback-ack primitive never wrote owned ledger.' );
            alynt_ledger_set( $o, 'phase', 'rollback_sent' );
            if ( $rolled_back ) alynt_core_assert( true === $writer->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ), 'Physical ROLLBACK primitive failed.' );
        } finally { alynt_core_private( $o, 'finish' ); }
        alynt_core_assert( 'poisoned' === alynt_core_property( $o, 'phase' ) && $original === alynt_registration_frame( $db ) && false === alynt_core_private( $o, 'admit', array( 'POST', alynt_registration_form() ) ), 'Unknown ROLLBACK claimed definite acknowledgement or replayed.' );
    }
    list( $o, $core ) = alynt_admission_primitive( $original ); $writer = alynt_core_property( $o, 'reader' );
    try {
        alynt_admission_locked( $o, $core ); alynt_core_assert( alynt_core_private( $o, 'admission_write', array( $table ) ), 'Owned KILL proof never wrote ledger.' ); $thread = $writer->thread_id;
        alynt_core_assert( $thread !== $db->thread_id && $thread !== $wpdb->dbh->thread_id, 'Would kill a borrowed connection.' );
        alynt_core_assert( true === $db->query( 'KILL CONNECTION ' . (int) $thread ), 'Owned connection kill did not acknowledge.' );
    } finally { alynt_core_private( $o, 'finish' ); }
    alynt_core_assert( 'poisoned' === alynt_core_property( $o, 'phase' ) && $original === alynt_registration_frame( $db ), 'Killed owned writer reconnected/committed.' );
    alynt_core_done( 'protocol_begin_commit_rollback_ack_omission_real_outcomes_and_owned_postupdate_connection_kill_poison_no_replay' );

    list( $o ) = alynt_admission_primitive( $original );
    try { alynt_core_assert( alynt_core_private( $o, 'metadata', array( $table ) ) && false === alynt_core_private( $o, 'metadata', array( $wpdb->users ) ) && false === alynt_core_private( $o, 'metadata', array( $table . '`' ) ), 'Wrong schema/identifier supported.' ); }
    finally { alynt_core_private( $o, 'finish' ); }
    foreach ( array( 'connection' => array( 3, 131073 ), 'ledger' => array( 4, 131073 ), 'queue' => array( 7, 1048577 ) ) as $role => $case ) { alynt_scan_seed( array( $role => str_repeat( 'x', $case[1] ) ) ); alynt_admit( alynt_registration_form(), false, $case[0] ); alynt_scan_seed( $values ); }
    foreach ( array( 'safety' => '{}', 'ledger' => '{}', 'settings' => 'a:0:{}', 'connection' => 'O:8:"stdClass":0:{}', 'queue' => 'a:1:{i:0;b:1;}' ) as $role => $raw ) { alynt_scan_seed( array( $role => $raw ) ); alynt_admit( alynt_registration_form(), false, 20 ); alynt_scan_seed( $values ); }
    $s = json_decode( $values['safety'], true ); $s['writer']['authenticated_grant'] = array( 'active' => true ); alynt_scan_seed( array( 'safety' => json_encode( $s ) ) ); alynt_admit( alynt_registration_form(), false, 20 ); alynt_scan_seed( $values );
    foreach ( $values as $role => $raw ) { alynt_scan_seed( array( $role => $raw ), 'yes' ); alynt_admit( alynt_registration_form(), false, 20 ); alynt_scan_seed( $values ); }
    alynt_scan_case_name(); try { alynt_admit( alynt_registration_form(), false, 7 ); } finally { alynt_scan_case_name( true ); }
    foreach ( array( 'off', 'auto-off' ) as $autoload ) { alynt_scan_seed( $values, $autoload ); $before = alynt_registration_frame( $db ); alynt_admit( alynt_registration_form(), true, 40, 1 ); alynt_admission_receipt( $db, $before ); alynt_scan_seed( $values ); }
    $position = 0; foreach ( $values as $role => $raw ) { ++$position; alynt_scan_delete( $role ); alynt_admit( alynt_registration_form(), false, $position ); alynt_scan_seed( array( $role => $raw ) ); }
    // Reinserted owned rows have fresh IDs; accept the intentional new baseline.
    $original = alynt_registration_frame( $db );
    alynt_core_done( 'native_metadata_prefetch_caps_binary_names_complete_semantics_all_autoload_and_missing_roles_refuse_without_repair' );
    return $original;
}
