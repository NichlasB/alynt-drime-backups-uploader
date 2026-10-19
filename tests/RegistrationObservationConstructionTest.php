<?php
require_once __DIR__ . '/Support/RegistrationObservationConstructionFixture.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class RegistrationObservationConstructionTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Registration_Observation_Construction_Fixture;

    public function test_own_origin_and_fresh_independent_read_are_constructed_without_io(): void {
        $d = $this->retainObservation();
        $this->assertSame( 7, $d['actor_id'] ); $this->assertSame( $d['mutation']['context_digest'], $d['context_digest'] );
        $this->assertTrue( $this->observationProperty( 'origin' )->matches( $d ) );
        $this->assertNull( $this->observationProperty( 'scope' ), 'Do not retain a write-capable core object.' );
        $used = $this->observationProperty( 'used' ); $this->assertGreaterThanOrEqual( 12, count( $used ) );
        $i = $this->observationIntent( $d['mutation']['command_id'] );
        $r = $this->observationCall( 'construct_observation', $i ); $this->assertIsArray( $r, 'An always-unavailable owner must fail.' );
        $this->assertSame( $d['mutation']['command_id'], $r['envelope']['observed_command_id'] );
        $this->assertSame( $d['scope']['site_uuid'], $r['envelope']['site_uuid'] );
        $this->assertArrayNotHasKey( $r['envelope']['command_id'], $used );
        $this->assertSame( 300, $r['envelope']['expires_at'] - $r['envelope']['issued_at'] );
        $actions = array_column( array_column( $this->registrationCalls( 'verify_nonce' ), 1 ), 1 );
        foreach ( $this->registrationCalls( 'create_nonce' ) as $call ) $this->assertContains( $call[1][0], $actions, 'Every minted full nonce must be independently verified.' );
        $this->assertNotSame( $GLOBALS['alynt_core_request_test']['nonce_actions']['read-issue'], $r['action'] );
        foreach ( array( 'dispatch_supported', 'execution_supported', 'barrier_verified', 'closure_proven' ) as $key ) $this->assertFalse( $r[$key] );
        $this->assertObservationCleared();
        $this->assertFalse( $this->observationCall( 'construct_observation', $i ) );
        $this->assertFalse( $this->observationCall( 'retain_registration', $this->registrationFrame(), $this->registrationForm() ) );
    }

    public function test_fresh_authorized_observer_does_not_adopt_historical_mutation_actor(): void {
        $d = $this->retainObservation(); $i = $this->observationIntent( $d['mutation']['command_id'] );
        $GLOBALS['alynt_core_request_test']['actor'] = 8; $GLOBALS['alynt_core_request_test']['cookie_actor'] = 8;
        $this->assertIsArray( $this->observationCall( 'construct_observation', $i ) );
        $this->assertSame( 7, $d['actor_id'] ); $this->assertObservationCleared();
    }

    public function test_missing_origin_consumes_read_without_authentication_or_later_import(): void {
        $this->assertFalse( $this->observationCall( 'construct_observation', array() ) );
        $this->assertSame( array(), $this->registrationCalls( 'validate_cookie' ) );
        $this->assertFalse( $this->observationCall( 'retain_registration', $this->registrationFrame(), $this->registrationForm() ) );
        $this->assertObservationCleared();
    }

    public function test_retention_refusals_are_consumed_before_nonce_minting(): void {
        $GLOBALS['alynt_core_request_test']['session_now'] = false;
        $this->assertFalse( $this->observationCall( 'retain_registration', $this->registrationFrame(), $this->registrationForm() ) );
        $this->assertSame( array(), $this->registrationCalls( 'create_nonce' ) );
        $GLOBALS['alynt_core_request_test']['session_now'] = true;
        $this->assertFalse( $this->observationCall( 'retain_registration', $this->registrationFrame(), $this->registrationForm() ) );
        $this->assertObservationCleared();
    }

    public function test_mutation_full_nonce_is_not_substituted_by_valid_issuance_nonce(): void {
        $GLOBALS['alynt_core_request_test']['on']['create_nonce'] = function () {
            $GLOBALS['alynt_core_request_test']['on']['verify_nonce'] = function () {
                foreach ( $GLOBALS['alynt_core_request_test']['nonce_actions'] as $n => $a ) if ( 'unit-nonce' !== $n ) unset( $GLOBALS['alynt_core_request_test']['nonce_actions'][$n] );
            };
        };
        $this->assertFalse( $this->observationCall( 'retain_registration', $this->registrationFrame(), $this->registrationForm() ) );
        $this->assertCount( 1, $this->registrationCalls( 'create_nonce' ) ); $this->assertObservationCleared();
    }

    public function test_read_full_nonce_has_an_independent_guard(): void {
        $d = $this->retainObservation(); $i = $this->observationIntent( $d['mutation']['command_id'] );
        $GLOBALS['alynt_core_request_test']['on']['create_nonce'] = function () {
            $GLOBALS['alynt_core_request_test']['on']['verify_nonce'] = function () {
                foreach ( $GLOBALS['alynt_core_request_test']['nonce_actions'] as $n => $a ) if ( ! in_array( $n, array( 'unit-nonce', 'read-issue' ), true ) ) unset( $GLOBALS['alynt_core_request_test']['nonce_actions'][$n] );
            };
        };
        $this->assertFalse( $this->observationCall( 'construct_observation', $i ) ); $this->assertObservationCleared();
    }

    public function test_bad_read_nonce_is_rejected_before_new_nonce_or_protected_io(): void {
        $d = $this->retainObservation(); $i = $this->observationIntent( $d['mutation']['command_id'] ); $i['nonce'] = 'unit-nonce';
        $this->assertFalse( $this->observationCall( 'construct_observation', $i ) );
        $this->assertCount( 1, $this->registrationCalls( 'create_nonce' ) ); $this->assertObservationCleared();
    }

    public function test_reentry_during_retention_poisoned_outer_and_never_retains_origin(): void {
        $nested = null;
        $GLOBALS['alynt_core_request_test']['on']['validate_cookie'] = function () use ( &$nested ) { $nested = $this->observationCall( 'construct_observation', array() ); };
        $this->assertFalse( $this->observationCall( 'retain_registration', $this->registrationFrame(), $this->registrationForm() ) );
        $this->assertFalse( $nested ); $this->assertObservationCleared();
    }

    public function test_read_callback_actor_swap_is_not_adopted(): void {
        $d = $this->retainObservation(); $i = $this->observationIntent( $d['mutation']['command_id'] );
        $GLOBALS['alynt_core_request_test']['on']['create_nonce'] = function () { $GLOBALS['alynt_core_request_test']['actor'] = 8; $GLOBALS['alynt_core_request_test']['cookie_actor'] = 8; };
        $this->assertFalse( $this->observationCall( 'construct_observation', $i ) ); $this->assertObservationCleared();
    }

    public function test_only_own_fences_cover_authentication_and_are_removed_on_throw(): void {
        $facts = array();
        $GLOBALS['alynt_core_request_test']['on']['validate_cookie'] = function () use ( &$facts ) {
            $facts['count'] = count( $this->ownedFilters['query'] );
            $facts['priority'] = $this->ownedFilters['query'][0][1];
            $facts['autoload'] = ( $this->ownedFilters['pre_wp_load_alloptions'][0][0] )();
            $f = $this->ownedFilters['query'][0][0];
            $sql = "SELECT option_value FROM wp_options WHERE option_name = 'wp_user_roles' LIMIT 1";
            $facts['role'] = $f( $sql );
            try { $f( "SELECT option_value FROM wp_options WHERE option_name = 'alynt_drime_backups_settings' LIMIT 1" ); }
            catch ( RuntimeException $e ) { $facts['protected_refused'] = true; throw $e; }
        };
        $roles = $GLOBALS['wp_roles'];
        $this->assertFalse( $this->observationCall( 'retain_registration', $this->registrationFrame(), $this->registrationForm() ) );
        $this->assertSame( array( 'count' => 1, 'priority' => PHP_INT_MAX, 'autoload' => array(), 'role' => "SELECT option_value FROM wp_options WHERE option_name = 'wp_user_roles' LIMIT 1", 'protected_refused' => true ), $facts );
        $this->assertSame( $roles, $GLOBALS['wp_roles'] ); $this->assertObservationCleared();
    }
}
