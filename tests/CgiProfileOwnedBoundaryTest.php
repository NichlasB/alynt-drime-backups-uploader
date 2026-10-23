<?php
/** Standalone fixture refuses CLI; never opens a site, listener or database. */
final class CgiProfileOwnedBoundaryTest extends PHPUnit\Framework\TestCase {
    public function test_owned_cgi_fixture_cannot_execute_as_cli() {
        $file = dirname( __DIR__ ) . '/scripts/tests/cgi-profile-probe.php';
        $process = proc_open( array( PHP_BINARY, '-n', $file ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $process ); fclose( $pipes[0] );
        $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0, proc_close( $process ) ); $this->assertSame( '', $out ); $this->assertSame( '', $err );
    }
}
