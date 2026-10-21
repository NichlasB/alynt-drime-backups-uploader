<?php
/** Passive test-only seams; original root, qualification and factory stay authoritative. */
require_once dirname( __DIR__, 2 ) . '/includes/class-registration-native-lifecycle.php';
require_once __DIR__ . '/QualificationObservationWrapper.php';
trait Alynt_Test_Native_Qualification_Observation {
    use Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Methods, Alynt_Test_Qualification_Observation_Wrapper {
        Alynt_Test_Qualification_Observation_Wrapper::qualify_admission_outcome insteadof Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Methods;
        Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Methods::qualify_admission_outcome as private qualification_source;
        Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Methods::open_lifecycle_session as private session_source;
    }
    private $observed_sessions = array();
    private $session_observation_busy = false;
    private $session_observation_failed = false;
    private $observation_taken = false;
    private function open_lifecycle_session( $writer ) {
        $phase = $this->native_resource;
        $available = ! $this->session_observation_busy && ! $this->session_observation_failed && ! $this->observation_taken;
        if ( ! $available ) $this->session_observation_failed = true;
        $this->session_observation_busy = true;
        $returned = false; $result = null;
        try {
            $result = $this->session_source( $writer ); // Exactly once, including invalid observation context.
            $returned = true;
            return $result;
        } finally {
            try {
                if ( ! $available || ! $returned || true !== $result || $this->session_observation_failed || $phase !== $this->native_resource ||
                    ! in_array( $phase, array( 'capture', 'barrier' ), true ) || $writer !== ( 'barrier' === $phase ) ||
                    array_keys( $this->observed_sessions ) !== ( 'capture' === $phase ? array() : array( 'capture' ) ) ||
                    ! $this->reader instanceof mysqli || 'mysqli' !== get_class( $this->reader ) ) {
                    $this->session_observation_failed = true;
                } else {
                    $id = $this->reader->thread_id; // Native property only; subclasses/accessor objects refused above.
                    if ( ! is_int( $id ) || $id < 1 || $id > 4294967295 || in_array( $id, $this->observed_sessions, true ) ) $this->session_observation_failed = true;
                    else $this->observed_sessions[$phase] = $id;
                }
            } catch ( Throwable $ignored ) { $this->session_observation_failed = true; } // Never replaces the original return/throw.
            $this->session_observation_busy = false;
            if ( $this->session_observation_failed ) $this->observed_sessions = array();
        }
    }
    private function take_native_qualification_observation() {
        if ( $this->observation_taken ) return false;
        $this->observation_taken = true;
        $receipt = $this->qualification_observation; $sessions = $this->observed_sessions;
        $this->qualification_observation = null; $this->observed_sessions = array();
        if ( 0 !== func_num_args() || ! Alynt_Test_Native_Qualification_Manifest::verify() || ! Alynt_Test_Native_Qualification_Manifest::composition() ||
            ! $this->root_attempted || $this->busy || $this->active || $this->admission_join_busy || $this->session_observation_busy ||
            ! $this->qualification_observation_spent || $this->qualification_observation_interrupted || $this->interrupted || $this->admission_join_interrupted ) return false;
        foreach ( array( 'native_resource', 'native_handle', 'reader', 'native_outcome_writer', 'physical', 'read_physical', 'barrier_auth', 'barrier_auth_facts', 'origin', 'origin_data', 'scope', 'started', 'lifecycle_clock', 'read_started', 'before', 'proposal', 'context', 'after', 'recorded_at', 'admission_origin', 'admission_clock', 'native_outcome', 'staged', 'inflight' ) as $k ) if ( null !== $this->$k ) return false;
        foreach ( array( 'used', 'admission_ids', 'fences' ) as $k ) if ( array() !== $this->$k ) return false;
        return array( 'observation' => $receipt, 'sessions' => $sessions, 'factory_observation_valid' => ! $this->session_observation_failed && array_keys( $sessions ) === array( 'capture', 'barrier' ) );
    }
}
