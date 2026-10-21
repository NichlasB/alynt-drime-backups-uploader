<?php
/** Private API doubles ONLY. No WordPress, connection, native IO or storage proof. */
trait Alynt_Test_Same_Root_Lifecycle_Ports {
    public $fixture;
    public function __construct( $fixture ) { $this->fixture = $fixture; }
    public function run( ...$args ) { return $this->lifecycle_root( ...$args ); }
    public function state( $key, ...$value ) { if ( $value ) $this->$key = $value[0]; return $this->$key; }
    private function lifecycle_profile( $core ) { $this->event( 'profile' ); return $core['db']; }
    private function lifecycle_physical( $core, $facts ) { $this->event( 'physical' ); return $core['db'] === $facts && empty( $this->fixture->physical_bad ); }
    private function lifecycle_capture( $core ) { $this->event( 'capture' ); return 'capture' === $this->fixture->fault ? false : $this->fixture->before; }
    private function lifecycle_attempt( $core, $command ) {
        $this->event( 'attempt' );
        $this->fixture->pin = array( $this->admission_origin, $this->admission_ids, $this->admission_clock, $this->proposal );
        $this->fixture->observed = $this->before;
        if ( 'empty' !== $this->fixture->rows ) {
            $this->fixture->observed['rows']['ledger']['value'] = $this->after;
            $this->fixture->observed['rows']['ledger']['octets'] = (string) strlen( $this->after );
        }
        if ( 'unavailable' === $this->fixture->fault ) return false;
        $this->start_native_outcome(); $this->native_outcome_event( 'writer_created' );
        $this->native_outcome_event( 'attempt_reserved' ); $this->native_outcome_send( 'begin' ); $this->native_outcome_ack( 'begin', true );
        $this->native_outcome_event( 'transaction_owned' );
        if ( 'not_written' !== $this->fixture->fault ) { $this->native_outcome_event( 'bind_verified' ); $this->native_outcome_send( 'dml' ); }
        if ( in_array( $this->fixture->fault, array( 'not_written', 'rolled_back' ), true ) ) {
            $this->native_outcome_send( 'rollback' ); $this->native_outcome_ack( 'rollback', true ); $this->native_outcome_event( 'rollback_idle' ); return false;
        }
        $this->native_outcome_ack( 'dml', true ); $this->native_outcome['facts']['affected_rows'] = 'one'; $this->native_outcome_event( 'readback' ); $this->native_outcome_send( 'commit' );
        if ( 'uncertain_throw' === $this->fixture->fault ) throw new RuntimeException( 'Synthetic secret never exported.' );
        if ( 'uncertain' === $this->fixture->fault ) return false;
        $this->native_outcome_ack( 'commit', true ); $this->native_outcome_event( 'commit_idle' ); return true;
    }
    private function lifecycle_release() {
        $this->event( 'release' );
        if ( 'release_throw' === $this->fixture->fault ) throw new RuntimeException( 'Synthetic release secret.' );
        if ( null !== $this->native_outcome && ! $this->native_cleanup_done ) {
            $this->native_outcome_send( 'close' ); $this->native_outcome_ack( 'close', 'close_unknown' !== $this->fixture->fault );
            $this->native_outcome['facts']['handle_remaining'] = false;
            $this->native_cleanup_done = true; $this->native_outcome_closed = true;
        }
        return 'release_false' !== $this->fixture->fault;
    }
    private function lifecycle_read( $core ) {
        $this->event( 'read' );
        if ( 'read_throw' === $this->fixture->fault ) throw new RuntimeException( 'Synthetic read secret.' );
        return 'read_bad' === $this->fixture->fault ? false : $this->fixture->observed;
    }
    private function qualify_admission_outcome( $pin, $guards ) {
        $r = $this->source_qualification( $pin, $guards );
        $this->fixture->qualification = $r;
        return $r;
    }
    private function event( $event ) {
        $this->fixture->events[] = $event;
        if ( null !== $this->fixture->on ) { $on = $this->fixture->on; $on( $event, $this ); }
    }
}
