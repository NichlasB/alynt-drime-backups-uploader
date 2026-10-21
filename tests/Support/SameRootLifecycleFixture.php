<?php
require_once __DIR__ . '/RegistrationObservationConstructionFixture.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-registration-lifecycle-integration.php';
require_once __DIR__ . '/SameRootLifecyclePorts.php';
/** Same unmodified orchestrator/guards/validators; fixed IO boundaries doubled. */
final class Alynt_Test_Same_Root_Lifecycle {
    const EVENTS = Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::EVENTS;
    use Alynt_Drime_Backups_Uploader_Registration_Lifecycle_Integration_Methods { Alynt_Drime_Backups_Uploader_Registration_Lifecycle_Integration_Methods::qualify_admission_outcome as private source_qualification; }
    use Alynt_Test_Same_Root_Lifecycle_Ports {
        Alynt_Test_Same_Root_Lifecycle_Ports::qualify_admission_outcome insteadof Alynt_Drime_Backups_Uploader_Registration_Lifecycle_Integration_Methods;
    }
}
trait Alynt_Test_Same_Root_Lifecycle_Fixture {
    use Alynt_Test_Registration_Observation_Construction_Fixture { setUp as protected setupSameRoot; }
    private $sameRoot;
    private $sameFixture;
    protected function setUp(): void {
        $this->setupSameRoot();
        mt_srand( 9283 );
        $this->sameFixture = (object) array( 'before' => $this->registrationFrame(), 'observed' => null, 'pin' => null, 'fault' => '', 'rows' => 'registered', 'events' => array(), 'on' => null, 'qualification' => null, 'physical_bad' => false );
        $this->sameRoot = new Alynt_Test_Same_Root_Lifecycle( $this->sameFixture );
    }
    private function sameRun( ...$args ) { return $this->sameRoot->run( ...($args ?: array( 'POST', $this->registrationForm() )) ); }
    private function sameNew() {
        mt_srand( 9283 );
        $this->sameRoot = new Alynt_Test_Same_Root_Lifecycle( $this->sameFixture );
        $this->sameFixture->events = array(); $this->sameFixture->on = null; $this->sameFixture->qualification = null;
        $GLOBALS['alynt_core_request_test']['calls'] = array(); $GLOBALS['alynt_core_request_test']['on'] = array(); $GLOBALS['alynt_core_request_test']['nonce_result'] = 1;
    }
    private function assertSameUuidCount( $expected ) {
        $actual = wp_generate_uuid4(); mt_srand( 9283 );
        for ( $n = 0; $n < $expected; $n++ ) wp_generate_uuid4();
        $this->assertSame( wp_generate_uuid4(), $actual, 'Exact seeded UUID consumption (no production RNG replacement).' );
    }
    private function assertSameRootCleared() {
        foreach ( array( 'origin', 'origin_data', 'scope', 'started', 'lifecycle_clock', 'read_started', 'before', 'proposal', 'context', 'after', 'recorded_at', 'admission_origin', 'admission_clock', 'native_outcome', 'staged', 'inflight', 'reader', 'native_outcome_writer', 'physical', 'read_physical' ) as $k ) $this->assertNull( $this->sameRoot->state( $k ), $k );
        foreach ( array( 'used', 'admission_ids', 'fences' ) as $k ) $this->assertSame( array(), $this->sameRoot->state( $k ), $k );
        foreach ( array( 'root_attempted', 'attempted', 'claimed', 'finished', 'consumed', 'observation_attempted', 'admission_qualification_spent' ) as $k ) $this->assertTrue( $this->sameRoot->state( $k ), $k );
        foreach ( array( 'active', 'busy', 'admission_join_busy' ) as $k ) $this->assertFalse( $this->sameRoot->state( $k ), $k );
        $this->assertSame( array(), $this->registrationCalls( 'protected_io' ) ); $this->assertSame( array(), $this->registrationCalls( 'query' ) );
    }
    private function assertSameCandidate( $r, $classification = 'registered_closed' ) {
        $this->assertIsArray( $r, 'Always-unavailable composition must fail positive progression. ' . json_encode( array( $this->sameFixture->events, $this->sameFixture->qualification ) ) ); $this->assertCount( 9, $r );
        $this->assertSame( $classification, $r['classification'] ); $this->assertTrue( $r['candidate_only'] );
        foreach ( array( 'barrier_verified', 'closure_proven', 'authenticated', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $k ) $this->assertFalse( $r[$k], $k );
    }
    private function sameCheckpoints( &$facts, $fault = null ) {
        $GLOBALS['alynt_core_request_test']['on']['verify_nonce'] = function () use ( &$facts, $fault ) {
            $calls = $this->registrationCalls( 'verify_nonce' ); $last = end( $calls );
            $facts[] = array( 'n' => count( $calls ), 'action' => $last[1][1], 'events' => $this->sameFixture->events, 'before' => $this->sameRoot->state( 'before' ), 'origin' => $this->sameRoot->state( 'origin_data' ), 'staged' => $this->sameRoot->state( 'staged' ), 'inflight' => $this->sameRoot->state( 'inflight' ), 'finished' => $this->sameRoot->state( 'finished' ), 'start' => $this->sameRoot->state( 'started' ), 'read_start' => $this->sameRoot->state( 'read_started' ) );
            $this->sameCheckpoints( $facts, $fault ); if ( null !== $fault ) $fault( count( $calls ), $last[1] );
        };
    }
}
