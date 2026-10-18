<?php
use PHPUnit\Framework\TestCase;
/** Isolated no-IO API tests; not genuine WordPress or native transaction proof.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ScanLedgerCheckpointBoundaryTest extends TestCase {
    public function test_no_public_writer_provider_handle_or_production_registration() {
        require_once dirname( __DIR__ ) . '/includes/class-scan-ledger-checkpoint.php';
        $o = Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint::create();
        $this->assertFalse( Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint::create( new stdClass() ) );
        $this->assertFalse( class_exists( 'wpdb', false ) );
        $names = array_map( function ( $m ) { return $m->name; }, ( new ReflectionObject( $o ) )->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $names );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $names );
        $this->assertSame( array( 'candidate_only' => true, 'storage_supported' => false, 'dispatch_supported' => false, 'execution_supported' => false ), $o->disposition() );
        $_SERVER['REQUEST_METHOD'] = 'POST'; $m = new ReflectionMethod( $o, 'checkpoint' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true );
        $this->assertFalse( $m->invoke( $o, 'POST', array() ) );
        $this->assertSame( $o->disposition(), $o->__debugInfo() );
        $this->assertStringNotContainsString( 'scan-ledger-checkpoint', file_get_contents( dirname( __DIR__ ) . '/alynt-drime-backups-uploader.php' ) );
        $this->expectException( LogicException::class ); serialize( $o );
    }
}
