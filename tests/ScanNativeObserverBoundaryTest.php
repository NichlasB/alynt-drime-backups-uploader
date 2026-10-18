<?php
use PHPUnit\Framework\TestCase;
/** Isolated API tests, not genuine core or native storage proof.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ScanNativeObserverBoundaryTest extends TestCase {
    private function owner() { require_once dirname( __DIR__ ) . '/includes/class-scan-native-observer.php'; return Alynt_Drime_Backups_Uploader_Scan_Native_Observer::create(); }
    private function property( $o, $name ) { $p = new ReflectionProperty( $o, $name ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); return $p->getValue( $o ); }
    public function test_fixed_zero_io_constructor_and_public_api_expose_no_provider_or_handle() {
        $o = $this->owner(); $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Native_Observer::create( function () { throw new LogicException(); } ) );
        $this->assertFalse( class_exists( 'wpdb', false ) ); $this->assertNull( $this->property( $o, 'reader' ) );
        $names = array_map( function ( $m ) { return $m->name; }, ( new ReflectionObject( $o ) )->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $names );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition', 'inspect' ), $names );
        $_SERVER['REQUEST_METHOD'] = 'POST'; $result = $o->inspect( 'POST', array() );
        $this->assertSame( array( 'outcome' => 'unavailable', 'source' => 'closed_scan_native_observation_v1', 'storage_supported' => false, 'dispatch_supported' => false, 'execution_supported' => false ), $result );
        $this->assertSame( 0, $this->property( $o, 'protected_reads' ) ); $this->assertSame( $o->disposition(), $o->__debugInfo() );
    }
    public function test_malformed_input_preserves_error_handler_and_never_opens_native_reader() {
        $o = $this->owner(); $prior = function () { throw new LogicException(); }; set_error_handler( $prior );
        try {
            $_SERVER['REQUEST_METHOD'] = 'POST';
            foreach ( array( null, false, '', new stdClass(), array( 'purpose' => 'receipt_read' ) ) as $bad ) {
                $this->assertSame( 'unavailable', $o->inspect( 'POST', $bad )['outcome'] ); $this->assertNull( $this->property( $o, 'reader' ) );
            }
            $observed = set_error_handler( $prior ); $this->assertSame( $prior, $observed ); restore_error_handler();
        } finally { restore_error_handler(); }
    }
    public function test_owner_cannot_clone_serialize_or_be_production_loaded() {
        $o = $this->owner();
        try { clone $o; $this->fail( 'Clone escaped.' ); } catch ( Error $e ) { $this->assertStringContainsString( '__clone', $e->getMessage() ); }
        $this->assertStringNotContainsString( 'scan-native-observer', file_get_contents( dirname( __DIR__ ) . '/alynt-drime-backups-uploader.php' ) );
        $this->expectException( LogicException::class ); serialize( $o );
    }
}
