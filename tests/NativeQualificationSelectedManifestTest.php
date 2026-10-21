<?php
/** Definitions only: no actual WordPress, SQL, listener, request or native proof. */
final class NativeQualificationSelectedManifestTest extends PHPUnit\Framework\TestCase {
    private function isolatedArgs() {
        $args = array( PHP_BINARY, '-n' );
        $p = proc_open( array_merge( $args, array( '-r', 'echo (int) function_exists("json_encode"), (int) function_exists("token_get_all");' ) ), array( array( 'pipe','r' ),array( 'pipe','w' ),array( 'pipe','w' ) ), $pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] ); $capabilities = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0, proc_close( $p ) ); $this->assertSame( '', $err ); $this->assertContains( $capabilities,array( '00','01','10','11' ) );
        // -n stays mandatory. Load only real source-verification dependencies if shared.
        foreach ( array( 'json','tokenizer' ) as $i => $extension ) if ( '0' === $capabilities[$i] ) {
            $path = rtrim( ini_get( 'extension_dir' ),'/\\' ) . DIRECTORY_SEPARATOR . $extension . '.' . PHP_SHLIB_SUFFIX;
            $this->assertFileExists( $path ); $args = array_merge( $args,array( '-d','extension=' . $path ) );
        }
        return $args;
    }
    public function test_fixed_cli_composition_retains_the_unavailable_production_entry() {
        $args = $this->isolatedArgs();
        $p = proc_open( array_merge( $args,array( dirname( __DIR__ ) . '/scripts/tests/native-qualification-selected-preflight.php' ) ),array( array( 'pipe','r' ),array( 'pipe','w' ),array( 'pipe','w' ) ),$pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] ); $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0,proc_close( $p ),$out ); $this->assertSame( '',$err ); $m = json_decode( $out,true );
        $this->assertSame( array( 'passed','source','wordpress_bootstrap','final_entry_unavailable','suppression_supported' ),array_keys( $m ) );
        $this->assertTrue( $m['passed'] ); $this->assertTrue( $m['final_entry_unavailable'] ); $this->assertFalse( $m['wordpress_bootstrap'] ); $this->assertFalse( $m['suppression_supported'] ); $this->assertCount( 7,$m['source'] );
        foreach ( $m['source'] as $digest ) $this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/D',$digest );
    }
    public function test_required_real_dependencies_do_not_load_native_extensions_or_wordpress() {
        $args = $this->isolatedArgs();
        $code = 'echo (int) function_exists("json_encode"), (int) function_exists("token_get_all"), (int) extension_loaded("mysqli"), (int) extension_loaded("pdo_mysql"), (int) function_exists("wp_verify_nonce");';
        $p = proc_open( array_merge( $args,array( '-r',$code ) ),array( array( 'pipe','r' ),array( 'pipe','w' ),array( 'pipe','w' ) ),$pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] ); $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0,proc_close( $p ) ); $this->assertSame( '',$err ); $this->assertSame( '11000',$out );
    }
    public function test_missing_tokenizer_keeps_the_original_source_gate_closed() {
        $args = array_merge( $this->isolatedArgs(),array( '-d','disable_functions=token_get_all','-d','display_errors=0','-d','log_errors=0' ) );
        $p = proc_open( array_merge( $args,array( dirname( __DIR__ ) . '/scripts/tests/native-qualification-selected-preflight.php' ) ),array( array( 'pipe','r' ),array( 'pipe','w' ),array( 'pipe','w' ) ),$pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] ); $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 1,proc_close( $p ) ); $this->assertSame( '',$err ); $m = json_decode( $out,true );
        $this->assertIsArray( $m ); $this->assertFalse( $m['passed'] ); $this->assertSame( 'native_qualification_preflight',$m['failure'] );
    }
}
