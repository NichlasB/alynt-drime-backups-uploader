<?php
require_once __DIR__ . '/Support/RegistrationConstructionFixture.php';
/** Isolated extension-free API boundaries, NOT WordPress/native/physical proof.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class NativeQualificationObservationApiTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Registration_Construction_Fixture;
    /** @dataProvider cases */
    public function test_observation_preserves_actual_root_over_isolated_api_edges( $case ) {
        $args = array( PHP_BINARY, '-n' );
        $p = proc_open( array_merge( $args, array( '-r', 'echo function_exists("json_encode") ? "yes" : "no";' ) ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] ); $json = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0, proc_close( $p ) ); $this->assertSame( '', $err ); $this->assertContains( $json, array( 'yes', 'no' ) );
        if ( 'no' === $json ) { $path = rtrim( ini_get( 'extension_dir' ), '/\\' ) . DIRECTORY_SEPARATOR . 'json.' . PHP_SHLIB_SUFFIX; $this->assertFileExists( $path ); $args = array_merge( $args, array( '-d', 'extension=' . $path ) ); }
        $p = proc_open( array_merge( $args, array( __DIR__ . '/Support/NativeQualificationApiWorker.php', $case ) ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $p ); fwrite( $pipes[0], json_encode( $this->registrationFrame() ) ); fclose( $pipes[0] );
        $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] ); $exit = proc_close( $p );
        $this->assertSame( '', $err ); $this->assertSame( 0, $exit, $out ); $r = json_decode( $out, true );
        $this->assertIsArray( $r ); $this->assertTrue( $r['ok'], $out ); $this->assertSame( $case, $r['case'] ); $this->assertGreaterThan( 40, $r['checks'] );
        foreach ( array( 'native_extension', 'authentication', 'storage', 'native_execution', 'private_protocol_ipc', 'wiring' ) as $k ) $this->assertFalse( $r[$k], $k );
    }
    public function cases() {
        $cases = array( 'commit', 'uncertain', 'uncertain_throw', 'absence', 'entry', 'malformed', 'get', 'stale', 'actor', 'role', 'capture-open-false', 'capture-open-throw', 'barrier-open-false', 'barrier-open-throw', 'capture-close-false', 'writer-close-false', 'barrier-close-false', 'barrier-commit-false', 'barrier-root-age', 'barrier-read-age', 'take-handle', 'control-bypass', 'control-factory', 'control-return', 'control-take' );
        for ( $n = 1; $n <= 24; $n++ ) $cases[] = 'guard-' . $n;
        return array_map( function ( $s ) { return array( $s ); }, $cases );
    }
}
