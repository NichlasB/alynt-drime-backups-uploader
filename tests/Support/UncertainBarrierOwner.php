<?php
/** Definitions only. No installed runtime caller or native-enabled proof entry. */
require_once __DIR__ . '/UncertainBarrierManifest.php';
require_once __DIR__ . '/UncertainBarrierProjection.php';
require_once __DIR__ . '/UncertainBarrierObservation.php';
final class Alynt_Test_Uncertain_Barrier_Owner {
    use Alynt_Test_Uncertain_Barrier_Observation;
    const EVENTS = Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::EVENTS;
    private function __construct() {}
    public static function create() { return 0 === func_num_args() ? new self() : false; }
    private function native_entry_available() { return false; }
}
