<?php
use PHPUnit\Framework\TestCase;
/** @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class UncertainBarrierNativeManifestTest extends TestCase {
    public function test_fixed_successor_preserves_original_composition_and_closed_public_surface() {
        require dirname( __DIR__ ) . '/scripts/tests/native-lifecycle-proof-source.php';
        require __DIR__ . '/Support/UncertainBarrierNativeManifest.php';
        $this->assertTrue( Alynt_Test_Uncertain_Barrier_Native_Manifest::verify() );
        $this->assertTrue( Alynt_Test_Uncertain_Barrier_Native_Manifest::composition() );
        $this->assertFalse( Alynt_Test_Uncertain_Barrier_Native_Manifest::verify( 'provider' ) );
        $this->assertFalse( Alynt_Test_Uncertain_Barrier_Native_Manifest::composition( 'root' ) );
        $owner = Alynt_Test_Uncertain_Barrier_Native_Owner::create();
        $this->assertInstanceOf( Alynt_Test_Uncertain_Barrier_Native_Owner::class, $owner );
        $this->assertFalse( Alynt_Test_Uncertain_Barrier_Native_Owner::create( 'handle' ) );
        $c = new ReflectionClass( $owner );
        $this->assertTrue( $c->isFinal() ); $this->assertTrue( $c->getConstructor()->isPrivate() );
        foreach ( array( 'lifecycle_root','take_uncertain_barrier_observation','take_native_qualification_observation','native_entry_available' ) as $method ) $this->assertTrue( $c->getMethod( $method )->isPrivate() );
        $public = array_map( function ( $m ) { return $m->getName(); }, $c->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $public );
        $this->assertSame( array( '__debugInfo','__serialize','__unserialize','__wakeup','create','disposition' ), $public );
        $this->assertFalse( function_exists( 'wp_verify_nonce' ) ); $this->assertFalse( class_exists( 'wpdb', false ) );
    }
}
