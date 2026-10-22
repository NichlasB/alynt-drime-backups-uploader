<?php
use PHPUnit\Framework\TestCase;
/** @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class CacheContentionNativeManifestTest extends TestCase {
    public function test_successor_keeps_both_native_predecessors_and_composition_closed() {
        require dirname( __DIR__ ) . '/scripts/tests/native-lifecycle-proof-source.php';
        require __DIR__ . '/Support/CacheContentionNativeManifest.php';
        $this->assertTrue( Alynt_Test_Cache_Contention_Native_Manifest::verify() );
        $this->assertFalse( Alynt_Test_Cache_Contention_Native_Manifest::verify( 'provider' ) );
        $this->assertTrue( Alynt_Test_Cache_Witness_Native_Manifest::verify() );
        $this->assertTrue( Alynt_Test_Uncertain_Barrier_Native_Manifest::composition() );
        $this->assertFalse( function_exists( 'wp_verify_nonce' ) );
        $this->assertFalse( class_exists( 'wpdb', false ) );
        $this->assertSame( array( 'verify' ), array_map( function ( $m ) { return $m->getName(); }, ( new ReflectionClass( Alynt_Test_Cache_Contention_Native_Manifest::class ) )->getMethods( ReflectionMethod::IS_PUBLIC ) ) );
    }
}
