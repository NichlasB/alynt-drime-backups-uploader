<?php
require_once __DIR__ . '/NativeLifecycleAuthDb.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-registration-native-lifecycle.php';
/** Actual adapter/guard/transaction/cleanup/qualification; API edges doubled. */
final class Alynt_Test_Native_Lifecycle_Api {
    use Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Methods { qualify_admission_outcome as private source_qualification; }
    const EVENTS = Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::EVENTS;
    public $frame_data; public $core; public $fault; public $dbs = array(); public $events = array(); public $qualified; public $guards = array(); public $on;
    public function __construct( $frame, $fault ) { $this->frame_data = $frame; $this->fault = $fault; $this->core = array( 'db' => new Alynt_Test_Native_Lifecycle_Auth_Db(), 'blog' => 1, 'prefix' => 'wp_', 'options' => 'wp_options' ); }
    public function run( ...$args ) { return $this->lifecycle_root( ...($args ?: array( 'POST', array( 'purpose' => 'register_closed', 'confirm' => 'register', 'nonce' => 'unit-nonce' ) )) ); }
    public function state( $k, ...$v ) { if ( $v ) $this->$k = $v[0]; return $this->$k; }
    private function native_entry_available() { return 'entry' !== $this->fault; }
    private function frame() { return $this->core; } // Only this installation/frame API is doubled.
    private function profile() { $this->event( 'profile' ); return true; }
    private function native_idle( $db ) { return array( 'thread' => (string) spl_object_id( $db ), 'tx_ro' => $db->ro ); }
    private function policy() { return true; } // Physical server policy is not established by these tests.
    private function session( $tx ) {
        if ( ! $this->reader instanceof mysqli ) return false;
        $active = false; foreach ( $this->reader->calls as $call ) { if ( 'begin' === $call ) $active = true; elseif ( in_array( $call, array( 'commit', 'rollback' ), true ) ) $active = false; }
        return $active === $tx;
    }
    private function open_reader( $auth, array $facts ) {
        $kind = $this->native_resource; $this->event( 'open-' . $kind );
        alynt_native_check( $auth === $this->core['db']->dbh && array() === $auth->calls, 'borrowed handle untouched' );
        $this->reader = new mysqli(); $this->dbs[$kind] = $this->reader; $db = $this->reader;
        $db->inspect = function ( $point ) use ( $kind, $db ) { $this->apiPoint( $kind, $db, $point ); };
        $label = 'admission' === $kind ? 'writer' : $kind;
        if ( $label . '-close-false' === $this->fault ) $db->returns['close'] = false;
        if ( $label . '-close-throw' === $this->fault ) $db->throws[] = 'close';
        if ( 'admission' === $kind && in_array( $this->fault, array( 'uncertain', 'absence' ), true ) ) $db->returns['commit'] = false;
        if ( 'admission' === $kind && 'uncertain_throw' === $this->fault ) $db->throws[] = 'commit';
        if ( 'admission' === $kind && 'write-false' === $this->fault ) $db->returns['execute'] = false;
        if ( 'barrier' === $kind && 'barrier-commit-false' === $this->fault ) $db->returns['commit'] = false;
        if ( $label . '-open-throw' === $this->fault ) throw new RuntimeException( 'Expected isolated factory fault.' );
        return $label . '-open-false' !== $this->fault;
    }
    private function metadata( $table ) {
        $this->event( 'metadata-' . $this->native_resource ); alynt_native_check( 'wp_options' === $table, 'fixed metadata table' );
        return ! ( 'barrier' === $this->native_resource && ( ( 'metadata-first' === $this->fault && 0 === $this->barrier_metadata_checks ) || ( 'metadata-last' === $this->fault && 1 === $this->barrier_metadata_checks ) ) );
    }
    private function admission_row( $table, $role, $lock ) {
        alynt_native_check( 'wp_options' === $table && $this->protected_reads < 40, 'fixed admission projection cap' );
        ++$this->protected_reads; $this->event( 'row-' . $this->native_resource );
        alynt_native_check( $lock ? 'transaction_owned' === $this->phase : 'capturing' === $this->phase, 'projection phase' );
        if ( ( 'capture-row' === $this->fault && 1 === $this->protected_reads ) || ( 'compare-row' === $this->fault && 21 === $this->protected_reads ) || ( 'readback-row' === $this->fault && 31 === $this->protected_reads ) ) return false;
        $row = $this->frame_data['rows'][$role];
        if ( $this->protected_reads > 30 && 'ledger' === $role ) { $row['value'] = $this->after; $row['octets'] = (string) strlen( $this->after ); }
        return $row;
    }
    private function barrier_row( $table, $role ) {
        alynt_native_check( 'wp_options' === $table && 'transaction_owned' === $this->phase && ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() )[$this->barrier_reads] ?? null ) === $role, 'fixed safety-first barrier projection' );
        ++$this->barrier_reads; $this->event( 'barrier-row' );
        if ( 'barrier-row' === $this->fault ) return false;
        $row = $this->frame_data['rows'][$role];
        if ( 'ledger' === $role && 'absence' !== $this->fault ) { $row['value'] = $this->observed_ledger; $row['octets'] = (string) strlen( $row['value'] ); }
        return $row;
    }
    private function barrier_zero_dml() {
        $this->event( 'zero' ); ++$this->zero_dml_checks;
        return ! ( ( 'zero-first' === $this->fault && 1 === $this->zero_dml_checks ) || ( 'zero-last' === $this->fault && 2 === $this->zero_dml_checks ) );
    }
    public $observed_ledger;
    private function qualify_admission_outcome( $pin, $guards ) { $this->qualified = $this->source_qualification( $pin, $guards ); return $this->qualified; }
    private function apiPoint( $kind, $db, $point ) {
        $this->event( $kind . '-' . $point );
        if ( in_array( $point, array( 'begin', 'execute', 'commit', 'rollback' ), true ) ) {
            alynt_native_check( ( 'admission' === $kind ? 10 : 22 ) === count( $this->guards ), 'no core callbacks under locks and exact pre-BEGIN guards' );
        }
        if ( 'admission' === $kind && 'commit' === $point ) $this->observed_ledger = $this->after;
        if ( 'close' === $point ) {
            alynt_native_check( null === $this->reader && null === $this->native_handle, 'detach before close' );
            if ( 'admission' === $kind ) alynt_native_check( null === $this->native_outcome_writer, 'admission writer detached before close' );
        }
    }
    private function event( $event ) { $this->events[] = $event; if ( $this->on ) ($this->on)( $event, $this ); }
}
