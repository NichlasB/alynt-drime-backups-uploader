<?php
require_once __DIR__ . '/Support/RegistrationObservationIntegrationFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class RegistrationObservationNativeBarrierBoundaryTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Registration_Observation_Integration_Fixture;
    public function test_native_entry_without_own_capture_refuses_once_without_io(): void {
        list( $d, $frame, $i ) = $this->integrationFixture();
        $this->assertFalse( $this->observationCall( 'observe_native', $i ) );
        $this->assertSame( 0, $this->observationProperty( 'barrier_reads' ) ); $this->assertSame( 0, $this->observationProperty( 'zero_dml_checks' ) );
        $this->assertSame( 'unopened', $this->observationProperty( 'phase' ) ); $this->assertObservationCleared();
        foreach ( array( 'barrier_auth', 'barrier_auth_facts', 'physical', 'staged', 'inflight', 'reader' ) as $k ) $this->assertNull( $this->observationProperty( $k ) );
        $this->assertFalse( $this->observationCall( 'observe_native', $i ) );
    }
    public function test_no_public_native_port_or_production_wiring_or_admission_dml_reuse(): void {
        $this->assertTrue( ( new ReflectionMethod( $this->observationOwner, 'observe_native' ) )->isPrivate() );
        $public = array(); foreach ( ( new ReflectionClass( $this->observationOwner ) )->getMethods( ReflectionMethod::IS_PUBLIC ) as $m ) $public[] = $m->getName(); sort( $public );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $public );
        foreach ( array_merge( array( dirname( __DIR__ ) . '/alynt-drime-backups-uploader.php' ), glob( dirname( __DIR__ ) . '/includes/class-plugin*.php' ) ) as $path ) $this->assertStringNotContainsString( 'registration-observation-native-barrier', file_get_contents( $path ) );
        $source = file_get_contents( dirname( __DIR__ ) . '/includes/class-registration-observation-construction.php' );
        foreach ( array( 'Registration_Admission_Storage', 'Registration_Admission_Transaction', 'admission_write' ) as $part ) $this->assertStringNotContainsString( $part, $source );
    }
    public function test_candidate_data_cannot_mint_native_qualification(): void {
        list( $d, $frame, $i ) = $this->integrationFixture();
        $this->assertIntegrationResult( $this->integrationCall( $frame, $i ), 'registered_closed' );
        $this->assertSame( 'unopened', $this->observationProperty( 'phase' ) ); $this->assertSame( 0, $this->observationProperty( 'barrier_reads' ) );
        $this->assertFalse( $this->observationCall( 'observe_native', $i ) );
    }
    public function test_unknown_acknowledgement_states_are_poisoned_without_an_owned_handle(): void {
        foreach ( array( 'begin_sent', 'commit_sent', 'rollback_sent', 'transaction_owned' ) as $phase ) {
            $this->observationOwner = Alynt_Drime_Backups_Uploader_Registration_Observation_Construction::create();
            $this->observationProperty( 'phase', $phase ); $this->observationCall( 'close_barrier' );
            $this->assertSame( 'poisoned', $this->observationProperty( 'phase' ) ); $this->assertNull( $this->observationProperty( 'reader' ) ); $this->assertNull( $this->observationProperty( 'physical' ) );
        }
    }
}
