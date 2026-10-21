<?php
/** Fixed CLI-only native proof composition. No production import or public root. */
if ( 'cli' !== PHP_SAPI || ! defined( 'ABSPATH' ) ) exit( 1 );
require_once dirname( __DIR__, 2 ) . '/tests/Support/NativeQualificationObservation.php';
final class Alynt_Test_Native_Qualification_No_Fault_Owner {
    use Alynt_Test_Native_Qualification_Observation;
    const EVENTS = Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::EVENTS;
    private function __construct() {}
    public static function create() { return 0 === func_num_args() ? new self() : false; }
    private function native_entry_available() { return true; }
}
