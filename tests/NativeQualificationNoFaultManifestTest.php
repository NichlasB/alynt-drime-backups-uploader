<?php
/** Definitions only: no actual WordPress, SQL, listener, request or native proof. */
final class NativeQualificationNoFaultManifestTest extends PHPUnit\Framework\TestCase {
    public function test_fixed_cli_composition_retains_the_unavailable_production_entry() {
        $args = array( PHP_BINARY, '-n' );
        $p = proc_open( array_merge( $args, array( '-r', 'echo function_exists("json_encode") ? "yes" : "no";' ) ), array( array( 'pipe','r' ),array( 'pipe','w' ),array( 'pipe','w' ) ), $pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] ); $json = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0, proc_close( $p ) ); $this->assertSame( '', $err ); $this->assertContains( $json,array( 'yes','no' ) );
        if ( 'no' === $json ) { $path = rtrim( ini_get( 'extension_dir' ),'/\\' ) . DIRECTORY_SEPARATOR . 'json.' . PHP_SHLIB_SUFFIX; $this->assertFileExists( $path ); $args = array_merge( $args,array( '-d','extension=' . $path ) ); }
        $p = proc_open( array_merge( $args,array( dirname( __DIR__ ) . '/scripts/tests/native-qualification-no-fault-preflight.php' ) ),array( array( 'pipe','r' ),array( 'pipe','w' ),array( 'pipe','w' ) ),$pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] ); $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0,proc_close( $p ),$out ); $this->assertSame( '',$err ); $m = json_decode( $out,true );
        $this->assertSame( array( 'passed','source','wordpress_bootstrap','final_entry_unavailable','suppression_supported' ),array_keys( $m ) );
        $this->assertTrue( $m['passed'] ); $this->assertTrue( $m['final_entry_unavailable'] ); $this->assertFalse( $m['wordpress_bootstrap'] ); $this->assertFalse( $m['suppression_supported'] ); $this->assertCount( 7,$m['source'] );
        foreach ( $m['source'] as $digest ) $this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/D',$digest );
    }
}
