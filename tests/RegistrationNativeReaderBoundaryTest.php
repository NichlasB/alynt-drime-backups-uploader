<?php
use PHPUnit\Framework\TestCase;
/**
 * No native connection/authentication simulation. Actual positives live in the owned proof.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class RegistrationNativeReaderBoundaryTest extends TestCase {
    private function owner() {
        $path = dirname( __DIR__ ) . '/includes/class-registration-native-reader.php';
        $this->assertFileExists( $path ); require_once $path;
        return Alynt_Drime_Backups_Uploader_Registration_Native_Reader::create();
    }
    private function member( $o, $key ) { $p = new ReflectionProperty( $o, $key ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); return $p->getValue( $o ); }
    private function read( $o, $method, $body ) { $r = new ReflectionMethod( $o, 'read_registration' ); if ( PHP_VERSION_ID < 80100 ) $r->setAccessible( true ); return $r->invoke( $o, $method, $body ); }
    public function test_zero_io_creation_and_no_public_reader_or_transferable_handle() {
        $o = $this->owner(); $this->assertFalse( Alynt_Drime_Backups_Uploader_Registration_Native_Reader::create( 'caller' ) );
        $this->assertNull( $this->member( $o, 'reader' ) ); $this->assertSame( 0, $this->member( $o, 'protected_reads' ) );
        $methods = array_map( function ( $m ) { return $m->name; }, ( new ReflectionObject( $o ) )->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $methods );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $methods );
        $this->assertSame( $o->disposition(), $o->__debugInfo() );
    }
    public function test_malformed_private_attempt_is_consumed_and_restores_handler_without_io() {
        $o = $this->owner(); $_SERVER['REQUEST_METHOD'] = 'POST'; $prior = function () { throw new LogicException(); }; set_error_handler( $prior );
        try {
            $this->assertFalse( $this->read( $o, 'POST', array( 'provider' => 'caller' ) ) );
            $this->assertTrue( $this->member( $o, 'consumed' ) ); $this->assertNull( $this->member( $o, 'reader' ) ); $this->assertFalse( $this->member( $o, 'active' ) );
            $this->assertFalse( $this->read( $o, 'POST', array() ) ); $this->assertSame( 0, $this->member( $o, 'protected_reads' ) );
            $observed = set_error_handler( $prior ); $this->assertSame( $prior, $observed ); restore_error_handler();
        } finally { restore_error_handler(); }
    }
    public function test_dormant_no_bootstrap_wiring_and_serialization_refused() {
        $o = $this->owner(); $this->assertStringNotContainsString( 'registration-native-reader', file_get_contents( dirname( __DIR__ ) . '/alynt-drime-backups-uploader.php' ) );
        try { clone $o; $this->fail( 'Clone escaped.' ); } catch ( Error $error ) { $this->assertStringContainsString( '__clone', $error->getMessage() ); }
        $this->expectException( LogicException::class ); serialize( $o );
    }
}
