<?php
require_once __DIR__ . '/Support/RegistrationObservationConstructionFixture.php';

/**
 * Isolated refusal tests only; genuine WordPress proof is a separate local runner.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class RegistrationObservationNativeBoundaryTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Registration_Observation_Construction_Fixture;
    public function test_unsupported_native_profile_cannot_capture_or_retry(): void {
        $this->assertFalse( $this->observationCall( 'retain_native_registration', 'POST', $this->registrationForm() ) );
        $this->assertSame( 0, $this->observationProperty( 'protected_reads' ) );
        $this->assertNull( $this->observationProperty( 'reader' ) );
        $this->assertTrue( $this->observationProperty( 'capture_attempted' ) );
        $this->assertTrue( $this->observationProperty( 'retention_attempted' ) );
        $this->assertFalse( $this->observationCall( 'retain_native_registration', 'POST', $this->registrationForm() ) );
        $this->assertObservationCleared();
    }
    public function test_synthetic_origin_cannot_enter_the_native_read_lane(): void {
        $d = $this->retainObservation(); $i = $this->observationIntent( $d['mutation']['command_id'] );
        $GLOBALS['alynt_core_request_test']['calls'] = array();
        $this->assertFalse( $this->observationCall( 'construct_native_observation', $i ) );
        $this->assertTrue( $this->observationProperty( 'native_read_attempted' ) );
        $this->assertSame( array(), $this->registrationCalls( 'validate_cookie' ) );
        $this->assertSame( array(), $this->registrationCalls( 'create_nonce' ) );
        $this->assertObservationCleared();
    }
    public function test_malformed_native_capture_consumes_before_authentication(): void {
        $this->assertFalse( $this->observationCall( 'retain_native_registration', 'GET', array() ) );
        $this->assertSame( array(), $this->registrationCalls( 'validate_cookie' ) );
        $this->assertSame( array(), $this->registrationCalls( 'create_nonce' ) );
        $this->assertNull( $this->observationProperty( 'native_started' ) );
        $this->assertObservationCleared();
    }
}
