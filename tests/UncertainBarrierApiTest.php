<?php
require_once __DIR__ . '/Support/RegistrationConstructionFixture.php';
/** Isolated API edges only. No genuine authentication, storage or native execution.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class UncertainBarrierApiTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Registration_Construction_Fixture;
    /** @dataProvider cases */
    public function test_original_root_and_new_terminal_observation( $case ) {
        $args = array( PHP_BINARY, '-n' );
        $p = proc_open( array_merge( $args, array( '-r', 'echo (int) function_exists("json_encode"), ",", (int) function_exists("token_get_all");' ) ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] ); $json = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0, proc_close( $p ) ); $this->assertSame( '', $err ); $this->assertContains( $json, array( '0,0', '0,1', '1,0', '1,1' ) );
        foreach ( array( 'json', 'tokenizer' ) as $i => $extension ) if ( '0' === explode( ',', $json )[$i] ) {
            $path = rtrim( ini_get( 'extension_dir' ), '/\\' ) . DIRECTORY_SEPARATOR . $extension . '.' . PHP_SHLIB_SUFFIX;
            $this->assertFileExists( $path ); $args = array_merge( $args, array( '-d', 'extension=' . $path ) );
        }
        $p = proc_open( array_merge( $args, array( __DIR__ . '/Support/UncertainBarrierApiWorker.php', $case ) ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $p ); fwrite( $pipes[0], json_encode( $this->registrationFrame() ) ); fclose( $pipes[0] );
        $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] ); $exit = proc_close( $p );
        $this->assertSame( '', $err ); $this->assertSame( 0, $exit, $out ); $r = json_decode( $out, true );
        $this->assertIsArray( $r ); $this->assertTrue( $r['ok'], $out ); $this->assertSame( $case, $r['case'] );
        $this->assertFalse( $r['native_execution'] ); $this->assertFalse( $r['wiring'] );
        if ( in_array( $case, array( 'commit', 'uncertain', 'uncertain_throw', 'uncertain-row', 'uncertain-row-throw' ), true ) ) {
            $this->assertIsArray( $r['terminal'] ); $this->assertFalse( $r['terminal']['native_proven'] ); $this->assertFalse( $r['terminal']['timeout_proven'] ); $this->assertFalse( $r['terminal']['cleanup_proven'] );
        }
    }
    public function cases() {
        $cases = array( 'commit', 'uncertain', 'uncertain_throw', 'uncertain-row', 'uncertain-row-throw', 'entry', 'malformed', 'get', 'stale', 'actor', 'role', 'barrier-commit-false', 'barrier-close-false', 'barrier-root-age', 'barrier-read-age', 'final-reentry', 'retained-handle', 'control-alias', 'control-return', 'control-guard' );
        for ( $n = 1; $n <= 24; $n++ ) $cases[] = 'guard-' . $n;
        return array_map( function ( $v ) { return array( $v ); }, $cases );
    }
}
