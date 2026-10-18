<?php
/** CLI-only synthetic closed state, built entirely in memory before owned seeding. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) || ! defined( 'SHORTINIT' ) || SHORTINIT ) exit( 1 );
$repo = dirname( __DIR__, 2 );
foreach ( array( 'class-schedule-safety-option-cas', 'trait-schedule-safety-state-validation', 'trait-schedule-safety-state-transitions', 'class-schedule-safety-store', 'trait-cadence-authority-validation', 'trait-cadence-authority-mutations', 'trait-cadence-authority-dispatch', 'class-cadence-authority-store', 'trait-cadence-authority-lifecycle-preparation', 'class-cadence-authority-lifecycle-store', 'trait-consent-ledger-validation', 'trait-consent-ledger-commands', 'trait-consent-ledger-migration', 'trait-consent-ledger-api-boundary', 'class-cadence-consent-ledger', 'trait-closed-consent-provenance-validation', 'class-closed-consent-provenance-store', 'trait-closed-writer-barrier-records', 'trait-closed-writer-barrier-validation', 'trait-closed-writer-barrier-transitions', 'trait-closed-writer-barrier-commands', 'class-closed-writer-barrier-store', 'class-closed-writer-admission-contract', 'class-closed-writer-admission-snapshot', 'class-closed-writer-admission-projection', 'class-serialized-settings-parser', 'class-serialized-settings-target', 'class-real-closed-writer-data', 'class-real-closed-writer-model', 'class-real-closed-writer-native-observer' ) as $part ) require_once $repo . '/includes/' . $part . '.php';

/** No WordPress operations: synthetic genesis is not authenticated provenance. */
final class Alynt_Native_Observation_Memory extends Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS {
    private $row;
    public function __construct() {}
    public function read( $name ) { return self::STATE_OPTION === $name ? $this->row : null; }
    public function create( $value ) { if ( null !== $this->row ) return false; $this->row = array( 'option_value' => $value, 'autoload' => 'no' ); return true; }
    public function swap( $name, $expected, $value ) { if ( self::STATE_OPTION !== $name || null === $this->row || $this->row['option_value'] !== $expected ) return false; $this->row['option_value'] = $value; return true; }
}
function alynt_native_six() {
    $memory = new Alynt_Native_Observation_Memory(); $now = time(); $clock = function () use ( $now ) { return $now; }; $binding = str_repeat( 'a', 64 ); $site = '11111111-1111-4111-8111-111111111111';
    $lifecycle = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Lifecycle_Store( $memory, $clock );
    $s = $lifecycle->prepare_lifecycle( $binding, 'every_15_minutes', $now + 900 ); alynt_core_assert( is_array( $s ), 'Synthetic lifecycle genesis failed.' );
    $closed = new Alynt_Drime_Backups_Uploader_Closed_Consent_Provenance_Store( $memory, $site, $clock, true );
    $s = $closed->prepare_closed( $binding, $s['lifecycle']['permission_generation'], function () { return true; } ); alynt_core_assert( is_array( $s ), 'Synthetic closed checkpoint failed.' );
    $barrier = new Alynt_Drime_Backups_Uploader_Closed_Writer_Barrier_Store( $memory, $site, $clock, true );
    $s = $barrier->prepare_closed( $binding, $s['lifecycle']['permission_generation'], function () { return true; } );
    alynt_core_assert( is_array( $s ) && 6 === $s['schema_version'], 'Synthetic closed schema six failed.' ); return $s;
}
/** Fixed owned-table-only native fixture writes; never a source injection port. */
function alynt_native_seed_rows( array $values, $autoload = 'no' ) {
    global $wpdb;
    $prefix = $GLOBALS['alynt_core_proof']['prefix'];
    alynt_core_check( $wpdb->options === $prefix . 'options' && 1 === preg_match( '/^wp_alynt_core_probe_[a-f0-9]{12}_$/D', $prefix ), 'Owned native fixture target mismatch.' );
    $db = new mysqli( '127.0.0.1', 'root', getenv( 'MYSQL_PWD' ), 'local', (int) $GLOBALS['argv'][2] ); $db->set_charset( 'utf8mb4' );
    try {
        alynt_core_check( $GLOBALS['argv'][4] === $db->query( "SELECT option_value FROM {$prefix}options WHERE option_name='probe_owner'" )->fetch_row()[0], 'Native fixture ownership marker mismatch.' );
        alynt_core_check( in_array( $autoload, array( 'no', 'off', 'yes' ), true ), 'Unplanned fixture metadata refused.' );
        $stmt = $db->prepare( "INSERT INTO {$prefix}options(option_name,option_value,autoload) VALUES(?,?,?) ON DUPLICATE KEY UPDATE option_value=VALUES(option_value),autoload=VALUES(autoload)" );
        foreach ( $values as $name => $raw ) { alynt_core_check( in_array( $name, array( 'alynt_drime_backups_settings', 'alynt_drime_backups_schedule_safety_state' ), true ) && is_string( $raw ), 'Nonfixture native row refused.' ); $stmt->bind_param( 'sss', $name, $raw, $autoload ); $stmt->execute(); }
        $stmt->close();
    } finally { $db->close(); }
}
function alynt_native_observe( $form, $expected_reads, $positive = false ) {
    $owner = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Native_Observer::create();
    $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { $value = alynt_core_private( $owner, 'observe', array( 'POST', $form ) ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; }
    alynt_core_assert( $positive ? $value instanceof Alynt_Drime_Backups_Uploader_Closed_Writer_Observation_Contract : false === $value, 'Native private observation decision differed.' );
    alynt_core_assert( $expected_reads === alynt_core_property( $owner, 'protected_reads' ), 'Native protected-read ordering/count differed.' );
    alynt_core_assert( null === alynt_core_property( $owner, 'reader' ) && false === alynt_core_property( $owner, 'active' ), 'Owned native lifetime escaped.' );
    return $value;
}
