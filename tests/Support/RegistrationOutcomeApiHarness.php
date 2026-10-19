<?php
/** Same production trait bodies; all native/session/row/deadline APIs are doubles. */
$root = dirname( __DIR__, 2 ) . '/includes/';
foreach ( array( 'trait-registration-native-outcome-capture', 'trait-registration-native-outcome-session', 'trait-native-closed-begin-session', 'trait-registration-admission-storage', 'trait-registration-admission-transaction' ) as $part ) require_once $root . $part . '.php';
final class Alynt_Outcome_Api_Harness {
    use Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Capture;
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Storage;
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Transaction;
    use Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session, Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Session {
        Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Session::open_writer insteadof Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session;
        Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Session::finish insteadof Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session;
        Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session::open_writer as private open_writer_without_outcome;
        Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session::finish as private finish_without_outcome;
    }
    private $reader; private $physical; private $phase = 'unopened'; private $before; private $after = 'after'; private $proposal;
    private $writes = 0; private $protected_reads = 0; private $read_physical;
    public $db; public $auth; public $fault; public $row_calls = array();
    public function __construct( $fault = '' ) {
        $this->fault = $fault; $this->db = new mysqli(); $this->auth = new mysqli();
        $rows = array(); foreach ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) as $role ) $rows[$role] = array( 'option_id' => '1', 'autoload' => 'no', 'value' => 'before-' . $role, 'octets' => (string) strlen( 'before-' . $role ) );
        $this->before = array( 'rows' => $rows ); $this->proposal = array( 'command' => array( 'expires_at' => 500 ) );
    }
    public function call( $method, ...$args ) { return $this->$method( ...$args ); }
    public function state( $name ) { return $this->$name; }
    private function open_reader( $auth, array $before ) {
        if ( $auth !== $this->auth || array( 'original' => true ) !== $before ) throw new LogicException( 'Borrowed identity changed.' );
        if ( 'never_created' === $this->fault ) return false;
        $this->reader = $this->db; if ( 'open_throw' === $this->fault ) throw new RuntimeException( 'Synthetic open interruption.' ); return true;
    }
    private function native_idle( $db ) { return array( 'tx_ro' => $db->ro ); }
    private function policy() { return true; }
    private function session( $transaction ) {
        if ( 'begin_idle' === $this->fault && 'begin_sent' === $this->phase ) return false;
        if ( 'commit_idle' === $this->fault && 'commit_sent' === $this->phase ) return false;
        if ( 'rollback_idle' === $this->fault && 'rollback_sent' === $this->phase ) return false;
        if ( 'post_bind_session' === $this->fault && $this->native_outcome['facts']['events']['bind_verified'] && ! $this->native_outcome['facts']['events']['dml_sent'] ) return false;
        return true;
    }
    private function budget( $e ) { return ! ('post_bind_budget' === $this->fault && $this->native_outcome['facts']['events']['bind_verified'] && ! $this->native_outcome['facts']['events']['dml_sent']); }
    private function metadata( $table ) { return 'wp_options' === $table; }
    private function admission_row( $table, $role, $lock ) {
        if ( 'wp_options' !== $table || true !== $lock ) throw new LogicException( 'Unexpected projection.' );
        $this->row_calls[] = $role; $this->protected_reads++;
        if ( count( $this->row_calls ) > 10 && $this->fault === 'readback:' . $role ) return false;
        $row = $this->before['rows'][$role];
        if ( count( $this->row_calls ) > 10 && 'ledger' === $role ) { $row['value'] = $this->after; $row['octets'] = (string) strlen( $this->after ); }
        return $row;
    }
}
