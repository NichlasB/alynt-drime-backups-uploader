<?php
/** No WordPress, native session, listener or private control channel. */
final class NativeQualificationManifestTest extends PHPUnit\Framework\TestCase {
    public function test_fixed_source_and_two_passive_aliases_are_closed_and_socket_free() {
        $args = array( PHP_BINARY, '-n' );
        $p = proc_open( array_merge( $args, array( '-r', 'echo function_exists("json_encode") ? "yes" : "no";' ) ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] ); $json = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0, proc_close( $p ) ); $this->assertSame( '', $err ); $this->assertContains( $json, array( 'yes', 'no' ) );
        if ( 'no' === $json ) { $path = rtrim( ini_get( 'extension_dir' ), '/\\' ) . DIRECTORY_SEPARATOR . 'json.' . PHP_SHLIB_SUFFIX; $this->assertFileExists( $path ); $args = array_merge( $args, array( '-d', 'extension=' . $path ) ); }
        $p = proc_open( array_merge( $args, array( dirname( __DIR__ ) . '/scripts/tests/native-qualification-preflight.php' ) ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] ); $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0, proc_close( $p ), $out ); $this->assertSame( '', $err );
        $this->assertSame( array( 'passed' => true, 'two_passive_aliases_only' => true, 'final_entry_unavailable' => true, 'wordpress_or_storage_executed' => false ), json_decode( $out, true ) );
    }
}
