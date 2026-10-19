<?php
/** Genuine revocation/physical swap and owned corruption, no competing writers. */
function alynt_barrier_denials() {
    foreach ( array( 6, 8 ) as $phase ) foreach ( array( 'session', 'capability', 'cookie', 'actor', 'method', 'table', 'physical', 'budget', 'reset', 'throw', 'reentry' ) as $fault ) {
        list( $o, $i ) = alynt_barrier_fixture(); $facts = array(); $restore = null;
        $probe = alynt_handoff_probe( $facts, function ( $n, $life, $owner ) use ( $phase, $fault, &$restore, $i ) {
            if ( $n !== $phase ) return $life;
            if ( 'physical' === $fault ) { $GLOBALS['wpdb']->db_connect( false ); }
            elseif ( 'budget' === $fault ) alynt_origin_set( $owner, 'started', hrtime( true ) - 10000000001 );
            elseif ( 'reset' === $fault ) alynt_origin_set( $owner, 'started', hrtime( true ) );
            elseif ( 'throw' === $fault ) throw new RuntimeException( 'Owned native callback failure.' );
            elseif ( 'reentry' === $fault ) alynt_core_private( $owner, 'observe_native', array( $i ) );
            else $restore = alynt_origin_change( $fault );
            return $life;
        } );
        add_filter( 'nonce_life', $probe, 10, 2 );
        try { alynt_barrier_run( $o, $i, false, 8 === $phase ? 10 : 0 ); }
        finally { remove_filter( 'nonce_life', $probe, 10 ); if ( $restore ) $restore(); wp_cache_flush(); $GLOBALS['wp_roles'] = new WP_Roles(); }
        alynt_core_assert( count( $facts ) >= $phase, 'Native late callback control not reached.' );
    }
    alynt_core_done( 'pre_begin_post_close_physical_auth_request_clock_throw_reentry_failures' );
    foreach ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) as $role ) {
        list( $o, $i ) = alynt_barrier_fixture(); $db = alynt_scan_db(); try { $frame = alynt_registration_frame( $db ); } finally { $db->close(); }
        alynt_scan_seed( array( $role => $frame['rows'][$role]['value'] ), 'yes' ); alynt_barrier_run( $o, $i );
    }
    alynt_core_done( 'all_ten_bad_autoload_rows_unavailable_after_close_no_repair' );
    list( $o, $i ) = alynt_barrier_fixture(); alynt_scan_delete( 'queue' ); alynt_barrier_run( $o, $i, false, 7 );
    alynt_core_assert( 'rolled_back' === alynt_core_property( $o, 'phase' ), 'Missing locked row did not acknowledge owned read-transaction rollback.' );
    alynt_core_done( 'missing_seventh_row_rolls_back_only_definitely_owned_read_transaction' );
    list( $o, $i ) = alynt_barrier_fixture(); alynt_scan_seed( array( 'queue' => 'malformed' ) ); alynt_barrier_run( $o, $i );
    list( $o, $i ) = alynt_barrier_fixture(); alynt_barrier_run( $o, $i, 'registered_closed' );
    alynt_core_done( 'malformed_codec_refusal_and_final_positive_recovery' );
}
