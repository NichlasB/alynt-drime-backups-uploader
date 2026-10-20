<?php
require_once __DIR__ . '/Support/RegistrationConstructionFixture.php';
/** @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class RegistrationNativeFailureApiTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Registration_Construction_Fixture;
    /** @dataProvider cases */
    public function test_composed_native_failure_boundary_using_extension_free_api_doubles( $case ) {
        $args = array( PHP_BINARY, '-n' );
        $probe = proc_open( array_merge( $args, array( '-r', 'echo function_exists("json_encode") ? "yes" : "no";' ) ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $probe ); fclose( $pipes[0] ); $json = stream_get_contents( $pipes[1] ); $error = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0, proc_close( $probe ) ); $this->assertSame( '', $error ); $this->assertContains( $json, array( 'yes', 'no' ) );
        if ( 'no' === $json ) { $path = rtrim( ini_get( 'extension_dir' ), '/\\' ) . DIRECTORY_SEPARATOR . 'json.' . PHP_SHLIB_SUFFIX; $this->assertFileExists( $path ); $args = array_merge( $args, array( '-d', 'extension=' . $path ) ); }
        $p = proc_open( array_merge( $args, array( __DIR__ . '/Support/RegistrationFailureApiWorker.php', $case ) ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $p ); fwrite( $pipes[0], json_encode( $this->registrationFrame() ) ); fclose( $pipes[0] );
        $output = stream_get_contents( $pipes[1] ); $error = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] ); $exit = proc_close( $p );
        $this->assertSame( '', $error ); $this->assertSame( 0, $exit, $output ); $result = json_decode( $output, true );
        $this->assertIsArray( $result ); $this->assertTrue( $result['ok'] ); $this->assertSame( $case, $result['case'] ); $this->assertGreaterThan( 30, $result['checks'] );
        foreach ( array( 'native_extension', 'authentication', 'storage', 'execution' ) as $key ) $this->assertFalse( $result[$key] );
    }
    public function cases() {
        return array_map( function ( $s ) { return array( $s ); }, array( 'commit', 'standalone', 'throw-begin', 'false-begin', 'begin-idle', 'throw-after-begin-ack', 'throw-prepare', 'false-prepare', 'throw-bind', 'false-bind', 'throw-execute', 'false-execute', 'throw-after-dml-ack', 'throw-statement_close', 'no-terminal-after-execute', 'throw-commit', 'false-commit', 'commit-idle', 'throw-after-commit-ack', 'throw-rollback', 'false-rollback', 'rollback-idle', 'throw-after-rollback-ack', 'throw-close', 'false-close', 'throw-setup', 'throw-capture', 'throw-metadata', 'throw-auth', 'throw-construct', 'throw-final', 'throw-qualify', 'malformed', 'wrong-domain', 'denied', 'get', 'stale', 'fault-pin', 'fault-id', 'fault-clock', 'fault-reentry', 'fault-seal', 'fault-slot' ) );
    }
}
