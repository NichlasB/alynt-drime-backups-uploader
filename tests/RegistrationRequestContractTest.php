<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/class-registration-request-contract.php';
/** Pure grammar tests; no core nonce issuance or authentication. */
class RegistrationRequestContractTest extends TestCase {
    private function form( $purpose = 'register_closed' ) {
        $f = array( 'contract' => 'scan_registration_request_v2', 'purpose' => $purpose, 'command_id' => '11111111-1111-4111-8111-111111111111', 'site_uuid' => '22222222-2222-4222-8222-222222222222', 'enrollment_binding' => str_repeat( 'a', 64 ), 'expected_generation' => '10', 'expected_ledger_revision' => '20', 'context_digest' => str_repeat( 'b', 64 ), 'issued_at' => '100', 'expires_at' => '400', 'confirm' => 'register', 'nonce' => 'unit-nonce' );
        if ( 'reserve_wpvivid_intent' === $purpose ) { $f['confirm'] = 'reserve'; $f['operation_id'] = '33333333-3333-4333-8333-333333333333'; }
        if ( in_array( $purpose, array( 'observe_registration', 'observe_reservation' ), true ) ) { unset( $f['enrollment_binding'], $f['expected_generation'], $f['expected_ledger_revision'], $f['context_digest'] ); $f['contract'] = 'scan_registration_observation_v2'; $f['confirm'] = 'observe'; $f['observed_command_id'] = '33333333-3333-4333-8333-333333333333'; }
        return $f;
    }
    public function test_positive_purposes_have_separate_actions_bound_to_every_typed_field() {
        $c = 'Alynt_Drime_Backups_Uploader_Registration_Request_Contract'; $actions = array();
        foreach ( array( 'register_closed', 'reserve_wpvivid_intent', 'observe_registration', 'observe_reservation' ) as $purpose ) {
            $f = $this->form( $purpose ); $p = $c::parse( $f ); $this->assertIsArray( $p ); $actions[] = $p['action']; $this->assertSame( 100, $p['envelope']['issued_at'] ); $this->assertArrayNotHasKey( 'nonce', $p['envelope'] );
            foreach ( $f as $key => $value ) {
                if ( in_array( $key, array( 'contract', 'purpose', 'confirm', 'nonce' ), true ) ) continue;
                $other = $f; $other[$key] = in_array( $key, array( 'issued_at', 'expected_generation', 'expected_ledger_revision' ), true ) ? (string) ( (int) $value + 1 ) : ( 'expires_at' === $key ? '399' : str_replace( array( '1', '2', '3', 'a', 'b' ), array( '5', '6', '7', 'c', 'd' ), $value ) );
                $changed = $c::parse( $other ); $this->assertIsArray( $changed, "$purpose/$key" ); $this->assertNotSame( $p['action'], $changed['action'], "$purpose/$key" );
            }
        }
        $this->assertCount( 4, array_unique( $actions ) ); $this->assertStringStartsWith( 'alynt_drime_backups_scan_reservation_command_v2:', $actions[1] );
        $this->assertStringStartsWith( 'alynt_drime_backups_scan_registration_receipt_v2:', $actions[2] );
        $issue = $c::issue_action( 1, 'register_closed' ); $this->assertNotContains( $issue, $actions ); $this->assertNotSame( $issue, $c::issue_action( 2, 'register_closed' ) );
        $this->assertFalse( $c::issue_action( 1, 'rollback' ) );
    }
    public function test_malformed_stale_shape_overflow_and_untrusted_ports_are_not_coerced() {
        $c = 'Alynt_Drime_Backups_Uploader_Registration_Request_Contract';
        foreach ( array( 'contract' => 'closed_writer_request_v1', 'purpose' => 'upgrade_closed', 'confirm' => 'close', 'actor_id' => '7', 'provider' => 'database', 'nonce' => array(), 'context_digest' => strtoupper( str_repeat( 'b', 64 ) ), 'issued_at' => '0100', 'expires_at' => '401', 'expected_generation' => (string) PHP_INT_MAX, 'expected_ledger_revision' => 20 ) as $key => $value ) { $f = $this->form(); $f[$key] = $value; $this->assertFalse( $c::parse( $f ), $key ); }
        $f = $this->form(); $nonce = 'unit-nonce'; $f['nonce'] =& $nonce; $this->assertFalse( $c::parse( $f ) );
        foreach ( array( array( 'purpose' => 'register_closed', 'confirm' => 'register', 'nonce' => 'unit-nonce' ), array( 'purpose' => 'reserve_wpvivid_intent', 'confirm' => 'reserve', 'nonce' => 'unit-nonce', 'operation_id' => '33333333-3333-4333-8333-333333333333' ) ) as $f ) { $this->assertIsArray( $c::issuance( $f ) ); $f['actor_id'] = '7'; $this->assertFalse( $c::issuance( $f ) ); }
    }
    public function test_complete_encoded_nonce_bound_accepts_4096_and_rejects_4097() {
        $c = 'Alynt_Drime_Backups_Uploader_Registration_Request_Contract'; $f = $this->form(); $f['nonce'] = str_repeat( 'é', 500 ); $padding = 4096 - strlen( json_encode( $f ) ); $this->assertGreaterThan( 0, $padding );
        $f['nonce'] .= str_repeat( 'x', $padding ); $this->assertSame( 4096, strlen( json_encode( $f ) ) ); $this->assertIsArray( $c::parse( $f ) ); $f['nonce'] .= 'x'; $this->assertFalse( $c::parse( $f ) );
        $f = $this->form(); $f['nonce'] = str_repeat( '\\', 1000 ); $this->assertIsArray( $c::parse( $f ) ); // Once-unslashing cannot erase the raw encoded cap.
        $f['nonce'] = str_repeat( '\\', 2048 ); $this->assertFalse( $c::parse( $f ) );
    }
}
