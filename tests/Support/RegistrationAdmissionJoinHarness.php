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
    use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Core_Guard;
    use Alynt_Drime_Backups_Uploader_Registration_Construction;
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Candidate;
    use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;
    private $attempted = false; private $active = false; private $consumed = false; private $started = 0; private $last_clock = 0; private $scope;
    private $reader; private $physical; private $read_physical; private $phase = 'unopened';
    private $native_outcome; private $protected_reads = 0; private $writes = 0;
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
        if ( 'writer' === $this->fault ) return false;
        $this->reader = new Alynt_Admission_Join_Connection_Double(); $this->phase = 'idle'; return true;
    }
    private function metadata( $table ) { return 'wp_options' === $table && 'metadata' !== $this->fault; }
    /** API forwarding seam only: NOT actual transaction, DML, ack or physical cleanup proof. */
    private function admission_transaction( $core, $e ) {
        $this->sent_origin = array( $this->admission_origin, $this->admission_ids, $this->admission_clock, $this->proposal );
        ++$this->writes; $this->protected_reads = 40; $this->phase = 'uncertain' === $this->fault ? 'commit_sent' : 'committed';
        return 'uncertain' !== $this->fault;
    }
    private function finish() {
        if ( null !== $this->reader ) { $this->reader->close(); ++$this->closes; }
        $this->reader = null; $this->physical = null;
        if ( 'committed' === $this->phase ) $this->released_origin = $this->admission_origin;
        if ( 'close' === $this->fault ) $this->phase = 'poisoned';
    }
    private function clear_native_outcome() { $this->native_outcome = null; }
}
