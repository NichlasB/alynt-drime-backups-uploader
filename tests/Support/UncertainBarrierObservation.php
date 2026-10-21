<?php
/** Passive test-only terminal capture. Original root return/throw is never replaced. */
require_once __DIR__ . '/NativeQualificationObservation.php';
trait Alynt_Test_Uncertain_Barrier_Observation {
    use Alynt_Test_Native_Qualification_Observation { lifecycle_root as private terminal_source; }
    private $terminal_busy = false;
    private $terminal_spent = false;
    private $terminal_failed = false;
    private $terminal_taken = false;
    private $terminal_event;
    private function lifecycle_root( $method, $body ) {
        $available = ! $this->terminal_busy && ! $this->terminal_spent;
        if ( ! $available ) $this->terminal_failed = true;
        $this->terminal_busy = true; $this->terminal_spent = true;
        $returned = false; $result = null;
        try {
            $result = $this->terminal_source( ...func_get_args() );
            $returned = true;
            return $result;
        } finally {
            $this->terminal_event = null;
            try {
                if ( $available && ! $this->terminal_failed ) {
                    // Primitive projection only: no error text, handles, callbacks, clocks or IO.
                    $this->terminal_event = Alynt_Test_Uncertain_Barrier_Projection::capture(
                        $returned, $result, $this->phase, $this->barrier_receipt,
                        $this->protected_reads, $this->barrier_reads, $this->writes,
                        $this->barrier_metadata_checks, $this->zero_dml_checks
                    );
                }
            } catch ( Throwable $ignored ) { $this->terminal_failed = true; }
            $this->terminal_busy = false;
        }
    }
    private function take_uncertain_barrier_observation() {
        if ( $this->terminal_taken ) return false;
        $this->terminal_taken = true; $event = $this->terminal_event; $this->terminal_event = null;
        $qualified = $this->take_native_qualification_observation(); // Original erasure/factory gate, consumed even on refusal.
        return 0 === func_num_args() && Alynt_Test_Uncertain_Barrier_Manifest::verify() && Alynt_Test_Uncertain_Barrier_Manifest::composition() &&
            ! $this->terminal_busy && ! $this->terminal_failed && $this->terminal_spent &&
            is_array( $event ) && is_array( $qualified ) && true === $qualified['factory_observation_valid'] ?
            array( 'terminal' => $event, 'qualification' => $qualified['observation'] ) : false;
    }
}
