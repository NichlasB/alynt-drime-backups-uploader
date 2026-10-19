<?php
require_once __DIR__ . '/RegistrationObservationConstructionFixture.php';
/** API doubles and candidate bytes only; no genuine WordPress/auth/native proof. */
trait Alynt_Test_Registration_Observation_Integration_Fixture {
    use Alynt_Test_Registration_Observation_Construction_Fixture;
    private function integrationFixture( $phase = 'registered' ) {
        $frame = $this->registrationFrame();
        $this->assertTrue( $this->observationCall( 'retain_registration', $frame, $this->registrationForm() ) );
        $d = $this->observationProperty( 'origin_data' ); $ids = array_slice( array_keys( $this->observationProperty( 'used' ) ), -10 );
        $roles = array( 'wpvivid_snapshot', 'outbox_snapshot', 'queue_addition' );
        $proposal = array( 'command' => $d['mutation'], 'actor_id' => $d['actor_id'], 'operation_id' => $d['operation_id'], 'effect_ids' => array_combine( $roles, array_slice( $ids, 4, 3 ) ), 'reservation_command_ids' => array_combine( $roles, array_slice( $ids, 7, 3 ) ), 'terminal_id' => $ids[2], 'resolution_id' => $ids[3], 'dependency_digest' => hash( 'sha256', 'registration_full_frame_v2:' . json_encode( $frame ) ) );
        $contract = 'Alynt_Drime_Backups_Uploader_Closed_Registration_Contract';
        $this->assertSame( $d['context_digest'], $contract::registration_context( $d['scope'], $proposal ), 'Fixture must use the constructor\'s own proposal, not a fabricated origin.' );
        if ( 'empty' !== $phase ) {
            if ( 'conflict' === $phase ) { $proposal['actor_id']++; $proposal['command']['context_digest'] = $contract::registration_context( $d['scope'], $proposal ); }
            $raw = $contract::register( $frame['rows']['ledger']['value'], $d['scope'], $proposal, $d['mutation']['issued_at'] ); $this->assertIsString( $raw );
            if ( 'reserved' === $phase ) {
                $e = $proposal['command']; $e['purpose'] = 'reserve_wpvivid_intent'; $e['command_id'] = $proposal['reservation_command_ids']['wpvivid_snapshot']; $e['expected_ledger_revision']++; $e['confirm'] = 'reserve'; $e['operation_id'] = $proposal['operation_id'];
                $p = array( 'command' => $e, 'actor_id' => $d['actor_id'], 'dependency_digest' => str_repeat( 'c', 64 ), 'before' => 'a:0:{}', 'replacement' => serialize( array( str_repeat( 'd', 64 ) => array( 'size' => 12, 'mtime' => 101 ) ) ) );
                $p['command']['context_digest'] = $contract::reservation_context( $d['context_digest'], $p );
                $raw = $contract::reserve( $raw, $d['scope'], $p, $d['mutation']['issued_at'] + 1 ); $this->assertIsString( $raw );
            }
            $this->assertIsArray( $contract::inspect( $raw, $d['scope'] ) ); $this->rowValue( $frame, 'ledger', $raw );
        }
        $intent = $this->observationIntent( $d['mutation']['command_id'] ); $GLOBALS['alynt_core_request_test']['calls'] = array();
        return array( $d, $frame, $intent );
    }
    private function integrationCall( $frame, $intent ) { return $this->observationCall( 'observe_candidate', $frame, $intent ); }
    private function assertIntegrationCleared() {
        $this->assertObservationCleared();
        foreach ( array( 'staged', 'inflight', 'reader', 'native_started' ) as $key ) $this->assertNull( $this->observationProperty( $key ), $key );
        foreach ( array( 'capture_attempted', 'native_read_attempted' ) as $key ) $this->assertFalse( $this->observationProperty( $key ), $key );
        $this->assertSame( 0, $this->observationProperty( 'protected_reads' ) );
    }
    private function assertIntegrationResult( $r, $classification ) {
        $this->assertIsArray( $r, 'Always-unavailable is not a successful integration.' ); $this->assertSame( $classification, $r['classification'] ); $this->assertTrue( $r['candidate_only'] ); $this->assertCount( 9, $r );
        foreach ( array( 'barrier_verified', 'closure_proven', 'authenticated', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $key ) $this->assertFalse( $r[$key], $key );
    }
    private function integrationCheckpoints( &$facts, $fault = null ) {
        $GLOBALS['alynt_core_request_test']['on']['verify_nonce'] = function () use ( &$facts, $fault ) {
            $calls = $this->registrationCalls( 'verify_nonce' ); $n = count( $calls ); $nonce = $calls[$n - 1][1];
            $staged = $this->observationProperty( 'staged' );
            $facts[] = array( 'n' => $n, 'action' => $nonce[1], 'origin' => null !== $this->observationProperty( 'origin' ), 'staged' => null !== $staged, 'inflight' => null !== $this->observationProperty( 'inflight' ), 'finished' => $this->observationProperty( 'finished' ), 'outer' => null === $staged ? null : $staged[3]['started_ns'], 'window' => $this->observationProperty( 'started' ) );
            $this->integrationCheckpoints( $facts, $fault ); if ( null !== $fault ) $fault( $n, $nonce );
        };
    }
}
