<?php
/** Actual root/constructor/guard/retention bodies; physical/session/storage APIs doubled. */
require_once dirname( __DIR__, 2 ) . '/includes/class-registration-native-admission.php';
final class Alynt_Admission_Join_Connection_Double {
    public $closed = false;
    public function close() { if ( $this->closed ) throw new LogicException( 'Double close.' ); $this->closed = true; return true; }
}
final class Alynt_Admission_Join_Db_Double {
    public $dbh;
    public function __construct() { $this->dbh = new Alynt_Admission_Join_Connection_Double(); }
    public function prepare( $sql, $role ) {
        if ( 'SELECT option_value FROM wp_options WHERE option_name = %s LIMIT 1' !== $sql || 'wp_user_roles' !== $role ) throw new LogicException( 'Unexpected role SQL.' );
        return "SELECT option_value FROM wp_options WHERE option_name = 'wp_user_roles' LIMIT 1";
    }
}
final class Alynt_Admission_Join_Harness {
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Root;
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Join;
    use Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Capture;
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Qualification { qualify_admission_outcome as private qualify_source; }
    use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Core_Guard;
    use Alynt_Drime_Backups_Uploader_Registration_Construction;
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Candidate;
    use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;
    private $attempted = false; private $active = false; private $consumed = false; private $started = 0; private $last_clock = 0; private $scope;
    private $reader; private $physical; private $read_physical; private $phase = 'unopened';
    private $native_outcome_writer; private $protected_reads = 0; private $writes = 0;
    const EVENTS = Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::EVENTS;
    public $qualifications = array(); public $qualification_fault = ''; public $qualification_replay;
    public $captured; public $core; public $writer_origin; public $sent_origin; public $released_origin; public $root_start; public $closes = 0; public $fault = '';
    public function __construct( $frame ) {
        $this->captured = $frame; $this->core = array( 'db' => new Alynt_Admission_Join_Db_Double(), 'blog' => 1, 'prefix' => 'wp_', 'options' => 'wp_options' );
    }
    public function run( $body, $joined = true, $method = 'POST' ) { return $this->admission_root( $method, $body, $joined ); }
    public function state( $key, ...$value ) { if ( $value ) $this->$key = $value[0]; return $this->$key; }
    private function frame() { return $this->core; }
    private function pin( $core ) { if ( null === $this->scope ) $this->scope = $core; return $core === $this->scope; }
    private function profile() { return true; } // Physical/core provenance deliberately NOT proved here.
    private function native_idle( $auth ) { return $auth === $this->core['db']->dbh ? array( 'profile' => 'API-double-only' ) : false; }
    private function auth_same( $core, $auth, $facts ) { return $core === $this->core && $auth === $core['db']->dbh && false !== $facts && ! $auth->closed; }
    private function open_reader( $auth, $facts ) { $this->root_start = $this->started; $this->reader = new Alynt_Admission_Join_Connection_Double(); return true; }
    private function admission_capture( $core ) { $this->phase = 'capturing'; $this->protected_reads = 20; return 'capture' === $this->fault ? false : $this->captured; }
    private function open_writer( $auth, $facts ) {
        $this->writer_origin = array( $this->admission_origin, $this->admission_ids, $this->admission_clock, $this->proposal );
        $this->start_native_outcome();
        if ( 'writer' === $this->fault ) return false;
        $this->reader = new Alynt_Admission_Join_Connection_Double(); $this->phase = 'idle'; $this->native_outcome_event( 'writer_created' ); return true;
    }
    private function metadata( $table ) { return 'wp_options' === $table && 'metadata' !== $this->fault; }
    /** API forwarding seam only: NOT actual transaction, DML, ack or physical cleanup proof. */
    private function admission_transaction( $core, $e ) {
        $this->sent_origin = array( $this->admission_origin, $this->admission_ids, $this->admission_clock, $this->proposal );
        ++$this->writes; $this->protected_reads = 40;
        // Synthetic call-site observations only; never native transaction evidence.
        $this->native_outcome_event( 'attempt_reserved' ); $this->native_outcome_send( 'begin' ); $this->native_outcome_ack( 'begin', true );
        $this->native_outcome_event( 'transaction_owned' );
        if ( 'not_written' !== $this->fault ) {
            $this->native_outcome_event( 'bind_verified' ); $this->native_outcome_send( 'dml' );
        }
        if ( in_array( $this->fault, array( 'rolled_back', 'not_written' ), true ) ) {
            $this->native_outcome_send( 'rollback' ); $this->native_outcome_ack( 'rollback', true ); $this->native_outcome_event( 'rollback_idle' );
            $this->phase = 'rolled_back'; return false;
        }
        $this->native_outcome_ack( 'dml', true ); $this->native_outcome['facts']['affected_rows'] = 'one'; $this->native_outcome_event( 'readback' );
        $this->native_outcome_send( 'commit' );
        if ( 'uncertain' === $this->fault ) { $this->phase = 'commit_sent'; return false; }
        $this->native_outcome_ack( 'commit', true ); $this->native_outcome_event( 'commit_idle' ); $this->phase = 'committed'; return true;
    }
    private function finish() {
        if ( null !== $this->reader ) {
            if ( null !== $this->native_outcome && ! $this->native_cleanup_done ) $this->native_outcome_send( 'close' );
            $this->reader->close(); ++$this->closes;
            if ( null !== $this->native_outcome && ! $this->native_cleanup_done ) $this->native_outcome_ack( 'close', 'close' !== $this->fault );
        }
        $this->reader = null; $this->physical = null;
        if ( null !== $this->native_outcome ) { $this->native_outcome['facts']['handle_remaining'] = false; $this->native_cleanup_done = true; $this->native_outcome_closed = true; }
        if ( 'committed' === $this->phase ) $this->released_origin = $this->admission_origin;
        if ( 'close' === $this->fault ) $this->phase = 'poisoned';
    }
    /** Instrumentation wraps the unchanged source method, never a favorable replacement. */
    private function qualify_admission_outcome( $pin, $guards ) {
        $saved = array( $this->native_outcome, $this->native_cleanup_done, $this->native_outcome_closed, $this->native_outcome_tick, $this->reader, $this->native_outcome_writer, $this->physical );
        switch ( $this->qualification_fault ) {
            case 'no_capture': $this->native_outcome = null; break;
            case 'invalid': $this->native_outcome['valid'] = false; break;
            case 'unsealed': $this->native_outcome_closed = false; break;
            case 'unclean': $this->native_cleanup_done = false; break;
            case 'reader': $this->reader = true; break;
            case 'writer': $this->native_outcome_writer = true; break;
            case 'physical': $this->physical = array( 'remaining' => true ); break;
            case 'read_physical': $this->read_physical = array( 'remaining' => true ); break;
            case 'clock_regression': $this->last_clock = time() + 1; break;
            case 'raw': $this->native_outcome['raw_returns']['commit'] = false; break;
            case 'tick': ++$this->native_outcome_tick; break;
            case 'history':
                foreach ( array( 'sent', 'ack_at', 'idle' ) as $suffix ) {
                    $this->native_outcome['facts']['events']['rollback_' . $suffix] = $this->native_outcome['facts']['events']['commit_' . $suffix];
                    $this->native_outcome['facts']['events']['commit_' . $suffix] = 0;
                }
                $this->native_outcome['facts']['acks']['commit'] = 'not_sent'; $this->native_outcome['raw_returns']['commit'] = 'not_called';
                $this->native_outcome['facts']['acks']['rollback'] = 'yes'; $this->native_outcome['raw_returns']['rollback'] = true; break;
            case 'missing_release':
                $this->native_outcome['facts']['events']['close_at'] = 0; --$this->native_outcome_tick;
                $this->native_outcome['facts']['acks']['close'] = 'unknown'; $this->native_outcome['raw_returns']['close'] = false; break;
        }
        $before = $this->native_outcome;
        try {
            $result = $this->qualify_source( $pin, $guards );
            $this->qualification_replay = $this->qualify_source( $pin, $guards );
            $this->qualifications[] = array( 'result' => $result, 'before' => $before, 'after' => $this->native_outcome, 'guards' => $guards, 'spent' => $this->admission_qualification_spent );
            return $result;
        } finally {
            list( $this->native_outcome, $this->native_cleanup_done, $this->native_outcome_closed, $this->native_outcome_tick, $this->reader, $this->native_outcome_writer, $this->physical ) = $saved;
            $this->read_physical = null;
        }
    }
}
