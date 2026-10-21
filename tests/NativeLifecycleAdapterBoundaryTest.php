<?php
require_once __DIR__ . '/Support/RegistrationConstructionFixture.php';
require_once dirname( __DIR__ ) . '/includes/class-registration-native-lifecycle.php';
/** Closed final-class entry evidence only; no genuine WordPress/native proof.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class NativeLifecycleAdapterBoundaryTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Registration_Construction_Fixture;
    public function test_final_factory_and_entry_refuse_before_authentication_or_native_io() {
        $class = Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle::class;
        $owner = $class::create(); $reflection = new ReflectionClass( $owner );
        $this->assertFalse( $class::create( 'provider' ) ); $this->assertTrue( $reflection->isFinal() ); $this->assertTrue( $reflection->getConstructor()->isPrivate() );
        $names = array_map( function ( $m ) { return $m->name; }, $reflection->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $names );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $names );
        $this->assertSame( array( 'candidate_only' => true, 'storage_supported' => false, 'dispatch_supported' => false, 'execution_supported' => false ), $owner->__debugInfo() );
        $GLOBALS['alynt_core_request_test']['calls'] = array();
        $method = $reflection->getMethod( 'lifecycle_root' ); $this->assertTrue( $method->isPrivate() ); if ( PHP_VERSION_ID < 80100 ) $method->setAccessible( true );
        $this->assertFalse( $method->invoke( $owner, 'POST', $this->registrationForm() ) );
        $this->assertFalse( $method->invoke( $owner, 'POST', $this->registrationForm() ) );
        $this->assertSame( array(), $GLOBALS['alynt_core_request_test']['calls'], 'Closed entry cannot invoke even a core authentication API.' );
        foreach ( array( 'reader', 'native_handle', 'native_outcome_writer', 'physical', 'read_physical', 'barrier_auth', 'barrier_auth_facts', 'native_resource', 'origin_data', 'before', 'proposal', 'after' ) as $k ) {
            $p = $reflection->getProperty( $k ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $this->assertNull( $p->getValue( $owner ), $k );
        }
        foreach ( array( 'lifecycle_root', 'setup_native_writer', 'setup_native_barrier', 'lifecycle_release', 'native_entry_available', 'admission_transaction', 'barrier_capture' ) as $m ) $this->assertTrue( $reflection->getMethod( $m )->isPrivate() );
        foreach ( array_merge( array( dirname( __DIR__ ) . '/alynt-drime-backups-uploader.php' ), glob( dirname( __DIR__ ) . '/includes/class-plugin*.php' ) ) as $path ) $this->assertStringNotContainsString( 'class-registration-native-lifecycle.php', file_get_contents( $path ) );
        $this->expectException( LogicException::class ); serialize( $owner );
    }
}
