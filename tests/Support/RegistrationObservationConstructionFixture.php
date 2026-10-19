<?php
require_once __DIR__ . '/RegistrationConstructionFixture.php';

/** API shims only: no WordPress bootstrap, SQL, real cookies or users. */
trait Alynt_Test_Registration_Observation_Construction_Fixture {
    use Alynt_Test_Registration_Construction_Fixture { setUp as protected setupConstruction; tearDown as protected teardownConstruction; }
    private $observationOwner;
    private $ownedFilters = array();
    protected function setUp(): void {
        if ( ! function_exists( 'wp_create_nonce' ) ) {
            function wp_create_nonce( $action ) {
                alynt_test_core_call( 'create_nonce', array( $action ) );
                $nonce = substr( hash( 'sha256', $action ), 0, 10 );
                $GLOBALS['alynt_core_request_test']['nonce_actions'][$nonce] = $action;
                return $nonce;
            }
        }
        $this->setupConstruction();
        $GLOBALS['alynt_test_owned_filter_inventory'] = array();
        $this->ownedFilters =& $GLOBALS['alynt_test_owned_filter_inventory'];
        \Brain\Monkey\Functions\when( 'remove_filter' )->alias( function ( $hook, $callback, $priority = 10 ) {
            $f =& $GLOBALS['alynt_test_owned_filter_inventory'];
            $key = array_search( array( $callback, $priority ), $f[$hook] ?? array(), true );
            if ( false === $key ) throw new LogicException( 'Removal must identify an owned callback and exact priority.' );
            unset( $f[$hook][$key] ); if ( ! $f[$hook] ) unset( $f[$hook] ); return true;
        } );
        require_once dirname( __DIR__, 2 ) . '/includes/class-registration-observation-construction.php';
        $this->observationOwner = Alynt_Drime_Backups_Uploader_Registration_Observation_Construction::create();
        $GLOBALS['alynt_core_request_test']['nonce_actions'] = array( 'unit-nonce' => Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( 1, 'register_closed' ) );
    }
    protected function tearDown(): void {
        try { $this->assertSame( array(), array_filter( $this->ownedFilters ), 'Owned filters must not survive a construction phase.' ); }
        finally { $this->ownedFilters = array(); unset( $GLOBALS['alynt_test_owned_filter_inventory'] ); $this->observationOwner = null; $this->teardownConstruction(); }
    }
    private function observationCall( $method, ...$args ) { $m = new ReflectionMethod( $this->observationOwner, $method ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true ); return $m->invokeArgs( $this->observationOwner, $args ); }
    private function observationProperty( $name, ...$value ) { $p = new ReflectionProperty( $this->observationOwner, $name ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); if ( $value ) $p->setValue( $this->observationOwner, $value[0] ); return $p->getValue( $this->observationOwner ); }
    private function retainObservation() {
        $this->assertTrue( $this->observationCall( 'retain_registration', $this->registrationFrame(), $this->registrationForm() ), 'Own guarded origin construction must succeed, not merely refuse.' );
        $d = $this->observationProperty( 'origin_data' ); $this->assertIsArray( $d );
        return $d;
    }
    private function observationIntent( $id ) {
        $action = Alynt_Drime_Backups_Uploader_Registration_Observation_Request::issue_action( 1, $id );
        $GLOBALS['alynt_core_request_test']['nonce_actions']['read-issue'] = $action;
        return array( 'purpose' => 'observe_registration', 'confirm' => 'observe', 'observed_command_id' => $id, 'nonce' => 'read-issue' );
    }
    private function assertObservationCleared() {
        foreach ( array( 'origin', 'origin_data', 'scope', 'started' ) as $name ) $this->assertNull( $this->observationProperty( $name ), $name );
        foreach ( array( 'used', 'fences' ) as $name ) $this->assertSame( array(), $this->observationProperty( $name ), $name );
        $this->assertFalse( $this->observationProperty( 'busy' ) );
        $this->assertSame( array(), $this->registrationCalls( 'protected_io' ) ); $this->assertSame( array(), $this->registrationCalls( 'query' ) );
    }
}
