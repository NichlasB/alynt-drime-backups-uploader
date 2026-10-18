<?php
/** Owned SQL fault primitives; reflection here is not a production request port. */
function alynt_native_begin_primitive() {
    global $wpdb;
    $owner = Alynt_Drime_Backups_Uploader_Native_Closed_Begin::create();
    alynt_native_begin_set( $owner, 'used', true ); alynt_native_begin_set( $owner, 'started', hrtime( true ) );
    $facts = alynt_core_private( $owner, 'native_idle', array( $wpdb->dbh ) );
    alynt_core_assert( is_array( $facts ) && alynt_core_private( $owner, 'open_writer', array( $wpdb->dbh, $facts ) ), 'Owned primitive writer setup failed.' );
    return $owner;
}
function alynt_native_begin_failures( $db, $eight, $settings, $form, $original ) {
    global $wpdb;
    $table = $wpdb->options; $e = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::parse( $form )['envelope'];
    $owner = alynt_native_begin_primitive();
    try {
        alynt_core_assert( alynt_core_private( $owner, 'metadata', array( $table ) ), 'Supported owned options schema refused.' );
        alynt_core_assert( false === alynt_core_private( $owner, 'metadata', array( $wpdb->users ) ) && false === alynt_core_private( $owner, 'metadata', array( $table . '`' ) ), 'Unsupported schema/identifier was admitted.' );
    } finally { alynt_core_private( $owner, 'finish' ); }
    alynt_core_done( 'genuine_supported_metadata_positive_owned_wrong_schema_and_identifier_negative' );
    // A second genuine pre-lock nonce callback changes only owned safety bytes.
    $nonce_calls = 0;
    $change = function ( $life ) use ( &$nonce_calls, $eight ) { if ( 2 === ++$nonce_calls ) alynt_native_seed_rows( array( 'alynt_drime_backups_schedule_safety_state' => ' ' . json_encode( $eight ) ) ); return $life; };
    add_filter( 'nonce_life', $change );
    try { $owner = alynt_native_begin_run( $form, false, 3 ); }
    finally { remove_filter( 'nonce_life', $change ); }
    alynt_core_assert( 2 === $nonce_calls && 'rolled_back' === alynt_core_property( $owner, 'phase' ) && 0 === alynt_core_property( $owner, 'writes' ), 'Raw safety equality or confirmed conflict rollback missing.' );
    alynt_native_seed_rows( array( 'alynt_drime_backups_schedule_safety_state' => json_encode( $eight ) ) );
    alynt_core_done( 'genuine_prelock_callback_raw_safety_change_rejected_under_lock' );

    // Actual private locking primitive; NOWAIT probes cannot acquire either row.
    $owner = alynt_native_begin_primitive(); $writer = alynt_core_property( $owner, 'reader' );
    try {
        $writer->query( 'START TRANSACTION READ WRITE' ); alynt_native_begin_set( $owner, 'phase', 'transaction_owned' );
        foreach ( array( true, false ) as $safety ) {
            alynt_core_assert( is_array( alynt_core_private( $owner, 'row', array( $table, $safety, true ) ) ), 'Native row lock primitive failed.' );
            $name = $safety ? 'alynt_drime_backups_schedule_safety_state' : 'alynt_drime_backups_settings';
            $blocked = false; $code = 0; $db->query( 'START TRANSACTION' );
            try {
                $result = $db->query( "SELECT option_id FROM {$table} WHERE option_name='{$name}' FOR UPDATE NOWAIT" );
                if ( false === $result ) { $code = $db->errno; $blocked = 1205 === $code; }
                elseif ( $result instanceof mysqli_result ) $result->free();
            }
            catch ( mysqli_sql_exception $error ) { $code = $error->getCode(); $blocked = 1205 === $code; }
            finally { $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
            alynt_core_assert( $blocked, 'Private locking read omitted a required safety/settings lock; native error code ' . $code . '.' );
        }
    } finally { alynt_core_private( $owner, 'finish' ); }
    alynt_core_assert( 'rolled_back' === alynt_core_property( $owner, 'phase' ) && $original === alynt_native_begin_rows( $db ), 'Lock primitive changed durable rows.' );
    alynt_core_done( 'genuine_both_row_locks_exclude_independent_nowait_without_target_writes' );

    // Exhaust a private read fence AFTER native UPDATE, before COMMIT. This is
    // deterministic native abort/rollback evidence, not an injected SQL error.
    $owner = alynt_native_begin_primitive(); $writer = alynt_core_property( $owner, 'reader' );
    try {
        $a = alynt_core_private( $owner, 'row', array( $table, true, false ) ); $b = alynt_core_private( $owner, 'row', array( $table, false, false ) );
        $frame = alynt_core_private( $owner, 'frame' );
        $raw = alynt_core_private( $owner, 'prepare', array( $frame, $e, $a, $b, '99999999-9999-4999-8999-000000000001', time() ) );
        alynt_core_assert( is_string( $raw ), 'Rollback primitive candidate preparation failed.' );
        alynt_native_begin_set( $owner, 'protected_reads', 5 );
        $value = alynt_core_private( $owner, 'transact', array( $frame, $e, $a, $b, $raw ) );
        alynt_core_assert( false === $value && 1 === alynt_core_property( $owner, 'writes' ) && 'transaction_owned' === alynt_core_property( $owner, 'phase' ), 'Post-update rollback control never reached real UPDATE.' );
    } finally { alynt_core_private( $owner, 'finish' ); }
    alynt_core_assert( 'rolled_back' === alynt_core_property( $owner, 'phase' ) && $original === alynt_native_begin_rows( $db ), 'Post-update abort left durable safety change.' );
    alynt_core_done( 'native_update_then_private_read_fence_abort_confirmed_rollback_original_rows' );

    $p = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::plan( $eight, $e, $settings, time(), 1, 'no', '99999999-9999-4999-8999-000000000002', true );
    alynt_core_assert( is_array( $p ), 'Failure primitive closed candidate failed.' ); $raw = json_encode( $p['state'] );
    foreach ( array( 'option_id', 'autoload', 'value' ) as $field ) {
        $owner = alynt_native_begin_primitive(); $writer = alynt_core_property( $owner, 'reader' );
        try {
            $writer->query( 'START TRANSACTION READ WRITE' ); alynt_native_begin_set( $owner, 'phase', 'transaction_owned' );
            $a = alynt_core_private( $owner, 'row', array( $table, true, true ) ); alynt_core_private( $owner, 'row', array( $table, false, true ) );
            if ( 'option_id' === $field ) $a[$field] = (string) ( (int) $a[$field] + 100000 );
            elseif ( 'autoload' === $field ) $a[$field] = 'NO';
            else $a[$field] .= ' ';
            alynt_core_assert( false === alynt_core_private( $owner, 'write_safety', array( $table, $a, $raw ) ) && 1 === alynt_core_property( $owner, 'writes' ), 'Exact safety CAS accepted changed ' . $field . '.' );
        } finally { alynt_core_private( $owner, 'finish' ); }
        alynt_core_assert( 'rolled_back' === alynt_core_property( $owner, 'phase' ) && $original === alynt_native_begin_rows( $db ), 'Zero-row CAS was success or changed durable rows.' );
    }
    alynt_core_done( 'genuine_exact_row_id_autoload_binary_value_cas_zero_rows_refuse_and_rollback' );
    foreach ( array( false, true ) as $commit ) {
        $owner = alynt_native_begin_primitive(); $writer = alynt_core_property( $owner, 'reader' );
        try {
            $a = alynt_core_private( $owner, 'row', array( $table, true, false ) );
            alynt_native_begin_set( $owner, 'phase', 'begin_sent' ); $writer->query( 'START TRANSACTION READ WRITE' );
            alynt_native_begin_set( $owner, 'phase', 'transaction_owned' );
            alynt_core_assert( alynt_core_private( $owner, 'metadata', array( $table ) ), 'Primitive metadata missing.' );
            alynt_core_private( $owner, 'row', array( $table, true, true ) ); alynt_core_private( $owner, 'row', array( $table, false, true ) );
            alynt_core_assert( alynt_core_private( $owner, 'write_safety', array( $table, $a, $raw ) ), 'Uncertainty primitive did not update owned safety row.' );
            alynt_native_begin_set( $owner, 'phase', 'commit_sent' );
            if ( $commit ) $writer->query( 'COMMIT AND NO CHAIN NO RELEASE' );
            // Model an unobserved acknowledgement at a genuine physical boundary.
            // This does NOT claim an actual dropped COMMIT response on the wire.
        } finally { alynt_core_private( $owner, 'finish' ); }
        alynt_core_assert( 'poisoned' === alynt_core_property( $owner, 'phase' ) && null === alynt_core_property( $owner, 'reader' ), 'Commit-sent ambiguity was not latched/closed.' );
        $rows = alynt_native_begin_rows( $db );
        alynt_core_assert( $rows['alynt_drime_backups_schedule_safety_state']['value'] === ( $commit ? $raw : $original['alynt_drime_backups_schedule_safety_state']['value'] ) && $rows['alynt_drime_backups_settings'] === $original['alynt_drime_backups_settings'], 'Unknown disposition assumed a single physical outcome.' );
        alynt_core_assert( false === alynt_core_private( $owner, 'begin', array( 'POST', $form ) ) && 1 === alynt_core_property( $owner, 'writes' ), 'Poisoned owner replayed.' );
        alynt_native_seed_rows( array( 'alynt_drime_backups_schedule_safety_state' => json_encode( $eight ) ) );
    }
    alynt_core_done( 'protocol_missing_commit_ack_simulation_on_real_committed_and_uncommitted_sessions_no_replay' );

    $owner = alynt_native_begin_primitive(); $writer = alynt_core_property( $owner, 'reader' );
    try {
        $thread = $writer->thread_id;
        alynt_core_assert( $thread !== $db->thread_id && $thread !== $wpdb->dbh->thread_id, 'Failure victim is not exclusively owned.' );
        alynt_native_begin_set( $owner, 'phase', 'begin_sent' ); $writer->query( 'START TRANSACTION READ WRITE' );
        // Exact owned private session only; never KILL an application connection.
        alynt_core_assert( true === $db->query( 'KILL CONNECTION ' . (int) $thread ), 'Owned connection termination was not acknowledged.' );
        $lost = false;
        try { $result = $writer->query( 'SELECT 1' ); $lost = false === $result; if ( $result instanceof mysqli_result ) $result->free(); }
        catch ( mysqli_sql_exception $error ) { $lost = in_array( $error->getCode(), array( 2006, 2013 ), true ); }
        alynt_core_assert( $lost, 'Owned native connection remained usable after termination.' );
    } finally { alynt_core_private( $owner, 'finish' ); }
    alynt_core_assert( 'poisoned' === alynt_core_property( $owner, 'phase' ) && null === alynt_core_property( $owner, 'reader' ) && $original === alynt_native_begin_rows( $db ), 'Genuine owned disconnect was repaired or retained.' );
    alynt_core_done( 'genuine_owned_connection_termination_begin_uncertainty_poison_no_reconnect' );

    // Revocation after a confirmed commit withholds private success; it must not
    // reclassify the already durable closed event or replay its command.
    $nonce_calls = 0; $token = alynt_core_login(); $form = alynt_native_begin_form( $eight, $settings );
    $revoke = function ( $life ) use ( &$nonce_calls, $token ) { if ( 3 === ++$nonce_calls ) alynt_core_mutate( function () use ( $token ) { WP_Session_Tokens::get_instance( 90000001 )->destroy( $token ); } ); return $life; };
    add_filter( 'nonce_life', $revoke );
    try { $owner = alynt_native_begin_run( $form, false, 8 ); }
    finally { remove_filter( 'nonce_life', $revoke ); }
    $rows = alynt_native_begin_rows( $db );
    alynt_core_assert( 3 === $nonce_calls && 'committed' === alynt_core_property( $owner, 'phase' ) && $original['alynt_drime_backups_schedule_safety_state']['value'] !== $rows['alynt_drime_backups_schedule_safety_state']['value'] && $original['alynt_drime_backups_settings'] === $rows['alynt_drime_backups_settings'], 'Post-commit revocation undid or replayed durable closed begin.' );
    alynt_core_done( 'genuine_postcommit_revocation_denies_without_undo_replay_or_target_write' );
}
