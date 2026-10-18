<?php
use PHPUnit\Framework\TestCase;
/** Isolated API/structure checks, not genuine WordPress or SQL proof.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class NativePairedBoundaryTest extends TestCase {
    private function owner() { require_once dirname( __DIR__ ) . '/includes/class-native-closed-begin.php'; return Alynt_Drime_Backups_Uploader_Native_Closed_Begin::create(); }
    private function invoke( $o, $name, $args = array() ) { $m = new ReflectionMethod( $o, $name ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true ); return $m->invokeArgs( $o, $args ); }
    private function read( $o, $name ) { $p = new ReflectionProperty( $o, $name ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); return $p->getValue( $o ); }
    public function test_completion_requires_internal_confirmed_begin_not_boolean_or_caller_bytes() {
        $o = $this->owner(); $this->assertFalse( $this->invoke( $o, 'complete' ) );
        $this->assertNull( $this->read( $o, 'continuation' ) ); $this->assertNull( $this->read( $o, 'reader' ) );
        $this->assertSame( 0, $this->read( $o, 'protected_reads' ) ); $this->assertSame( 0, $this->read( $o, 'writes' ) );
        $this->assertFalse( class_exists( 'wpdb', false ) );
        foreach ( array( 'disable', 'complete', 'resolve', 'pair_transaction', 'write_settings', 'resolution_barrier', 'receipt_fact' ) as $name ) $this->assertTrue( ( new ReflectionMethod( $o, $name ) )->isPrivate() );
    }
    public function test_malformed_private_flows_are_consumed_before_protected_reads() {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        foreach ( array( 'disable', 'resolve' ) as $name ) foreach ( array( array(), null, new stdClass(), array( 'purpose' => 'receipt_read' ) ) as $bad ) {
            $o = $this->owner(); $this->assertFalse( $this->invoke( $o, $name, array( 'POST', $bad ) ) );
            $this->assertTrue( $this->read( $o, 'used' ) ); $this->assertNull( $this->read( $o, 'continuation' ) );
            $this->assertSame( 0, $this->read( $o, 'writes' ) ); $this->assertSame( 0, $this->read( $o, 'protected_reads' ) );
            $this->assertFalse( $this->invoke( $o, $name, array( 'POST', $bad ) ) );
        }
    }
    public function test_critical_sections_have_no_wordpress_domain_validator_or_callback() {
        $root = dirname( __DIR__ ); $o = $this->owner();
        foreach ( array( 'pair_transaction', 'write_settings', 'resolution_barrier' ) as $name ) {
            $m = new ReflectionMethod( $o, $name ); $lines = file( $m->getFileName() ); $source = implode( '', array_slice( $lines, $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1 ) );
            $this->assertDoesNotMatchRegularExpression( '/(?:Model|Json_Contract|Observation_Contract)::|\b(?:wp_[a-z_]+|do_action|apply_filters|get_option|call_user_func)\s*\(/', $source );
        }
        $this->assertStringNotContainsString( 'native-paired', file_get_contents( $root . '/alynt-drime-backups-uploader.php' ) );
        $this->assertStringNotContainsString( 'native-receipt', file_get_contents( $root . '/alynt-drime-backups-uploader.php' ) );
        $this->assertFalse( $o->disposition()['execution_supported'] );
    }
}
