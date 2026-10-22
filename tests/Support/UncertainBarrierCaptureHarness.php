<?php
/** Test-only adapter around the ACTUAL production barrier loop, not native proof. */
final class Alynt_Test_Barrier_Capture_Harness {
    use Alynt_Drime_Backups_Uploader_Registration_Observation_Native_Barrier;
    public $events = array(); private $fault; private $reader; private $calls = array();
    public function __construct( $fault ) { $this->fault = $fault; $this->phase = 'idle' === $fault ? 'closed' : 'idle'; $this->barrier_metadata_checks = 1; $this->reader = new Alynt_Test_Barrier_Capture_Query( $this ); }
    public function hit( $name ) { $this->events[] = $name; return $name !== $this->fault; }
    public function run() { try { return $this->barrier_capture( array( 'options'=>'wp_options','blog'=>1,'prefix'=>'wp_' ), 1, array() ); } catch ( RuntimeException $e ) { if ( 'row_throw' !== $this->fault ) throw $e; return false; } }
    public function reads() { return $this->barrier_reads; }
    public function phase() { return $this->phase; }
    private function next( $name ) { $this->calls[$name] = 1 + ( $this->calls[$name] ?? 0 ); return $this->hit( $name . '_' . $this->calls[$name] ); }
    private function session( $tx ) { return $this->next( 'session' ); }
    private function barrier_budget( $outer, $e ) { return $this->next( 'budget' ); }
    private function barrier_zero_dml() { if ( ! $this->next( 'zero' ) ) return false; ++$this->zero_dml_checks; return true; }
    private function barrier_metadata( $table ) { if ( ! $this->hit( 'metadata' ) ) return false; ++$this->barrier_metadata_checks; return true; }
    private function barrier_row( $table, $role ) { ++$this->barrier_reads; $this->hit( 'row:' . $role ); if ( 'row_throw' === $this->fault ) throw new RuntimeException( 'Synthetic row failure.' ); return 'row' === $this->fault ? false : array( 'synthetic'=>true ); }
}
final class Alynt_Test_Barrier_Capture_Query {
    private $owner;
    public function __construct( $o ) { $this->owner = $o; }
    public function query( $sql ) { return $this->owner->hit( $sql ) && ( 'START TRANSACTION READ WRITE' !== $sql || $this->owner->hit( 'begin' ) ); }
}
