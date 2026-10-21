<?php
require_once __DIR__ . '/Support/UncertainBarrierManifest.php';
/** Definitions/source/reflection only: no native-enabled owner, hooks or IO methods. */
final class UncertainBarrierManifestTest extends PHPUnit\Framework\TestCase {
    public function test_closed_composition_and_new_identity_preserve_all_predecessors() {
        $this->assertTrue( Alynt_Test_Uncertain_Barrier_Manifest::verify() );
        $this->assertTrue( Alynt_Test_Uncertain_Barrier_Manifest::composition() );
        $this->assertFalse( Alynt_Test_Uncertain_Barrier_Manifest::verify( 'untrusted' ) );
        $this->assertFalse( Alynt_Test_Uncertain_Barrier_Manifest::composition( 'untrusted' ) );
        $this->assertFalse( Alynt_Test_Uncertain_Barrier_Owner::create( 'enable' ) );
        $this->assertTrue( Alynt_Test_Native_Qualification_Selected_Manifest::composition() );
        $o = Alynt_Test_Uncertain_Barrier_Owner::create(); $class = new ReflectionObject( $o );
        foreach ( array( 'native_entry_available' => array(), 'take_uncertain_barrier_observation' => array() ) as $method => $args ) {
            $m = $class->getMethod( $method ); $this->assertTrue( $m->isPrivate() ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true );
            $this->assertFalse( $m->invokeArgs( $o, $args ) );
        }
        $methods = array_filter( $class->getMethods( ReflectionMethod::IS_PUBLIC ), function ( $m ) { return ! $m->isConstructor(); } );
        $source = new ReflectionClass( 'Alynt_Test_Native_Qualification_Owner' );
        $existing = array_filter( $source->getMethods( ReflectionMethod::IS_PUBLIC ), function ( $m ) { return ! $m->isConstructor(); } );
        $names = function ( $rows ) { $r = array_values( array_map( function ( $m ) { return $m->name; }, $rows ) ); sort( $r ); return $r; };
        $this->assertSame( $names( $existing ), $names( $methods ), 'No new public entry or handle-export method.' );
    }
}
