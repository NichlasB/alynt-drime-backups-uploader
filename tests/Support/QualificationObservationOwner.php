<?php
/** Fixed proof-only composition. No runtime caller or public observation port. */
require_once dirname( __DIR__, 2 ) . '/includes/class-registration-native-admission.php';
require_once __DIR__ . '/QualificationObservationWrapper.php';
final class Alynt_Test_Qualification_Observation_Owner {
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Root;
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Join;
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Qualification, Alynt_Test_Qualification_Observation_Wrapper {
        Alynt_Test_Qualification_Observation_Wrapper::qualify_admission_outcome insteadof Alynt_Drime_Backups_Uploader_Registration_Admission_Qualification;
        Alynt_Drime_Backups_Uploader_Registration_Admission_Qualification::qualify_admission_outcome as private qualification_source;
    }
    use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Core_Guard;
    use Alynt_Drime_Backups_Uploader_Registration_Construction;
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Candidate;
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Storage;
    use Alynt_Drime_Backups_Uploader_Registration_Admission_Transaction;
    use Alynt_Drime_Backups_Uploader_Native_Observer_Session;
    use Alynt_Drime_Backups_Uploader_Native_Options_Metadata;
    use Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Capture;
    use Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session, Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Session {
        Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Session::open_writer insteadof Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session;
        Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Session::finish insteadof Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session;
        Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session::open_writer as private open_writer_without_outcome;
        Alynt_Drime_Backups_Uploader_Native_Closed_Begin_Session::finish as private finish_without_outcome;
    }
    use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;
    const EVENTS = Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::EVENTS;
    private $attempted = false;
    private $active = false;
    private $consumed = false;
    private $scope;
    private $reader;
    private $physical;
    private $read_physical;
    private $phase = 'unopened';
    private $started = 0;
    private $last_clock = 0;
    private $protected_reads = 0;
    private $writes = 0;
    private function __construct() {}
    public static function create() {
        return 0 === func_num_args() ? new self() : false;
    }
    private function admit( $method, $body ) {
        return $this->admission_root( 2 === func_num_args() ? $method : null, $body, false );
    }
    private function admit_retained( $method, $body ) {
        return $this->admission_root( 2 === func_num_args() ? $method : null, $body, true );
    }
    private function pin( $core ) {
        if ( null === $this->scope ) {
            $this->scope = $core;
        }
        return $this->scope === $core;
    }
}
