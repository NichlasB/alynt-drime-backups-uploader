<?php
use PHPUnit\Framework\TestCase;
/** No WordPress, native driver or database proof is claimed by this API test.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class NativeObserverBoundaryTest extends TestCase {
    private function owner() {
        require_once dirname( __DIR__ ) . '/includes/class-real-closed-writer-native-observer.php';
        return Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Native_Observer::create();
    }
    private function property( $owner, $name ) { $p = new ReflectionProperty( $owner, $name ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); return $p->getValue( $owner ); }
    public function test_constructor_has_no_provider_or_native_dependency_and_public_api_cannot_export_capability() {
        $owner = $this->owner(); $this->assertFalse( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Native_Observer::create( function () { throw new RuntimeException( 'Never call' ); } ) );
        $this->assertNull( $this->property( $owner, 'reader' ) ); $this->assertFalse( class_exists( 'wpdb', false ) );
        $names = array_map( function ( $m ) { return $m->name; }, ( new ReflectionObject( $owner ) )->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $names );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition', 'inspect' ), $names );
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $result = $owner->inspect( 'POST', array() ); $this->assertSame( array( 'outcome' => 'unavailable', 'source' => 'closed_native_observation_v1', 'storage_supported' => false, 'dispatch_supported' => false, 'execution_supported' => false ), $result );
        $this->assertSame( 0, $this->property( $owner, 'protected_reads' ) ); $this->assertSame( $owner->disposition(), $owner->__debugInfo() );
    }
    public function test_invalid_intents_do_not_install_error_handlers_open_storage_or_autoload_wordpress() {
        $owner = $this->owner(); $calls = array(); $loader = function ( $class ) use ( &$calls ) { $calls[] = $class; }; spl_autoload_register( $loader );
        $prior = function () { throw new RuntimeException( 'prior handler' ); }; set_error_handler( $prior );
        try {
            $_SERVER['REQUEST_METHOD'] = 'POST';
            foreach ( array( null, '', array(), array( 'purpose' => 'receipt_read' ), new stdClass() ) as $bad ) { $this->assertSame( 'unavailable', $owner->inspect( 'POST', $bad )['outcome'] ); $this->assertNull( $this->property( $owner, 'reader' ) ); }
            $observed = set_error_handler( $prior ); $this->assertSame( $prior, $observed ); restore_error_handler(); $this->assertSame( array(), $calls );
        } finally { restore_error_handler(); spl_autoload_unregister( $loader ); }
    }
    public function test_serialization_and_cloning_cannot_transfer_private_session() {
        $owner = $this->owner();
        try { clone $owner; $this->fail( 'Clone escaped' ); } catch ( Error $e ) { $this->assertStringContainsString( '__clone', $e->getMessage() ); }
        $this->expectException( LogicException::class ); serialize( $owner );
    }
    public function test_definitions_remain_unregistered_and_no_shared_connection_transaction_or_reconnect_calls_exist() {
        $root = dirname( __DIR__ ); $bootstrap = file_get_contents( $root . '/alynt-drime-backups-uploader.php' );
        $this->assertStringNotContainsString( 'native-observer', $bootstrap );
        foreach ( array( 'class-real-closed-writer-native-observer.php', 'trait-native-observer-session.php', 'trait-native-observer-rows.php' ) as $file ) {
            $source = implode( '', array_map( function ( $t ) { return is_array( $t ) ? ( in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $t[1] ) : $t; }, token_get_all( file_get_contents( $root . '/includes/' . $file ) ) ) );
            $this->assertDoesNotMatchRegularExpression( '/\b(?:update_option|add_option|delete_option|unserialize|mysqli_report|check_connection|db_connect|add_action|add_filter|register_rest_route)\s*\(/', $source );
            $this->assertDoesNotMatchRegularExpression( '/->(?:commit|rollback|autocommit|ping)\s*\(/', $source );
        }
    }
}
