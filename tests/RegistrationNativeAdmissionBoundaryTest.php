<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/class-registration-native-admission.php';
/** No WordPress, database, native-positive or actual admission proof here. */
class RegistrationNativeAdmissionBoundaryTest extends TestCase {
    private function privateCall( $owner, $body, $method = 'POST' ) { $r = new ReflectionMethod( $owner, 'admit' ); if ( PHP_VERSION_ID < 80100 ) $r->setAccessible( true ); return $r->invoke( $owner, $method, $body ); }
    private function property( $owner, $key ) { $r = new ReflectionProperty( $owner, $key ); if ( PHP_VERSION_ID < 80100 ) $r->setAccessible( true ); return $r->getValue( $owner ); }
    public function test_factory_and_public_surface_are_dormant_handle_free_and_untransferable() {
        $o = Alynt_Drime_Backups_Uploader_Registration_Native_Admission::create(); $this->assertIsObject( $o );
        $this->assertFalse( Alynt_Drime_Backups_Uploader_Registration_Native_Admission::create( 'provider' ) );
        $this->assertTrue( ( new ReflectionMethod( $o, 'admit' ) )->isPrivate() );
        foreach ( array( 'checkpoint', 'write_safety', 'write_pair', 'execute', 'issue', 'consume', 'matches', 'prepare' ) as $method ) $this->assertFalse( method_exists( $o, $method ) );
        foreach ( array( 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $flag ) $this->assertFalse( $o->disposition()[$flag] );
        $this->assertSame( $o->disposition(), $o->__debugInfo() ); $this->assertNull( $this->property( $o, 'reader' ) ); $this->assertSame( 0, $this->property( $o, 'protected_reads' ) );
        $this->expectException( LogicException::class ); serialize( $o );
    }
    public function test_malformed_and_wrong_purpose_refuse_without_reads_and_consume_outer_lifetime() {
        foreach ( array( null, array(), array( 'purpose' => 'reserve_wpvivid_intent', 'confirm' => 'reserve', 'nonce' => 'not-authenticated', 'operation_id' => '00000000-0000-4000-8000-000000000001' ) ) as $body ) {
            $o = Alynt_Drime_Backups_Uploader_Registration_Native_Admission::create(); $this->assertFalse( $this->privateCall( $o, $body ) );
            $this->assertTrue( $this->property( $o, 'attempted' ) ); $this->assertTrue( $this->property( $o, 'consumed' ) ); $this->assertFalse( $this->property( $o, 'active' ) );
            foreach ( array( 'reader', 'physical', 'read_physical', 'scope', 'before', 'proposal', 'context', 'after', 'recorded_at' ) as $key ) $this->assertNull( $this->property( $o, $key ) );
            $this->assertSame( 0, $this->property( $o, 'protected_reads' ) ); $this->assertSame( 0, $this->property( $o, 'writes' ) );
            $this->assertFalse( $this->privateCall( $o, array( 'purpose' => 'register_closed', 'confirm' => 'register', 'nonce' => 'not-authenticated' ) ) );
        }
    }
    public function test_production_bootstrap_and_existing_readonly_owner_do_not_load_the_coordinator() {
        foreach ( array_merge( array( dirname( __DIR__ ) . '/alynt-drime-backups-uploader.php', dirname( __DIR__ ) . '/includes/class-registration-native-reader.php' ), glob( dirname( __DIR__ ) . '/includes/class-plugin*.php' ) ) as $path ) $this->assertStringNotContainsString( 'class-registration-native-admission.php', file_get_contents( $path ) );
    }
}
