<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/class-scan-observation-request.php';
require_once dirname( __DIR__ ) . '/includes/class-scan-observation-data.php';
require_once __DIR__ . '/Support/ClosedScanLedgerFixture.php';
class ScanObservationContractTest extends TestCase {
    use Alynt_Test_Closed_Scan_Ledger_Fixture;
    private function form() { return array( 'contract' => 'scan_effect_observation_v1', 'purpose' => 'observe_closed_manifest', 'command_id' => $this->uuid( 1 ), 'site_uuid' => $this->uuid( 9999 ), 'issued_at' => '100', 'expires_at' => '400', 'confirm' => 'observe', 'nonce' => 'not-authentication' ); }
    private function frame() {
        $rows = array(); $n = 0;
        foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $role => $def ) $rows[$role] = array( 'option_id' => (string) ++$n, 'option_name' => $def[0], 'autoload' => 'no', 'octets' => '6', 'value' => 'a:0:{}' );
        $values = array( 'safety' => json_encode( array( 'enrollment_binding' => str_repeat( 'a', 64 ), 'lifecycle' => array( 'permission_generation' => 10 ) ) ), 'settings' => serialize( array( 'site_uuid' => $this->uuid( 9999 ) ) ), 'ledger' => json_encode( $this->ledger() ) );
        foreach ( $values as $role => $raw ) { $rows[$role]['value'] = $raw; $rows[$role]['octets'] = (string) strlen( $raw ); }
        return array( 'blog_id' => 1, 'prefix' => 'wp_', 'database' => 'synthetic', 'rows' => $rows );
    }
    public function test_exact_positive_read_intent_has_separate_domain_and_immutable_action() {
        $f = $this->form(); $p = Alynt_Drime_Backups_Uploader_Scan_Observation_Request::parse( $f );
        $this->assertSame( 100, $p['envelope']['issued_at'] ); $this->assertSame( 'not-authentication', $p['nonce'] );
        $this->assertStringStartsWith( 'alynt_drime_backups_scan_observation_v1:', $p['action'] );
        foreach ( array( 'command_id' => $this->uuid( 2 ), 'site_uuid' => $this->uuid( 3 ), 'issued_at' => '101', 'expires_at' => '399' ) as $key => $value ) { $changed = $f; $changed[$key] = $value; $this->assertNotSame( $p['action'], Alynt_Drime_Backups_Uploader_Scan_Observation_Request::parse( $changed )['action'] ); }
    }
    public function test_malformed_extra_wrong_domain_and_noncanonical_numbers_refuse() {
        foreach ( array( 'purpose' => 'receipt_read', 'contract' => 'closed_writer_request_v1', 'site_uuid' => strtoupper( $this->uuid( 9999 ) ) . 'x', 'nonce' => '', 'issued_at' => '0100', 'expires_at' => '401', 'extra' => 'provider' ) as $key => $value ) { $f = $this->form(); $f[$key] = $value; $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Observation_Request::parse( $f ), $key ); }
        foreach ( array( null, array(), new stdClass() ) as $bad ) $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Observation_Request::parse( $bad ) );
        foreach ( array( (string) PHP_INT_MAX, '-1', '1e2', '', str_repeat( 'x', 2049 ), 100, array() ) as $bad ) { $f = $this->form(); $f['issued_at'] = $bad; $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Observation_Request::parse( $f ) ); }
    }
    public function test_positive_closed_consistency_and_every_role_difference_are_discriminating() {
        $f = $this->frame(); $this->assertTrue( Alynt_Drime_Backups_Uploader_Scan_Observation_Data::consistent( $f, $f, $this->uuid( 9999 ) ) );
        foreach ( array_keys( $f['rows'] ) as $role ) { $other = $f; $other['rows'][$role]['autoload'] = 'off'; $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Observation_Data::consistent( $f, $other, $this->uuid( 9999 ) ), $role ); }
        $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Observation_Data::consistent( $f, $f, $this->uuid( 9998 ) ) );
    }
    public function test_stable_but_corrupt_codec_or_enrollment_data_is_not_a_closed_candidate() {
        foreach ( array( 'ledger' => '{}', 'safety' => '{}', 'settings' => 'a:0:{}', 'connection' => 'O:8:"stdClass":0:{}', 'wpvivid_snapshot' => 'a:1:{i:0;b:1;}', 'outbox_snapshot' => 'a:1:{i:0;b:1;}', 'queue' => 'a:1:{i:0;b:1;}', 'failed' => 'a:1:{i:0;b:1;}' ) as $role => $raw ) {
            $f = $this->frame(); $f['rows'][$role]['value'] = $raw; $f['rows'][$role]['octets'] = (string) strlen( $raw ); $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Observation_Data::consistent( $f, $f, $this->uuid( 9999 ) ), $role );
        }
    }
}
