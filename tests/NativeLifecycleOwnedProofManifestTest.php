<?php
/** Socket-free proof identity/closed-entry check. Never bootstraps WordPress or SQL. */
use PHPUnit\Framework\TestCase;

final class NativeLifecycleOwnedProofManifestTest extends TestCase {
    public function test_fixed_composition_and_unavailable_final_entry_without_wordpress(): void {
        $args = array( PHP_BINARY, '-n' );
        $probe = proc_open( array_merge( $args, array( '-r', 'echo function_exists("json_encode") ? "yes" : "no";' ) ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $probe ); fclose( $pipes[0] ); $json = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0, proc_close( $probe ) ); $this->assertSame( '', $err ); $this->assertContains( $json, array( 'yes', 'no' ) );
        if ( 'no' === $json ) { $path = rtrim( ini_get( 'extension_dir' ), '/\\' ) . DIRECTORY_SEPARATOR . 'json.' . PHP_SHLIB_SUFFIX; $this->assertFileExists( $path ); $args = array_merge( $args, array( '-d', 'extension=' . $path ) ); }
        $command = array_merge( $args, array( dirname( __DIR__ ) . '/scripts/tests/native-lifecycle-proof-preflight.php' ) );
        $pipes = array(); $p = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] );
        $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0, proc_close( $p ), $out . $err ); $this->assertSame( '', $err );
        $this->assertSame( array( 'passed' => true, 'composition_equal_except_fixed_entry' => true, 'final_entry_unavailable' => true, 'wordpress_or_storage_executed' => false ), json_decode( $out, true ) );
    }
}
