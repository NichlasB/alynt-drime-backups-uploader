<?php
/** Composed production bodies over API-only core/frame/row/session doubles. */
require_once dirname( __DIR__, 2 ) . '/includes/class-registration-native-admission.php';
final class Alynt_Failure_Core_Db {
    public $dbh;
    public function __construct() { $this->dbh = new mysqli(); }
    public function prepare( $sql, $name ) {
        alynt_failure_check( 'SELECT option_value FROM wp_options WHERE option_name = %s LIMIT 1' === $sql && 'wp_user_roles' === $name, 'fixed role-only query preparation' );
        return "SELECT option_value FROM wp_options WHERE option_name = 'wp_user_roles' LIMIT 1";
    }
}
final class Alynt_Failure_Api_Harness {
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Root;
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Join;
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Qualification { qualify_admission_outcome as private qualify_source; }
    use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Core_Guard;
    use Alynt_Drime_Backups_Uploader_Registration_Construction;
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Candidate;
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Storage;
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Transaction;
    use Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Capture;
    use Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session, Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Session {
        Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Session::open_writer insteadof Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session;
        Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Session::finish insteadof Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session;
        Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session::open_writer as private open_writer_without_outcome;
        Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session::finish as private finish_without_outcome;
    }
    use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;
    const EVENTS = Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::EVENTS;
    private $attempted = false; private $active = false; private $consumed = false; private $started = 0; private $last_clock = 0; private $scope;
    private $reader; private $physical; private $read_physical; private $phase = 'unopened'; private $protected_reads = 0; private $writes = 0;
    public $db; public $core; public $frame_data; public $fault; public $rows = 0; public $samples = array(); public $qualified = 0; public $replay;
    public function __construct( $frame, $fault ) {
        $this->frame_data = $frame; $this->fault = $fault; $this->db = new mysqli();
        $this->core = array( 'db' => new Alynt_Failure_Core_Db(), 'blog' => 1, 'prefix' => 'wp_', 'options' => 'wp_options' );
    }
    public function run( $form, $joined = true, $method = 'POST' ) { return $this->admission_root( $method, $form, $joined ); }
    public function state( $key, ...$v ) { if ( $v ) $this->$key = $v[0]; return $this->$key; }
    private function frame() { if ( 'throw-setup' === $this->fault ) alynt_failure_inject( 'setup' ); return $this->core; }
    private function pin( $core ) { if ( null === $this->scope ) $this->scope = $core; return $core === $this->scope; }
    private function profile() { return true; } // Never real physical/core provenance.
    private function native_idle( $db ) { return $db === $this->core['db']->dbh ? array( 'borrowed' => true ) : array( 'tx_ro' => $db->ro ); }
    private function auth_same( $core, $auth, $facts ) { return $core === $this->core && $auth === $core['db']->dbh && array() === $auth->calls && array( 'borrowed' => true ) === $facts; }
    private function open_reader( $auth, array $facts ) {
        alynt_failure_check( $auth === $this->core['db']->dbh && array() === $auth->calls, 'original borrowed auth identity' );
        $this->reader = $this->native_outcome_started ? $this->db : new mysqli(); return true;
    }
    private function admission_capture( $core ) {
        if ( 'throw-capture' === $this->fault ) alynt_failure_inject( 'capture' );
        $this->phase = 'capturing'; $this->protected_reads = 20; return $this->frame_data;
    }
    private function metadata( $table ) { if ( 'throw-metadata' === $this->fault ) alynt_failure_inject( 'metadata' ); return 'wp_options' === $table; }
    private function policy() { return true; } // Explicit policy/session API doubles.
    private function session( $tx ) {
        $point = array( 'begin_sent' => 'begin', 'commit_sent' => 'commit', 'rollback_sent' => 'rollback' )[$this->phase] ?? '';
        if ( $point && 'throw-after-' . $point . '-ack' === $this->fault ) alynt_failure_inject( 'after-' . $point . '-ack' );
        if ( $point && $point . '-idle' === $this->fault ) return false;
        return ! ( 'no-terminal-after-execute' === $this->fault && 'transaction_owned' === $this->phase && $this->native_outcome['facts']['events']['dml_sent'] );
    }
    private function admission_row( $table, $role, $lock ) {
        alynt_failure_check( 'wp_options' === $table && true === $lock && 'transaction_owned' === $this->phase, 'fixed owned row API' );
        ++$this->rows; ++$this->protected_reads;
        if ( 11 === $this->rows && 'throw-after-dml-ack' === $this->fault ) alynt_failure_inject( 'after-dml-ack' );
        $row = $this->before['rows'][$role];
        if ( $this->rows > 10 && 'ledger' === $role ) { $row['value'] = $this->after; $row['octets'] = (string) strlen( $this->after ); }
        return $row;
    }
    private function qualify_admission_outcome( $pin, $guards ) {
        ++$this->qualified;
        alynt_failure_check( true === $this->native_cleanup_done && true === $this->native_outcome_closed && null === $this->reader && null === $this->native_outcome_writer && null === $this->physical && null === $this->read_physical, 'cleanup seal/empty slots before qualification' );
        if ( 'throw-qualify' === $this->fault ) alynt_failure_inject( 'qualify' );
        if ( 'fault-pin' === $this->fault ) $this->before['rows']['queue']['value'] .= ' ';
        if ( 'fault-id' === $this->fault ) array_pop( $this->admission_ids );
        if ( 'fault-clock' === $this->fault ) $this->started -= 10000000001;
        if ( 'fault-reentry' === $this->fault ) $this->run( array() );
        if ( 'fault-seal' === $this->fault ) $this->native_outcome_closed = false;
        if ( 'fault-slot' === $this->fault ) $this->physical = array( 'remaining' => true );
        $before = $this->native_outcome; $result = $this->qualify_source( $pin, $guards );
        alynt_failure_check( $before === $this->native_outcome, 'qualification cannot reopen immutable capture' );
        $this->replay = $this->qualify_source( $pin, $guards );
        $this->samples[] = array( 'result' => $result, 'capture' => $before, 'guards' => $guards );
        $this->physical = null; $this->native_outcome_closed = true;
        return $result;
    }
}
