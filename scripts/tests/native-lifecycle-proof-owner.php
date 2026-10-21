<?php
/** Fixed CLI proof composition. The sole behavioral difference is its private entry. */
if ( 'cli' !== PHP_SAPI || ! defined( 'ABSPATH' ) ) exit( 1 );
final class Alynt_Test_Native_Lifecycle_Owner {
    use Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Methods;
    const EVENTS = Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::EVENTS;
    private function __construct() {}
    public static function create() { return 0 === func_num_args() ? new self() : false; }
    private function native_entry_available() { return true; }
}
