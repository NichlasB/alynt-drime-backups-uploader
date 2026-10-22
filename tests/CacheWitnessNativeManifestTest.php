<?php
use PHPUnit\Framework\TestCase;
/** @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class CacheWitnessNativeManifestTest extends TestCase {
    public function test_successor_source_and_original_native_composition_remain_closed() {
        require dirname( __DIR__ ) . '/scripts/tests/native-lifecycle-proof-source.php';
        require __DIR__ . '/Support/CacheWitnessNativeManifest.php';
        $this->assertTrue( Alynt_Test_Cache_Witness_Native_Manifest::verify() );
        $this->assertFalse( Alynt_Test_Cache_Witness_Native_Manifest::verify( 'provider' ) );
        $this->assertTrue( Alynt_Test_Uncertain_Barrier_Native_Manifest::composition() );
        $this->assertSame( '2ac6e5fb44961d21ff4f04996e1f011b8aaa4ed8534746e0711220713c8066f4', Alynt_Test_Uncertain_Barrier_Native_Manifest::DIGEST );
        $this->assertFalse( function_exists( 'wp_verify_nonce' ) );
        $this->assertFalse( class_exists( 'wpdb', false ) );
        $this->assertSame( array( 'verify' ), array_map( function ( $m ) { return $m->getName(); }, ( new ReflectionClass( Alynt_Test_Cache_Witness_Native_Manifest::class ) )->getMethods( ReflectionMethod::IS_PUBLIC ) ) );
    }
}
