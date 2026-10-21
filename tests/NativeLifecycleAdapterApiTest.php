<?php
require_once __DIR__ . '/Support/RegistrationConstructionFixture.php';
/** Extension-free API tests only, not WordPress authentication or native proof.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class NativeLifecycleAdapterApiTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Registration_Construction_Fixture;
    /** @dataProvider cases */
    public function test_fixed_adapter_over_isolated_api_edges( $case ) {
        $args = array( PHP_BINARY, '-n' );
        $probe = proc_open( array_merge( $args, array( '-r', 'echo function_exists("json_encode") ? "yes" : "no";' ) ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $probe ); fclose( $pipes[0] ); $json = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0, proc_close( $probe ) ); $this->assertSame( '', $err ); $this->assertContains( $json, array( 'yes', 'no' ) );
        if ( 'no' === $json ) { $path = rtrim( ini_get( 'extension_dir' ), '/\\' ) . DIRECTORY_SEPARATOR . 'json.' . PHP_SHLIB_SUFFIX; $this->assertFileExists( $path ); $args = array_merge( $args, array( '-d', 'extension=' . $path ) ); }
        $p = proc_open( array_merge( $args, array( __DIR__ . '/Support/NativeLifecycleApiWorker.php', $case ) ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $p ); fwrite( $pipes[0], json_encode( $this->registrationFrame() ) ); fclose( $pipes[0] );
        $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] ); $exit = proc_close( $p );
        $this->assertSame( '', $err ); $this->assertSame( 0, $exit, $out ); $r = json_decode( $out, true );
        $this->assertIsArray( $r ); $this->assertTrue( $r['ok'], $out ); $this->assertSame( $case, $r['case'] ); $this->assertGreaterThan( 40, $r['checks'] );
        foreach ( array( 'native_extension', 'authentication', 'storage', 'native_execution', 'wiring' ) as $k ) $this->assertFalse( $r[$k], $k );
    }
    public function cases() {
        $cases = array( 'commit', 'uncertain', 'uncertain_throw', 'absence', 'entry', 'malformed', 'get', 'stale', 'actor', 'role', 'capture-close-false', 'capture-close-throw', 'writer-close-false', 'writer-close-throw', 'barrier-close-false', 'barrier-close-throw', 'capture-open-false', 'capture-open-throw', 'writer-open-false', 'writer-open-throw', 'barrier-open-false', 'barrier-open-throw', 'writer-actor', 'barrier-actor', 'barrier-cookie', 'barrier-auth', 'barrier-root-age', 'barrier-read-age', 'capture-reentry', 'writer-reentry', 'barrier-reentry', 'final-reentry', 'zero-first', 'zero-last', 'metadata-first', 'metadata-last', 'barrier-commit-false', 'barrier-row', 'capture-row', 'compare-row', 'readback-row', 'write-false' );
        for ( $n = 1; $n <= 24; $n++ ) $cases[] = 'guard-' . $n;
        foreach ( array( 'mutation', 'read', 'release', 'zero', 'erase', 'postclose' ) as $control ) $cases[] = 'control-' . $control;
        return array_map( function ( $s ) { return array( $s ); }, $cases );
    }
}
