<?php
use PHPUnit\Framework\TestCase;
/** API/structure proof only, not a genuine WordPress/native SQL proof.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class NativeClosedBeginBoundaryTest extends TestCase {
    private function owner() { require_once dirname( __DIR__ ) . '/includes/class-native-closed-begin.php'; return Alynt_Drime_Backups_Uploader_Native_Closed_Begin::create(); }
    private function read( $owner, $name ) { $p = new ReflectionProperty( $owner, $name ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); return $p->getValue( $owner ); }
    private function begin( $owner, $body ) { $m = new ReflectionMethod( $owner, 'begin' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true ); return $m->invoke( $owner, 'POST', $body ); }
    public function test_zero_io_owner_has_no_public_execute_provider_or_storage_handle() {
        $owner = $this->owner();
        $this->assertFalse( Alynt_Drime_Backups_Uploader_Native_Closed_Begin::create( function () { throw new RuntimeException( 'never' ); } ) );
        $this->assertNull( $this->read( $owner, 'reader' ) ); $this->assertFalse( class_exists( 'wpdb', false ) );
        $methods = array_map( function ( $m ) { return $m->name; }, ( new ReflectionObject( $owner ) )->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $methods );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $methods );
        $this->assertSame( $owner->disposition(), $owner->__debugInfo() );
        $this->assertFalse( $owner->disposition()['storage_supported'] );
    }
    public function test_malformed_intent_is_one_shot_before_native_or_wordpress_dependencies() {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        foreach ( array( null, '', array(), new stdClass(), array( 'purpose' => 'begin_disable' ) ) as $bad ) {
            $owner = $this->owner(); $this->assertFalse( $this->begin( $owner, $bad ) );
            $this->assertTrue( $this->read( $owner, 'used' ) ); $this->assertSame( 0, $this->read( $owner, 'protected_reads' ) ); $this->assertSame( 0, $this->read( $owner, 'writes' ) );
            $this->assertNull( $this->read( $owner, 'reader' ) ); $this->assertFalse( $this->begin( $owner, $bad ) );
        }
    }
    public function test_nontransferable_owner_refuses_clone_and_serialization() {
        $owner = $this->owner();
        try { clone $owner; $this->fail( 'Clone escaped' ); } catch ( Error $error ) { $this->assertStringContainsString( '__clone', $error->getMessage() ); }
        $this->expectException( LogicException::class ); serialize( $owner );
    }
    public function test_bootstrap_stays_unwired_and_fixed_sql_has_no_target_update_or_core_hooks() {
        $root = dirname( __DIR__ );
        $this->assertStringNotContainsString( 'native-closed-begin', file_get_contents( $root . '/alynt-drime-backups-uploader.php' ) );
        foreach ( array( 'class-native-closed-begin.php', 'trait-native-closed-begin-session.php', 'trait-native-closed-begin-storage.php' ) as $file ) {
            $source = implode( '', array_map( function ( $t ) { return is_array( $t ) ? ( in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $t[1] ) : $t; }, token_get_all( file_get_contents( $root . '/includes/' . $file ) ) ) );
            $this->assertDoesNotMatchRegularExpression( '/\b(?:update_option|add_option|delete_option|unserialize|mysqli_report|db_connect|check_connection|add_action|add_filter|register_rest_route)\s*\(/', $source );
            $this->assertDoesNotMatchRegularExpression( '/->(?:commit|rollback|autocommit|ping)\s*\(/', $source );
        }
        $source = file_get_contents( $root . '/includes/class-native-closed-begin.php' );
        $critical = substr( $source, strpos( $source, 'private function transact' ), strpos( $source, 'private function budget' ) - strpos( $source, 'private function transact' ) );
        $this->assertDoesNotMatchRegularExpression( '/\b(?:wp_[a-z_]+|get_option|do_action|apply_filters)\s*\(/', $critical );
        $this->assertDoesNotMatchRegularExpression( '/(?:Model|Observation_Contract|Json_Contract)::/', $critical );
        $storage = file_get_contents( $root . '/includes/trait-native-closed-begin-storage.php' );
        $this->assertSame( 1, substr_count( $storage, 'UPDATE `' ) );
        $this->assertStringContainsString( "$" . "name = 'alynt_drime_backups_schedule_safety_state'", $storage );
    }
}
