<?php
/** Source/reflection only; never invokes the enabled CLI root or WordPress.
 * Keep the large CLI-only trait composition out of the shared suite process.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class UncertainBarrierNoFaultManifestTest extends PHPUnit\Framework\TestCase {
    public function test_fixed_cli_exception_matches_the_dormant_owner_without_new_public_ports() {
        require_once dirname( __DIR__ ) . '/scripts/tests/native-lifecycle-proof-source.php';
        require_once __DIR__ . '/Support/UncertainBarrierNoFaultManifest.php';
        $this->assertTrue( Alynt_Test_Uncertain_Barrier_No_Fault_Manifest::verify() );
        $this->assertTrue( Alynt_Test_Uncertain_Barrier_No_Fault_Manifest::composition() );
        $this->assertFalse( Alynt_Test_Uncertain_Barrier_No_Fault_Manifest::verify( 'provider' ) );
        $this->assertFalse( Alynt_Test_Uncertain_Barrier_No_Fault_Manifest::composition( 'enable' ) );
        $this->assertFalse( Alynt_Test_Uncertain_Barrier_No_Fault_Owner::create( true ) );
        $closed = new ReflectionClass( 'Alynt_Test_Uncertain_Barrier_Owner' );
        $proof = new ReflectionClass( 'Alynt_Test_Uncertain_Barrier_No_Fault_Owner' );
        $this->assertSame( $closed->getDefaultProperties(), $proof->getDefaultProperties() );
        foreach ( array( $closed, $proof ) as $c ) {
            $public = array_map( function ( $m ) { return $m->getName(); }, $c->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $public );
            $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $public );
            $this->assertTrue( $c->getMethod( 'lifecycle_root' )->isPrivate() );
            $this->assertTrue( $c->getMethod( 'take_uncertain_barrier_observation' )->isPrivate() );
        }
        $m = $closed->getMethod( 'native_entry_available' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true );
        $this->assertFalse( $m->invoke( Alynt_Test_Uncertain_Barrier_Owner::create() ) );
        $this->assertTrue( Alynt_Test_Native_Qualification_Selected_Manifest::verify() );
    }
}
