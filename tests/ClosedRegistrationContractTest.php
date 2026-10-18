<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/class-scan-effect-manifest.php';
require_once __DIR__ . '/Support/ClosedRegistrationFixture.php';
require_once dirname( __DIR__ ) . '/includes/class-closed-registration-contract.php';

/** Positive bookkeeping is mandatory; none of these tests authenticate or write. */
class ClosedRegistrationContractTest extends TestCase {
    use Alynt_Test_Closed_Registration_Fixture;
    public function test_positive_register_reserve_and_historical_lookup_never_authorize_effects() {
        $c = $this->registration_class(); $this->assertTrue( class_exists( $c ), 'Approved pure contract must exist.' );
        $raw = $this->registered(); $this->assertIsString( $raw ); $s = json_decode( $raw, true ); $o = reset( $s['operations'] );
        $this->assertSame( 21, $s['ledger_revision'] ); $this->assertSame( 10, $s['authority_generation'] ); $this->assertSame( 'registered', $o['phase'] );
        $p = $this->reserve_proposal( $raw ); $reserved = $c::reserve( $raw, $this->rscope(), $p, 120 ); $this->assertIsString( $reserved ); $next = json_decode( $reserved, true ); $r = reset( $next['operations'] );
        $this->assertSame( 22, $next['ledger_revision'] ); $this->assertSame( $o['command'], $r['command'] ); $this->assertSame( $o['registration_receipt'], $r['registration_receipt'] );
        $this->assertSame( $o['effects']['outbox_snapshot'], $r['effects']['outbox_snapshot'] ); $this->assertSame( $o['effects']['queue_addition'], $r['effects']['queue_addition'] );
        $this->assertSame( 'intent_reserved', $r['effects']['wpvivid_snapshot']['state'] ); $this->assertNotSame( $r['effects']['wpvivid_snapshot']['before_hash'], $r['effects']['wpvivid_snapshot']['after_hash'] );
        $this->assertNull( $r['effects']['wpvivid_snapshot']['receipt'] ); $this->assertNull( $r['terminal'] );
        foreach ( array( array( $o['command']['command_id'], 'observe_registration', $o['context_digest'], 'registered_closed' ), array( $p['command']['command_id'], 'observe_reservation', $p['command']['context_digest'], 'intent_recorded_closed' ) ) as $case ) {
            $result = $c::lookup( $reserved, $this->rscope(), $this->observe_command( $case[0], $case[1] ), $case[2], 510 );
            $this->assertSame( $case[3], $result['classification'] );
            foreach ( array( 'storage_supported', 'dispatch_supported', 'execution_supported', 'retry_supported', 'authenticated' ) as $flag ) $this->assertFalse( $result[$flag] );
            $this->assertStringNotContainsString( $this->rid( 1 ), json_encode( $result ) ); $this->assertArrayNotHasKey( 'after', $result );
        }
        $this->assertSame( $raw, $this->registered() ); $this->assertFalse( $c::register( $raw, $this->rscope(), $this->rproposal(), 120 ) ); $this->assertFalse( $c::reserve( $reserved, $this->rscope(), $p, 121 ) );
    }
    public function test_every_registration_binding_rejects_stale_tampered_or_reused_data() {
        $c = $this->registration_class();
        foreach ( array( 'actor_id' => 8, 'operation_id' => $this->rid( 20 ), 'terminal_id' => $this->rid( 21 ), 'resolution_id' => $this->rid( 22 ), 'dependency_digest' => str_repeat( 'e', 64 ) ) as $key => $value ) { $p = $this->rproposal(); $p[$key] = $value; $this->assertFalse( $c::register( json_encode( $this->rroot() ), $this->rscope(), $p, 110 ), $key ); }
        foreach ( array( 'expected_ledger_revision' => 19, 'expected_generation' => 11, 'purpose' => 'reserve_wpvivid_intent', 'expires_at' => 401, 'command_id' => $this->rid( 2 ), 'nonce' => 'not-stored' ) as $key => $value ) { $p = $this->rproposal(); $p['command'][$key] = $value; $p['command']['context_digest'] = $c::registration_context( $this->rscope(), $p ); $this->assertFalse( $c::register( json_encode( $this->rroot() ), $this->rscope(), $p, 110 ), $key ); }
        $p = $this->rproposal(); $p['effect_ids']['wpvivid_snapshot'] = $p['operation_id']; $p['command']['context_digest'] = $c::registration_context( $this->rscope(), $p ); $this->assertFalse( $c::register( json_encode( $this->rroot() ), $this->rscope(), $p, 110 ) );
        foreach ( array( 99, 400, '110', PHP_INT_MAX ) as $now ) $this->assertFalse( $c::register( json_encode( $this->rroot() ), $this->rscope(), $this->rproposal(), $now ) );
    }
    public function test_reservation_is_owned_once_only_and_cannot_extend_the_original_deadline() {
        $c = $this->registration_class(); $raw = $this->registered();
        foreach ( array( 'actor_id' => 8, 'before' => 'not serialized', 'replacement' => 'O:8:"stdClass":0:{}' ) as $key => $value ) { $p = $this->reserve_proposal( $raw ); $p[$key] = $value; $p['command']['context_digest'] = $c::reservation_context( $this->rproposal()['command']['context_digest'], $p ); $this->assertFalse( $c::reserve( $raw, $this->rscope(), $p, 120 ), $key ); }
        foreach ( array( 'operation_id' => $this->rid( 99 ), 'command_id' => $this->rid( 9 ), 'issued_at' => 110, 'expires_at' => 410, 'expected_ledger_revision' => 20, 'confirm' => 'register' ) as $key => $value ) { $p = $this->reserve_proposal( $raw ); $p['command'][$key] = $value; $p['command']['context_digest'] = $c::reservation_context( $this->rproposal()['command']['context_digest'], $p ); $this->assertFalse( $c::reserve( $raw, $this->rscope(), $p, 120 ), $key ); }
        $p = $this->reserve_proposal( $raw ); $p['replacement'] = $p['before']; $p['command']['context_digest'] = $c::reservation_context( $this->rproposal()['command']['context_digest'], $p ); $this->assertIsString( $c::reserve( $raw, $this->rscope(), $p, 120 ) );
        $this->assertFalse( $c::reserve( $raw, $this->rscope(), $p, 109 ) ); $this->assertFalse( $c::reserve( $raw, $this->rscope(), $p, 400 ) );
    }
    public function test_absence_and_wrong_context_never_become_retry_or_receipt_proof() {
        $c = $this->registration_class(); $raw = $this->registered(); $p = $this->rproposal();
        $query = $this->observe_command( $this->rid( 91 ) ); $r = $c::lookup( $raw, $this->rscope(), $query, str_repeat( 'f', 64 ), 510 );
        $this->assertSame( 'not_observed_in_candidate', $r['classification'] ); $this->assertFalse( $r['retry_supported'] );
        $query = $this->observe_command( $p['command']['command_id'] ); $r = $c::lookup( $raw, $this->rscope(), $query, str_repeat( 'f', 64 ), 510 ); $this->assertSame( 'domain_conflict', $r['classification'] );
        $query['purpose'] = 'observe_reservation'; $r = $c::lookup( $raw, $this->rscope(), $query, $p['command']['context_digest'], 510 ); $this->assertSame( 'domain_conflict', $r['classification'] );
        $query = $this->observe_command( $p['command']['command_id'] ); $query['command_id'] = $p['command']['command_id']; $this->assertFalse( $c::lookup( $raw, $this->rscope(), $query, $p['command']['context_digest'], 510 ) );
        $query = $this->observe_command( $p['command']['command_id'] );
        $this->assertIsArray( $c::lookup( $raw, $this->rscope(), $query, $p['command']['context_digest'], 799 ) );
        $this->assertFalse( $c::lookup( $raw, $this->rscope(), $query, $p['command']['context_digest'], 800 ) );
    }
}
