<?php
require_once __DIR__ . '/QualificationObservationProjection.php';
/** The only behavioral exception in the source manifest. No IO, callbacks or clocks. */
trait Alynt_Test_Qualification_Observation_Wrapper {
    private $qualification_observation_spent = false;
    private $qualification_observation_interrupted = false;
    private $qualification_observation;
    private function qualify_admission_outcome( $pin, $guards ) {
        $available = false === $this->qualification_observation_spent;
        $this->qualification_observation_spent = true;
        if ( ! $available ) {
            $this->qualification_observation_interrupted = true;
            $this->qualification_observation = null; // Reentry/replay invalidates proof, never alters source return.
            return $this->qualification_source( $pin, $guards );
        }
        $before = null; $returned = false; $result = null;
        try {
            $before = Alynt_Test_Qualification_Observation_Projection::state( true === $this->native_cleanup_done && true === $this->native_outcome_closed, null === $this->reader && null === $this->native_outcome_writer && null === $this->physical && null === $this->read_physical, $this->native_outcome, $this->protected_reads, $this->writes );
        } catch ( Throwable $ignored ) { $before = null; } // Observation failure must not bypass the actual alias.
        try {
            $result = $this->qualification_source( $pin, $guards );
            $returned = true;
            return $result;
        } finally {
            $this->qualification_observation = null;
            try {
                $after = Alynt_Test_Qualification_Observation_Projection::state( true === $this->native_cleanup_done && true === $this->native_outcome_closed, null === $this->reader && null === $this->native_outcome_writer && null === $this->physical && null === $this->read_physical, $this->native_outcome, $this->protected_reads, $this->writes );
                if ( is_array( $before ) && true === $this->qualification_observation_spent && false === $this->qualification_observation_interrupted ) $this->qualification_observation = Alynt_Test_Qualification_Observation_Projection::receipt( $returned, $result, $before, $after );
            } catch ( Throwable $ignored ) { $this->qualification_observation = null; }
        }
    }
}
