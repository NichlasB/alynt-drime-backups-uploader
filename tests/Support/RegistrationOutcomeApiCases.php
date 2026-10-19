<?php
/** Falsifiable source-seam API cases; never actual auth/session/storage evidence. */
function alynt_outcome_api_cases( $group ) {
    if ( 'commit' === $group ) {
        list( $o, $ok ) = alynt_outcome_run(); $c = alynt_outcome_snapshot( $o );
        alynt_outcome_api_check( true === $ok && 'committed' === $c['history'], 'positive known commit' );
        foreach ( array( 'begin', 'dml', 'commit', 'close' ) as $key ) alynt_outcome_api_check( true === $c['raw_returns'][$key] && 'yes' === $c['facts']['acks'][$key], $key . ' raw acknowledgement' );
        alynt_outcome_api_check( 1 === $o->state( 'writes' ) && 'one' === $c['facts']['affected_rows'] && false === $c['facts']['handle_remaining'], 'one CAS and released client claim' );
        $before = $o->state( 'native_outcome' ); $calls = $o->db->calls; $o->call( 'finish' );
        alynt_outcome_api_check( $before === $o->state( 'native_outcome' ) && $calls === $o->db->calls, 'second cleanup cannot promote or repeat' );
    } elseif ( 'ambiguous' === $group ) {
        foreach ( array( 'begin', 'commit' ) as $point ) foreach ( array( false, 'throw', 'idle' ) as $mode ) {
            list( $o, $ok ) = alynt_outcome_run( 'idle' === $mode ? $point . '_idle' : '', false === $mode ? array( $point => false ) : array(), 'throw' === $mode ? array( $point ) : array() ); $c = alynt_outcome_snapshot( $o );
            alynt_outcome_api_check( false === $ok && ('begin' === $point ? 'unsupported' : 'uncertain') === $c['history'], $point . ' ambiguity not favorable' );
            alynt_outcome_api_check( ('idle' === $mode ? true : (false === $mode ? false : 'unknown')) === $c['raw_returns'][$point], 'raw return separate' );
            alynt_outcome_api_check( ! in_array( 'rollback', $o->db->calls, true ), 'no retry after ambiguous command' );
            alynt_outcome_api_check( 0 === $c['facts']['events'][$point === 'begin' ? 'transaction_owned' : 'commit_idle'], 'ack not ownership/idle' );
        }
    } elseif ( 'write_failures' === $group ) {
        foreach ( array( 'prepare', 'bind', 'execute' ) as $point ) foreach ( array( false, 'throw' ) as $mode ) {
            list( $o, $ok ) = alynt_outcome_run( '', false === $mode ? array( $point => false ) : array(), 'throw' === $mode ? array( $point ) : array() ); $c = alynt_outcome_snapshot( $o );
            alynt_outcome_api_check( false === $ok && 1 === $o->state( 'writes' ), 'failed prepare/bind consumes attempt' );
            alynt_outcome_api_check( ('execute' === $point ? 'rolled_back' : 'not_written') === $c['history'], 'attempt not DML marker' );
            alynt_outcome_api_check( ('execute' === $point) === ($c['facts']['events']['dml_sent'] > 0), 'exact execute send site' );
            alynt_outcome_api_check( ! in_array( 'commit', $o->db->calls, true ), 'failed write cannot commit' );
        }
        foreach ( array( 'post_bind_session', 'post_bind_budget' ) as $fault ) {
            list( $o, $ok ) = alynt_outcome_run( $fault ); $c = alynt_outcome_snapshot( $o );
            alynt_outcome_api_check( false === $ok && 0 === $c['facts']['events']['dml_sent'] && ! in_array( 'execute', $o->db->calls, true ), 'final pre-execute gate' );
        }
        list( $o, $ok ) = alynt_outcome_run( '', array(), array( 'statement_close' ) ); $c = alynt_outcome_snapshot( $o );
        alynt_outcome_api_check( false === $ok && 'rolled_back' === $c['history'] && true === $c['raw_returns']['dml'] && 0 === $c['facts']['events']['readback'], 'late statement cleanup failure retains raw execute ack' );
    } elseif ( 'rollback' === $group ) {
        foreach ( array( false, 'throw', 'idle', 'yes' ) as $mode ) {
            list( $o, $ok ) = alynt_outcome_run( 'idle' === $mode ? 'rollback_idle' : '', array( 'execute' => false ) + (false === $mode ? array( 'rollback' => false ) : array()), 'throw' === $mode ? array( 'rollback' ) : array() ); $c = alynt_outcome_snapshot( $o );
            alynt_outcome_api_check( false === $ok && ('yes' === $mode ? 'rolled_back' : 'unsupported') === $c['history'], 'rollback acknowledgement and idle separate' );
            alynt_outcome_api_check( 1 === count( array_keys( $o->db->calls, 'rollback', true ) ), 'one rollback only' );
        }
        foreach ( array( 0, 2 ) as $rows ) { list( $o, $ok ) = alynt_outcome_run( '', array( 'rows' => $rows ) ); $c = alynt_outcome_snapshot( $o ); alynt_outcome_api_check( false === $ok && 'rolled_back' === $c['history'] && ($rows === 0 ? 'zero' : 'other') === $c['facts']['affected_rows'], 'exact affected rows' ); }
    } elseif ( 'cleanup' === $group ) {
        foreach ( array( false, 'throw' ) as $mode ) {
            list( $o, $ok ) = alynt_outcome_run( '', false === $mode ? array( 'close' => false ) : array(), 'throw' === $mode ? array( 'close' ) : array() ); $c = alynt_outcome_snapshot( $o );
            alynt_outcome_api_check( 'committed' === $c['history'] && 'poisoned' === $o->state( 'phase' ), 'close failure cannot rewrite commit' );
            alynt_outcome_api_check( 'unknown' === $c['facts']['acks']['close'] && 0 === $c['facts']['events']['close_at'], 'null handle without positive close not released' );
        }
        foreach ( array( 'never_created', 'open_throw' ) as $fault ) {
            list( $o, $ok ) = alynt_outcome_run( $fault ); $c = alynt_outcome_snapshot( $o );
            alynt_outcome_api_check( false === $ok && 'not_written' === $c['history'], 'factory failure history' );
            alynt_outcome_api_check( ('open_throw' === $fault) === ($c['facts']['events']['writer_created'] > 0), 'created versus never-created explicit' );
            alynt_outcome_api_check( ('open_throw' === $fault) === in_array( 'close', $o->db->calls, true ), 'only created handle closed' );
        }
    } elseif ( 'readback' === $group ) {
        foreach ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) as $role ) {
            list( $o, $ok ) = alynt_outcome_run( 'readback:' . $role ); $c = alynt_outcome_snapshot( $o );
            alynt_outcome_api_check( false === $ok && 'rolled_back' === $c['history'] && 0 === $c['facts']['events']['readback'] && ! in_array( 'commit', $o->db->calls, true ), 'each of ten readbacks required' );
        }
    } elseif ( 'lifetime' === $group ) {
        foreach ( array( 'duplicate', 'unknown_event', 'ceiling', 'restart', 'late_ack', 'late_send' ) as $fault ) {
            if ( in_array( $fault, array( 'late_ack', 'late_send' ), true ) ) list( $o ) = alynt_outcome_run();
            else { $o = new Alynt_Outcome_Api_Harness(); $o->call( 'open_writer', $o->auth, array( 'original' => true ) ); }
            if ( 'duplicate' === $fault ) $o->call( 'native_outcome_event', 'writer_created' );
            elseif ( 'unknown_event' === $fault ) $o->call( 'native_outcome_event', array() );
            elseif ( 'ceiling' === $fault ) { $p = new ReflectionProperty( $o, 'native_outcome_tick' ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $p->setValue( $o, 32 ); $o->call( 'native_outcome_event', 'accepted_at' ); }
            elseif ( 'late_ack' === $fault ) $o->call( 'native_outcome_ack', 'close', true );
            elseif ( 'late_send' === $fault ) $o->call( 'native_outcome_send', 'rollback' );
            else alynt_outcome_api_check( false === $o->call( 'start_native_outcome' ), 'no reopen' );
            alynt_outcome_api_check( false === $o->state( 'native_outcome' )['valid'], 'permanent capture refusal' );
            $o->call( 'finish' );
            $o->call( 'clear_native_outcome' ); alynt_outcome_api_check( null === $o->state( 'native_outcome' ) && 0 === $o->state( 'native_outcome_tick' ) && true === $o->state( 'native_outcome_started' ), 'erase without latch reset' );
            alynt_outcome_api_check( false === $o->call( 'start_native_outcome' ) && null === $o->state( 'native_outcome' ), 'erased capture cannot revive' );
        }
        list( $o ) = alynt_outcome_run( '', array(), array( 'commit' ) ); $before = $o->state( 'native_outcome' );
        $o->call( 'native_outcome_ack', 'commit', true ); $after = $o->state( 'native_outcome' );
        alynt_outcome_api_check( false === $after['valid'] && $before['raw_returns'] === $after['raw_returns'] && $before['facts'] === $after['facts'], 'late unknown cannot become acknowledged' );
        $o = new Alynt_Outcome_Api_Harness(); $o->call( 'clear_native_outcome' );
        alynt_outcome_api_check( false === $o->call( 'start_native_outcome' ) && null === $o->state( 'native_outcome' ), 'early erased capture consumed' );
    } elseif ( 'ownership' === $group ) {
        foreach ( array( 'auth', 'foreign' ) as $swap ) {
            $o = new Alynt_Outcome_Api_Harness(); $o->call( 'open_writer', $o->auth, array( 'original' => true ) );
            $other = 'auth' === $swap ? $o->auth : new mysqli(); $p = new ReflectionProperty( $o, 'reader' ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $p->setValue( $o, $other );
            $p = new ReflectionProperty( $o, 'phase' ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $p->setValue( $o, 'transaction_owned' );
            $o->call( 'finish' );
            alynt_outcome_api_check( false === $o->state( 'native_outcome' )['valid'] && array() === $other->calls && 1 === count( array_keys( $o->db->calls, 'close', true ) ), 'close original owned writer, never substituted/borrowed handle' );
            alynt_outcome_api_check( null === $o->state( 'reader' ) && null === $o->state( 'native_outcome_writer' ), 'no ownership slot survives cleanup' );
        }
        $o = new Alynt_Outcome_Api_Harness(); $o->call( 'open_writer', $o->auth, array( 'original' => true ) );
        $o->db->inspect = function( $point ) use ( $o ) { if ( 'close' === $point ) $o->call( 'finish' ); };
        $o->call( 'finish' );
        alynt_outcome_api_check( false === $o->state( 'native_outcome' )['valid'] && 1 === count( array_keys( $o->db->calls, 'close', true ) ), 'in-flight cleanup reentry poisons without repeated close' );
    }
}
