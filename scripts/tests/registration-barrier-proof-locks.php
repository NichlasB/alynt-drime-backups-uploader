<?php
/** Physical lock primitive proof, explicitly separate from genuine coordinator auth. */
function alynt_barrier_locks() {
    global $wpdb;
    list( $o ) = alynt_barrier_fixture(); $core = alynt_core_private( $o, 'frame' ); $auth = $wpdb->dbh;
    $facts = alynt_core_private( $o, 'native_idle', array( $auth ) ); $other = alynt_scan_db(); $table = $core['options'];
    try {
        alynt_core_assert( alynt_core_private( $o, 'open_writer', array( $auth, $facts ) ) && alynt_core_private( $o, 'metadata', array( $table ) ), 'Owned primitive session/metadata setup failed.' );
        $reader = alynt_core_property( $o, 'reader' );
        alynt_core_assert( $reader !== $auth && $reader->thread_id !== $auth->thread_id && $reader->thread_id !== $other->thread_id, 'Lock primitive borrowed another session.' );
        alynt_origin_set( $o, 'phase', 'begin_sent' ); alynt_core_assert( true === $reader->query( 'START TRANSACTION READ WRITE' ) && alynt_core_private( $o, 'session', array( true ) ), 'Owned primitive BEGIN failed.' );
        alynt_origin_set( $o, 'phase', 'transaction_owned' );
        foreach ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) as $role ) alynt_core_assert( is_array( alynt_core_private( $o, 'barrier_row', array( $table, $role ) ) ), 'Fixed locking projection failed.' );
        alynt_core_assert( 10 === alynt_core_property( $o, 'barrier_reads' ) && false === alynt_core_private( $o, 'barrier_row', array( $table, 'safety' ) ), 'Lock sweep escaped ten-read ceiling.' );
        foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $def ) {
            $other->query( 'START TRANSACTION READ WRITE' ); $blocked = false;
            $stmt = $other->prepare( "SELECT option_id FROM `{$table}` FORCE INDEX (option_name) WHERE option_name=? LIMIT 2 FOR UPDATE NOWAIT" );
            try { $stmt->bind_param( 's', $def[0] ); if ( true === $stmt->execute() ) $stmt->get_result()->free(); else $blocked = in_array( $stmt->errno, array( 1205, 3572 ), true ); }
            catch ( mysqli_sql_exception $error ) { $blocked = in_array( $error->getCode(), array( 1205, 3572 ), true ); }
            finally { $stmt->close(); $other->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
            alynt_core_assert( $blocked, 'Each of all ten real row locks must remain held until release.' );
        }
        alynt_core_assert( alynt_core_private( $o, 'barrier_zero_dml' ), 'Physical lock primitive issued DML.' );
        alynt_origin_set( $o, 'phase', 'commit_sent' ); alynt_core_assert( true === $reader->query( 'COMMIT AND NO CHAIN NO RELEASE' ) && alynt_core_private( $o, 'session', array( false ) ), 'Physical primitive COMMIT failed.' );
        alynt_origin_set( $o, 'phase', 'barrier_released' ); alynt_core_private( $o, 'close_barrier' );
        alynt_core_assert( null === alynt_core_property( $o, 'reader' ) && 'barrier_released' === alynt_core_property( $o, 'phase' ), 'Physical primitive close not acknowledged.' );
        $other->query( 'START TRANSACTION READ WRITE' );
        foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $def ) {
            $stmt = $other->prepare( "SELECT option_id FROM `{$table}` FORCE INDEX (option_name) WHERE option_name=? LIMIT 2 FOR UPDATE NOWAIT" );
            $stmt->bind_param( 's', $def[0] ); alynt_core_assert( true === $stmt->execute() && 1 === $stmt->get_result()->num_rows, 'Released lock remained unavailable.' ); $stmt->close();
        }
        $other->query( 'ROLLBACK AND NO CHAIN NO RELEASE' );
        alynt_core_assert( $facts === alynt_core_private( $o, 'native_idle', array( $auth ) ), 'Physical proof altered borrowed auth session.' );
    } finally { alynt_core_private( $o, 'close_barrier' ); $other->close(); }
    alynt_core_done( 'physical_fixed_projection_all_ten_locks_held_and_released_no_dml_primitive_not_auth_proof' );
}
