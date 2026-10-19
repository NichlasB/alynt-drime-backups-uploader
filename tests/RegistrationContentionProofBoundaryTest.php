<?php
/** Pure CLI rejection checks only; never claims native locking/authentication proof. */
final class RegistrationContentionProofBoundaryTest extends PHPUnit\Framework\TestCase {
    /** @dataProvider rejectedPeerArguments */
    public function test_peer_rejects_unowned_or_unbounded_arguments_before_io( array $args ): void {
        $command = array_merge( array( PHP_BINARY, dirname( __DIR__ ) . '/scripts/tests/registration-contention-peer.php' ), $args ); $pipes = array();
        $process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $process ); fclose( $pipes[0] );
        $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 1, proc_close( $process ) ); $this->assertSame( '', $out ); $this->assertSame( '', $err );
    }
    public function rejectedPeerArguments(): array {
        $base = array( '10004', 'wp_alynt_core_probe_0123456789ab_', str_repeat( 'a', 24 ), 'commit', '12345' );
        $out = array( 'missing' => array( array() ) );
        foreach ( array( 0 => 'not-a-port', 1 => 'wp_', 2 => 'wrong-marker', 3 => 'arbitrary-sql', 4 => '65536' ) as $key => $value ) { $args = $base; $args[$key] = $value; $out['invalid-' . $key] = array( $args ); }
        return $out;
    }
}
