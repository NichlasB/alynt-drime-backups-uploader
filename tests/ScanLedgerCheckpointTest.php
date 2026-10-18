<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/class-scan-ledger-checkpoint-plan.php';
require_once dirname( __DIR__ ) . '/includes/class-scan-ledger-checkpoint-request.php';
require_once __DIR__ . '/Support/ClosedScanLedgerFixture.php';
/** Pure synthetic candidates, not WordPress authentication or database evidence. */
class ScanLedgerCheckpointTest extends TestCase {
    use Alynt_Test_Closed_Scan_Ledger_Fixture;
    private function frame( $ledger = null ) {
        $rows = array(); $n = 0;
        foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $role => $def ) $rows[$role] = array( 'option_id' => (string) ++$n, 'option_name' => $def[0], 'autoload' => 'no', 'octets' => '6', 'value' => 'a:0:{}' );
        $values = array( 'safety' => json_encode( array( 'enrollment_binding' => str_repeat( 'a', 64 ), 'lifecycle' => array( 'permission_generation' => 10 ) ) ), 'settings' => serialize( array( 'site_uuid' => $this->uuid( 9999 ) ) ), 'ledger' => json_encode( $ledger ?? $this->ledger() ) );
        foreach ( $values as $role => $raw ) { $rows[$role]['value'] = $raw; $rows[$role]['octets'] = (string) strlen( $raw ); }
        return array( 'blog_id' => 1, 'prefix' => 'wp_', 'database' => 'synthetic', 'rows' => $rows );
    }
    private function plan( $f, $context = null ) { return Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Plan::prepare( $f, $this->uuid( 9999 ), $context ?? Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Plan::context( $f ) ); }
    private function form() { return array( 'contract' => 'scan_ledger_checkpoint_v1', 'purpose' => 'checkpoint_closed_ledger', 'command_id' => $this->uuid( 1 ), 'site_uuid' => $this->uuid( 9999 ), 'issued_at' => '100', 'expires_at' => '400', 'confirm' => 'checkpoint', 'nonce' => 'synthetic', 'context_digest' => str_repeat( 'a', 64 ) ); }
    public function test_positive_successor_changes_only_revision_and_preserves_terminal_history() {
        foreach ( array( 0, 1, 4 ) as $count ) {
            $s = $this->ledger( $count, true ); $raw = $this->plan( $this->frame( $s ) ); $this->assertIsString( $raw );
            ++$s['ledger_revision']; $this->assertSame( json_encode( $s ), $raw );
        }
    }
    public function test_every_dependency_and_scope_is_bound_and_replay_is_stale() {
        $f = $this->frame(); $context = Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Plan::context( $f );
        foreach ( array_keys( $f['rows'] ) as $role ) { $bad = $f; $bad['rows'][$role]['autoload'] = 'off'; $this->assertFalse( $this->plan( $bad, $context ), $role ); }
        foreach ( array( 'blog_id' => 2, 'prefix' => 'other_', 'database' => 'other' ) as $key => $v ) { $bad = $f; $bad[$key] = $v; $this->assertFalse( $this->plan( $bad, $context ) ); }
        $raw = $this->plan( $f ); $f['rows']['ledger']['value'] = $raw; $f['rows']['ledger']['octets'] = (string) strlen( $raw );
        $this->assertFalse( $this->plan( $f, $context ) );
    }
    public function test_pending_capacity_overflow_open_and_malformed_states_refuse() {
        $s = $this->ledger(); $s['ledger_revision'] = PHP_INT_MAX - 5;
        foreach ( array( $s, $this->ledger( 1 ), $this->ledger( 16, true ) ) as $bad ) $this->assertFalse( $this->plan( $this->frame( $bad ) ) );
        foreach ( array( 'closed' => false, 'participant_digest' => str_repeat( 'b', 64 ), 'enrollment_binding' => str_repeat( 'b', 64 ), 'ledger_revision' => '1000' ) as $key => $v ) { $s = $this->ledger(); $s[$key] = $v; $this->assertFalse( $this->plan( $this->frame( $s ) ) ); }
        $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Plan::context( array() ) );
        $this->assertFalse( Alynt_Drime_Backups_Uploader_Closed_Scan_Effect_Ledger::budget_candidate( 108545, 0, 0 ) );
        $this->assertTrue( Alynt_Drime_Backups_Uploader_Closed_Scan_Effect_Ledger::budget_candidate( 108544, 15, 0 ) );
    }
    public function test_request_domain_all_fields_and_context_are_bound_without_issuance() {
        $f = $this->form(); $p = Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Request::parse( $f );
        $this->assertSame( 100, $p['envelope']['issued_at'] ); $this->assertSame( 'checkpoint_closed_ledger', $p['envelope']['purpose'] );
        $this->assertStringStartsWith( 'alynt_drime_backups_scan_ledger_checkpoint_v1:', $p['action'] );
        foreach ( array( 'command_id' => $this->uuid( 2 ), 'site_uuid' => $this->uuid( 3 ), 'issued_at' => '101', 'expires_at' => '399', 'context_digest' => str_repeat( 'b', 64 ) ) as $key => $v ) { $other = $f; $other[$key] = $v; $this->assertNotSame( $p['action'], Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Request::parse( $other )['action'] ); }
        foreach ( array( 'contract' => 'scan_effect_observation_v1', 'purpose' => 'observe_closed_manifest', 'confirm' => 'observe', 'context_digest' => strtoupper( str_repeat( 'a', 64 ) ), 'extra' => 'target', 'issued_at' => '0100', 'expires_at' => '401', 'nonce' => array() ) as $key => $v ) { $other = $f; $other[$key] = $v; $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Request::parse( $other ), $key ); }
    }
    public function test_unencodable_frame_cannot_collapse_to_a_false_json_fingerprint() {
        $f = $this->frame(); $f['rows']['connection']['value'] = "\xff"; $f['rows']['connection']['octets'] = '1';
        $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Plan::context( $f ) );
    }
    public function test_complete_encoded_request_including_nonce_key_has_an_exact_byte_limit() {
        $f = $this->form(); $f['nonce'] = str_repeat( 'é', 600 );
        $p = Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Request::parse( $f ); $this->assertIsArray( $p );
        $encoded = $p['envelope']; $encoded['nonce'] = $f['nonce']; $padding = 4097 - strlen( json_encode( $encoded ) );
        $this->assertGreaterThan( 0, $padding ); $this->assertLessThan( 500, $padding );
        $f['nonce'] .= str_repeat( 'x', $padding );
        $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Request::parse( $f ) );
        $f['nonce'] = substr( $f['nonce'], 0, -1 );
        $this->assertIsArray( Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Request::parse( $f ) );
    }
}
