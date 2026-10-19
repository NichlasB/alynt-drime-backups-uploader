<?php
/** Extension-free source-seam tests only; no real authentication/native storage. */
final class RegistrationNativeOutcomeApiTest extends PHPUnit\Framework\TestCase {
    private static $workerArguments;
    private function workerArguments() {
        if ( null !== self::$workerArguments ) return self::$workerArguments;
        $process = proc_open( array( PHP_BINARY, '-n', '-r', 'echo function_exists("json_encode") ? "yes" : "no";' ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $process ); fclose( $pipes[0] ); $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0, proc_close( $process ) ); $this->assertSame( '', $err ); $this->assertContains( $out, array( 'yes', 'no' ) );
        $args = array( PHP_BINARY, '-n' );
        if ( 'no' === $out ) {
            $json = rtrim( ini_get( 'extension_dir' ), '/\\' ) . DIRECTORY_SEPARATOR . 'json.' . PHP_SHLIB_SUFFIX;
            $this->assertFileExists( $json, 'Use only the installed JSON module; never enable native mysqli.' ); $args[] = '-d'; $args[] = 'extension=' . $json;
        }
        return self::$workerArguments = $args;
    }
    /** @dataProvider apiGroups */
    public function test_fixed_capture_source_seams_with_isolated_api_doubles( $group ) {
        $worker = __DIR__ . '/Support/RegistrationOutcomeApiWorker.php';
        $process = proc_open( array_merge( $this->workerArguments(), array( $worker, $group ) ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $process ); fclose( $pipes[0] );
        $output = stream_get_contents( $pipes[1] ); $error = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] ); $exit = proc_close( $process );
        $this->assertSame( '', $error ); $this->assertSame( 0, $exit, $output );
        $result = json_decode( $output, true ); $this->assertIsArray( $result ); $this->assertTrue( $result['ok'] );
        $this->assertSame( $group, $result['group'] ); $this->assertGreaterThan( 0, $result['checks'] );
        foreach ( array( 'native_extension', 'authentication', 'storage', 'execution' ) as $flag ) $this->assertFalse( $result[$flag], $flag );
    }
    public function apiGroups() { return array_map( function( $group ) { return array( $group ); }, array( 'commit', 'ambiguous', 'write_failures', 'rollback', 'cleanup', 'readback', 'lifetime', 'ownership' ) ); }
}
