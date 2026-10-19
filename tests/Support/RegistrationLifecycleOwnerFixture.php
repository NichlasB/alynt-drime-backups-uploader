<?php
require_once __DIR__ . '/RegistrationObservationConstructionFixture.php';
/** Isolated API doubles only; no WordPress, native connection or site state. */
trait Alynt_Test_Registration_Lifecycle_Owner_Fixture {
    use Alynt_Test_Registration_Observation_Construction_Fixture { setUp as protected setupObservation; }
    private $lifecycleOwner;
    protected function setUp(): void {
        $this->setupObservation();
        $path = dirname( __DIR__, 2 ) . '/includes/class-registration-lifecycle-request-owner.php';
        $this->assertFileExists( $path, 'Approved fixed lifecycle owner must exist.' ); require_once $path;
        $this->lifecycleOwner = Alynt_Drime_Backups_Uploader_Registration_Lifecycle_Request_Owner::create();
    }
    private function lifecycleCall( ...$args ) { return $this->lifecycleInvoke( 'prepare_admission', ...$args ); }
    private function lifecycleInvoke( $method, ...$args ) { $m = new ReflectionMethod( $this->lifecycleOwner, $method ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true ); return $m->invokeArgs( $this->lifecycleOwner, $args ); }
    private function lifecycleProperty( $name, ...$value ) { $p = new ReflectionProperty( $this->lifecycleOwner, $name ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); if ( $value ) $p->setValue( $this->lifecycleOwner, $value[0] ); return $p->getValue( $this->lifecycleOwner ); }
    private function lifecycleNew() { $this->lifecycleOwner = Alynt_Drime_Backups_Uploader_Registration_Lifecycle_Request_Owner::create(); }
    private function lifecycleCheckpoints( &$facts, $fault = null ) {
        $GLOBALS['alynt_core_request_test']['on']['verify_nonce'] = function () use ( &$facts, $fault ) {
            $calls = $this->registrationCalls( 'verify_nonce' ); $last = end( $calls );
            $facts[] = array( 'index' => count( $calls ), 'nonce' => $last[1][0], 'action' => $last[1][1], 'origin' => $this->lifecycleProperty( 'origin_data' ), 'used' => $this->lifecycleProperty( 'used' ), 'clock' => $this->lifecycleProperty( 'root_clock' ), 'started' => $this->lifecycleProperty( 'started' ), 'phase' => $this->lifecycleProperty( 'phase' ) );
            $this->lifecycleCheckpoints( $facts, $fault ); if ( null !== $fault ) $fault( count( $calls ) );
        };
    }
    private function assertLifecycleCleared() {
        foreach ( array( 'origin', 'origin_data', 'scope', 'started', 'root_clock' ) as $key ) $this->assertNull( $this->lifecycleProperty( $key ), $key );
        foreach ( array( 'used', 'fences' ) as $key ) $this->assertSame( array(), $this->lifecycleProperty( $key ), $key );
        $this->assertSame( 'closed', $this->lifecycleProperty( 'phase' ) ); $this->assertTrue( $this->lifecycleProperty( 'attempted' ) ); $this->assertTrue( $this->lifecycleProperty( 'consumed' ) );
        $this->assertFalse( $this->lifecycleProperty( 'active' ) ); $this->assertFalse( $this->lifecycleProperty( 'busy' ) );
        $this->assertSame( array(), $this->registrationCalls( 'protected_io' ) ); $this->assertSame( array(), $this->registrationCalls( 'query' ) );
    }
}
