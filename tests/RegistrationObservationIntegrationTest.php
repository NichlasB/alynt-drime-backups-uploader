<?php
require_once __DIR__ . '/Support/RegistrationObservationIntegrationFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class RegistrationObservationIntegrationTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Registration_Observation_Integration_Fixture;
    public function test_fixed_private_integration_reaches_a_valid_candidate_without_native_io(): void {
        $this->assertTrue( method_exists( $this->observationOwner, 'observe_candidate' ), 'Approved fixed private integration must exist, not only refusal tests.' );
        foreach ( array( 'registered', 'reserved', 'empty', 'conflict' ) as $phase ) {
            $this->observationOwner = Alynt_Drime_Backups_Uploader_Registration_Observation_Construction::create();
            list( $d, $frame, $i ) = $this->integrationFixture( $phase ); $r = $this->integrationCall( $frame, $i );
            $classification = 'empty' === $phase ? 'not_observed_in_candidate' : ( 'conflict' === $phase ? 'domain_conflict' : 'registered_closed' );
            $this->assertIntegrationResult( $r, $classification ); $this->assertIntegrationCleared();
            foreach ( array( $d['context_digest'], $d['mutation']['command_id'], $i['nonce'], 'unit-cookie', $frame['rows']['ledger']['value'] ) as $secret ) $this->assertStringNotContainsString( $secret, json_encode( $r ) );
        }
    }
    public function test_transfer_and_final_guards_are_independent_and_cleanup_precedes_the_next_phase(): void {
        list( $d, $frame, $i ) = $this->integrationFixture(); $facts = array(); $this->integrationCheckpoints( $facts );
        $this->assertIntegrationResult( $this->integrationCall( $frame, $i ), 'registered_closed' );
        $this->assertCount( 7, $facts, 'Initial issuance, renewed issuance/full, pre-claim issuance/full, post-finish issuance/full.' );
        $issue = Alynt_Drime_Backups_Uploader_Registration_Observation_Request::issue_action( 1, $d['mutation']['command_id'] );
        foreach ( $facts as $fact ) {
            $n = $fact['n']; $this->assertSame( in_array( $n, array( 1, 2, 4, 6 ), true ) ? $issue : $facts[2]['action'], $fact['action'] );
            $this->assertSame( $n <= 3, $fact['origin'] ); $this->assertSame( in_array( $n, array( 4, 5 ), true ), $fact['staged'] ); $this->assertFalse( $fact['inflight'] ); $this->assertSame( $n >= 6, $fact['finished'] );
            if ( 4 === $n ) $this->assertLessThanOrEqual( $fact['window'], $fact['outer'], 'Outer start was latched before the inner window.' );
        }
        $this->assertNotSame( $issue, $facts[2]['action'] ); $this->assertCount( 1, $this->registrationCalls( 'create_nonce' ) ); $this->assertIntegrationCleared();
    }
    public function test_bad_issuance_nonce_fails_before_lookup_uuid_or_nonce_minting(): void {
        list( $d, $frame, $i ) = $this->integrationFixture(); $i['nonce'] = 'unit-nonce';
        mt_srand( 7711 ); $expected = wp_generate_uuid4(); mt_srand( 7711 );
        $this->assertFalse( $this->integrationCall( $frame, $i ) ); $this->assertSame( $expected, wp_generate_uuid4(), 'No UUID issuance before the independent small read guard.' );
        $this->assertSame( array(), $this->registrationCalls( 'create_nonce' ) ); $this->assertIntegrationCleared();
    }
    public function test_full_nonce_denial_cannot_reach_staging_even_if_later_guards_would_refuse(): void {
        list( $d, $frame, $i ) = $this->integrationFixture(); $facts = array();
        $GLOBALS['alynt_core_request_test']['on']['create_nonce'] = function () use ( &$facts ) {
            $this->integrationCheckpoints( $facts, function ( $n, $nonce ) { if ( 'read-issue' !== $nonce[0] ) unset( $GLOBALS['alynt_core_request_test']['nonce_actions'][$nonce[0]] ); } );
        };
        $this->assertFalse( $this->integrationCall( $frame, $i ) ); $this->assertNotEmpty( $facts );
        foreach ( $facts as $fact ) $this->assertFalse( $fact['staged'], 'No staged handoff before full read nonce succeeds.' );
        $this->assertIntegrationCleared();
    }
    public function test_each_late_nonce_guard_is_required_without_adopting_other_domain_credentials(): void {
        foreach ( array( 4, 5, 6, 7 ) as $deny ) {
            $this->observationOwner = Alynt_Drime_Backups_Uploader_Registration_Observation_Construction::create(); list( $d, $frame, $i ) = $this->integrationFixture(); $facts = array();
            $this->integrationCheckpoints( $facts, function ( $n, $nonce ) use ( $deny ) { if ( $n === $deny ) unset( $GLOBALS['alynt_core_request_test']['nonce_actions'][$nonce[0]] ); } );
            $this->assertFalse( $this->integrationCall( $frame, $i ), 'guard ' . $deny ); $this->assertCount( $deny, $facts ); $this->assertIntegrationCleared();
            $GLOBALS['alynt_core_request_test']['on'] = array(); $GLOBALS['alynt_core_request_test']['nonce_actions']['unit-nonce'] = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( 1, 'register_closed' );
        }
    }
    public function test_a_fresh_observer_does_not_replace_the_historical_mutation_actor(): void {
        list( $d, $frame, $i ) = $this->integrationFixture(); $GLOBALS['alynt_core_request_test']['actor'] = 8; $GLOBALS['alynt_core_request_test']['cookie_actor'] = 8;
        $this->assertIntegrationResult( $this->integrationCall( $frame, $i ), 'registered_closed' ); $this->assertSame( 7, $d['actor_id'] ); $this->assertIntegrationCleared();
    }
    public function test_legacy_construction_only_entry_point_always_clears_and_cannot_be_reimported(): void {
        list( $d, $frame, $i ) = $this->integrationFixture(); $blueprint = $this->observationCall( 'construct_observation', $i ); $this->assertIsArray( $blueprint );
        $this->assertObservationCleared(); $this->assertNull( $this->observationProperty( 'staged' ) ); $this->assertNull( $this->observationProperty( 'inflight' ) );
        $this->assertFalse( $this->integrationCall( $frame, $i ) ); $this->assertFalse( $this->integrationCall( $frame, $blueprint ) );
        $this->assertFalse( $this->observationCall( 'retain_registration', $frame, $this->registrationForm() ) );
    }
    public function test_current_candidate_corruption_does_not_bypass_full_frame_validation(): void {
        list( $d, $frame, $i ) = $this->integrationFixture(); $this->rowValue( $frame, 'queue', 'malformed' );
        $this->assertFalse( $this->integrationCall( $frame, $i ) ); $this->assertIntegrationCleared(); $this->assertFalse( $this->integrationCall( $this->registrationFrame(), $i ) );
    }
}
