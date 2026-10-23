<?php
/** Operator preflight protection only; never runs WordPress, SQL or HTTP in CI. */
final class ClosedEntryOwnedProofBoundaryTest extends PHPUnit\Framework\TestCase {
    /** @dataProvider rejectedInvocations */
    public function test_operator_scripts_refuse_without_wordpress_database_or_http_execution( $file, $args, $expected ) {
        $command = array_merge( array( PHP_BINARY, '-n', dirname( __DIR__ ) . '/scripts/tests/' . $file ), $args );
        $p = proc_open( $command, array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] );
        $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( $expected, proc_close( $p ) ); $this->assertSame( '', $out ); $this->assertSame( '', $err );
    }
    public function rejectedInvocations() {
        return array(
            array( 'closed-entry-owned-proof.php', array(), 1 ),
            array( 'closed-entry-owned-proof.php', array( dirname( __DIR__ ), '10004' ), 1 ),
            array( 'closed-entry-core-worker.php', array(), 1 ),
            array( 'closed-entry-core-bootstrap.php', array(), 1 ),
            array( 'closed-entry-http-probe.php', array(), 0 ),
        );
    }
    /** @dataProvider phpScripts */
    public function test_proof_sources_parse_on_the_current_supported_php_without_execution( $file ) {
        $p = proc_open( array( PHP_BINARY, '-n', '-l', dirname( __DIR__ ) . '/scripts/tests/' . $file ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] );
        $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0, proc_close( $p ) ); $this->assertSame( '', $err ); $this->assertStringContainsString( 'No syntax errors detected', $out );
    }
    public function phpScripts() {
        return array_map( function ( $f ) { return array( $f ); }, array( 'closed-entry-owned-proof.php', 'closed-entry-core-bootstrap.php', 'closed-entry-core-worker.php', 'closed-entry-http-probe.php' ) );
    }
}
